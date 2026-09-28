# Multi-Brand Biometric Devices Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let owners/admins register ZKTeco/eSSL/Realtime, Hikvision, and any JSON-pushing brand (Suprema BioStar 2, Anviz CrossChex, …) from the Biometric Devices page, each with brand-specific setup steps.

**Architecture:** Driver classes (`ZKTecoDriver`, `HikvisionDriver`, `GenericWebhookDriver`) implement one `BiometricDriver` contract and are listed in `config/biometric.php`. ZKTeco keeps its fixed SN-based URLs; the other brands get a per-device token URL `/api/biometric/hook/{token}`. Every driver returns `PunchLog` objects that one shared `BiometricPunchProcessor` turns into attendance.

**Tech Stack:** Laravel 12, PHP 8.2, Spatie Permission, Blade + vanilla JS (page already uses `post/put/del/toast` helpers from `layouts/app.blade.php`), PHPUnit on SQLite in-memory.

**Spec:** `docs/superpowers/specs/2026-09-28-multi-brand-biometric-design.md`

---

## Conventions every task must follow

- **Tests** run on SQLite. Every feature test class mocks the license check in `setUp()`:
  ```php
  $this->mock(\App\Services\LicenseService::class, fn ($m) => $m->shouldReceive('check')->andReturn(true));
  ```
- Run tests with: `php artisan test --filter=<ClassName>`
- **Time rule (important):** the app timezone is `UTC`, but ZKTeco machines send local wall-clock times that are stored unchanged (09:00 at the gym is saved as `09:00`). Every driver must produce the **same kind of value**: the machine's wall-clock time, labelled with the app timezone. Use `WallClock` (Task 2) for any time that carries an offset or is a Unix timestamp. Never call `->setTimezone('UTC')` on a punch time.
- The existing `BiometricPushControllerTest` (7 tests) and `BiometricConnectionTest` (6 tests) must stay green after every task.

## File map

| File | Responsibility |
|---|---|
| `database/migrations/2026_09_28_000001_add_brand_fields_to_biometric_devices_table.php` | New columns, nullable SN, token backfill |
| `app/Models/BiometricDevice.php` (modify) | Casts, token auto-generation, `timezone()`, `storePayload()` |
| `config/biometric.php` | Driver registry, generic presets, default timezone |
| `app/Biometric/PunchLog.php` | Value object: employeeId, time, type |
| `app/Biometric/WallClock.php` | Instant → device wall-clock time helper |
| `app/Biometric/Contracts/BiometricDriver.php` | Driver interface |
| `app/Biometric/DriverRegistry.php` | Resolve drivers, list for UI/validation |
| `app/Biometric/BiometricPunchProcessor.php` | Member lookup, duplicate check, attendance write (moved from controller) |
| `app/Biometric/Drivers/ZKTecoDriver.php` | Existing JSON/XML/form parsers (moved) |
| `app/Biometric/Drivers/HikvisionDriver.php` | Hikvision HTTP Listening events |
| `app/Biometric/Drivers/GenericWebhookDriver.php` | Field-mapping driver |
| `app/Http/Controllers/Api/BiometricPushController.php` (modify) | ZKTeco URLs → ZKTecoDriver + processor |
| `app/Http/Controllers/Api/BiometricWebhookController.php` | Token URL for non-ZKTeco brands |
| `routes/api.php` (modify) | `/api/biometric/hook/{token}` |
| `app/Http/Controllers/Web/BiometricDeviceWebController.php` (modify) | Brand/settings validation, token regenerate, setup-panel data |
| `routes/web.php` (modify) | regenerate-token + setup routes |
| `resources/views/biometric/devices.blade.php` (modify) | Brand-aware add/edit form, badges, Setup/Test panel |

---

### Task 0: Commit the pending, unrelated work first

The working tree already holds finished but uncommitted work from earlier (trainer portal, role locks, biometric connection status). Commit it in two commits so the multi-brand commits stay clean.

- [ ] **Step 1: Check the tree and run the suite**

Run: `git status --short` and `php artisan test`
Expected: only `ExampleTest` fails (license, known). Everything else passes.

- [ ] **Step 2: Commit the trainer workspace and role locks together**

`routes/web.php` and the sidebar layout carry changes for both, so they go in one commit (a split would leave a broken intermediate commit).

```bash
git add app/Http/Controllers/Trainer resources/views/trainer app/Services/TrainerService.php app/Http/Controllers/Web/DashboardController.php resources/views/dashboard-trainer.blade.php tests/Feature/TrainerPortalTest.php routes/web.php routes/api.php app/Http/Controllers/Controller.php app/Http/Controllers/Api/AttendanceController.php app/Http/Controllers/Api/TrainerController.php app/Http/Controllers/Web/TrainerWebController.php app/Http/Controllers/Web/TrainerCommissionWebController.php resources/views/trainers/commission.blade.php resources/views/layouts/app.blade.php tests/Feature/RoleAccessTest.php
git commit -m "Add trainer workspace and lock web/API routes by role

Co-Authored-By: Claude Opus 5.5 (1M context) <noreply@anthropic.com>"
```

- [ ] **Step 3: (merged into Step 2)**

- [ ] **Step 4: Commit the biometric connection status**

```bash
git add app/Models/BiometricDevice.php app/Services/UnknownBiometricDevices.php app/Http/Controllers/Api/BiometricPushController.php app/Http/Controllers/Web/BiometricDeviceWebController.php resources/views/biometric/devices.blade.php tests/Feature/BiometricConnectionTest.php tests/Feature/BiometricPushControllerTest.php
git commit -m "Show biometric device online status and track unregistered serials

Co-Authored-By: Claude Opus 5.5 (1M context) <noreply@anthropic.com>"
```

- [ ] **Step 5: Verify** `git status --short` shows a clean tree, apart from this plan file if it is not yet committed.

---

### Task 1: Migration and model fields

**Files:**
- Create: `database/migrations/2026_09_28_000001_add_brand_fields_to_biometric_devices_table.php`
- Create: `config/biometric.php` (only the `timezone` key for now; Task 2 adds the rest)
- Modify: `app/Models/BiometricDevice.php`
- Test: `tests/Feature/BiometricDeviceModelTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\BiometricDevice;
use App\Models\Gym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BiometricDeviceModelTest extends TestCase
{
    use RefreshDatabase;

    private function gym(): Gym
    {
        return Gym::create(['name' => 'G', 'slug' => 'g', 'email' => 'g@test.local', 'status' => 'active']);
    }

    public function test_new_device_defaults_to_zkteco_and_gets_a_token(): void
    {
        $d = BiometricDevice::create([
            'gym_id' => $this->gym()->id, 'serial_number' => 'SN1', 'name' => 'Door',
            'api_key' => BiometricDevice::generateApiKey(),
        ]);

        $d->refresh();
        $this->assertSame('zkteco', $d->brand);
        $this->assertSame(40, strlen($d->webhook_token));
    }

    public function test_serial_number_is_optional(): void
    {
        $d = BiometricDevice::create([
            'gym_id' => $this->gym()->id, 'name' => 'Face', 'brand' => 'hikvision',
            'api_key' => BiometricDevice::generateApiKey(),
        ]);

        $this->assertNull($d->fresh()->serial_number);
    }

    public function test_settings_cast_and_timezone_fallback(): void
    {
        $d = BiometricDevice::create([
            'gym_id' => $this->gym()->id, 'name' => 'X', 'brand' => 'generic',
            'api_key' => BiometricDevice::generateApiKey(), 'settings' => ['timezone' => 'Asia/Dubai'],
        ]);

        $this->assertSame('Asia/Dubai', $d->fresh()->timezone());

        $d->update(['settings' => []]);
        $this->assertSame(config('biometric.timezone'), $d->fresh()->timezone());
    }

    public function test_store_payload_truncates_to_10kb(): void
    {
        $d = BiometricDevice::create([
            'gym_id' => $this->gym()->id, 'name' => 'X', 'api_key' => BiometricDevice::generateApiKey(),
        ]);

        $d->storePayload(str_repeat('a', 20000));

        $d->refresh();
        $this->assertSame(10240, strlen($d->last_payload));
        $this->assertNotNull($d->last_payload_at);
    }

    public function test_regenerate_webhook_token_changes_it(): void
    {
        $d = BiometricDevice::create([
            'gym_id' => $this->gym()->id, 'name' => 'X', 'api_key' => BiometricDevice::generateApiKey(),
        ]);
        $old = $d->webhook_token;

        $d->regenerateWebhookToken();

        $this->assertNotSame($old, $d->fresh()->webhook_token);
    }
}
```

- [ ] **Step 2: Run it and confirm it fails**

Run: `php artisan test --filter=BiometricDeviceModelTest`
Expected: FAIL with `no such column: brand`.

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biometric_devices', function (Blueprint $table) {
            $table->string('brand', 30)->default('zkteco')->after('gym_id');
            $table->string('webhook_token', 64)->nullable()->unique()->after('api_key');
            $table->json('settings')->nullable()->after('webhook_token');
            $table->text('last_payload')->nullable()->after('last_seen_at');
            $table->timestamp('last_payload_at')->nullable()->after('last_payload');
            $table->string('serial_number')->nullable()->change();
        });

        DB::table('biometric_devices')->whereNull('webhook_token')->orderBy('id')->each(function ($row) {
            DB::table('biometric_devices')->where('id', $row->id)->update(['webhook_token' => Str::random(40)]);
        });
    }

    public function down(): void
    {
        Schema::table('biometric_devices', function (Blueprint $table) {
            $table->dropUnique(['webhook_token']);
            $table->dropColumn(['brand', 'webhook_token', 'settings', 'last_payload', 'last_payload_at']);
        });
    }
};
```

(`serial_number` stays nullable on rollback; making it `NOT NULL` again would fail if Hikvision rows without an SN exist.)

- [ ] **Step 4: Create `config/biometric.php`**

```php
<?php

return [
    // Timezone the machines' clocks run in. Used to turn Unix timestamps and
    // offset-carrying times into the wall-clock time we store (see WallClock).
    'timezone' => env('BIOMETRIC_TIMEZONE', 'Asia/Karachi'),
];
```

- [ ] **Step 5: Update the model**

In `app/Models/BiometricDevice.php`, replace `$fillable` and `$casts`, and add a `booted()` method and three helpers under `generateApiKey()`:

```php
    protected $fillable = [
        'gym_id', 'brand', 'serial_number', 'name', 'model',
        'location', 'api_key', 'webhook_token', 'settings',
        'is_active', 'last_seen_at',
    ];

    protected $casts = [
        'is_active'       => 'boolean',
        'last_seen_at'    => 'datetime',
        'last_payload_at' => 'datetime',
        'settings'        => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $device) {
            $device->brand         ??= 'zkteco';
            $device->webhook_token ??= Str::random(40);
        });
    }
```

```php
    public function regenerateWebhookToken(): void
    {
        $this->forceFill(['webhook_token' => Str::random(40)])->save();
    }

    /** Timezone the machine's clock runs in. */
    public function timezone(): string
    {
        return $this->settings['timezone'] ?? config('biometric.timezone');
    }

    /** Keep a copy of the last raw request (capped at 10 KB) for the Setup / Test panel. */
    public function storePayload(string $raw): void
    {
        $this->forceFill([
            'last_payload'    => substr($raw, 0, 10240),
            'last_payload_at' => now(),
        ])->saveQuietly();
    }
```

- [ ] **Step 6: Run the tests**

Run: `php artisan test --filter="BiometricDeviceModelTest|BiometricPushControllerTest|BiometricConnectionTest"`
Expected: all PASS.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_09_28_000001_add_brand_fields_to_biometric_devices_table.php config/biometric.php app/Models/BiometricDevice.php tests/Feature/BiometricDeviceModelTest.php
git commit -m "Add brand, webhook token, settings and last payload to biometric devices

Co-Authored-By: Claude Opus 5.5 (1M context) <noreply@anthropic.com>"
```

---

### Task 2: Driver contract, PunchLog, WallClock, registry

**Files:**
- Create: `app/Biometric/PunchLog.php`, `app/Biometric/WallClock.php`, `app/Biometric/Contracts/BiometricDriver.php`, `app/Biometric/DriverRegistry.php`
- Modify: `config/biometric.php`
- Test: `tests/Unit/Biometric/WallClockTest.php`, `tests/Feature/DriverRegistryTest.php`

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Biometric/WallClockTest.php`:

```php
<?php

namespace Tests\Unit\Biometric;

use App\Biometric\WallClock;
use Carbon\Carbon;
use Tests\TestCase;

class WallClockTest extends TestCase
{
    public function test_offset_time_keeps_gym_wall_clock(): void
    {
        $t = WallClock::fromInstant(Carbon::parse('2026-09-28T09:00:00+05:00'), 'Asia/Karachi');

        $this->assertSame('2026-09-28 09:00:00', $t->format('Y-m-d H:i:s'));
        $this->assertSame(config('app.timezone'), $t->timezoneName);
    }

    public function test_utc_instant_is_shown_in_gym_time(): void
    {
        $t = WallClock::fromInstant(Carbon::parse('2026-09-28T04:00:00Z'), 'Asia/Karachi');

        $this->assertSame('2026-09-28 09:00:00', $t->format('Y-m-d H:i:s'));
    }

    public function test_unix_timestamp(): void
    {
        $ts = Carbon::parse('2026-09-28T04:00:00Z')->timestamp;

        $this->assertSame('2026-09-28 09:00:00', WallClock::fromUnix($ts, 'Asia/Karachi')->format('Y-m-d H:i:s'));
    }

    public function test_naive_string_is_taken_as_is(): void
    {
        $this->assertSame('2026-09-28 09:00:00', WallClock::parse('2026-09-28 09:00:00', 'Asia/Karachi')->format('Y-m-d H:i:s'));
    }
}
```

`tests/Feature/DriverRegistryTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Biometric\Contracts\BiometricDriver;
use App\Biometric\DriverRegistry;
use Tests\TestCase;

class DriverRegistryTest extends TestCase
{
    public function test_lists_configured_brands_with_labels(): void
    {
        $options = app(DriverRegistry::class)->options();

        $this->assertSame(['zkteco', 'hikvision', 'generic'], array_keys($options));
        $this->assertSame('ZKTeco / eSSL / Realtime', $options['zkteco']);
    }

    public function test_resolves_driver_instances(): void
    {
        $driver = app(DriverRegistry::class)->get('zkteco');

        $this->assertInstanceOf(BiometricDriver::class, $driver);
        $this->assertSame('zkteco', $driver->key());
    }

    public function test_unknown_brand_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(DriverRegistry::class)->get('nope');
    }
}
```

`DriverRegistryTest` stays red until Tasks 4–6 create the driver classes. This task only needs `WallClockTest` to pass.

- [ ] **Step 2: Run the WallClock test and confirm it fails**

Run: `php artisan test --filter=WallClockTest`
Expected: FAIL with `Class "App\Biometric\WallClock" not found`.

- [ ] **Step 3: Create `app/Biometric/PunchLog.php`**

```php
<?php

namespace App\Biometric;

use Carbon\Carbon;

/** One punch read from a machine, already normalised to wall-clock time. */
final class PunchLog
{
    public const IN  = 'in';
    public const OUT = 'out';

    public function __construct(
        public readonly string $employeeId,
        public readonly Carbon $time,
        public readonly ?string $type = null, // in | out | null (toggle)
    ) {}
}
```

- [ ] **Step 4: Create `app/Biometric/WallClock.php`**

```php
<?php

namespace App\Biometric;

use Carbon\Carbon;

/**
 * Attendance stores the machine's wall-clock time labelled with the app timezone
 * (that is how ZKTeco punches have always been saved). These helpers turn any
 * machine time into that same form.
 */
final class WallClock
{
    /** A real instant (has an offset, or is UTC) → wall-clock time in $deviceTz. */
    public static function fromInstant(Carbon $instant, string $deviceTz): Carbon
    {
        return $instant->copy()->setTimezone($deviceTz)->shiftTimezone(config('app.timezone'));
    }

    public static function fromUnix(int|float $seconds, string $deviceTz): Carbon
    {
        return self::fromInstant(Carbon::createFromTimestamp($seconds, 'UTC'), $deviceTz);
    }

    /** A string that may or may not carry an offset. Without one it is already wall-clock. */
    public static function parse(string $value, string $deviceTz): Carbon
    {
        $hasOffset = (bool) preg_match('/(Z|[+-]\d{2}:?\d{2})$/', trim($value));

        return $hasOffset
            ? self::fromInstant(Carbon::parse($value), $deviceTz)
            : Carbon::parse($value, config('app.timezone'));
    }
}
```

- [ ] **Step 5: Create `app/Biometric/Contracts/BiometricDriver.php`**

```php
<?php

namespace App\Biometric\Contracts;

use App\Biometric\PunchLog;
use App\Models\BiometricDevice;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

interface BiometricDriver
{
    /** Registry key, stored in biometric_devices.brand. */
    public function key(): string;

    /** Shown in the brand dropdown. */
    public function label(): string;

    /** 'serial' = fixed URL, device found by SN. 'token' = per-device /api/biometric/hook/{token}. */
    public function identifiesBy(): string;

    /** @return PunchLog[] */
    public function parse(Request $request, BiometricDevice $device): array;

    /** Reply the machine expects after a push. */
    public function acknowledge(): Response;

    /** @return string[] ordered, human-readable setup steps */
    public function setupSteps(BiometricDevice $device, string $baseUrl): array;

    /** Validation rules for keys inside biometric_devices.settings, e.g. ['settings.timezone' => [...]]. */
    public function settingsRules(): array;
}
```

- [ ] **Step 6: Create `app/Biometric/DriverRegistry.php`**

```php
<?php

namespace App\Biometric;

use App\Biometric\Contracts\BiometricDriver;

class DriverRegistry
{
    /** @return array<string, string> key => label, in config order */
    public function options(): array
    {
        return collect(config('biometric.drivers'))
            ->map(fn ($class, $key) => $this->get($key)->label())
            ->all();
    }

    public function keys(): array
    {
        return array_keys(config('biometric.drivers'));
    }

    public function get(string $key): BiometricDriver
    {
        $class = config("biometric.drivers.{$key}");

        if (! $class) {
            throw new \InvalidArgumentException("Unknown biometric brand [{$key}].");
        }

        return app($class);
    }
}
```

- [ ] **Step 7: Add drivers and presets to `config/biometric.php`**

```php
<?php

return [
    // Timezone the machines' clocks run in. Used to turn Unix timestamps and
    // offset-carrying times into the wall-clock time we store (see WallClock).
    'timezone' => env('BIOMETRIC_TIMEZONE', 'Asia/Karachi'),

    // Brand dropdown, in display order. To add a brand: write a driver class and add a line.
    'drivers' => [
        'zkteco'    => App\Biometric\Drivers\ZKTecoDriver::class,
        'hikvision' => App\Biometric\Drivers\HikvisionDriver::class,
        'generic'   => App\Biometric\Drivers\GenericWebhookDriver::class,
    ],

    // Starting points for the Generic Webhook form. UNVERIFIED against real payloads:
    // the UI tells the user to confirm with a test punch and the Last received data panel.
    'generic_presets' => [
        'suprema_biostar2' => [
            'label'          => 'Suprema BioStar 2 (unverified)',
            'records_path'   => 'events',
            'employee_field' => 'user_id.user_id',
            'time_field'     => 'datetime',
            'time_format'    => 'iso',
        ],
        'anviz_crosschex' => [
            'label'          => 'Anviz CrossChex Cloud (unverified)',
            'records_path'   => 'payload.list',
            'employee_field' => 'employee.workno',
            'time_field'     => 'checktime',
            'time_format'    => 'auto',
            'type_field'     => 'checktype',
            'type_in_value'  => '0',
            'type_out_value' => '1',
        ],
    ],
];
```

- [ ] **Step 8: Run the WallClock test**

Run: `php artisan test --filter=WallClockTest`
Expected: 4 PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Biometric config/biometric.php tests/Unit/Biometric/WallClockTest.php tests/Feature/DriverRegistryTest.php
git commit -m "Add biometric driver contract, PunchLog, WallClock and registry

Co-Authored-By: Claude Opus 5.5 (1M context) <noreply@anthropic.com>"
```

---

### Task 3: BiometricPunchProcessor (move shared logic out of the controller)

**Files:**
- Create: `app/Biometric/BiometricPunchProcessor.php`
- Test: `tests/Feature/BiometricPunchProcessorTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Biometric\BiometricPunchProcessor;
use App\Biometric\PunchLog;
use App\Models\Attendance;
use App\Models\BiometricDevice;
use App\Models\Gym;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BiometricPunchProcessorTest extends TestCase
{
    use RefreshDatabase;

    private BiometricDevice $device;
    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web']);

        $gym = Gym::create(['name' => 'G', 'slug' => 'g', 'email' => 'g@test.local', 'status' => 'active']);
        $this->device = BiometricDevice::create(['gym_id' => $gym->id, 'name' => 'D', 'api_key' => 'k', 'brand' => 'hikvision']);
        $this->member = User::create(['gym_id' => $gym->id, 'name' => 'M', 'email' => 'm@test.local', 'password' => 'x', 'status' => 'active', 'biometric_code' => '77']);
    }

    private function process(string $time, ?string $type = null): bool
    {
        return app(BiometricPunchProcessor::class)->process(new PunchLog('77', Carbon::parse($time), $type), $this->device);
    }

    public function test_toggle_mode_checks_in_then_out(): void
    {
        $this->process('2026-09-28 09:00:00');
        $this->process('2026-09-28 10:30:00');

        $a = Attendance::first();
        $this->assertSame('2026-09-28 09:00:00', $a->check_in_time->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-28 10:30:00', $a->check_out_time->format('Y-m-d H:i:s'));
    }

    public function test_explicit_in_while_already_in_is_ignored(): void
    {
        $this->process('2026-09-28 09:00:00', PunchLog::IN);
        $this->process('2026-09-28 09:30:00', PunchLog::IN);

        $this->assertSame(1, Attendance::count());
        $this->assertNull(Attendance::first()->check_out_time);
    }

    public function test_explicit_out_without_open_session_is_ignored(): void
    {
        $this->process('2026-09-28 09:00:00', PunchLog::OUT);

        $this->assertSame(0, Attendance::count());
    }

    public function test_duplicate_within_window_is_skipped(): void
    {
        $this->process('2026-09-28 09:00:00');
        $this->process('2026-09-28 09:00:30');

        $this->assertSame(1, Attendance::count());
        $this->assertNull(Attendance::first()->check_out_time);
    }

    public function test_unknown_employee_is_auto_created_in_device_gym(): void
    {
        app(BiometricPunchProcessor::class)->process(new PunchLog('999', Carbon::parse('2026-09-28 09:00:00')), $this->device);

        $this->assertDatabaseHas('users', ['biometric_code' => '999', 'gym_id' => $this->device->gym_id]);
    }
}
```

- [ ] **Step 2: Run it and confirm it fails**

Run: `php artisan test --filter=BiometricPunchProcessorTest`
Expected: FAIL with `Class "App\Biometric\BiometricPunchProcessor" not found`.

- [ ] **Step 3: Create the processor**

The member lookup and auto-create code is copied unchanged from `BiometricPushController::processLog/resolveOrCreateUser/autoCreateMemberFromDevice`. Only the explicit in/out branch is new.

```php
<?php

namespace App\Biometric;

use App\Models\BiometricDevice;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\BiometricAttendanceService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Turns a PunchLog from any brand into attendance for the device's gym. */
class BiometricPunchProcessor
{
    public function __construct(
        private AttendanceService $attendance,
        private BiometricAttendanceService $biometricAttendance,
    ) {}

    public function process(PunchLog $log, BiometricDevice $device): bool
    {
        $employeeId = trim($log->employeeId);
        if ($employeeId === '') {
            return false;
        }

        $user = $this->resolveOrCreateUser($employeeId, $device);

        if (! $user) {
            Log::info('Biometric: unknown employee', [
                'employee_id' => $employeeId,
                'gym_id'      => $device->gym_id,
                'device_id'   => $device->id,
            ]);
            return false;
        }

        $time = $log->time;

        if ($this->biometricAttendance->isDuplicate($user->id, $device->gym_id, $time)) {
            Log::info('Biometric push: duplicate punch skipped', [
                'user_id'     => $user->id,
                'employee_id' => $employeeId,
                'time'        => $time,
                'device_id'   => $device->id,
            ]);
            return true;
        }

        $open = $this->attendance->findOpenSession($user->id, $device->gym_id);

        // Machine said "in" but member is already inside, or "out" with nothing open: ignore.
        if (($log->type === PunchLog::IN && $open) || ($log->type === PunchLog::OUT && ! $open)) {
            return true;
        }

        // null type (and consistent in/out) → toggle, exactly as ZKTeco always worked
        $this->attendance->processLog(
            user:         $user,
            gymId:        $device->gym_id,
            time:         $time,
            source:       'biometric',
            deviceUserId: $employeeId,
        );

        return true;
    }

    private function resolveOrCreateUser(string $employeeId, BiometricDevice $device): ?User
    {
        $gymId = $device->gym_id;

        $byCode = User::where('gym_id', $gymId)
            ->where('biometric_code', $employeeId)
            ->first();

        if ($byCode) {
            return $byCode;
        }

        $byId = User::where('gym_id', $gymId)
            ->where('id', $employeeId)
            ->first();

        if ($byId) {
            if (empty($byId->biometric_code)) {
                $byId->update(['biometric_code' => $employeeId]);
            }

            return $byId;
        }

        $byPhone = User::where('gym_id', $gymId)
            ->where('phone', $employeeId)
            ->first();

        if ($byPhone) {
            if (empty($byPhone->biometric_code)) {
                $byPhone->update(['biometric_code' => $employeeId]);
            }

            return $byPhone;
        }

        return $this->autoCreateMemberFromDevice($employeeId, $device);
    }

    private function autoCreateMemberFromDevice(string $employeeId, BiometricDevice $device): ?User
    {
        $gymId = $device->gym_id;
        $email = sprintf(
            'device-%s-%s@local.member',
            preg_replace('/[^A-Za-z0-9]/', '', $employeeId) ?: 'member',
            Str::lower(Str::random(8))
        );

        $user = User::create([
            'gym_id'         => $gymId,
            'name'           => 'Device Member ' . $employeeId,
            'email'          => $email,
            'password'       => Str::random(32),
            'status'         => 'active',
            'biometric_code' => $employeeId,
        ]);

        try {
            if (! $user->hasRole('member')) {
                $user->assignRole('member');
            }
        } catch (\Throwable $e) {
            Log::warning('Biometric auto-create role assign failed', [
                'user_id'     => $user->id,
                'gym_id'      => $gymId,
                'employee_id' => $employeeId,
                'error'       => $e->getMessage(),
            ]);
        }

        Log::info('Biometric auto-created member from device', [
            'user_id'     => $user->id,
            'gym_id'      => $gymId,
            'employee_id' => $employeeId,
            'device_id'   => $device->id,
        ]);

        return $user;
    }
}
```

- [ ] **Step 4: Run the tests**

Run: `php artisan test --filter=BiometricPunchProcessorTest`
Expected: 5 PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Biometric/BiometricPunchProcessor.php tests/Feature/BiometricPunchProcessorTest.php
git commit -m "Extract biometric punch processing into shared processor

Co-Authored-By: Claude Opus 5.5 (1M context) <noreply@anthropic.com>"
```

---

### Task 4: ZKTecoDriver and slim push controller

**Files:**
- Create: `app/Biometric/Drivers/ZKTecoDriver.php`
- Modify: `app/Http/Controllers/Api/BiometricPushController.php`
- Test: existing `tests/Feature/BiometricPushControllerTest.php` (regression, no edits)

- [ ] **Step 1: Run the regression suite first (baseline)**

Run: `php artisan test --filter="BiometricPushControllerTest|BiometricConnectionTest"`
Expected: 13 PASS. These must still pass after the refactor.

- [ ] **Step 2: Create `app/Biometric/Drivers/ZKTecoDriver.php`**

The parsers are moved unchanged from the controller. They now return `PunchLog` objects with `type = null` (toggle), because the controller never used ZKTeco's status field either ("not always reliable").

```php
<?php

namespace App\Biometric\Drivers;

use App\Biometric\Contracts\BiometricDriver;
use App\Biometric\PunchLog;
use App\Biometric\WallClock;
use App\Models\BiometricDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/** ZKTeco ADMS / iClock push. Also eSSL and ZK-based Realtime machines. */
class ZKTecoDriver implements BiometricDriver
{
    public function key(): string        { return 'zkteco'; }
    public function label(): string      { return 'ZKTeco / eSSL / Realtime'; }
    public function identifiesBy(): string { return 'serial'; }

    public function acknowledge(): Response
    {
        return response('OK', 200);
    }

    public function settingsRules(): array
    {
        return [];
    }

    public function setupSteps(BiometricDevice $device, string $baseUrl): array
    {
        $host = parse_url($baseUrl, PHP_URL_HOST);
        $port = parse_url($baseUrl, PHP_URL_PORT) ?: (str_starts_with($baseUrl, 'https') ? 443 : 80);

        return [
            'On the machine open COMM → Cloud Server Setting (ADMS).',
            "Server address: {$host}",
            "Server port: {$port} (if the machine won't connect over HTTPS, try port 80 / HTTP).",
            'Leave the URL path empty or set it to /api/biometric/push.',
            "The machine's serial number must be exactly: " . ($device->serial_number ?: '—'),
            "Enroll each member on the machine with their Biometric Code (or User ID) as the Employee Number.",
        ];
    }

    public function parse(Request $request, BiometricDevice $device): array
    {
        $contentType = $request->header('Content-Type', '');
        $raw         = $request->getContent();

        if (str_contains($contentType, 'json') || str_starts_with(trim($raw), '{') || str_starts_with(trim($raw), '[')) {
            $rows = $this->parseJson($request);
        } elseif (str_contains($contentType, 'xml') || str_starts_with(trim($raw), '<')) {
            $rows = $this->parseXml($raw);
        } elseif ($request->has('table') || $request->has('Stamp')) {
            $rows = $this->parseFormPost($request);
        } else {
            $rows = [];
        }

        $tz = $device->timezone();

        return array_values(array_map(
            fn ($r) => new PunchLog((string) $r['employee_id'], WallClock::parse((string) $r['time'], $tz)),
            $rows
        ));
    }

    /**
     * JSON format — PUSH SDK v3 (G3, SpeedFace series etc.)
     * { "records": [{ "employee_id": "5", "time": "2026-05-12 09:00:00", "type": 0 }] }
     */
    private function parseJson(Request $request): array
    {
        $logs = [];

        $records = $request->input('records')
            ?? $request->input('attendance_log')
            ?? (is_array($request->all()) ? $request->all() : []);

        foreach ((array) $records as $rec) {
            $logs[] = [
                'employee_id' => $rec['employee_id'] ?? $rec['EnrollNumber'] ?? $rec['user_id'] ?? null,
                'time'        => $rec['time'] ?? $rec['LogTime'] ?? $rec['timestamp'] ?? null,
            ];
        }

        return array_filter($logs, fn ($l) => $l['employee_id'] && $l['time']);
    }

    /**
     * XML format — iClock protocol (F18, K40, MA300, UA860 etc.)
     * <Log><row pin="5" time="2026-05-12 09:00:00" status="0" /></Log>
     */
    private function parseXml(string $raw): array
    {
        $logs = [];

        try {
            $xml = simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NOERROR);
            if (! $xml) return [];

            $rows = $xml->row ?? $xml->record ?? $xml->Log->row ?? [];

            foreach ($rows as $row) {
                $attrs = (array) $row->attributes();
                $attr  = $attrs['@attributes'] ?? [];

                $logs[] = [
                    'employee_id' => $attr['pin'] ?? $attr['uid'] ?? $attr['EnrollNumber'] ?? null,
                    'time'        => $attr['time'] ?? $attr['LogTime'] ?? null,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('ZKTeco XML parse error: ' . $e->getMessage());
        }

        return array_filter($logs, fn ($l) => $l['employee_id'] && $l['time']);
    }

    /**
     * Form-POST format — iClock legacy (table=ATTLOG&Stamp=...)
     * "5\t2026-05-12 09:00:00\t0\t1\t\t0\n..."
     */
    private function parseFormPost(Request $request): array
    {
        $logs  = [];
        $stamp = $request->input('Stamp', '');

        foreach (explode("\n", trim($stamp)) as $line) {
            $line = trim($line);
            if (! $line) continue;

            $parts = preg_split('/\t+/', $line);
            if (count($parts) < 2) continue;

            $logs[] = [
                'employee_id' => $parts[0] ?? null,
                'time'        => $parts[1] ?? null,
            ];
        }

        return array_filter($logs, fn ($l) => $l['employee_id'] && $l['time']);
    }
}
```

- [ ] **Step 3: Rewrite `app/Http/Controllers/Api/BiometricPushController.php`**

Replace the whole file. The routes, SN/api_key lookup, `markSeen`, the unknown-device recording and the responses stay the same. Parsing and processing are delegated to the driver and the processor, and the raw payload is stored.

```php
<?php

namespace App\Http\Controllers\Api;

use App\Biometric\BiometricPunchProcessor;
use App\Biometric\Drivers\ZKTecoDriver;
use App\Http\Controllers\Controller;
use App\Models\BiometricDevice;
use App\Services\UnknownBiometricDevices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Fixed-URL endpoint for ZKTeco ADMS / iClock push (also eSSL, ZK-based Realtime).
 *
 * Machine setup: Server Address = our host, Port = 80/443, URL path = /api/biometric/push.
 * Devices identify themselves via the iClock `SN` query param (matched against
 * biometric_devices.serial_number). `api_key` is still accepted as a fallback for
 * manual testing (Postman/curl).
 *
 * Other brands use per-device token URLs — see BiometricWebhookController.
 */
class BiometricPushController extends Controller
{
    public function __construct(
        private ZKTecoDriver $driver,
        private BiometricPunchProcessor $processor,
        private UnknownBiometricDevices $unknownDevices,
    ) {}

    public function receive(Request $request)
    {
        $device = $this->findDevice($request);

        if (! $device) {
            $this->recordUnknown($request, 'push');
            return response()->json(['error' => 'Device not registered or inactive'], 401);
        }

        // Mark seen even when disabled, so the owner can see the machine is still trying to connect.
        $device->markSeen();
        $device->storePayload($request->getContent() ?: http_build_query($request->all()));

        if (! $device->is_active) {
            return response()->json(['error' => 'Device not registered or inactive'], 401);
        }

        foreach ($this->driver->parse($request, $device) as $log) {
            try {
                $this->processor->process($log, $device);
            } catch (\Throwable $e) {
                Log::warning('Biometric log error', [
                    'device' => $device->serial_number,
                    'log'    => ['employee_id' => $log->employeeId, 'time' => (string) $log->time],
                    'error'  => $e->getMessage(),
                ]);
            }
        }

        return $this->driver->acknowledge();
    }

    /** ZKTeco heartbeat — the machine checks the server is alive. */
    public function ping(Request $request)
    {
        $device = $this->findDevice($request);

        $device ? $device->markSeen() : $this->recordUnknown($request, 'ping');

        return response('OK', 200);
    }

    /** Registered device by SN (or api_key fallback), active or not — callers check is_active. */
    private function findDevice(Request $request): ?BiometricDevice
    {
        $serialNumber = $this->serialNumber($request);

        if ($serialNumber) {
            $device = BiometricDevice::where('serial_number', $serialNumber)->first();
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

        return BiometricDevice::where('api_key', $apiKey)->first();
    }

    private function serialNumber(Request $request): ?string
    {
        $sn = $request->query('SN') ?? $request->input('SN');

        return is_string($sn) && $sn !== '' ? $sn : null;
    }

    private function recordUnknown(Request $request, string $endpoint): void
    {
        if ($sn = $this->serialNumber($request)) {
            $this->unknownDevices->record($sn, $request->ip(), $endpoint);
        }
    }
}
```

- [ ] **Step 4: Run the regression suite**

Run: `php artisan test --filter="BiometricPushControllerTest|BiometricConnectionTest|BiometricPunchProcessorTest"`
Expected: all PASS, with no changes to the old tests.

- [ ] **Step 5: Commit**

```bash
git add app/Biometric/Drivers/ZKTecoDriver.php app/Http/Controllers/Api/BiometricPushController.php
git commit -m "Move ZKTeco parsing into a driver, controller delegates to processor

Co-Authored-By: Claude Opus 5.5 (1M context) <noreply@anthropic.com>"
```

---

### Task 5: HikvisionDriver

**Files:**
- Create: `app/Biometric/Drivers/HikvisionDriver.php`
- Test: `tests/Feature/HikvisionDriverTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Biometric\Drivers\HikvisionDriver;
use App\Biometric\PunchLog;
use App\Models\BiometricDevice;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class HikvisionDriverTest extends TestCase
{
    private function device(): BiometricDevice
    {
        return new BiometricDevice(['brand' => 'hikvision', 'settings' => ['timezone' => 'Asia/Karachi']]);
    }

    private function event(array $overrides = []): array
    {
        return array_replace_recursive([
            'ipAddress' => '192.168.1.64',
            'dateTime'  => '2026-09-28T09:00:00+05:00',
            'eventType' => 'AccessControllerEvent',
            'AccessControllerEvent' => [
                'majorEventType'   => 5,
                'subEventType'     => 75,
                'employeeNoString' => '42',
                'attendanceStatus' => 'checkIn',
            ],
        ], $overrides);
    }

    private function jsonRequest(array $body): Request
    {
        return Request::create('/x', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($body));
    }

    public function test_parses_raw_json_access_event(): void
    {
        $logs = app(HikvisionDriver::class)->parse($this->jsonRequest($this->event()), $this->device());

        $this->assertCount(1, $logs);
        $this->assertSame('42', $logs[0]->employeeId);
        $this->assertSame('2026-09-28 09:00:00', $logs[0]->time->format('Y-m-d H:i:s'));
        $this->assertSame(PunchLog::IN, $logs[0]->type);
    }

    public function test_parses_multipart_event_log_and_ignores_images(): void
    {
        $request = Request::create('/x', 'POST', ['event_log' => json_encode($this->event([
            'AccessControllerEvent' => ['attendanceStatus' => 'checkOut'],
        ]))], [], ['Picture' => UploadedFile::fake()->image('face.jpg')]);

        $logs = app(HikvisionDriver::class)->parse($request, $this->device());

        $this->assertCount(1, $logs);
        $this->assertSame(PunchLog::OUT, $logs[0]->type);
    }

    public function test_skips_non_attendance_events(): void
    {
        $doorAlarm = $this->event(['AccessControllerEvent' => ['majorEventType' => 2]]);
        $noEmployee = $this->event();
        unset($noEmployee['AccessControllerEvent']['employeeNoString']);

        $driver = app(HikvisionDriver::class);
        $this->assertSame([], $driver->parse($this->jsonRequest($doorAlarm), $this->device()));
        $this->assertSame([], $driver->parse($this->jsonRequest($noEmployee), $this->device()));
    }

    public function test_falls_back_to_numeric_employee_no_and_null_type(): void
    {
        $e = $this->event();
        unset($e['AccessControllerEvent']['employeeNoString'], $e['AccessControllerEvent']['attendanceStatus']);
        $e['AccessControllerEvent']['employeeNo'] = 7;

        $logs = app(HikvisionDriver::class)->parse($this->jsonRequest($e), $this->device());

        $this->assertSame('7', $logs[0]->employeeId);
        $this->assertNull($logs[0]->type);
    }

    public function test_utc_time_is_converted_to_gym_wall_clock(): void
    {
        $logs = app(HikvisionDriver::class)->parse($this->jsonRequest($this->event(['dateTime' => '2026-09-28T04:00:00Z'])), $this->device());

        $this->assertSame('2026-09-28 09:00:00', $logs[0]->time->format('Y-m-d H:i:s'));
    }

    public function test_garbage_body_returns_nothing(): void
    {
        $request = Request::create('/x', 'POST', [], [], [], ['CONTENT_TYPE' => 'text/plain'], 'hello');

        $this->assertSame([], app(HikvisionDriver::class)->parse($request, $this->device()));
    }
}
```

- [ ] **Step 2: Run it and confirm it fails**

Run: `php artisan test --filter=HikvisionDriverTest`
Expected: FAIL with `Class "App\Biometric\Drivers\HikvisionDriver" not found`.

- [ ] **Step 3: Create the driver**

```php
<?php

namespace App\Biometric\Drivers;

use App\Biometric\Contracts\BiometricDriver;
use App\Biometric\PunchLog;
use App\Biometric\WallClock;
use App\Models\BiometricDevice;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hikvision access-control terminals (DS-K1T…) using "HTTP Listening" event push.
 * Body is JSON, either raw or in a multipart part named event_log / AccessControllerEvent
 * (face snapshots come as extra file parts and are ignored).
 */
class HikvisionDriver implements BiometricDriver
{
    private const MAJOR_EVENT_ACCESS = 5;

    public function key(): string          { return 'hikvision'; }
    public function label(): string        { return 'Hikvision'; }
    public function identifiesBy(): string { return 'token'; }

    // Always 200 — otherwise the terminal re-sends the same event in a loop.
    public function acknowledge(): Response
    {
        return response('OK', 200);
    }

    public function settingsRules(): array
    {
        return ['settings.timezone' => ['nullable', 'timezone']];
    }

    public function setupSteps(BiometricDevice $device, string $baseUrl): array
    {
        $url  = rtrim($baseUrl, '/') . '/api/biometric/hook/' . $device->webhook_token;
        $host = parse_url($baseUrl, PHP_URL_HOST);
        $port = parse_url($baseUrl, PHP_URL_PORT) ?: (str_starts_with($baseUrl, 'https') ? 443 : 80);
        $path = '/api/biometric/hook/' . $device->webhook_token;

        return [
            "Open the terminal's web page (or iVMS-4200) → Configuration → Network → Advanced Settings → HTTP Listening.",
            "Destination IP / domain: {$host}   Port: {$port}",
            "URL: {$path}",
            "(Full URL, if the screen asks for one: {$url})",
            'Protocol: HTTP (use HTTPS only if the firmware supports it). Save.',
            'Under Event / Linkage, make sure Access Control events are sent to the listening host.',
            'Set each member\'s Employee No. on the terminal to their Biometric Code (or User ID).',
        ];
    }

    public function parse(Request $request, BiometricDevice $device): array
    {
        $event = $this->extractEvent($request);
        if (! $event) {
            return [];
        }

        $ace = $event['AccessControllerEvent'] ?? [];
        if ((int) ($ace['majorEventType'] ?? 0) !== self::MAJOR_EVENT_ACCESS) {
            return [];
        }

        $employee = $ace['employeeNoString'] ?? $ace['employeeNo'] ?? null;
        $time     = $event['dateTime'] ?? null;
        if ($employee === null || $employee === '' || ! $time) {
            return [];
        }

        $type = match ($ace['attendanceStatus'] ?? null) {
            'checkIn'  => PunchLog::IN,
            'checkOut' => PunchLog::OUT,
            default    => null,
        };

        return [new PunchLog((string) $employee, WallClock::parse((string) $time, $device->timezone()), $type)];
    }

    private function extractEvent(Request $request): ?array
    {
        foreach (['event_log', 'AccessControllerEvent'] as $part) {
            $value = $request->input($part);
            if (is_string($value) && ($decoded = json_decode($value, true)) && is_array($decoded)) {
                return $decoded;
            }
        }

        $decoded = json_decode($request->getContent(), true);

        return is_array($decoded) ? $decoded : null;
    }
}
```

- [ ] **Step 4: Run the tests**

Run: `php artisan test --filter=HikvisionDriverTest`
Expected: 6 PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Biometric/Drivers/HikvisionDriver.php tests/Feature/HikvisionDriverTest.php
git commit -m "Add Hikvision HTTP Listening driver

Co-Authored-By: Claude Opus 5.5 (1M context) <noreply@anthropic.com>"
```

---

### Task 6: GenericWebhookDriver

**Files:**
- Create: `app/Biometric/Drivers/GenericWebhookDriver.php`
- Test: `tests/Feature/GenericWebhookDriverTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Biometric\Drivers\GenericWebhookDriver;
use App\Biometric\PunchLog;
use App\Models\BiometricDevice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Tests\TestCase;

class GenericWebhookDriverTest extends TestCase
{
    private function device(array $settings): BiometricDevice
    {
        return new BiometricDevice(['brand' => 'generic', 'settings' => $settings + ['timezone' => 'Asia/Karachi']]);
    }

    private function req(array $body): Request
    {
        return Request::create('/x', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($body));
    }

    public function test_nested_records_path_and_iso_time(): void
    {
        $device = $this->device(['records_path' => 'data.events', 'employee_field' => 'user.id', 'time_field' => 'at', 'time_format' => 'iso']);
        $body   = ['data' => ['events' => [
            ['user' => ['id' => 'A1'], 'at' => '2026-09-28T04:00:00Z'],
            ['user' => ['id' => 'A2'], 'at' => '2026-09-28T05:00:00Z'],
        ]]];

        $logs = app(GenericWebhookDriver::class)->parse($this->req($body), $device);

        $this->assertCount(2, $logs);
        $this->assertSame('A1', $logs[0]->employeeId);
        $this->assertSame('2026-09-28 09:00:00', $logs[0]->time->format('Y-m-d H:i:s'));
    }

    public function test_single_object_body_with_unix_time_and_in_out_mapping(): void
    {
        $device = $this->device([
            'employee_field' => 'emp', 'time_field' => 'ts', 'time_format' => 'unix',
            'type_field' => 'dir', 'type_in_value' => 'I', 'type_out_value' => 'O',
        ]);
        $ts = Carbon::parse('2026-09-28T04:00:00Z')->timestamp;

        $logs = app(GenericWebhookDriver::class)->parse($this->req(['emp' => 9, 'ts' => $ts, 'dir' => 'O']), $device);

        $this->assertSame('9', $logs[0]->employeeId);
        $this->assertSame('2026-09-28 09:00:00', $logs[0]->time->format('Y-m-d H:i:s'));
        $this->assertSame(PunchLog::OUT, $logs[0]->type);
    }

    public function test_unix_ms_and_auto_formats(): void
    {
        $ms = Carbon::parse('2026-09-28T04:00:00Z')->getTimestampMs();
        $driver = app(GenericWebhookDriver::class);

        $a = $driver->parse($this->req(['e' => 1, 't' => $ms]), $this->device(['employee_field' => 'e', 'time_field' => 't', 'time_format' => 'unix_ms']));
        $b = $driver->parse($this->req(['e' => 1, 't' => '2026-09-28 09:00:00']), $this->device(['employee_field' => 'e', 'time_field' => 't', 'time_format' => 'auto']));
        $c = $driver->parse($this->req(['e' => 1, 't' => (string) intdiv($ms, 1000)]), $this->device(['employee_field' => 'e', 'time_field' => 't', 'time_format' => 'auto']));

        foreach ([$a, $b, $c] as $logs) {
            $this->assertSame('2026-09-28 09:00:00', $logs[0]->time->format('Y-m-d H:i:s'));
        }
    }

    public function test_wrong_mapping_yields_nothing(): void
    {
        $device = $this->device(['employee_field' => 'nope', 'time_field' => 'at', 'time_format' => 'iso']);

        $this->assertSame([], app(GenericWebhookDriver::class)->parse($this->req(['emp' => 1, 'at' => '2026-09-28 09:00:00']), $device));
    }

    public function test_unparseable_time_skips_record(): void
    {
        $device = $this->device(['employee_field' => 'e', 'time_field' => 't', 'time_format' => 'iso']);

        $this->assertSame([], app(GenericWebhookDriver::class)->parse($this->req(['e' => 1, 't' => 'not a date']), $device));
    }

    public function test_secret_check(): void
    {
        $driver = app(GenericWebhookDriver::class);
        $device = $this->device(['employee_field' => 'e', 'time_field' => 't', 'time_format' => 'auto', 'secret_header' => 'X-Secret', 'secret_value' => 's3cret']);

        $good = $this->req([]); $good->headers->set('X-Secret', 's3cret');
        $bad  = $this->req([]); $bad->headers->set('X-Secret', 'wrong');

        $this->assertTrue($driver->secretMatches($good, $device));
        $this->assertFalse($driver->secretMatches($bad, $device));
        $this->assertTrue($driver->secretMatches($this->req([]), $this->device(['employee_field' => 'e', 'time_field' => 't', 'time_format' => 'auto'])));
    }
}
```

- [ ] **Step 2: Run it and confirm it fails**

Run: `php artisan test --filter=GenericWebhookDriverTest`
Expected: FAIL with `Class "App\Biometric\Drivers\GenericWebhookDriver" not found`.

- [ ] **Step 3: Create the driver**

```php
<?php

namespace App\Biometric\Drivers;

use App\Biometric\Contracts\BiometricDriver;
use App\Biometric\PunchLog;
use App\Biometric\WallClock;
use App\Models\BiometricDevice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Any machine or vendor cloud (Suprema BioStar 2, Anviz CrossChex, …) that POSTs JSON.
 * The owner maps fields with dot-paths in the device settings.
 */
class GenericWebhookDriver implements BiometricDriver
{
    public function key(): string          { return 'generic'; }
    public function label(): string        { return 'Other brand (Generic Webhook — Suprema, Anviz, …)'; }
    public function identifiesBy(): string { return 'token'; }

    public function acknowledge(): Response
    {
        return response()->json(['ok' => true]);
    }

    public function settingsRules(): array
    {
        return [
            'settings.records_path'   => ['nullable', 'string', 'max:100'],
            'settings.employee_field' => ['required', 'string', 'max:100'],
            'settings.time_field'     => ['required', 'string', 'max:100'],
            'settings.time_format'    => ['required', 'in:auto,unix,unix_ms,iso'],
            'settings.type_field'     => ['nullable', 'string', 'max:100'],
            'settings.type_in_value'  => ['nullable', 'string', 'max:50'],
            'settings.type_out_value' => ['nullable', 'string', 'max:50'],
            'settings.secret_header'  => ['nullable', 'string', 'max:100', 'required_with:settings.secret_value'],
            'settings.secret_value'   => ['nullable', 'string', 'max:200', 'required_with:settings.secret_header'],
            'settings.timezone'       => ['nullable', 'timezone'],
        ];
    }

    public function setupSteps(BiometricDevice $device, string $baseUrl): array
    {
        $url   = rtrim($baseUrl, '/') . '/api/biometric/hook/' . $device->webhook_token;
        $steps = [
            "In the machine's (or vendor software's) webhook / HTTP push settings, set the URL to: {$url}",
            'Method: POST, body: JSON.',
        ];

        if (! empty($device->settings['secret_header'])) {
            $steps[] = "Add header {$device->settings['secret_header']} with the secret saved on this device.";
        }

        $steps[] = 'Make one test punch, then open "Last received data" below and check the field mapping matches.';
        $steps[] = "Set each member's user/employee ID on the machine to their Biometric Code (or User ID).";

        return $steps;
    }

    public function secretMatches(Request $request, BiometricDevice $device): bool
    {
        $header = $device->settings['secret_header'] ?? null;
        if (! $header) {
            return true;
        }

        return hash_equals((string) ($device->settings['secret_value'] ?? ''), (string) $request->header($header, ''));
    }

    public function parse(Request $request, BiometricDevice $device): array
    {
        $s    = $device->settings ?? [];
        $body = json_decode($request->getContent(), true);
        if (! is_array($body)) {
            return [];
        }

        $records = empty($s['records_path']) ? $body : data_get($body, $s['records_path']);
        if (! is_array($records)) {
            return [];
        }
        if (! array_is_list($records)) {
            $records = [$records]; // single object
        }

        $logs = [];
        foreach ($records as $rec) {
            $employee = data_get($rec, $s['employee_field'] ?? '');
            $rawTime  = data_get($rec, $s['time_field'] ?? '');
            if ($employee === null || $employee === '' || $rawTime === null || $rawTime === '') {
                continue;
            }

            $time = $this->toTime($rawTime, $s['time_format'] ?? 'auto', $device->timezone());
            if (! $time) {
                continue;
            }

            $logs[] = new PunchLog((string) $employee, $time, $this->toType($rec, $s));
        }

        return $logs;
    }

    private function toTime(mixed $raw, string $format, string $tz): ?Carbon
    {
        try {
            return match (true) {
                $format === 'unix'                     => WallClock::fromUnix((float) $raw, $tz),
                $format === 'unix_ms'                  => WallClock::fromUnix(((float) $raw) / 1000, $tz),
                $format === 'auto' && is_numeric($raw) => WallClock::fromUnix(strlen((string) (int) $raw) > 10 ? ((float) $raw) / 1000 : (float) $raw, $tz),
                default                                => WallClock::parse((string) $raw, $tz),
            };
        } catch (\Throwable) {
            return null;
        }
    }

    private function toType(mixed $rec, array $s): ?string
    {
        if (empty($s['type_field'])) {
            return null;
        }

        $value = (string) data_get($rec, $s['type_field'], '');

        return match (true) {
            isset($s['type_in_value'])  && $value === (string) $s['type_in_value']  => PunchLog::IN,
            isset($s['type_out_value']) && $value === (string) $s['type_out_value'] => PunchLog::OUT,
            default => null,
        };
    }
}
```

- [ ] **Step 4: Run the driver tests plus the registry test from Task 2**

Run: `php artisan test --filter="GenericWebhookDriverTest|DriverRegistryTest"`
Expected: 6 + 3 PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Biometric/Drivers/GenericWebhookDriver.php tests/Feature/GenericWebhookDriverTest.php
git commit -m "Add generic webhook driver with field mapping

Co-Authored-By: Claude Opus 5.5 (1M context) <noreply@anthropic.com>"
```

---

### Task 7: Token webhook endpoint

**Files:**
- Create: `app/Http/Controllers/Api/BiometricWebhookController.php`
- Modify: `routes/api.php` (inside the existing public `Route::prefix('biometric')` group)
- Test: `tests/Feature/BiometricWebhookTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\BiometricDevice;
use App\Models\Gym;
use App\Models\User;
use App\Services\LicenseService;
use App\Services\UnknownBiometricDevices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BiometricWebhookTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(LicenseService::class, fn ($m) => $m->shouldReceive('check')->andReturn(true));
        Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web']);
        $this->gym = Gym::create(['name' => 'G', 'slug' => 'g', 'email' => 'g@test.local', 'status' => 'active']);
        User::create(['gym_id' => $this->gym->id, 'name' => 'M', 'email' => 'm@test.local', 'password' => 'x', 'status' => 'active', 'biometric_code' => '42']);
    }

    private function device(array $overrides = []): BiometricDevice
    {
        return BiometricDevice::create(array_merge([
            'gym_id' => $this->gym->id, 'name' => 'Face', 'brand' => 'hikvision',
            'api_key' => BiometricDevice::generateApiKey(), 'is_active' => true,
        ], $overrides));
    }

    private function hikEvent(): array
    {
        return [
            'dateTime' => '2026-09-28T09:00:00+05:00',
            'AccessControllerEvent' => ['majorEventType' => 5, 'employeeNoString' => '42'],
        ];
    }

    public function test_hikvision_punch_is_recorded_via_token_url(): void
    {
        $d = $this->device();

        $this->postJson("/api/biometric/hook/{$d->webhook_token}", $this->hikEvent())->assertOk();

        $this->assertDatabaseHas('attendances', ['gym_id' => $this->gym->id, 'source' => 'biometric']);
        $d->refresh();
        $this->assertNotNull($d->last_seen_at);
        $this->assertStringContainsString('AccessControllerEvent', $d->last_payload);
    }

    public function test_generic_punch_is_recorded(): void
    {
        $d = $this->device(['brand' => 'generic', 'settings' => ['employee_field' => 'emp', 'time_field' => 'at', 'time_format' => 'auto']]);

        $this->postJson("/api/biometric/hook/{$d->webhook_token}", ['emp' => '42', 'at' => '2026-09-28 09:00:00'])->assertOk();

        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_generic_secret_mismatch_is_rejected(): void
    {
        $d = $this->device(['brand' => 'generic', 'settings' => [
            'employee_field' => 'emp', 'time_field' => 'at', 'time_format' => 'auto',
            'secret_header' => 'X-Secret', 'secret_value' => 'right',
        ]]);

        $this->postJson("/api/biometric/hook/{$d->webhook_token}", ['emp' => '42', 'at' => '2026-09-28 09:00:00'], ['X-Secret' => 'wrong'])
            ->assertStatus(401);
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_unknown_token_is_404_and_recorded(): void
    {
        $this->postJson('/api/biometric/hook/doesnotexist123', [])->assertNotFound();

        $this->assertSame('token:doesno…', app(UnknownBiometricDevices::class)->recent()[0]['serial_number']);
    }

    public function test_disabled_device_is_seen_but_rejected(): void
    {
        $d = $this->device(['is_active' => false]);

        $this->postJson("/api/biometric/hook/{$d->webhook_token}", $this->hikEvent())->assertStatus(401);

        $this->assertNotNull($d->fresh()->last_seen_at);
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_get_is_a_heartbeat(): void
    {
        $d = $this->device();

        $this->get("/api/biometric/hook/{$d->webhook_token}")->assertOk();

        $this->assertSame('online', $d->fresh()->connectionState());
    }

    public function test_zkteco_device_cannot_use_token_url(): void
    {
        $d = $this->device(['brand' => 'zkteco', 'serial_number' => 'SN9']);

        $this->postJson("/api/biometric/hook/{$d->webhook_token}", [])->assertNotFound();
    }

    public function test_driver_exception_still_acknowledges(): void
    {
        $d = $this->device(['brand' => 'generic', 'settings' => ['employee_field' => 'emp', 'time_field' => 'at', 'time_format' => 'auto']]);

        $this->mock(\App\Biometric\BiometricPunchProcessor::class, fn ($m) => $m->shouldReceive('process')->andThrow(new \RuntimeException('boom')));

        $this->postJson("/api/biometric/hook/{$d->webhook_token}", ['emp' => '42', 'at' => '2026-09-28 09:00:00'])->assertOk();
        $this->assertStringContainsString('"emp":"42"', $d->fresh()->last_payload);
    }
}
```

- [ ] **Step 2: Run it and confirm it fails**

Run: `php artisan test --filter=BiometricWebhookTest`
Expected: FAIL with 404 on every request (route missing).

- [ ] **Step 3: Create the controller**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Biometric\BiometricPunchProcessor;
use App\Biometric\DriverRegistry;
use App\Biometric\Drivers\GenericWebhookDriver;
use App\Http\Controllers\Controller;
use App\Models\BiometricDevice;
use App\Services\UnknownBiometricDevices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Per-device URL for token brands (Hikvision, Generic webhook):
 *   GET  /api/biometric/hook/{token}  heartbeat
 *   POST /api/biometric/hook/{token}  events
 * The token alone identifies the device and its gym.
 */
class BiometricWebhookController extends Controller
{
    public function __construct(
        private DriverRegistry $drivers,
        private BiometricPunchProcessor $processor,
        private UnknownBiometricDevices $unknownDevices,
    ) {}

    public function __invoke(Request $request, string $token)
    {
        $device = BiometricDevice::where('webhook_token', $token)->first();
        $driver = $device ? $this->drivers->get($device->brand) : null;

        if (! $device || $driver->identifiesBy() !== 'token') {
            $this->unknownDevices->record('token:' . mb_substr($token, 0, 6) . '…', $request->ip(), 'hook');
            abort(404);
        }

        $device->markSeen();

        if ($request->isMethod('GET')) {
            return response('OK', 200);
        }

        $device->storePayload($request->getContent() ?: json_encode($request->except(array_keys($request->allFiles()))));

        if (! $device->is_active) {
            return response()->json(['error' => 'Device inactive'], 401);
        }

        if ($driver instanceof GenericWebhookDriver && ! $driver->secretMatches($request, $device)) {
            return response()->json(['error' => 'Invalid secret'], 401);
        }

        try {
            $logs = $driver->parse($request, $device);

            if ($logs === [] && $request->getContent() !== '') {
                Log::warning('Biometric webhook: data received but no punches extracted', ['device_id' => $device->id, 'brand' => $device->brand]);
            }

            foreach ($logs as $log) {
                $this->processor->process($log, $device);
            }
        } catch (\Throwable $e) {
            // Never make the machine retry-loop; the payload is kept for debugging.
            Log::warning('Biometric webhook error', ['device_id' => $device->id, 'error' => $e->getMessage()]);
        }

        return $driver->acknowledge();
    }
}
```

- [ ] **Step 4: Add the route**

In `routes/api.php`, inside the existing public group:

```php
Route::prefix('biometric')->group(function () {
    Route::post('push',  [BiometricPushController::class, 'receive'])->name('biometric.push');
    Route::get('push',   [BiometricPushController::class, 'ping'])->name('biometric.ping');
    Route::get('iclock/cdata', [BiometricPushController::class, 'ping'])->name('biometric.iclock');
    // Per-device URL for Hikvision / generic webhook brands
    Route::match(['get', 'post'], 'hook/{token}', BiometricWebhookController::class)
        ->where('token', '[A-Za-z0-9]{10,64}')->name('biometric.hook');
});
```

and add `use App\Http\Controllers\Api\BiometricWebhookController;` to the imports at the top.

The test URL `/api/biometric/hook/doesnotexist123` is 15 alphanumeric characters, so it matches the pattern and reaches the controller, which returns 404.

- [ ] **Step 5: Run the tests**

Run: `php artisan test --filter="BiometricWebhookTest|BiometricPushControllerTest|BiometricConnectionTest"`
Expected: all PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/BiometricWebhookController.php routes/api.php tests/Feature/BiometricWebhookTest.php
git commit -m "Add per-device token webhook for non-ZKTeco biometric brands

Co-Authored-By: Claude Opus 5.5 (1M context) <noreply@anthropic.com>"
```

---

### Task 8: Device management backend (brand, settings, token, setup data)

**Files:**
- Modify: `app/Http/Controllers/Web/BiometricDeviceWebController.php`
- Modify: `routes/web.php` (biometric group, inside the `role:owner|admin` block)
- Test: `tests/Feature/BiometricDeviceManagementTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\BiometricDevice;
use App\Models\Gym;
use App\Models\User;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BiometricDeviceManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(LicenseService::class, fn ($m) => $m->shouldReceive('check')->andReturn(true));
        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $gym = Gym::create(['name' => 'G', 'slug' => 'g', 'email' => 'g@test.local', 'status' => 'active']);
        $this->owner = User::create(['gym_id' => $gym->id, 'name' => 'O', 'email' => 'o@test.local', 'password' => 'x', 'status' => 'active']);
        $this->owner->assignRole('owner');
    }

    public function test_zkteco_requires_serial_number(): void
    {
        $this->actingAs($this->owner)->postJson('/biometric/devices', ['brand' => 'zkteco', 'name' => 'Door'])
            ->assertStatus(422)->assertJsonValidationErrors('serial_number');
    }

    public function test_hikvision_without_serial_gets_webhook_url(): void
    {
        $res = $this->actingAs($this->owner)->postJson('/biometric/devices', ['brand' => 'hikvision', 'name' => 'Face'])->assertOk();

        $this->assertStringContainsString('/api/biometric/hook/', $res->json('webhook_url'));
        $this->assertDatabaseHas('biometric_devices', ['brand' => 'hikvision', 'serial_number' => null]);
    }

    public function test_unknown_brand_is_rejected(): void
    {
        $this->actingAs($this->owner)->postJson('/biometric/devices', ['brand' => 'acme', 'name' => 'X'])
            ->assertStatus(422)->assertJsonValidationErrors('brand');
    }

    public function test_generic_requires_mapping(): void
    {
        $this->actingAs($this->owner)->postJson('/biometric/devices', ['brand' => 'generic', 'name' => 'X'])
            ->assertStatus(422)->assertJsonValidationErrors(['settings.employee_field', 'settings.time_field', 'settings.time_format']);

        $this->actingAs($this->owner)->postJson('/biometric/devices', ['brand' => 'generic', 'name' => 'X', 'settings' => [
            'employee_field' => 'emp', 'time_field' => 'at', 'time_format' => 'auto',
        ]])->assertOk();
    }

    public function test_update_can_change_settings_but_not_brand(): void
    {
        $id = $this->actingAs($this->owner)->postJson('/biometric/devices', ['brand' => 'generic', 'name' => 'X', 'settings' => [
            'employee_field' => 'emp', 'time_field' => 'at', 'time_format' => 'auto',
        ]])->json('device.id');

        $this->actingAs($this->owner)->putJson("/biometric/devices/{$id}", ['name' => 'Y', 'brand' => 'zkteco', 'settings' => [
            'employee_field' => 'user.id', 'time_field' => 'at', 'time_format' => 'iso',
        ]])->assertOk();

        $d = BiometricDevice::find($id);
        $this->assertSame('generic', $d->brand);
        $this->assertSame('user.id', $d->settings['employee_field']);
    }

    public function test_regenerate_token_invalidates_old_url(): void
    {
        $id  = $this->actingAs($this->owner)->postJson('/biometric/devices', ['brand' => 'hikvision', 'name' => 'Face'])->json('device.id');
        $old = BiometricDevice::find($id)->webhook_token;

        $res = $this->actingAs($this->owner)->postJson("/biometric/devices/{$id}/regenerate-token")->assertOk();

        $this->assertStringNotContainsString($old, $res->json('webhook_url'));
        $this->postJson("/api/biometric/hook/{$old}", [])->assertNotFound();
    }

    public function test_setup_endpoint_returns_steps_and_parse_preview(): void
    {
        $id = $this->actingAs($this->owner)->postJson('/biometric/devices', ['brand' => 'generic', 'name' => 'X', 'settings' => [
            'employee_field' => 'emp', 'time_field' => 'at', 'time_format' => 'auto',
        ]])->json('device.id');
        BiometricDevice::find($id)->storePayload(json_encode(['emp' => '5', 'at' => '2026-09-28 09:00:00']));

        $this->actingAs($this->owner)->getJson("/biometric/devices/{$id}/setup")
            ->assertOk()
            ->assertJsonPath('preview.count', 1)
            ->assertJsonPath('preview.punches.0.employee_id', '5')
            ->assertJsonStructure(['steps', 'webhook_url', 'last_payload', 'last_payload_at']);
    }
}
```

- [ ] **Step 2: Run it and confirm it fails**

Run: `php artisan test --filter=BiometricDeviceManagementTest`
Expected: FAIL (validation errors for SN are missing; the routes `regenerate-token` and `setup` return 404).

- [ ] **Step 3: Update the controller**

In `app/Http/Controllers/Web/BiometricDeviceWebController.php`:

Add imports:

```php
use App\Biometric\DriverRegistry;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Validation\Rule;
```

Change `index()` to also pass `$brands` and `$presets`. The return line becomes:

```php
        $brands  = app(DriverRegistry::class)->options();
        $presets = config('biometric.generic_presets');

        return view('biometric.devices', compact('devices', 'unknownDevices', 'brands', 'presets'));
```

Replace `store()` and `update()`, and add `regenerateToken()`, `setup()`, and two private helpers:

```php
    public function store(Request $request)
    {
        $registry = app(DriverRegistry::class);
        $request->validate(['brand' => ['required', Rule::in($registry->keys())]]);
        $driver = $registry->get($request->input('brand'));

        $data = $request->validate(array_merge([
            'brand'         => ['required', Rule::in($registry->keys())],
            'serial_number' => [$driver->identifiesBy() === 'serial' ? 'required' : 'nullable', 'string', 'max:100', 'unique:biometric_devices,serial_number'],
            'name'          => 'required|string|max:100',
            'model'         => 'nullable|string|max:50',
            'location'      => 'nullable|string|max:100',
            'settings'      => 'nullable|array',
        ], $driver->settingsRules()));

        $data['settings'] = $this->cleanSettings($data['settings'] ?? []);
        $data['gym_id']   = $this->gymId();
        $data['api_key']  = BiometricDevice::generateApiKey();

        $device = BiometricDevice::create($data);
        if ($device->serial_number) {
            app(UnknownBiometricDevices::class)->forget($device->serial_number);
        }

        return response()->json(['ok' => true, 'device' => $device, 'webhook_url' => $this->webhookUrl($device)]);
    }

    public function update(Request $request, BiometricDevice $device)
    {
        abort_if($device->gym_id !== $this->gymId(), 403);

        // Brand is fixed after creation — changing it would invalidate the machine's config.
        $driver = app(DriverRegistry::class)->get($device->brand);

        $data = $request->validate(array_merge([
            'name'     => 'required|string|max:100',
            'model'    => 'nullable|string|max:50',
            'location' => 'nullable|string|max:100',
            'settings' => 'nullable|array',
        ], $driver->settingsRules()));

        if (array_key_exists('settings', $data)) {
            $data['settings'] = $this->cleanSettings($data['settings'] ?? []);
        }

        $device->update($data);

        return response()->json(['ok' => true]);
    }

    public function regenerateToken(BiometricDevice $device)
    {
        abort_if($device->gym_id !== $this->gymId(), 403);
        $device->regenerateWebhookToken();

        return response()->json(['ok' => true, 'webhook_url' => $this->webhookUrl($device)]);
    }

    /** Data for the Setup / Test panel: steps, URL, last payload, and what we'd extract from it. */
    public function setup(Request $request, BiometricDevice $device)
    {
        abort_if($device->gym_id !== $this->gymId(), 403);

        $driver  = app(DriverRegistry::class)->get($device->brand);
        $preview = ['count' => 0, 'punches' => [], 'error' => null];

        if ($device->last_payload) {
            try {
                $replay = HttpRequest::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $device->last_payload);
                // Form/multipart bodies (ZKTeco ATTLOG, Hikvision event_log) were stored as query strings or JSON.
                if (! json_decode($device->last_payload, true)) {
                    parse_str($device->last_payload, $fields);
                    $replay = HttpRequest::create('/', 'POST', $fields, [], [], [], $device->last_payload);
                }

                $logs = $driver->parse($replay, $device);
                $preview['count']   = count($logs);
                $preview['punches'] = array_map(fn ($l) => [
                    'employee_id' => $l->employeeId,
                    'time'        => $l->time->format('Y-m-d H:i:s'),
                    'type'        => $l->type,
                ], array_slice($logs, 0, 10));
            } catch (\Throwable $e) {
                $preview['error'] = $e->getMessage();
            }
        }

        return response()->json([
            'steps'           => $driver->setupSteps($device, $request->getSchemeAndHttpHost()),
            'webhook_url'     => $this->webhookUrl($device),
            'last_payload'    => $device->last_payload,
            'last_payload_at' => $device->last_payload_at?->diffForHumans(),
            'preview'         => $preview,
        ]);
    }

    private function webhookUrl(BiometricDevice $device): ?string
    {
        $driver = app(DriverRegistry::class)->get($device->brand);

        return $driver->identifiesBy() === 'token'
            ? route('biometric.hook', $device->webhook_token)
            : null;
    }

    private function cleanSettings(array $settings): array
    {
        return array_filter($settings, fn ($v) => $v !== null && $v !== '');
    }
```

(`Request` is already imported. `HttpRequest` is an alias for the same class, used only to make it clear that the replay request is synthetic.)

- [ ] **Step 4: Add the routes**

In `routes/web.php`, inside the `module:biometric` group:

```php
                Route::post('/biometric/devices/{device}/regenerate-token', [BiometricDeviceWebController::class, 'regenerateToken'])->name('biometric.devices.regenerate-token');
                Route::get('/biometric/devices/{device}/setup',             [BiometricDeviceWebController::class, 'setup'])->name('biometric.devices.setup');
```

- [ ] **Step 5: Run the tests**

Run: `php artisan test --filter="BiometricDeviceManagementTest|BiometricConnectionTest|RoleAccessTest"`
Expected: all PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Web/BiometricDeviceWebController.php routes/web.php tests/Feature/BiometricDeviceManagementTest.php
git commit -m "Brand-aware device management: settings validation, token regenerate, setup data

Co-Authored-By: Claude Opus 5.5 (1M context) <noreply@anthropic.com>"
```

---

### Task 9: Devices page UI

**Files:**
- Modify: `resources/views/biometric/devices.blade.php`
- Test: `tests/Feature/BiometricDeviceManagementTest.php` (append one render test)

- [ ] **Step 1: Add a failing render test** to `BiometricDeviceManagementTest`:

```php
    public function test_page_renders_brand_dropdown_and_badges(): void
    {
        $this->actingAs($this->owner)->postJson('/biometric/devices', ['brand' => 'hikvision', 'name' => 'Face Terminal']);

        $this->actingAs($this->owner)->get('/biometric/devices')
            ->assertOk()
            ->assertSee('ZKTeco / eSSL / Realtime')
            ->assertSee('Suprema BioStar 2 (unverified)')
            ->assertSee('Face Terminal')
            ->assertSee('Setup / Test');
    }
```

Run: `php artisan test --filter=test_page_renders_brand_dropdown_and_badges`
Expected: FAIL (the dropdown and the "Setup / Test" button do not exist yet).

- [ ] **Step 2: Device list row changes**

In the `<td>` of the Device column, replace `<div class="cell-main">{{ $device->name }}</div>` with:

```blade
                        <div class="cell-main">{{ $device->name }}</div>
                        <span class="badge badge-purple" style="font-size:10px">{{ $brands[$device->brand] ?? $device->brand }}</span>
```

In the Serial / Model cell, replace `{{ $device->serial_number }}` with `{{ $device->serial_number ?: '—' }}`.

In the API Key cell, show the key only for ZKTeco. Wrap the existing `<div style="display:flex…">…</div>` in `@if($device->brand === 'zkteco') … @else <span class="cell-sub">Uses its own URL — see Setup / Test</span> @endif`.

Change the column header `API Key` to `Key / URL`.

In the Actions cell, add a button before Edit and change the Edit button so it passes the device as JSON:

```blade
                        <button class="btn btn-primary btn-sm" onclick="openSetup({{ $device->id }})">Setup / Test</button>
                        <button class="btn btn-outline btn-sm" onclick="openEdit({{ \Illuminate\Support\Js::from($device->only(['id', 'name', 'model', 'location', 'brand', 'settings'])) }})">Edit</button>
```

- [ ] **Step 3: Replace the Add modal body** (the `<div class="modal-body">` inside `#addModal`):

```blade
            <div class="modal-body" style="display:flex;flex-direction:column;gap:14px">
                <div class="form-group">
                    <label class="form-label">Brand *</label>
                    <select class="form-select" name="brand" id="addBrand" onchange="syncBrand('add')" required>
                        @foreach($brands as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Device Name *</label>
                    <input class="form-input" name="name" placeholder="e.g. Main Entrance" required>
                </div>
                <div class="form-group" data-brand-show="zkteco,hikvision">
                    <label class="form-label">Serial Number <span data-brand-show="zkteco">*</span></label>
                    <input class="form-input" name="serial_number" id="addSerial" placeholder="From machine info screen">
                </div>
                <div class="form-group">
                    <label class="form-label">Model</label>
                    <input class="form-input" name="model" placeholder="e.g. F18, K40, DS-K1T671">
                </div>
                <div class="form-group">
                    <label class="form-label">Location</label>
                    <input class="form-input" name="location" placeholder="e.g. Front door, Gym floor">
                </div>
                @include('biometric._settings-fields', ['prefix' => 'add'])
            </div>
```

Change the submit button text to `Register Device`.

- [ ] **Step 4: Replace the Edit modal body** (inside `#editModal`, after the hidden `editId`):

```blade
            <div class="modal-body" style="display:flex;flex-direction:column;gap:14px">
                <input type="hidden" id="editBrand">
                <div class="form-group">
                    <label class="form-label">Device Name *</label>
                    <input class="form-input" id="editName" name="name" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Model</label>
                    <input class="form-input" id="editModel" name="model">
                </div>
                <div class="form-group">
                    <label class="form-label">Location</label>
                    <input class="form-input" id="editLocation" name="location">
                </div>
                @include('biometric._settings-fields', ['prefix' => 'edit'])
            </div>
```

- [ ] **Step 5: Create `resources/views/biometric/_settings-fields.blade.php`**

```blade
{{-- Brand-specific settings. Needs $prefix ('add' | 'edit') and $presets. Fields use name="settings[...]". --}}
<div data-brand-show="hikvision,generic" class="form-group">
    <label class="form-label">Machine timezone</label>
    <input class="form-input" name="settings[timezone]" id="{{ $prefix }}Tz" placeholder="{{ config('biometric.timezone') }}">
</div>

<div data-brand-show="generic" style="display:flex;flex-direction:column;gap:12px;border-top:1px solid var(--border);padding-top:12px">
    <div class="form-group">
        <label class="form-label">Start from preset</label>
        <select class="form-select" onchange="applyPreset('{{ $prefix }}', this.value)">
            <option value="">— Custom —</option>
            @foreach($presets as $key => $p)
                <option value="{{ $key }}">{{ $p['label'] }}</option>
            @endforeach
        </select>
        <div class="cell-sub" style="margin-top:4px">Presets are a starting point. Make one test punch, then check "Last received data" in Setup / Test.</div>
    </div>
    <div class="form-grid">
        <div class="form-group"><label class="form-label">Records path</label><input class="form-input" name="settings[records_path]" placeholder="e.g. data.events (empty = whole body)"></div>
        <div class="form-group"><label class="form-label">Employee ID field *</label><input class="form-input" name="settings[employee_field]" placeholder="e.g. user.id"></div>
        <div class="form-group"><label class="form-label">Time field *</label><input class="form-input" name="settings[time_field]" placeholder="e.g. datetime"></div>
        <div class="form-group"><label class="form-label">Time format *</label>
            <select class="form-select" name="settings[time_format]">
                <option value="auto">Auto-detect</option><option value="iso">Date/time text (ISO)</option>
                <option value="unix">Unix seconds</option><option value="unix_ms">Unix milliseconds</option>
            </select>
        </div>
        <div class="form-group"><label class="form-label">In/Out field</label><input class="form-input" name="settings[type_field]" placeholder="optional"></div>
        <div class="form-group"><label class="form-label">In value / Out value</label>
            <div style="display:flex;gap:6px"><input class="form-input" name="settings[type_in_value]" placeholder="in"><input class="form-input" name="settings[type_out_value]" placeholder="out"></div>
        </div>
        <div class="form-group"><label class="form-label">Secret header</label><input class="form-input" name="settings[secret_header]" placeholder="optional, e.g. X-Webhook-Secret"></div>
        <div class="form-group"><label class="form-label">Secret value</label><input class="form-input" name="settings[secret_value]" placeholder="optional"></div>
    </div>
</div>
```

- [ ] **Step 6: Add the Setup / Test modal**, right before `@endsection`:

```blade
{{-- Setup / Test Modal --}}
<div id="setupModal" class="modal-overlay" style="display:none">
    <div class="modal modal-lg" style="max-width:720px">
        <div class="modal-header">
            <div class="modal-title">Setup / Test</div>
            <button class="modal-close" onclick="closeModals()">✕</button>
        </div>
        <div class="modal-body" style="display:flex;flex-direction:column;gap:16px;font-size:13px">
            <div id="setupUrlBox" style="display:none">
                <div class="form-label">This device's URL</div>
                <div style="display:flex;gap:8px;align-items:center">
                    <code id="setupUrl" style="flex:1;background:var(--bg-alt);padding:8px 12px;border-radius:6px;word-break:break-all"></code>
                    <button class="btn btn-primary btn-sm" onclick="copyKey(document.getElementById('setupUrl').textContent)">Copy</button>
                    <button class="btn btn-outline btn-sm" id="regenTokenBtn">↺ New URL</button>
                </div>
                <div id="regenConfirm" class="cell-sub" style="display:none;margin-top:6px">
                    The old URL will stop working and the machine must be updated.
                    <button class="btn btn-danger btn-sm" id="regenTokenYes">Yes, make new URL</button>
                </div>
            </div>
            <div>
                <div class="form-label">Steps</div>
                <ol id="setupSteps" style="padding-left:18px;line-height:1.8;color:var(--text)"></ol>
            </div>
            <div>
                <div class="form-label">Last received data <span id="setupPayloadAt" class="cell-sub"></span></div>
                <div id="setupPreview" style="margin-bottom:6px"></div>
                <pre id="setupPayload" style="background:var(--bg-alt);padding:10px;border-radius:6px;max-height:260px;overflow:auto;white-space:pre-wrap;font-size:11px"></pre>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="openSetup(currentSetupId)">↻ Refresh</button>
            <button class="btn btn-primary" onclick="closeModals()">Close</button>
        </div>
    </div>
</div>
```

- [ ] **Step 7: Update the page script** (inside `@push('scripts')`). Replace `openAdd`, `openEdit`, `closeModals`, `submitAdd` and `submitEdit`, and add the new functions:

```js
const PRESETS = @json($presets);
let currentSetupId = null;

function syncBrand(prefix) {
    const brand = prefix === 'add' ? document.getElementById('addBrand').value : document.getElementById('editBrand').value;
    const modal = document.getElementById(prefix + 'Modal');
    modal.querySelectorAll('[data-brand-show]').forEach(el => {
        el.hidden = !el.dataset.brandShow.split(',').includes(brand);
    });
    if (prefix === 'add') document.getElementById('addSerial').required = (brand === 'zkteco');
}

function applyPreset(prefix, key) {
    const p = PRESETS[key]; if (!p) return;
    const modal = document.getElementById(prefix + 'Modal');
    Object.entries(p).forEach(([field, value]) => {
        const input = modal.querySelector(`[name="settings[${field}]"]`);
        if (input) input.value = value;
    });
}

function formBody(form) {
    const body = { settings: {} };
    for (const [k, v] of new FormData(form)) {
        const m = k.match(/^settings\[(.+)\]$/);
        if (m) { if (v !== '') body.settings[m[1]] = v; } else body[k] = v;
    }
    return body;
}

function openAdd() {
    document.getElementById('addModal').style.display = 'flex';
    syncBrand('add');
}

function openEdit(d) {
    document.getElementById('editId').value       = d.id;
    document.getElementById('editBrand').value    = d.brand;
    document.getElementById('editName').value     = d.name;
    document.getElementById('editModel').value    = d.model || '';
    document.getElementById('editLocation').value = d.location || '';
    const modal = document.getElementById('editModal');
    modal.querySelectorAll('[name^="settings["]').forEach(i => i.value = '');
    Object.entries(d.settings || {}).forEach(([field, value]) => {
        const input = modal.querySelector(`[name="settings[${field}]"]`);
        if (input) input.value = value;
    });
    modal.style.display = 'flex';
    syncBrand('edit');
}

function closeModals() {
    ['addModal','editModal','keyModal','setupModal'].forEach(id => document.getElementById(id).style.display = 'none');
}

async function submitAdd(e) {
    e.preventDefault();
    const form = e.target;
    try {
        const res = await post('{{ route('biometric.devices.store') }}', formBody(form));
        form.reset();
        document.getElementById('addModal').style.display = 'none';
        toast('Device registered', 'success');
        openSetup(res.device.id, true);
    } catch(err) { toast(err.message, 'error'); }
}

async function submitEdit(e) {
    e.preventDefault();
    const id = document.getElementById('editId').value;
    try {
        await put(`/biometric/devices/${id}`, formBody(e.target));
        toast('Device updated', 'success');
        closeModals();
        setTimeout(() => window.location.reload(), 800);
    } catch(err) { toast(err.message, 'error'); }
}

async function openSetup(id, reloadOnClose = false) {
    currentSetupId = id;
    try {
        const d = await get(`/biometric/devices/${id}/setup`);
        document.getElementById('setupUrlBox').style.display = d.webhook_url ? 'block' : 'none';
        document.getElementById('setupUrl').textContent = d.webhook_url || '';
        document.getElementById('regenConfirm').style.display = 'none';
        document.getElementById('setupSteps').innerHTML = '';
        d.steps.forEach(s => {
            const li = document.createElement('li'); li.textContent = s;
            document.getElementById('setupSteps').appendChild(li);
        });
        document.getElementById('setupPayloadAt').textContent = d.last_payload_at ? `(${d.last_payload_at})` : '';
        let pretty = d.last_payload || 'Nothing received yet. Make a test punch on the machine, then press Refresh.';
        try { pretty = JSON.stringify(JSON.parse(d.last_payload), null, 2); } catch (_) {}
        document.getElementById('setupPayload').textContent = pretty;

        const prev = document.getElementById('setupPreview');
        if (!d.last_payload) prev.innerHTML = '';
        else if (d.preview.error) prev.innerHTML = `<span class="badge badge-red">Could not read: ${d.preview.error}</span>`;
        else if (d.preview.count === 0) prev.innerHTML = '<span class="badge badge-yellow">Data received but no punches extracted — check mapping</span>';
        else prev.innerHTML = `<span class="badge badge-green">✓ ${d.preview.count} punch(es) read</span> ` +
            d.preview.punches.map(p => `<span class="cell-sub">#${p.employee_id} @ ${p.time}${p.type ? ' (' + p.type + ')' : ''}</span>`).join(' · ');

        document.getElementById('setupModal').style.display = 'flex';
        if (reloadOnClose) document.querySelector('#setupModal .modal-close').onclick = () => { closeModals(); window.location.reload(); };
    } catch(err) { toast(err.message, 'error'); }
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('regenTokenBtn').onclick = () => document.getElementById('regenConfirm').style.display = 'block';
    document.getElementById('regenTokenYes').onclick = async () => {
        try {
            const res = await post(`/biometric/devices/${currentSetupId}/regenerate-token`);
            document.getElementById('setupUrl').textContent = res.webhook_url;
            document.getElementById('regenConfirm').style.display = 'none';
            toast('New URL created — update it on the machine', 'info');
        } catch(err) { toast(err.message, 'error'); }
    };
});
```

Delete the old `keyModal` markup and its "Register & Get API Key" flow, because the Setup panel now shows credentials after registration. Keep `copyKey`, `toggleDevice`, `deleteDevice` and `regenerateKey` as they are. In the `closeModals` list, drop `'keyModal'` once its markup is removed.

- [ ] **Step 8: Update the static "Machine Setup Guide" card intro**

Change its intro line to:

```blade
        <p>Each device's exact steps are under <strong>Setup / Test</strong> in its row. Quick reference for ZKTeco / eSSL / Realtime:</p>
```

- [ ] **Step 9: Run the tests and compile the views**

Run: `php artisan view:clear && php artisan test --filter="BiometricDeviceManagementTest|BiometricConnectionTest"`
Expected: all PASS.

- [ ] **Step 10: Manual browser check** (as owner, with `php artisan serve`)
1. Add Device, brand Hikvision: the SN field is optional, the mapping block is hidden, and Setup opens with a URL.
2. Add Device, brand Generic, preset Suprema: the fields fill in, and save works.
3. `curl -X POST <url> -H "Content-Type: application/json" -d '{"user_id":{"user_id":"1"},"datetime":"2026-09-28T09:00:00+05:00"}'` against a generic device mapped with `records_path` empty, `employee_field=user_id.user_id` and `time_field=datetime`. Refresh Setup: it shows "✓ 1 punch(es) read" and the payload.
4. Edit a generic device: the settings are pre-filled and the brand is not editable.
5. New URL: the confirm row appears, then the URL changes.

- [ ] **Step 11: Commit**

```bash
git add resources/views/biometric tests/Feature/BiometricDeviceManagementTest.php
git commit -m "Brand-aware devices page with Setup / Test panel

Co-Authored-By: Claude Opus 5.5 (1M context) <noreply@anthropic.com>"
```

---

### Task 10: Spec correction and full verification

**Files:**
- Modify: `docs/superpowers/specs/2026-09-28-multi-brand-biometric-design.md`

- [ ] **Step 1: Fix the spec's time rule**

In the HikvisionDriver section, replace `"time from dateTime (ISO 8601 with offset), converted to app timezone."` with:

```
- `time` from `dateTime`: converted to the machine's wall-clock time (device timezone, default `config('biometric.timezone')`) and labelled with the app timezone — the same form ZKTeco punches have always been stored in. See `WallClock`.
```

- [ ] **Step 2: Run the full suite**

Run: `php artisan test`
Expected: everything passes except `Tests\Feature\ExampleTest` (license check, known and out of scope).

- [ ] **Step 3: Run the migration against the real DB (MySQL) on a copy or staging**

Run: `php artisan migrate`
Expected: the migration runs; existing devices have `brand = zkteco` and a `webhook_token`; ZKTeco machines keep pushing (watch Last Seen).

- [ ] **Step 4: Commit**

```bash
git add docs/superpowers/specs/2026-09-28-multi-brand-biometric-design.md
git commit -m "Spec: store all brands' punch times as machine wall-clock

Co-Authored-By: Claude Opus 5.5 (1M context) <noreply@anthropic.com>"
```
