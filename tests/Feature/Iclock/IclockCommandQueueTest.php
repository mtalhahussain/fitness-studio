<?php

namespace Tests\Feature\Iclock;

use App\Models\DeviceCommand;
use App\Models\Gym;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\TrainerService;
use Spatie\Permission\Models\Role;

/** Portal-first enrollment: portal → device_commands → GET /iclock/getrequest → POST /iclock/devicecmd. */
class IclockCommandQueueTest extends IclockTestCase
{
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['owner', 'member', 'trainer'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->owner = User::create([
            'gym_id' => $this->gym->id, 'name' => 'Owner', 'email' => 'owner@test.local',
            'password' => 'x', 'status' => 'active',
        ]);
        $this->owner->assignRole('owner');
    }

    private function createMember(string $name = 'Ali Khan', ?Gym $gym = null): User
    {
        return app(MembershipService::class)->createMember(
            ['name' => $name, 'email' => str()->slug($name) . '@test.local'],
            ($gym ?? $this->gym)->id,
        );
    }

    private function poll(string $sn): string
    {
        return $this->get("/iclock/getrequest?SN={$sn}")->assertOk()->getContent();
    }

    private function reportResult(string $sn, string $body)
    {
        return $this->call('POST', "/iclock/devicecmd?SN={$sn}", [], [], [], ['CONTENT_TYPE' => 'text/plain'], $body);
    }

    // ── Enrollment on register ───────────────────────────────────────────────

    public function test_new_member_gets_pin_and_is_queued_to_every_active_zkteco_machine_of_the_gym(): void
    {
        $otherGym = Gym::create(['name' => 'Other', 'slug' => 'other', 'email' => 'other@test.local']);
        $this->device(['serial_number' => 'SN-FRONT']);
        $this->device(['serial_number' => 'SN-BACK']);
        $this->device(['serial_number' => 'SN-OFF', 'is_active' => false]);
        $this->device(['serial_number' => 'SN-ELSEWHERE', 'gym_id' => $otherGym->id]);

        $member = $this->createMember('Ali Khan');

        $this->assertSame((string) $member->id, $member->fresh()->biometric_code);
        $this->assertEqualsCanonicalizing(
            ['SN-FRONT', 'SN-BACK'],
            DeviceCommand::pluck('device_serial_number')->all()
        );

        $command = DeviceCommand::where('device_serial_number', 'SN-FRONT')->sole();
        $this->assertSame("DATA UPDATE USERINFO PIN={$member->id}\tName=Ali Khan\tPri=0\tPasswd=\tCard=\tGrp=1", $command->command);
        $this->assertSame(DeviceCommand::PENDING, $command->status);
        $this->assertSame($member->id, $command->user_id);
        $this->assertSame($command->id, (int) $command->cmd_id);
    }

    public function test_new_trainer_is_queued_too(): void
    {
        $this->device();

        $trainer = app(TrainerService::class)->createTrainer(
            ['name' => 'Coach Sara', 'email' => 'sara@test.local', 'specialization' => 'Yoga'],
            $this->gym->id,
        );

        $this->assertNotNull($trainer->fresh()->biometric_code);
        $this->assertStringContainsString('Name=Coach Sara', DeviceCommand::sole()->command);
    }

    public function test_member_is_created_even_without_machines(): void
    {
        $member = $this->createMember();

        $this->assertNotNull($member->fresh()->biometric_code);
        $this->assertSame(0, DeviceCommand::count());
    }

    // ── PIN generation ───────────────────────────────────────────────────────

    public function test_pin_falls_back_to_next_free_number_when_the_id_is_already_someones_pin(): void
    {
        $next = User::max('id') + 1;
        User::create([
            'gym_id' => $this->gym->id, 'name' => 'Legacy', 'email' => 'legacy@test.local',
            'password' => 'x', 'status' => 'active', 'biometric_code' => (string) ($next + 50),
        ]); // takes id $next, holds PIN $next+50
        User::create([
            'gym_id' => $this->gym->id, 'name' => 'Taker', 'email' => 'taker@test.local',
            'password' => 'x', 'status' => 'active', 'biometric_code' => (string) ($next + 1 + 1),
        ]); // takes id $next+1

        $member = $this->createMember('New One'); // id $next+2 is already the Taker's PIN

        $this->assertSame((string) ($next + 51), $member->fresh()->biometric_code);
        $this->assertLessThanOrEqual(9, strlen($member->fresh()->biometric_code));
    }

    public function test_pin_of_a_deleted_member_is_never_reused(): void
    {
        $old = $this->createMember('Old Member');
        $oldPin = $old->fresh()->biometric_code;
        $old->delete(); // soft delete

        $new = $this->createMember('New Member');

        $this->assertNotSame($oldPin, $new->fresh()->biometric_code);
    }

    public function test_same_pin_is_allowed_in_different_gyms(): void
    {
        $otherGym = Gym::create(['name' => 'Other', 'slug' => 'other', 'email' => 'other@test.local']);
        $a = $this->createMember('Gym A Member');
        User::create([
            'gym_id' => $otherGym->id, 'name' => 'B', 'email' => 'b@test.local',
            'password' => 'x', 'status' => 'active', 'biometric_code' => $a->fresh()->biometric_code,
        ]);

        $this->assertSame(1, User::where('gym_id', $otherGym->id)->where('biometric_code', $a->fresh()->biometric_code)->count());
    }

    // ── GET /iclock/getrequest ───────────────────────────────────────────────

    public function test_poll_returns_pending_commands_in_adms_format_and_marks_them_sent(): void
    {
        $device = $this->device();
        $member = $this->createMember('Ali Khan');
        $cmd    = DeviceCommand::sole();

        $body = $this->poll($device->serial_number);

        $this->assertSame("C:{$cmd->cmd_id}:DATA UPDATE USERINFO PIN={$member->id}\tName=Ali Khan\tPri=0\tPasswd=\tCard=\tGrp=1\n", $body);
        $this->assertSame(DeviceCommand::SENT, $cmd->fresh()->status);
        $this->assertNotNull($cmd->fresh()->sent_at);

        $this->assertSame('OK', $this->poll($device->serial_number));
    }

    public function test_poll_only_returns_this_machines_commands(): void
    {
        $front = $this->device(['serial_number' => 'SN-FRONT']);
        $back  = $this->device(['serial_number' => 'SN-BACK']);
        $this->createMember();

        $this->assertStringStartsWith('C:', $this->poll($front->serial_number));
        $this->assertSame(DeviceCommand::PENDING, DeviceCommand::where('device_serial_number', 'SN-BACK')->sole()->status);
        $this->assertStringStartsWith('C:', $this->poll($back->serial_number));
    }

    public function test_poll_hands_out_at_most_the_configured_batch(): void
    {
        config(['biometric.adms.commands_per_poll' => 2]);
        $device = $this->device();
        foreach (['A One', 'B Two', 'C Three'] as $name) {
            $this->createMember($name);
        }

        $this->assertCount(2, explode("\n", trim($this->poll($device->serial_number))));
        $this->assertCount(1, explode("\n", trim($this->poll($device->serial_number))));
        $this->assertSame('OK', $this->poll($device->serial_number));
    }

    public function test_unanswered_command_is_sent_again_later(): void
    {
        $device = $this->device();
        $this->createMember();
        $this->poll($device->serial_number);

        $this->assertSame('OK', $this->poll($device->serial_number));

        $this->travel(11)->minutes();
        $this->assertStringStartsWith('C:', $this->poll($device->serial_number));
    }

    public function test_unknown_or_inactive_machine_gets_no_commands(): void
    {
        $device = $this->device();
        $this->createMember();
        $device->update(['is_active' => false]);

        $this->assertSame('OK', $this->poll($device->serial_number));
        $this->assertSame('OK', $this->poll('TYPO-999'));
        $this->assertSame(DeviceCommand::PENDING, DeviceCommand::sole()->status);
    }

    // ── POST /iclock/devicecmd ───────────────────────────────────────────────

    public function test_result_marks_commands_done_or_failed(): void
    {
        $device = $this->device();
        $this->createMember('A One');
        $this->createMember('B Two');
        [$ok, $bad] = DeviceCommand::orderBy('id')->get()->all();
        $this->poll($device->serial_number);

        $this->reportResult($device->serial_number, "ID={$ok->cmd_id}&Return=0&CMD=DATA\nID={$bad->cmd_id}&Return=-1&CMD=DATA\n")
            ->assertOk()->assertSeeText('OK');

        $this->assertSame(DeviceCommand::DONE, $ok->fresh()->status);
        $this->assertSame("ID={$ok->cmd_id}&Return=0&CMD=DATA", $ok->fresh()->result);
        $this->assertNotNull($ok->fresh()->completed_at);
        $this->assertSame(DeviceCommand::FAILED, $bad->fresh()->status);
    }

    public function test_a_machine_cannot_answer_for_another_machines_command(): void
    {
        $front = $this->device(['serial_number' => 'SN-FRONT']);
        $this->device(['serial_number' => 'SN-BACK']);
        $this->createMember();
        $backCmd = DeviceCommand::where('device_serial_number', 'SN-BACK')->sole();

        $this->reportResult($front->serial_number, "ID={$backCmd->cmd_id}&Return=0&CMD=DATA\n")->assertOk();

        $this->assertSame(DeviceCommand::PENDING, $backCmd->fresh()->status);
    }

    public function test_result_from_unknown_serial_is_rejected(): void
    {
        $this->device();
        $this->createMember();
        $cmd = DeviceCommand::sole();

        $this->reportResult('TYPO-999', "ID={$cmd->cmd_id}&Return=0&CMD=DATA\n")->assertStatus(401);
        $this->assertSame(DeviceCommand::PENDING, $cmd->fresh()->status);
    }

    // ── Portal actions ───────────────────────────────────────────────────────

    public function test_owner_can_push_a_member_without_a_pin(): void
    {
        $this->device();
        $member = User::create(['gym_id' => $this->gym->id, 'name' => 'Walk In', 'email' => 'walkin@test.local', 'password' => 'x', 'status' => 'active']);
        $member->assignRole('member');

        $res = $this->actingAs($this->owner)->postJson("/biometric/users/{$member->id}/push")->assertOk();

        $this->assertSame((string) $member->id, $res->json('biometric_code'));
        $this->assertSame(1, $res->json('queued'));
        $this->actingAs($this->owner)->getJson("/biometric/users/{$member->id}")
            ->assertOk()->assertJsonPath('biometric_code', (string) $member->id)->assertJsonCount(1, 'commands');
    }

    public function test_regenerate_deletes_old_pin_then_pushes_new_one(): void
    {
        $device = $this->device();
        $member = $this->createMember('Ali Khan');
        $old    = $member->fresh()->biometric_code;
        $this->poll($device->serial_number);

        $res = $this->actingAs($this->owner)->postJson("/biometric/users/{$member->id}/regenerate")->assertOk();

        $new = $res->json('biometric_code');
        $this->assertNotSame($old, $new);
        $this->assertSame($new, $member->fresh()->biometric_code);

        $lines = explode("\n", trim($this->poll($device->serial_number)));
        $this->assertStringEndsWith(":DATA DELETE USERINFO PIN={$old}", $lines[0]);
        $this->assertStringContainsString("DATA UPDATE USERINFO PIN={$new}\t", $lines[1]);
    }

    public function test_owner_cannot_touch_another_gyms_member(): void
    {
        $otherGym = Gym::create(['name' => 'Other', 'slug' => 'other', 'email' => 'other@test.local']);
        $stranger = $this->createMember('Stranger', $otherGym);

        $pin = $stranger->fresh()->biometric_code;

        // 403 from the gym check, or 404 when the gym scope already hides the user from route binding.
        foreach (['push', 'regenerate'] as $action) {
            $status = $this->actingAs($this->owner)->postJson("/biometric/users/{$stranger->id}/{$action}")->status();
            $this->assertContains($status, [403, 404], "{$action} returned {$status}");
        }

        $this->assertSame($pin, $stranger->fresh()->biometric_code);
        $this->assertSame(0, DeviceCommand::count());
    }

    public function test_sync_users_queues_members_and_trainers_once(): void
    {
        $this->createMember('A One');
        $trainer = User::create(['gym_id' => $this->gym->id, 'name' => 'Coach', 'email' => 'coach@test.local', 'password' => 'x', 'status' => 'active']);
        $trainer->assignRole('trainer');
        $device = $this->device(['serial_number' => 'SN-NEW']); // added after the users

        $this->actingAs($this->owner)->postJson("/biometric/devices/{$device->id}/push-users")->assertOk()->assertJsonPath('queued', 2);
        $this->actingAs($this->owner)->postJson("/biometric/devices/{$device->id}/push-users")->assertOk()->assertJsonPath('queued', 0);

        $this->assertSame(2, DeviceCommand::where('device_serial_number', 'SN-NEW')->count());
        $this->assertFalse(DeviceCommand::where('command', 'like', '%Name=Owner%')->exists());
        $this->assertNotNull($trainer->fresh()->biometric_code);
    }

    public function test_deleting_a_member_removes_them_from_the_machines(): void
    {
        $device = $this->device();
        $member = $this->createMember();
        $pin    = $member->fresh()->biometric_code;
        $this->poll($device->serial_number);

        $this->actingAs($this->owner)->deleteJson("/members/{$member->id}")->assertOk();

        $this->assertStringEndsWith(":DATA DELETE USERINFO PIN={$pin}\n", $this->poll($device->serial_number));
    }

    public function test_pages_show_machine_pin_and_queue_status(): void
    {
        $device = $this->device();
        $this->createMember('A One');
        $this->createMember('B Two');
        DeviceCommand::orderBy('id')->first()->update(['status' => DeviceCommand::FAILED]);

        $this->actingAs($this->owner)->get('/biometric/devices')->assertOk()
            ->assertSee('Sync users')->assertSee('1 waiting')->assertSee('1 failed');
        $this->actingAs($this->owner)->get('/members')->assertOk()->assertSee('Machine PIN')->assertSee('machinePin.push', false);
        $this->actingAs($this->owner)->get('/trainers')->assertOk()->assertSee('Machine PIN');
    }

    public function test_renaming_a_member_updates_the_machines(): void
    {
        $device = $this->device();
        $member = $this->createMember('Ali Khan');
        $this->poll($device->serial_number);

        $this->actingAs($this->owner)->putJson("/members/{$member->id}", ['name' => "Ali\tRaza"])->assertOk();

        $this->assertStringContainsString("Name=Ali Raza\tPri=0", $this->poll($device->serial_number));
    }
}
