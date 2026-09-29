# Multi-Brand Biometric Devices — Design

**Date:** 2026-09-28
**Status:** Approved

## Goal

Let an owner/admin register biometric machines of different brands (ZKTeco/eSSL/Realtime, Hikvision, and any other brand that can push JSON — e.g. Suprema BioStar 2, Anviz CrossChex) from the Biometric Devices page, with brand-specific setup instructions, without touching code for the common cases.

## Constraints and decisions

- **Push only.** Every supported brand sends data to our server. No polling, no port forwarding or VPN at the gym. Models that can only be pulled are out of scope.
- **Hybrid driver model (option C).** Tested code drivers for ZKTeco and Hikvision, plus one configurable "Generic Webhook" driver with field mapping for everything else.
- **Existing ZKTeco behaviour must not change.** Existing devices default to the `zkteco` brand, and the current `/api/biometric/push` and `/api/biometric/iclock/cdata` routes keep working unchanged.
- **Honesty about unverified payloads.** Suprema and Anviz devices push through their vendor software (BioStar 2 / CrossChex Cloud). We have no verified sample payloads, so they ship as Generic presets that the UI marks as "confirm with a test punch", not as dedicated drivers.

## Architecture

### Driver contract

`App\Biometric\Contracts\BiometricDriver`:

| Method | Purpose |
|---|---|
| `key(): string` | `zkteco`, `hikvision`, `generic` |
| `label(): string` | Shown in the brand dropdown, e.g. "ZKTeco / eSSL / Realtime" |
| `identifiesBy(): string` | `serial` (SN query param) or `token` (URL token) |
| `parse(Request $request, BiometricDevice $device): array` | Returns a list of `PunchLog` |
| `acknowledge(): Response` | What the machine expects back (ZKTeco: plain `OK`; others: `200 OK`) |
| `setupSteps(BiometricDevice $device): array` | Ordered strings rendered on the Setup panel |
| `settingsRules(): array` | Laravel validation rules for the brand's `settings` |

`App\Biometric\PunchLog` is a small value object with `employeeId` (string), `time` (Carbon, already in the app timezone), and `type` (`in` | `out` | `null`).

### Registry

`config/biometric.php` lists driver classes:

```php
'drivers' => [
    'zkteco'    => App\Biometric\Drivers\ZKTecoDriver::class,
    'hikvision' => App\Biometric\Drivers\HikvisionDriver::class,
    'generic'   => App\Biometric\Drivers\GenericWebhookDriver::class,
],
'generic_presets' => [ 'suprema_biostar2' => [...], 'anviz_crosschex' => [...] ],
```

`App\Biometric\DriverRegistry` resolves a driver by key and exposes the list used by the UI dropdown and by the `brand` validation (`in:` the configured keys). To add a brand, write one class and add one config line.

### Shared punch processing

The member lookup, duplicate check, auto-create and attendance write currently live as private methods in `BiometricPushController` (`processLog`, `resolveOrCreateUser`, `autoCreateMemberFromDevice`). They move unchanged into `App\Biometric\BiometricPunchProcessor::process(PunchLog $log, BiometricDevice $device): bool`. Every driver's output goes through it.

## Data model

Migration on `biometric_devices`:

| Column | Type | Notes |
|---|---|---|
| `brand` | string(30), default `zkteco` | Existing rows become `zkteco` |
| `webhook_token` | string(64), nullable, unique | Generated for every device on create; backfilled for existing rows |
| `settings` | json, nullable | Brand settings: generic mapping, optional secret header, timezone override |
| `last_payload` | text, nullable | Raw body of the last request, truncated to 10 KB |
| `last_payload_at` | timestamp, nullable | |
| `serial_number` | becomes nullable (still unique) | Required only for `zkteco` (validation) |

The model casts `settings` to an array and gets `regenerateWebhookToken()`. The existing `api_key` column stays as it is, since it is still the ZKTeco manual-testing fallback.

## Request flow

**ZKTeco (unchanged URLs):** `/api/biometric/push?SN=...` and `/api/biometric/iclock/cdata?SN=...`. `BiometricPushController` resolves the device by SN (as today) and then delegates parsing to `ZKTecoDriver`.

**Token brands (new):** `GET|POST /api/biometric/hook/{token}` goes to `BiometricWebhookController`:

1. Find the device by `webhook_token`. If there is none, return `404` and record the attempt in `UnknownBiometricDevices` under the key `token:<first 6 chars>…`.
2. `markSeen()` and store `last_payload`.
3. If the device is disabled, return `401` without saving punches (same as ZKTeco).
4. `GET` is a heartbeat: return `200`.
5. `POST`: check the generic secret header if one is configured (mismatch returns `401`), then call `driver->parse()` and pass each `PunchLog` to `BiometricPunchProcessor`.
6. Return `driver->acknowledge()`.

The route sits outside `auth:sanctum`, next to the existing push routes.

## Drivers

### ZKTecoDriver
- Contains the existing `parseJson`, `parseXml` and `parseFormPost` logic, moved from the controller.
- Brand label: "ZKTeco / eSSL / Realtime".
- Behaviour must stay identical; the existing `BiometricPushControllerTest` suite is the regression guard.

### HikvisionDriver
- Accepts JSON either as the raw body or inside a multipart part named `event_log` or `AccessControllerEvent`. Image parts are ignored.
- Uses only events where `AccessControllerEvent.majorEventType == 5` and `employeeNoString` (fallback `employeeNo`) is present. All other events are skipped.
- `time` from `dateTime`: converted to the machine's wall-clock time (device timezone, default `config('biometric.timezone')`) and labelled with the app timezone — the same form ZKTeco punches have always been stored in. See `WallClock`.
- `type` comes from `attendanceStatus`: `checkIn` → `in`, `checkOut` → `out`, anything else → `null`.
- Always acknowledges with `200`, so the terminal does not resend in a loop.

### GenericWebhookDriver
Settings (validated through `settingsRules()`):

| Key | Required | Example |
|---|---|---|
| `records_path` | no | `data.events` (empty means the body is one record or a list) |
| `employee_field` | yes | `user_id`, `user.id` |
| `time_field` | yes | `datetime` |
| `time_format` | yes | `auto` \| `unix` \| `unix_ms` \| `iso` |
| `type_field` | no | `status` |
| `type_in_value` / `type_out_value` | no | `0` / `1` |
| `secret_header` / `secret_value` | no | `X-Webhook-Secret` / `abc…` |

- Dot-paths are read with `data_get`.
- A record missing the employee or time field is skipped.
- If a request carries a body but yields zero punches, the driver logs a warning and the Setup panel shows "Data received but no punches extracted — check mapping".
- Presets fill the form in the UI and are labelled "unverified — confirm with a test punch".

## UI (Biometric Devices page)

- **Add/Edit device:** Brand is the first field and the rest of the form changes with it.
  - ZKTeco: SN is required.
  - Hikvision: SN is optional.
  - Generic: preset dropdown plus the mapping fields and the optional secret.
- **Device list:** a brand badge next to the name; the existing connection badge (🟢/🔴/⚪/🟡); for token brands, a copyable webhook URL and a "Regenerate URL" button with a confirmation step.
- **Setup / Test panel** (per row): the driver's `setupSteps()`, the URL, **Last received data** (pretty-printed, with a timestamp), and the number of punches extracted from it (the result of re-running `parse()` on `last_payload`, without saving anything).
- Hikvision steps: *Configuration → Network → Advanced Settings → HTTP Listening → paste URL → enable Access Control events*.

## Error handling

- A driver exception is caught: log a warning with the device id and the error, keep `last_payload`, and still acknowledge with `200`/`OK` so the machine does not enter a retry storm.
- Unknown tokens and SNs feed the existing `UnknownBiometricDevices` list, which only admins see.
- The `last_payload` write is capped at 10 KB so a device pushing images cannot bloat the row.

## Testing

- **Regression:** all existing `BiometricPushControllerTest` and `BiometricConnectionTest` cases pass unchanged.
- **HikvisionDriver:** raw-JSON body; multipart `event_log`; non-attendance event skipped; `+05:00` → app-timezone conversion; `attendanceStatus` mapping.
- **GenericWebhookDriver:** nested `records_path`; single-object body; `unix` and `iso` time; in/out mapping; wrong mapping yields 0 punches plus a warning; secret header mismatch returns `401`.
- **Webhook route:** unknown token returns `404` and is recorded; disabled device returns `401` with `last_seen_at` updated; `GET` heartbeat marks the device seen.
- **Web:** brand validation (`in:` registry keys); SN required only for ZKTeco; generic settings validated; token regenerate changes the URL and the old token then returns `404`; the Setup panel shows the last payload.

## Out of scope

- Pull/polling of any device.
- Dedicated Suprema and Anviz drivers (add them once real sample payloads are available).
- Pushing users or fingerprints to machines (enrollment sync).
