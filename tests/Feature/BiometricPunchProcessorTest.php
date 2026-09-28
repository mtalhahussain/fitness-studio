<?php

namespace Tests\Feature;

use App\Biometric\BiometricPunchProcessor;
use App\Biometric\PunchLog;
use App\Models\Attendance;
use App\Models\BiometricDevice;
use App\Models\Gym;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BiometricPunchProcessorTest extends TestCase
{
    use RefreshDatabase;

    private BiometricDevice $device;
    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web']);

        $gym = Gym::create(['name' => 'G', 'slug' => 'g', 'email' => 'g@test.local', 'status' => 'active']);
        $this->device = BiometricDevice::create(['gym_id' => $gym->id, 'name' => 'D', 'api_key' => 'k', 'brand' => 'hikvision']);
        $this->member = User::create(['gym_id' => $gym->id, 'name' => 'M', 'email' => 'm@test.local', 'password' => 'x', 'status' => 'active', 'biometric_code' => '77']);
    }

    private function process(string $time, ?string $type = null): bool
    {
        return app(BiometricPunchProcessor::class)->process(new PunchLog('77', Carbon::parse($time), $type), $this->device);
    }

    public function test_toggle_mode_checks_in_then_out(): void
    {
        $this->process('2026-09-28 09:00:00');
        $this->process('2026-09-28 10:30:00');

        $a = Attendance::first();
        $this->assertSame('2026-09-28 09:00:00', $a->check_in_time->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-28 10:30:00', $a->check_out_time->format('Y-m-d H:i:s'));
    }

    public function test_explicit_in_while_already_in_is_ignored(): void
    {
        $this->process('2026-09-28 09:00:00', PunchLog::IN);
        $this->process('2026-09-28 09:30:00', PunchLog::IN);

        $this->assertSame(1, Attendance::count());
        $this->assertNull(Attendance::first()->check_out_time);
    }

    public function test_explicit_out_without_open_session_is_ignored(): void
    {
        $this->process('2026-09-28 09:00:00', PunchLog::OUT);

        $this->assertSame(0, Attendance::count());
    }

    public function test_duplicate_within_window_is_skipped(): void
    {
        $this->process('2026-09-28 09:00:00');
        $this->process('2026-09-28 09:00:30');

        $this->assertSame(1, Attendance::count());
        $this->assertNull(Attendance::first()->check_out_time);
    }

    public function test_unknown_employee_is_auto_created_in_device_gym(): void
    {
        app(BiometricPunchProcessor::class)->process(new PunchLog('999', Carbon::parse('2026-09-28 09:00:00')), $this->device);

        $this->assertDatabaseHas('users', ['biometric_code' => '999', 'gym_id' => $this->device->gym_id]);
    }

    public function test_explicit_in_with_nothing_open_checks_in(): void
    {
        $this->process('2026-09-28 09:00:00', PunchLog::IN);

        $this->assertSame(1, Attendance::count());
        $this->assertNull(Attendance::first()->check_out_time);
    }

    public function test_explicit_out_with_open_session_checks_out(): void
    {
        $this->process('2026-09-28 09:00:00');
        $this->process('2026-09-28 10:30:00', PunchLog::OUT);

        $a = Attendance::first();
        $this->assertSame('2026-09-28 10:30:00', $a->check_out_time->format('Y-m-d H:i:s'));
    }

    public function test_member_of_another_gym_with_same_code_is_not_matched(): void
    {
        $otherGym  = Gym::create(['name' => 'G2', 'slug' => 'g2', 'email' => 'g2@test.local', 'status' => 'active']);
        $otherUser = User::create(['gym_id' => $otherGym->id, 'name' => 'O', 'email' => 'o@test.local', 'password' => 'x', 'status' => 'active', 'biometric_code' => '55']);

        app(BiometricPunchProcessor::class)->process(new PunchLog('55', Carbon::parse('2026-09-28 09:00:00')), $this->device);

        $this->assertSame(0, Attendance::where('user_id', $otherUser->id)->count());
    }
}
