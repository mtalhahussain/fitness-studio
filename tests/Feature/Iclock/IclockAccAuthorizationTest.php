<?php

namespace Tests\Feature\Iclock;

use App\Biometric\DeviceCommandQueue;
use App\Models\DeviceCommand;
use Spatie\Permission\Models\Role;

class IclockAccAuthorizationTest extends IclockTestCase
{
    private function accDevice()
    {
        $device = $this->device();
        $this->get('/iclock/cdata?SN=' . $device->serial_number . '&DeviceType=acc')->assertOk();
        return $device->fresh();
    }

    public function test_bulk_sync_queues_schedule_once_then_users_and_door_one_permissions(): void
    {
        $device = $this->accDevice();
        Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web']);
        $this->member('14')->assignRole('member');
        $this->member('15')->assignRole('member');
        $queue = app(DeviceCommandQueue::class);
        $this->assertSame(2, $queue->pushAllUsers($device));
        $this->assertSame(0, $queue->pushAllUsers($device));
        $commands = DeviceCommand::orderBy('id')->get();
        $this->assertCount(5, $commands);
        $this->assertStringStartsWith('DATA UPDATE timezone TimezoneId=1', $commands[0]->command);
        foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Hol1', 'Hol2', 'Hol3'] as $day) {
            $this->assertStringContainsString("{$day}Time1=2359\t{$day}Time2=0\t{$day}Time3=0", $commands[0]->command);
        }
        $this->assertStringStartsWith('DATA UPDATE USERINFO PIN=14', $commands[1]->command);
        $this->assertSame("DATA UPDATE userauthorize Pin=14\tAuthorizeTimezoneId=1\tAuthorizeDoorId=1", $commands[2]->command);
        $this->assertStringStartsWith('DATA UPDATE USERINFO PIN=15', $commands[3]->command);
        $this->assertStringContainsString('userauthorize Pin=15', $commands[4]->command);
        $body = $this->get('/iclock/getrequest?SN=' . $device->serial_number)->assertOk()->getContent();
        $this->assertStringContainsString('DATA UPDATE user Pin=14', $body);
        $this->assertStringContainsString('DATA UPDATE userauthorize Pin=14', $body);
        // Repeated sync while the machine is executing does not duplicate sent commands.
        $this->assertSame(0, $queue->pushAllUsers($device));
        $this->assertSame(5, DeviceCommand::count());
    }

    public function test_single_user_sync_requeues_permission_even_if_user_record_is_already_pending(): void
    {
        $device = $this->accDevice();
        $member = $this->member('14');
        DeviceCommand::create([
            'device_serial_number' => $device->serial_number, 'user_id' => $member->id,
            'status' => 'pending', 'command' => DeviceCommandQueue::userInfoCommand($member),
        ]);
        $queue = app(DeviceCommandQueue::class);
        $this->assertSame(1, $queue->pushUser($member));
        $this->assertSame(0, $queue->pushUser($member));
        $this->assertSame(3, DeviceCommand::count());
        DeviceCommand::query()->update(['status' => 'done']);
        $this->assertSame(1, $queue->pushUser($member));
        $this->assertSame(6, DeviceCommand::count());
    }

    public function test_inactive_user_and_removed_pin_receive_authorization_deletion(): void
    {
        $device = $this->accDevice();
        $member = $this->member('14');
        $member->update(['status' => 'inactive']);
        $queue = app(DeviceCommandQueue::class);
        $queue->pushUser($member);
        $this->assertDatabaseHas('device_commands', ['command' => 'DATA DELETE userauthorize Pin=14']);
        $this->assertFalse(DeviceCommand::where('command', 'like', 'DATA UPDATE userauthorize%')->exists());
        $queue->removePin($device->gym_id, '15', $member->id);
        $commands = DeviceCommand::orderBy('id')->get();
        $this->assertSame('DATA DELETE userauthorize Pin=15', $commands[3]->command);
        $this->assertSame('DATA DELETE USERINFO PIN=15', $commands[4]->command);
    }

    public function test_adms_and_other_gyms_do_not_receive_acc_authorizations(): void
    {
        $this->device();
        $otherGym = \App\Models\Gym::create(['name' => 'Other', 'slug' => 'other', 'email' => 'other@test.local']);
        $this->device(['serial_number' => 'OTHER', 'gym_id' => $otherGym->id,
            'acc_push_state' => null]);
        app(DeviceCommandQueue::class)->pushUser($this->member('14'));
        $this->assertSame(1, DeviceCommand::count());
        $this->assertStringStartsWith('DATA UPDATE USERINFO PIN=14', DeviceCommand::sole()->command);
    }
}
