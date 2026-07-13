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
        $this->assertDatabaseHas('attendances', [
            'user_id'        => $user->id,
            'check_out_time' => null,
        ]);
    }

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
}
