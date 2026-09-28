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

    public function test_timezone_falls_back_when_stored_value_is_not_a_valid_identifier(): void
    {
        $d = BiometricDevice::create([
            'gym_id' => $this->gym()->id, 'name' => 'X', 'api_key' => BiometricDevice::generateApiKey(),
            'settings' => ['timezone' => 'Mars/Base'],
        ]);

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

    public function test_store_payload_does_not_split_a_multibyte_character_at_the_boundary(): void
    {
        $d = BiometricDevice::create([
            'gym_id' => $this->gym()->id, 'name' => 'X', 'api_key' => BiometricDevice::generateApiKey(),
        ]);

        $d->storePayload(str_repeat('a', 10239).'é'.'tail');

        $d->refresh();
        $this->assertTrue(mb_check_encoding($d->last_payload, 'UTF-8'));
        $this->assertLessThanOrEqual(10240, strlen($d->last_payload));
    }

    public function test_store_payload_scrubs_invalid_byte_sequences(): void
    {
        $d = BiometricDevice::create([
            'gym_id' => $this->gym()->id, 'name' => 'X', 'api_key' => BiometricDevice::generateApiKey(),
        ]);

        $d->storePayload("\xB0\xA1abc");

        $d->refresh();
        $this->assertTrue(mb_check_encoding($d->last_payload, 'UTF-8'));
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

    public function test_two_devices_with_null_serial_number_can_coexist(): void
    {
        $gymId = $this->gym()->id;

        $d1 = BiometricDevice::create([
            'gym_id' => $gymId, 'name' => 'A', 'api_key' => BiometricDevice::generateApiKey(),
        ]);
        $d2 = BiometricDevice::create([
            'gym_id' => $gymId, 'name' => 'B', 'api_key' => BiometricDevice::generateApiKey(),
        ]);

        $this->assertNull($d1->fresh()->serial_number);
        $this->assertNull($d2->fresh()->serial_number);
    }
}
