<?php

namespace Tests\Feature\Iclock;

use App\Models\Attendance;
use App\Models\DeviceCommand;

class IclockAccPushTest extends IclockTestCase
{
    private function connect($device): string
    {
        return $this->get('/iclock/cdata?' . http_build_query([
            'SN' => $device->serial_number, 'options' => 'all', 'pushver' => '3.1.2', 'DeviceType' => 'acc',
        ]))->assertOk()->getContent();
    }

    public function test_acc_handshake_persists_registration_and_returns_security_options(): void
    {
        $device = $this->device();
        $body = $this->connect($device);
        $this->assertStringContainsString("registry=ok\n", $body);
        $this->assertStringContainsString('PushProtVer=3.0.1', $body);
        $this->assertStringNotContainsString('GET OPTION FROM', $body);
        $this->assertSame($body, $this->connect($device));
        $this->post('/iclock/registry?SN=' . $device->serial_number)->assertOk()
            ->assertSeeText('RegistryCode=' . $device->fresh()->acc_push_state['registry_code']);
        $this->get('/iclock/push?SN=' . $device->serial_number)->assertOk()->assertSeeText('RequestDelay=10');
        $this->post('/iclock/push?SN=' . $device->serial_number)->assertOk();
        $this->get('/iclock/ping?SN=' . $device->serial_number)->assertOk();
        $this->assertArrayNotHasKey('acc_push_state', $device->fresh()->toArray());
    }

    public function test_unknown_and_inactive_machines_cannot_register_or_collect_commands(): void
    {
        $device = $this->device(['is_active' => false]);
        $this->connect($device);
        $this->assertNull($device->fresh()->acc_push_state);
        foreach ([$device->serial_number, 'UNKNOWN'] as $sn) {
            $this->post('/iclock/registry?SN=' . $sn)->assertUnauthorized();
            $this->get('/iclock/push?SN=' . $sn)->assertUnauthorized();
        }
    }

    public function test_commands_queued_before_detection_are_delivered_in_acc_format(): void
    {
        $device = $this->device();
        $user = $this->member('1001');
        $command = DeviceCommand::create([
            'device_serial_number' => $device->serial_number, 'user_id' => $user->id,
            'status' => 'pending', 'command' => "DATA UPDATE USERINFO PIN=1001\tName=Ali\tPri=0\tPasswd=\tCard=\tGrp=1",
        ]);
        $this->connect($device);
        $body = $this->get('/iclock/getrequest?SN=' . $device->serial_number)->assertOk()->getContent();
        $this->assertStringContainsString("C:{$command->cmd_id}:DATA UPDATE user Pin=1001\tName=Ali", $body);
        $this->assertStringNotContainsString('USERINFO', $body);
        $this->assertSame('sent', $command->fresh()->status);
        $this->call('POST', '/iclock/devicecmd?SN=' . $device->serial_number,
            [], [], [], ['CONTENT_TYPE' => 'text/plain'], "ID={$command->cmd_id}&Return=0&CMD=DATA UPDATE")
            ->assertOk();
        $this->assertSame('done', $command->fresh()->status);
        $delete = DeviceCommand::create([
            'device_serial_number' => $device->serial_number, 'status' => 'pending',
            'command' => 'DATA DELETE USERINFO PIN=1001',
        ]);
        $this->get('/iclock/getrequest?SN=' . $device->serial_number)->assertOk()
            ->assertSeeText("C:{$delete->cmd_id}:DATA DELETE user Pin=1001");
    }

    public function test_rtlog_records_in_out_and_ignores_alarm_and_duplicate_events(): void
    {
        $device = $this->device();
        $user = $this->member('1001');
        $this->connect($device);
        $body = "time=2026-10-09 09:00:00\tpin=1001\tevent=14\tinoutstatus=0\n";
        $this->upload($device->serial_number, 'rtlog', $body)->assertOk()->assertContent('OK');
        $this->upload($device->serial_number, 'rtlog', $body)->assertOk();
        $this->upload($device->serial_number, 'rtlog', "time=2026-10-09 09:10:00\tpin=1001\tevent=27\tinoutstatus=1")->assertOk();
        $this->assertSame(1, Attendance::count());
        $this->assertNull(Attendance::sole()->check_out_time);
        $this->upload($device->serial_number, 'rtlog', "time=2026-10-09 10:00:00\tpin=1001\tevent=14\tinoutstatus=1")->assertOk();
        $record = Attendance::sole();
        $this->assertSame($user->id, $record->user_id);
        $this->assertSame('2026-10-09 10:00:00', $record->check_out_time->format('Y-m-d H:i:s'));
        $this->assertStringContainsString('event=14', $device->fresh()->last_payload);
    }

    public function test_transaction_packed_timestamp_is_decoded_as_device_wall_clock(): void
    {
        $device = $this->device();
        $this->member('1001');
        $this->connect($device);
        $seconds = ((2026 - 2000) * 372 + 9 * 31 + 8) * 86400 + 9 * 3600;
        $this->upload($device->serial_number, 'tabledata',
            "transaction cardno=0\tpin=1001\teventtype=0\tinoutstate=0\ttime_second={$seconds}",
            ['tablename' => 'transaction'])->assertOk();
        $this->assertSame('2026-10-09 09:00:00', Attendance::sole()->check_in_time->format('Y-m-d H:i:s'));
    }

    public function test_toggle_mode_and_setup_preview_support_acc_events(): void
    {
        $device = $this->device(['settings' => ['punch_mode' => 'toggle']]);
        $this->member('1001');
        $this->connect($device);
        $body = "time=2026-10-09 09:00:00\tpin=1001\tevent=14\tinoutstatus=0\n"
            . "time=2026-10-09 10:00:00\tpin=1001\tevent=14\tinoutstatus=0";
        $this->upload($device->serial_number, 'rtlog', $body)->assertOk();
        $this->assertNotNull(Attendance::sole()->check_out_time);
        $request = \Illuminate\Http\Request::create('/', 'POST', [], [], [], [], $body);
        $logs = app(\App\Biometric\Drivers\ZKTecoDriver::class)->parse($request, $device->fresh());
        $this->assertCount(2, $logs);
        $this->assertNull($logs[0]->type);
    }

    public function test_malformed_and_non_person_events_do_not_create_attendance(): void
    {
        $device = $this->device();
        $this->connect($device);
        $this->upload($device->serial_number, 'rtlog', implode("\n", [
            "time=bad\tpin=1001\tevent=14",
            "time=2026-02-31 09:00:00\tpin=1001\tevent=14",
            "time=2026-10-09 09:00:00\tpin=0\tevent=14",
            "time=2026-10-09 09:00:00\tpin=1001\tevent=8",
            "time=2026-10-09 09:00:00\tpin=1001\tevent=4",
        ]))->assertOk();
        $this->assertSame(0, Attendance::count());
    }

    public function test_completed_multi_user_events_record_attendance_but_pending_verification_does_not(): void
    {
        $device = $this->device();
        $this->connect($device);
        foreach ([3, 15, 203] as $event) {
            $pin = (string) (1000 + $event);
            $user = $this->member($pin);
            $this->upload($device->serial_number, 'rtlog',
                "time=2026-10-09 01:06:03\tpin={$pin}\tcardno=0\teventaddr=1\tevent={$event}\tinoutstatus=0\tverifytype=1")
                ->assertOk();
            $this->assertDatabaseHas('attendances', ['user_id' => $user->id, 'source' => 'biometric']);
        }
        $this->member('9999');
        $this->upload($device->serial_number, 'rtlog',
            "time=2026-10-09 01:06:03\tpin=9999\tevent=26\tinoutstatus=0")->assertOk();
        $this->assertSame(3, Attendance::count());
    }
}
