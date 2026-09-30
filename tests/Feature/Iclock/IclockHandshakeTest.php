<?php

namespace Tests\Feature\Iclock;

use App\Services\LicenseService;
use App\Services\UnknownBiometricDevices;

class IclockHandshakeTest extends IclockTestCase
{
    private function handshake(string $sn)
    {
        return $this->get("/iclock/cdata?SN={$sn}&options=all&pushver=2.4.1&language=69");
    }

    public function test_known_device_gets_adms_options(): void
    {
        $device = $this->device();

        $response = $this->handshake($device->serial_number);

        $response->assertOk();
        $this->assertStringStartsWith('text/plain', $response->headers->get('Content-Type'));

        $lines = explode("\n", trim($response->getContent()));
        $this->assertSame('GET OPTION FROM: CKJG123456789', $lines[0]);
        foreach ([
            'Stamp=9999', 'OpStamp=9999', 'ATTLOGStamp=9999', 'OPERLOGStamp=9999',
            'ErrorDelay=30', 'Delay=10', 'TransInterval=1', 'TransFlag=1111000000',
            'Realtime=1', 'Encrypt=0',
        ] as $option) {
            $this->assertContains($option, $lines);
        }

        $this->assertNotNull($device->fresh()->last_seen_at);
    }

    public function test_handshake_echoes_last_upload_stamps(): void
    {
        $device = $this->device();
        $device->forceFill(['attlog_stamp' => '702345678', 'operlog_stamp' => '702340000'])->save();

        $lines = explode("\n", trim($this->handshake($device->serial_number)->getContent()));

        $this->assertContains('Stamp=702345678', $lines);
        $this->assertContains('ATTLOGStamp=702345678', $lines);
        $this->assertContains('OpStamp=702340000', $lines);
        $this->assertContains('OPERLOGStamp=702340000', $lines);
    }

    public function test_each_device_is_answered_with_its_own_serial(): void
    {
        $this->device(['serial_number' => 'SN-FRONT']);
        $this->device(['serial_number' => 'SN-BACK', 'name' => 'Back Door']);

        $this->assertStringStartsWith('GET OPTION FROM: SN-FRONT', $this->handshake('SN-FRONT')->getContent());
        $this->assertStringStartsWith('GET OPTION FROM: SN-BACK', $this->handshake('SN-BACK')->getContent());
    }

    public function test_unknown_serial_gets_no_options_and_is_recorded(): void
    {
        $response = $this->handshake('TYPO-999');

        $response->assertOk();
        $this->assertSame('OK', $response->getContent());
        $this->assertSame('TYPO-999', app(UnknownBiometricDevices::class)->recent()[0]['serial_number']);
    }

    public function test_inactive_device_gets_no_options_but_is_marked_seen(): void
    {
        $device = $this->device(['is_active' => false]);

        $this->assertSame('OK', $this->handshake($device->serial_number)->getContent());
        $this->assertNotNull($device->fresh()->last_seen_at);
    }

    public function test_invalid_license_does_not_block_device_traffic(): void
    {
        $this->mock(LicenseService::class, fn ($m) => $m->shouldReceive('check')->andReturn(false));
        $device = $this->device();

        $this->handshake($device->serial_number)->assertOk();
        $this->get("/iclock/getrequest?SN={$device->serial_number}")->assertOk();
        $this->upload($device->serial_number, 'ATTLOG', '')->assertOk();
    }

    public function test_command_poll_and_result_endpoints_answer_ok(): void
    {
        $device = $this->device();

        $this->get("/iclock/getrequest?SN={$device->serial_number}")->assertOk()->assertSeeText('OK');

        $this->call('POST', "/iclock/devicecmd?SN={$device->serial_number}", [], [], [], ['CONTENT_TYPE' => 'text/plain'], "ID=1&Return=0&CMD=DATA\n")
            ->assertOk()->assertSeeText('OK');
    }
}
