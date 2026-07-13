# ZKTeco Push Endpoint — Real Hardware Compatibility

## Problem

`BiometricPushController` (the device-facing push endpoint) was built assuming devices can
send a custom `X-Api-Key` header/param and that a JSON response is acceptable. In practice,
budget ZKTeco WiFi/fingerprint/face terminals (K40, F18, MB360, SpeedFace, iFace, etc.) only
expose **Server IP/Domain + Port + Path** in their configuration menu — no custom headers, no
extra query params beyond the standard iClock protocol fields (`SN`, `table`, `Stamp`, ...).

Consequences of the current implementation, if a real device is pointed at it today:

1. **Auth always fails.** The device never sends the required API key, so every push gets a
   401 and no attendance is ever recorded — silently, since this is a background device→server
   call with no UI feedback.
2. **Wrong response format.** Real iClock firmware expects a literal `OK` (plain text) body to
   mark a batch as delivered. A JSON body looks like a failure to the device, which may then
   resend the same batch.
3. **No duplicate protection in push mode.** `BiometricPushController::processLog()` calls
   `AttendanceService::processLog()` directly in pure toggle mode, bypassing the 60-second
   duplicate-window guard that `BiometricAttendanceService` already provides to the `/sync` and
   `/punch` endpoints. A resent batch (likely, given point 2) would toggle check-in ⇄ check-out
   on every resend and corrupt attendance data.

The existing multi-format log parsing (JSON / XML / form-POST `Stamp`) in
`BiometricPushController` already covers the different payload shapes various ZKTeco
models/firmware send, and stays unchanged. Likewise, `resolveOrCreateUser()` (matching a
device's `employee_id` against `biometric_code`, then `id`, then `phone`, then auto-creating a
member) is already solid and stays unchanged — only the duplicate-window check is added on top
of it.

## Scope

- Make the push endpoint work with **any** ZKTeco-compatible WiFi/fingerprint/face device that
  only supports configuring Server IP/Domain + Port + Path (no custom headers).
- Receive-only: recording attendance punches from the device. Sending commands to the device
  (remote enrollment, time sync, `getrequest` command polling) is explicitly **out of scope**.

## Fix

All changes are in-place in the existing `BiometricPushController` — no new endpoints.

1. **Device identification by `SN`.** The device's serial number, which iClock-protocol devices
   already send as a `SN` query param on every request, is matched against
   `biometric_devices.serial_number`. The existing `api_key` header/param check is kept as an
   optional fallback (useful for manual testing via Postman/curl) but is no longer required.
2. **Plain-text `OK` response.** A successful POST to the push endpoint always replies with the
   literal body `OK` (same `response('OK', 200)` convention the existing `ping()` method already
   uses), regardless of individual record outcomes. Per-record failures (e.g. unknown employee
   id) continue to be logged server-side only, same as today — they must not change the
   batch-level response, or the device will resend already-processed records.
3. **Add the duplicate-window guard, keep the existing user resolution.**
   `BiometricAttendanceService::isDuplicate(int $userId, ?int $gymId, Carbon $punchTime): bool`
   already implements the 60-second duplicate check used by `/sync` and `/punch`. Inject
   `BiometricAttendanceService` into `BiometricPushController` and call `isDuplicate()` right
   after `resolveOrCreateUser()` succeeds, before calling `AttendanceService::processLog()` — if
   it returns `true`, skip the record (same as a resend) instead of toggling attendance again.
   `resolveOrCreateUser()` itself (the `biometric_code` → `id` → `phone` → auto-create chain)
   is not changed.
4. **GET handshake unchanged.** `/iclock/cdata` and the push endpoint's `ping()` already reply
   `OK`; this is sufficient for devices to start pushing and stays as-is. Full `options=all`
   config-line responses (for advanced provisioning) are out of scope per the scope decision
   above.

## Data flow after fix

```
Any WiFi/finger/face device
 → GET  /api/biometric/iclock/cdata?SN=xxx      → "OK" (handshake)
 → POST /api/biometric/push?SN=xxx  (JSON/XML/form-POST — existing parsers unchanged)
      → device resolved by SN (biometric_devices.serial_number)
      → each record → resolveOrCreateUser() (unchanged) → isDuplicate() guard → AttendanceService::processLog()
      → "OK" (plain text) always, on success
```

## Error handling

- `SN` not registered (and no valid fallback `api_key`) → `401` JSON (this path only ever talks
  to an admin debugging the integration, not a device retry loop, so JSON is fine here).
- Unknown `employee_id` in a record, or any per-record processing exception → logged via
  `Log::warning`, record skipped, batch still replies `OK` (unchanged behavior).
- Malformed/unparseable payload → empty log list → still replies `OK` with `processed: 0`
  equivalent server-side logging (no device-facing error, since an empty push isn't actionable
  by the device).

## Testing

No biometric tests exist yet in `tests/`. New feature tests for `BiometricPushController` will
cover:

- Device resolved correctly by `SN` query param (no API key sent).
- Unregistered/unknown `SN` (and no fallback `api_key`) → `401`.
- Successful push → response body is exactly `OK`.
- Same `Stamp`/batch posted twice (simulating a device retry) → only one `Attendance` record is
  created, not two (duplicate-window guard via `BiometricAttendanceService::isDuplicate()`).
- Existing JSON/XML/form-POST parsing and `resolveOrCreateUser()` behavior are unchanged
  (regression coverage).
