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
