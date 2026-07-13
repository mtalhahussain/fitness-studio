# ZKTeco Push Endpoint Hardware Compatibility Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `POST /api/biometric/push` work with real ZKTeco WiFi/fingerprint/face devices that can only be configured with a Server IP/Domain + Port + Path (no custom headers), and stop it from double-toggling attendance when a device resends a batch.

**Architecture:** All changes are in `app/Http/Controllers/Api/BiometricPushController.php`. Device identification switches from requiring a custom `api_key` header/param to matching the standard iClock `SN` query param against `biometric_devices.serial_number` (with `api_key` kept as a fallback). The success response changes from JSON to the same literal `OK` body the controller's `ping()` method already returns. A duplicate-window guard is added by injecting the existing `BiometricAttendanceService` and calling its `isDuplicate()` method before recording a punch. `resolveOrCreateUser()` and the JSON/XML/form-POST parsers are untouched.

**Tech Stack:** Laravel 12, PHPUnit (`tests/Feature`), SQLite in-memory test DB (see `phpunit.xml`).

---

### Task 1: Identify device by `SN`, fall back to `api_key`

**Files:**
- Modify: `app/Http/Controllers/Api/BiometricPushController.php:37-52`
- Test: `tests/Feature/BiometricPushControllerTest.php` (create)

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/BiometricPushControllerTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\BiometricDevice;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BiometricPushControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeGym(): Gym
    {
        return Gym::create([
            'name'  => 'Test Gym',
            'slug'  => 'test-gym',
            'email' => 'gym@test.local',
        ]);
    }

    private function makeDevice(Gym $gym, array $overrides = []): BiometricDevice
    {
        return BiometricDevice::create(array_merge([
            'gym_id'        => $gym->id,
            'serial_number' => 'SN12345',
            'name'          => 'Main Entrance',
            'api_key'       => BiometricDevice::generateApiKey(),
            'is_active'     => true,
        ], $overrides));
    }

    public function test_push_is_authenticated_by_serial_number_alone(): void
    {
        $gym    = $this->makeGym();
        $device = $this->makeDevice($gym);
        $user   = User::create([
            'gym_id'   => $gym->id,
            'name'     => 'Member One',
            'email'    => 'member1@test.local',
            'password' => 'irrelevant',
            'status'   => 'active',
        ]);

        // No X-Api-Key header/param at all -- only SN, as real devices send.
        $response = $this->postJson('/api/biometric/push?SN=' . $device->serial_number, [
            'records' => [
                ['employee_id' => (string) $user->id, 'time' => '2026-07-13 09:00:00', 'type' => 0],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'source'  => 'biometric',
        ]);
    }

    public function test_push_with_unknown_serial_number_and_no_api_key_is_rejected(): void
    {
        $response = $this->postJson('/api/biometric/push?SN=does-not-exist', [
            'records' => [
                ['employee_id' => '1', 'time' => '2026-07-13 09:00:00', 'type' => 0],
            ],
        ]);

        $response->assertStatus(401);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=BiometricPushControllerTest`
Expected: `test_push_is_authenticated_by_serial_number_alone` FAILS with a 401 (no `SN` handling yet — only `api_key` is checked).

- [ ] **Step 3: Implement SN-based lookup with api_key fallback**

In `app/Http/Controllers/Api/BiometricPushController.php`, replace the `receive()` device-resolution block (lines 39–52):

```php
    public function receive(Request $request)
    {
        $device = $this->resolveDevice($request);

        if (! $device) {
            return response()->json(['error' => 'Device not registered or inactive'], 401);
        }
```

Add a new private method (place it in the `// ── Private ──` section, near `parseLogs`):

```php
    private function resolveDevice(Request $request): ?BiometricDevice
    {
        $serialNumber = $request->query('SN') ?? $request->input('SN');

        if ($serialNumber) {
            $device = BiometricDevice::where('serial_number', $serialNumber)->active()->first();
            if ($device) {
                return $device;
            }
        }

        $apiKey = $request->header('X-Api-Key')
            ?? $request->query('api_key')
            ?? $request->input('api_key');

        if (! $apiKey) {
            return null;
        }

        return BiometricDevice::where('api_key', $apiKey)->active()->first();
    }
```

Update the class docblock (lines 17–21) to document the `SN` param instead of implying a header is required:

```php
 * Machine setup (in device web panel or LCD menu):
 *   Server Address : yoursaas.com
 *   Server Port    : 443 (HTTPS) or 80
 *   URL Path       : /api/biometric/push
 *
 * Devices identify themselves via the standard iClock `SN` query param
 * (their serial number, matched against biometric_devices.serial_number) --
 * no custom header configuration is required. `api_key` is still accepted
 * as a fallback for manual testing (Postman/curl).
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --filter=BiometricPushControllerTest`
Expected: both tests PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/BiometricPushController.php tests/Feature/BiometricPushControllerTest.php
git commit -m "Authenticate ZKTeco push by device serial number

Budget WiFi/finger/face terminals only expose Server IP/Port/Path in
their config menu, not custom headers, so requiring an api_key header
meant real hardware could never authenticate. Match by the SN query
param the iClock protocol already sends, keeping api_key as a fallback."
```

---

### Task 2: Reply with plain `OK` instead of JSON

**Files:**
- Modify: `app/Http/Controllers/Api/BiometricPushController.php:53-80` (post-Task-1 line numbers)
- Test: `tests/Feature/BiometricPushControllerTest.php`

- [ ] **Step 1: Write the failing test**

Add to `BiometricPushControllerTest`:

```php
    public function test_successful_push_replies_with_plain_ok_body(): void
    {
        $gym    = $this->makeGym();
        $device = $this->makeDevice($gym);
        $user   = User::create([
            'gym_id'   => $gym->id,
            'name'     => 'Member Two',
            'email'    => 'member2@test.local',
            'password' => 'irrelevant',
            'status'   => 'active',
        ]);

        $response = $this->postJson('/api/biometric/push?SN=' . $device->serial_number, [
            'records' => [
                ['employee_id' => (string) $user->id, 'time' => '2026-07-13 09:00:00', 'type' => 0],
            ],
        ]);

        $response->assertOk();
        $this->assertSame('OK', $response->getContent());
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=test_successful_push_replies_with_plain_ok_body`
Expected: FAIL — current body is a JSON payload (`{"ok":true,...}`), not `OK`.

- [ ] **Step 3: Change the response**

In `receive()`, replace both success-path returns:

```php
        if (empty($logs)) {
            return response()->json(['ok' => true, 'processed' => 0]);
        }
```

```php
        if (empty($logs)) {
            return response('OK', 200);
        }
```

and:

```php
        return response()->json(['ok' => true, 'processed' => $processed, 'errors' => $errors]);
```

```php
        return response('OK', 200);
```

The `foreach` loop keeps logging per-record errors via `Log::warning` exactly as before — only the final response body changes.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --filter=BiometricPushControllerTest`
Expected: all tests in the file PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/BiometricPushController.php tests/Feature/BiometricPushControllerTest.php
git commit -m "Reply to ZKTeco push with plain OK instead of JSON

Real iClock firmware reads the literal body \"OK\" to mark a batch as
delivered; a JSON body reads as a failure and the device may resend
the same batch indefinitely."
```

---

### Task 3: Add duplicate-window guard

**Files:**
- Modify: `app/Http/Controllers/Api/BiometricPushController.php` (constructor + `processLog`)
- Test: `tests/Feature/BiometricPushControllerTest.php`

- [ ] **Step 1: Write the failing test**

Add to `BiometricPushControllerTest`:

```php
    public function test_resent_batch_does_not_double_record_attendance(): void
    {
        $gym    = $this->makeGym();
        $device = $this->makeDevice($gym);
        $user   = User::create([
            'gym_id'   => $gym->id,
            'name'     => 'Member Three',
            'email'    => 'member3@test.local',
            'password' => 'irrelevant',
            'status'   => 'active',
        ]);

        $payload = [
            'records' => [
                ['employee_id' => (string) $user->id, 'time' => '2026-07-13 09:00:00', 'type' => 0],
            ],
        ];

        // Device sends the same batch twice because it never got an ack it recognized before.
        $this->postJson('/api/biometric/push?SN=' . $device->serial_number, $payload)->assertOk();
        $this->postJson('/api/biometric/push?SN=' . $device->serial_number, $payload)->assertOk();

        $this->assertDatabaseCount('attendances', 1);
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=test_resent_batch_does_not_double_record_attendance`
Expected: FAIL — `assertDatabaseCount('attendances', 1)` sees 2 rows, because the second push toggles a check-out on top of the check-in from the first push.

- [ ] **Step 3: Inject `BiometricAttendanceService` and guard `processLog`**

In `app/Http/Controllers/Api/BiometricPushController.php`, update the constructor:

```php
use App\Services\BiometricAttendanceService;
```

```php
    public function __construct(
        private AttendanceService $attendance,
        private BiometricAttendanceService $biometricAttendance,
    ) {}
```

Update `processLog()` to check for a duplicate right after resolving the user, before touching `AttendanceService`:

```php
    private function processLog(array $log, BiometricDevice $device): bool
    {
        $employeeId = trim((string) ($log['employee_id'] ?? ''));
        if ($employeeId === '') {
            return false;
        }

        $user = $this->resolveOrCreateUser($employeeId, $device);

        if (! $user) {
            Log::info('Biometric: unknown employee', [
                'employee_id' => $employeeId,
                'gym_id'      => $device->gym_id,
                'device'      => $device->serial_number,
            ]);
            return false;
        }

        $time = Carbon::parse($log['time']);

        if ($this->biometricAttendance->isDuplicate($user->id, $device->gym_id, $time)) {
            Log::info('Biometric push: duplicate punch skipped', [
                'user_id'     => $user->id,
                'employee_id' => $employeeId,
                'time'        => $time,
                'device'      => $device->serial_number,
            ]);
            return true;
        }

        // Use toggle mode — machine punch = check-in OR check-out automatically
        $this->attendance->processLog(
            user:         $user,
            gymId:        $device->gym_id,
            time:         $time,
            source:       'biometric',
            deviceUserId: $employeeId,
        );

        return true;
    }
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --filter=BiometricPushControllerTest`
Expected: all tests in the file PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/BiometricPushController.php tests/Feature/BiometricPushControllerTest.php
git commit -m "Skip duplicate punches on ZKTeco push, matching /sync and /punch

Reuses BiometricAttendanceService::isDuplicate() so a resent batch
(now expected less often thanks to the OK-body fix, but still
possible on flaky networks) doesn't toggle check-in/check-out twice."
```

---

### Task 4: Regression coverage for the untouched parsers and user-resolution chain

**Files:**
- Test: `tests/Feature/BiometricPushControllerTest.php`

`parseXml`, `parseFormPost`, and `resolveOrCreateUser()`'s `biometric_code` → `id` → `phone` →
auto-create chain are not modified by Tasks 1–3, but they were also never covered by a test
before this plan. Lock in their current behavior now, while we're already adding tests to this
controller.

- [ ] **Step 1: Write the regression tests**

Add to `BiometricPushControllerTest`:

```php
    public function test_xml_iclock_payload_records_attendance(): void
    {
        $gym    = $this->makeGym();
        $device = $this->makeDevice($gym, ['serial_number' => 'SN-XML']);
        $user   = User::create([
            'gym_id'   => $gym->id,
            'name'     => 'Member Four',
            'email'    => 'member4@test.local',
            'password' => 'irrelevant',
            'status'   => 'active',
        ]);

        $xml = '<Log><row pin="' . $user->id . '" time="2026-07-13 09:00:00" status="0" /></Log>';

        $response = $this->call(
            'POST',
            '/api/biometric/push?SN=' . $device->serial_number,
            [], [], [], ['CONTENT_TYPE' => 'application/xml'],
            $xml
        );

        $response->assertOk();
        $this->assertDatabaseHas('attendances', ['user_id' => $user->id, 'source' => 'biometric']);
    }

    public function test_form_post_stamp_payload_records_attendance(): void
    {
        $gym    = $this->makeGym();
        $device = $this->makeDevice($gym, ['serial_number' => 'SN-FORM']);
        $user   = User::create([
            'gym_id'   => $gym->id,
            'name'     => 'Member Five',
            'email'    => 'member5@test.local',
            'password' => 'irrelevant',
            'status'   => 'active',
        ]);

        $response = $this->post('/api/biometric/push?SN=' . $device->serial_number, [
            'table' => 'ATTLOG',
            'Stamp' => "{$user->id}\t2026-07-13 09:00:00\t0\n",
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('attendances', ['user_id' => $user->id, 'source' => 'biometric']);
    }

    public function test_unmatched_employee_id_auto_creates_a_member(): void
    {
        $gym    = $this->makeGym();
        $device = $this->makeDevice($gym, ['serial_number' => 'SN-AUTO']);

        $this->assertDatabaseCount('users', 0);

        $response = $this->postJson('/api/biometric/push?SN=' . $device->serial_number, [
            'records' => [
                ['employee_id' => '999', 'time' => '2026-07-13 09:00:00', 'type' => 0],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('users', ['gym_id' => $gym->id, 'biometric_code' => '999']);
        $this->assertDatabaseCount('attendances', 1);
    }
```

- [ ] **Step 2: Run the tests to verify they pass**

Run: `php artisan test --filter=BiometricPushControllerTest`
Expected: all tests in the file PASS — these lock in existing behavior, so they should pass
without further production-code changes. If `test_unmatched_employee_id_auto_creates_a_member`
fails on the role assignment (`assignRole('member')`), that's expected in a fresh test DB with no
seeded roles — `autoCreateMemberFromDevice()` already catches that with a try/catch, so the user
is still created and the test still passes.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/BiometricPushControllerTest.php
git commit -m "Add regression tests for ZKTeco XML/form-POST parsing and auto-create

Locks in the existing parseXml/parseFormPost/resolveOrCreateUser
behavior with test coverage before it had any."
```

---

### Task 5: Full regression pass

**Files:**
- None (verification only)

- [ ] **Step 1: Run the whole test suite**

Run: `php artisan test`
Expected: all tests PASS, including `tests/Feature/BiometricPushControllerTest.php` and every
pre-existing test.

- [ ] **Step 2: Update README if needed**

Check `README.md` around the biometric section (currently mentions "ZKTeco Biometric device
integration"). If it references API-key-only device auth anywhere, update it to mention SN-based
identification. If it doesn't mention auth details at all, no change needed.
