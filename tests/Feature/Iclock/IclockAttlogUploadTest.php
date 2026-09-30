<?php

namespace Tests\Feature\Iclock;

use App\Models\Attendance;
use App\Models\Gym;
use App\Models\User;

class IclockAttlogUploadTest extends IclockTestCase
{
    public function test_attlog_row_from_raw_body_records_check_in(): void
    {
        $device = $this->device();
        $member = $this->member('1001');

        $response = $this->upload($device->serial_number, 'ATTLOG', $this->attlogLine('1001', '2026-09-30 09:00:00', 0) . "\n");

        $response->assertOk();
        $this->assertSame('OK: 1', $response->getContent());
        $this->assertDatabaseHas('attendances', [
            'user_id'        => $member->id,
            'gym_id'         => $this->gym->id,
            'source'         => 'biometric',
            'device_user_id' => '1001',
            'check_out_time' => null,
        ]);
        $this->assertSame('2026-09-30 09:00:00', Attendance::first()->check_in_time->format('Y-m-d H:i:s'));
    }

    public function test_batch_uses_status_for_in_and_out(): void
    {
        $device = $this->device();
        $member = $this->member('1001');

        $body = implode("\n", [
            $this->attlogLine('1001', '2026-09-30 09:00:00', 0),
            $this->attlogLine('1001', '2026-09-30 10:30:00', 1),
        ]) . "\n";

        $this->assertSame('OK: 2', $this->upload($device->serial_number, 'ATTLOG', $body)->getContent());

        $attendance = Attendance::where('user_id', $member->id)->sole();
        $this->assertSame('2026-09-30 10:30:00', $attendance->check_out_time->format('Y-m-d H:i:s'));
    }

    public function test_status_mode_does_not_treat_a_second_check_in_as_check_out(): void
    {
        $device = $this->device();
        $member = $this->member('1001');

        $body = $this->attlogLine('1001', '2026-09-30 09:00:00', 0) . "\n"
              . $this->attlogLine('1001', '2026-09-30 10:30:00', 0) . "\n";
        $this->upload($device->serial_number, 'ATTLOG', $body)->assertOk();

        $attendance = Attendance::where('user_id', $member->id)->sole();
        $this->assertNull($attendance->check_out_time);
    }

    public function test_toggle_mode_alternates_regardless_of_status(): void
    {
        $device = $this->device(['settings' => ['punch_mode' => 'toggle']]);
        $member = $this->member('1001');

        $body = $this->attlogLine('1001', '2026-09-30 09:00:00', 0) . "\n"
              . $this->attlogLine('1001', '2026-09-30 10:30:00', 0) . "\n";
        $this->upload($device->serial_number, 'ATTLOG', $body)->assertOk();

        $attendance = Attendance::where('user_id', $member->id)->sole();
        $this->assertSame('2026-09-30 10:30:00', $attendance->check_out_time->format('Y-m-d H:i:s'));
    }

    public function test_crlf_lines_and_unparseable_lines(): void
    {
        $device = $this->device();
        $this->member('1001');

        $body = $this->attlogLine('1001', '2026-09-30 09:00:00', 0) . "\r\n" . "garbage line\r\n" . "\r\n";

        $this->assertSame('OK: 1', $this->upload($device->serial_number, 'ATTLOG', $body)->getContent());
        $this->assertSame(1, Attendance::count());
    }

    public function test_upload_saves_the_attlog_stamp(): void
    {
        $device = $this->device();
        $this->member('1001');

        $this->upload($device->serial_number, 'ATTLOG', $this->attlogLine('1001', '2026-09-30 09:00:00'), ['Stamp' => '702345678']);

        $this->assertSame('702345678', $device->fresh()->attlog_stamp);
    }

    public function test_body_without_content_type_is_still_parsed(): void
    {
        $device = $this->device();
        $this->member('1001');

        $response = $this->call('POST', "/iclock/cdata?SN={$device->serial_number}&table=ATTLOG&Stamp=9999", [], [], [], [],
            $this->attlogLine('1001', '2026-09-30 09:00:00'));

        $this->assertSame('OK: 1', $response->getContent());
        $this->assertSame(1, Attendance::count());
    }

    public function test_punches_land_in_the_gym_of_the_device_that_sent_them(): void
    {
        $otherGym = Gym::create(['name' => 'Other Gym', 'slug' => 'other-gym', 'email' => 'other@test.local']);

        $front = $this->device(['serial_number' => 'SN-FRONT']);
        $back  = $this->device(['serial_number' => 'SN-BACK', 'name' => 'Back Door']);
        $other = $this->device(['serial_number' => 'SN-OTHER', 'gym_id' => $otherGym->id]);

        $here  = $this->member('1001');
        $there = User::create([
            'gym_id' => $otherGym->id, 'name' => 'Elsewhere', 'email' => 'elsewhere@test.local',
            'password' => 'irrelevant', 'status' => 'active', 'biometric_code' => '1001',
        ]);

        $this->upload($front->serial_number, 'ATTLOG', $this->attlogLine('1001', '2026-09-30 09:00:00', 0))->assertOk();
        $this->upload($back->serial_number, 'ATTLOG', $this->attlogLine('1001', '2026-09-30 11:00:00', 1))->assertOk();
        $this->upload($other->serial_number, 'ATTLOG', $this->attlogLine('1001', '2026-09-30 12:00:00', 0))->assertOk();

        $mine = Attendance::where('user_id', $here->id)->sole();
        $this->assertSame($this->gym->id, $mine->gym_id);
        $this->assertSame('2026-09-30 11:00:00', $mine->check_out_time->format('Y-m-d H:i:s'));

        $theirs = Attendance::where('user_id', $there->id)->sole();
        $this->assertSame($otherGym->id, $theirs->gym_id);
    }

    public function test_unknown_serial_is_rejected_and_nothing_saved(): void
    {
        $this->member('1001');

        $this->upload('TYPO-999', 'ATTLOG', $this->attlogLine('1001', '2026-09-30 09:00:00'))->assertStatus(401);
        $this->assertSame(0, Attendance::count());
    }

    public function test_inactive_device_is_rejected(): void
    {
        $device = $this->device(['is_active' => false]);
        $this->member('1001');

        $this->upload($device->serial_number, 'ATTLOG', $this->attlogLine('1001', '2026-09-30 09:00:00'))->assertStatus(401);
        $this->assertSame(0, Attendance::count());
    }

    public function test_operlog_is_acknowledged_without_creating_attendance(): void
    {
        $device = $this->device();
        $this->member('1001');

        $body = "OPLOG 4\t0\t2026-09-30 09:00:00\t0\t0\t0\t0\n"
              . "USER PIN=1001\tName=Member\tPri=0\tPasswd=\tCard=\tGrp=1\n";

        $response = $this->upload($device->serial_number, 'OPERLOG', $body, ['OpStamp' => '702340000']);

        $this->assertSame('OK: 2', $response->getContent());
        $this->assertSame(0, Attendance::count());
        $this->assertSame('702340000', $device->fresh()->operlog_stamp);
    }
}
