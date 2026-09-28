<?php

namespace Tests\Feature;

use App\Biometric\Drivers\ZKTecoDriver;
use App\Models\BiometricDevice;
use Tests\TestCase;

class ZKTecoDriverTest extends TestCase
{
    public function test_setup_steps_contain_correct_host_port_and_path(): void
    {
        $device = new BiometricDevice(['serial_number' => 'SN1']);

        $steps = (new ZKTecoDriver())->setupSteps($device, 'https://gym.example.com');

        $this->assertContains('Server address: gym.example.com', $steps);
        $this->assertTrue(
            collect($steps)->contains(fn ($step) => str_contains($step, 'Server port: 443')),
            'Expected a setup step mentioning Server port: 443'
        );
        $this->assertTrue(
            collect($steps)->contains(fn ($step) => str_contains($step, '/api/biometric/push')),
            'Expected a setup step mentioning /api/biometric/push'
        );
        $this->assertTrue(
            collect($steps)->contains(fn ($step) => str_contains($step, 'SN1')),
            'Expected a setup step mentioning the serial number SN1'
        );
    }
}
