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

class BiometricConnectionTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(LicenseService::class, fn ($m) => $m->shouldReceive('check')->andReturn(true));
        foreach (['admin', 'owner'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->gym = Gym::create(['name' => 'Gym A', 'slug' => 'gym-a', 'email' => 'a@test.local', 'status' => 'active']);
    }

    private function device(array $overrides = []): BiometricDevice
    {
        return BiometricDevice::create(array_merge([
            'gym_id' => $this->gym->id, 'serial_number' => 'SN-A-001', 'name' => 'Front Door',
            'api_key' => BiometricDevice::generateApiKey(), 'is_active' => true,
        ], $overrides));
    }

    private function user(string $role, ?int $gymId): User
    {
        $u = User::create(['gym_id' => $gymId, 'name' => ucfirst($role), 'email' => "{$role}@test.local", 'password' => 'x', 'status' => 'active']);
        $u->assignRole($role);

        return $u;
    }

    public function test_heartbeat_marks_registered_device_as_seen(): void
    {
        $device = $this->device();
        $this->assertSame('never', $device->connectionState());

        $this->get('/api/biometric/iclock/cdata?SN=SN-A-001')->assertOk();

        $device->refresh();
        $this->assertNotNull($device->last_seen_at);
        $this->assertSame('online', $device->connectionState());
    }

    public function test_device_goes_offline_after_threshold(): void
    {
        $device = $this->device(['last_seen_at' => now()->subMinutes(BiometricDevice::ONLINE_MINUTES + 1)]);

        $this->assertSame('offline', $device->connectionState());
    }

    public function test_disabled_device_is_seen_but_punches_are_rejected(): void
    {
        $device = $this->device(['is_active' => false]);

        $this->postJson('/api/biometric/push?SN=SN-A-001', [
            'records' => [['employee_id' => '1', 'time' => '2026-07-13 09:00:00', 'type' => 0]],
        ])->assertStatus(401);

        $this->assertSame('disabled', $device->fresh()->connectionState());
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_unknown_serial_is_recorded_and_rejected(): void
    {
        $this->get('/api/biometric/iclock/cdata?SN=TYPO-999')->assertOk();
        $this->postJson('/api/biometric/push?SN=TYPO-999', ['records' => []])->assertStatus(401);

        $recent = app(UnknownBiometricDevices::class)->recent();
        $this->assertCount(1, $recent);
        $this->assertSame('TYPO-999', $recent[0]['serial_number']);
        $this->assertSame(2, $recent[0]['hits']);
    }

    public function test_owner_sees_connection_badge_but_not_unknown_machines(): void
    {
        $this->device(['last_seen_at' => now()]);
        app(UnknownBiometricDevices::class)->record('TYPO-999', '1.2.3.4', 'ping');

        $this->actingAs($this->user('owner', $this->gym->id))->get('/biometric/devices')
            ->assertOk()
            ->assertSee('🟢 Online')
            ->assertDontSee('TYPO-999');
    }

    public function test_admin_sees_unknown_machines_until_registered(): void
    {
        app(UnknownBiometricDevices::class)->record('TYPO-999', '1.2.3.4', 'ping');
        $admin = $this->user('admin', null);

        $this->actingAs($admin)->withSession(['admin_active_gym_id' => $this->gym->id])
            ->get('/biometric/devices')
            ->assertOk()
            ->assertSee('Unregistered machines trying to connect')
            ->assertSee('TYPO-999');

        $this->actingAs($admin)->withSession(['admin_active_gym_id' => $this->gym->id])
            ->postJson('/biometric/devices', ['serial_number' => 'TYPO-999', 'name' => 'Back Door'])
            ->assertOk();

        $this->actingAs($admin)->withSession(['admin_active_gym_id' => $this->gym->id])
            ->get('/biometric/devices')
            ->assertDontSee('Unregistered machines trying to connect')
            ->assertSee('⚪ Never connected');
    }
}
