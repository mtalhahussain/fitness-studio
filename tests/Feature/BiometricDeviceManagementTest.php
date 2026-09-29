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

    public function test_undeclared_settings_keys_are_dropped(): void
    {
        $id = $this->actingAs($this->owner)->postJson('/biometric/devices', ['brand' => 'hikvision', 'name' => 'Face', 'settings' => [
            'timezone' => 'Asia/Dubai', 'junk' => 'x',
        ]])->json('device.id');

        $this->assertSame(['timezone' => 'Asia/Dubai'], BiometricDevice::find($id)->settings);
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

    public function test_index_renders_with_serialless_device(): void
    {
        $this->actingAs($this->owner)->postJson('/biometric/devices', ['brand' => 'hikvision', 'name' => 'Face'])->assertOk();

        $this->actingAs($this->owner)->get('/biometric/devices')->assertOk();
    }
}
