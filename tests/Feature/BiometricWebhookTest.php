<?php

namespace Tests\Feature;

use App\Biometric\BiometricPunchProcessor;
use App\Models\BiometricDevice;
use App\Models\Gym;
use App\Models\User;
use App\Services\LicenseService;
use App\Services\UnknownBiometricDevices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
            'AccessControllerEvent' => ['majorEventType' => 5, 'subEventType' => 75, 'employeeNoString' => '42'],
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

        $this->mock(BiometricPunchProcessor::class, fn ($m) => $m->shouldReceive('process')->andThrow(new \RuntimeException('boom')));

        $this->postJson("/api/biometric/hook/{$d->webhook_token}", ['emp' => '42', 'at' => '2026-09-28 09:00:00'])->assertOk();
        $this->assertStringContainsString('"emp":"42"', $d->fresh()->last_payload);
    }

    public function test_partial_failure_in_a_batch_still_processes_remaining_records(): void
    {
        $d = $this->device(['brand' => 'generic', 'settings' => [
            'records_path' => 'records', 'employee_field' => 'emp', 'time_field' => 'at', 'time_format' => 'auto',
        ]]);

        $calls = 0;
        $this->mock(BiometricPunchProcessor::class, function ($m) use (&$calls) {
            $m->shouldReceive('process')
                ->twice()
                ->andReturnUsing(function () use (&$calls) {
                    $calls++;
                    if ($calls === 1) {
                        throw new \RuntimeException('boom on first record');
                    }

                    return true;
                });
        });

        $payload = ['records' => [
            ['emp' => '42', 'at' => '2026-09-28 09:00:00'],
            ['emp' => '42', 'at' => '2026-09-28 09:05:00'],
        ]];

        $this->postJson("/api/biometric/hook/{$d->webhook_token}", $payload)->assertOk();
    }

    public function test_device_with_brand_removed_from_config_is_404(): void
    {
        $d = $this->device(['brand' => 'generic', 'settings' => ['employee_field' => 'emp', 'time_field' => 'at', 'time_format' => 'auto']]);

        // Simulate a brand that used to exist but was removed from config('biometric.drivers').
        DB::table('biometric_devices')->where('id', $d->id)->update(['brand' => 'removed']);

        $this->postJson("/api/biometric/hook/{$d->webhook_token}", ['emp' => '42', 'at' => '2026-09-28 09:00:00'])->assertNotFound();
    }

    public function test_heartbeat_is_acknowledged_without_storing_payload(): void
    {
        $d = $this->device();

        $this->postJson("/api/biometric/hook/{$d->webhook_token}", ['eventType' => 'heartBeat', 'ipAddress' => '10.0.0.5'])->assertOk();

        $d->refresh();
        $this->assertNotNull($d->last_seen_at);
        $this->assertNull($d->last_payload);
    }
}
