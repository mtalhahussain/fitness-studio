<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Gym;
use App\Models\User;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceHistoryTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;
    private User $owner;
    private User $member;
    private User $inactive;
    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(LicenseService::class, fn ($m) => $m->shouldReceive('check')->andReturn(true));
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->travelTo(\Carbon\Carbon::parse('2026-10-09 12:00:00'));
        $this->gym = Gym::create(['name' => 'Gym', 'slug' => 'history-gym', 'email' => 'gym@test.local', 'status' => 'active']);
        $other = Gym::create(['name' => 'Other', 'slug' => 'history-other', 'email' => 'other@test.local', 'status' => 'active']);
        $this->owner = $this->user('Owner', 'owner', $this->gym->id);
        $this->member = $this->user('Ali', 'member', $this->gym->id);
        $this->inactive = $this->user('Inactive', 'member', $this->gym->id);
        $this->inactive->update(['status' => 'inactive']);
        $this->outsider = $this->user('Outside', 'member', $other->id);
        $this->record($this->member, '2026-10-01 09:00:00');
        $this->record($this->member, '2026-10-01 18:00:00');
        $this->record($this->member, '2026-10-02 09:00:00');
        $this->record($this->member, '2026-09-30 09:00:00');
        $this->record($this->outsider, '2026-10-01 09:00:00');
    }

    private function user(string $name, string $role, int $gymId): User
    {
        $user = User::create(['gym_id' => $gymId, 'name' => $name, 'email' => strtolower($name).'@test.local', 'password' => 'secret', 'status' => 'active']);
        $user->assignRole($role);
        return $user;
    }

    private function record(User $user, string $time): void
    {
        Attendance::create(['gym_id' => $user->gym_id, 'user_id' => $user->id, 'check_in_time' => $time, 'check_out_time' => \Carbon\Carbon::parse($time)->addHour(), 'source' => 'biometric']);
    }

    public function test_monthly_view_includes_members_without_sessions_and_counts_unique_days(): void
    {
        $response = $this->actingAs($this->owner)->getJson('/attendance?month=2026-10&view=monthly');
        $response->assertOk()->assertJsonPath('summary.total', 3)->assertJsonPath('pagination.total', 2);
        $rows = collect($response->json('monthly'))->keyBy('id');
        $this->assertSame(2, $rows[$this->member->id]['present_count']);
        $this->assertSame(3, $rows[$this->member->id]['sessions']);
        $this->assertSame(0, $rows[$this->inactive->id]['present_count']);
        $this->assertFalse($rows->has($this->outsider->id));
        $this->assertCount(31, $response->json('dates'));
        $this->actingAs($this->owner)->get('/attendance?view=monthly')->assertOk()->assertSee('Monthly Attendance')->assertSee('All Members');
    }

    public function test_member_range_and_pagination_apply_to_records_and_summary(): void
    {
        $this->actingAs($this->owner)->getJson('/attendance?member_id='.$this->member->id.'&start_date=2026-10-01&end_date=2026-10-01&per_page=1&page=2')
            ->assertOk()->assertJsonPath('summary.total', 2)->assertJsonPath('pagination.current_page', 2)
            ->assertJsonPath('pagination.last_page', 2)->assertJsonCount(1, 'records')
            ->assertJsonPath('records.0.status', 'checked_out')->assertJsonPath('records.0.duration_mins', 60);
    }

    public function test_invalid_ranges_and_other_gym_members_are_rejected(): void
    {
        $this->actingAs($this->owner)->getJson('/attendance?member_id='.$this->outsider->id)->assertUnprocessable();
        $this->getJson('/attendance?start_date=2026-10-02&end_date=2026-10-01')->assertUnprocessable();
        $this->getJson('/attendance?start_date=2024-01-01&end_date=2026-10-01')->assertUnprocessable();
        $this->getJson('/attendance?month=2026-13')->assertUnprocessable();
    }

    public function test_inactive_member_history_and_filters_remain_available(): void
    {
        $this->record($this->inactive, '2026-10-05 23:59:59');
        $this->actingAs($this->owner)->getJson('/attendance?view=monthly&member_id='.$this->inactive->id.'&start_date=2026-10-05&end_date=2026-10-05')
            ->assertOk()->assertJsonPath('monthly.0.present_count', 1)->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('summary.total', 1);
        $this->getJson('/attendance?month=2026-10&status=checked_in')->assertOk()->assertJsonPath('summary.total', 0);
        $this->getJson('/attendance?month=2026-10&source=manual')->assertOk()->assertJsonPath('summary.total', 0);
        $this->getJson('/attendance?month=2026-10&search=Ali')->assertOk()->assertJsonPath('summary.total', 3)->assertJsonCount(1, 'monthly');
    }

    public function test_admin_monthly_view_uses_the_selected_gym(): void
    {
        $admin = $this->user('Admin', 'admin', $this->gym->id);
        $this->actingAs($admin)->withSession(['admin_active_gym_id' => $this->outsider->gym_id])
            ->getJson('/attendance?view=monthly&month=2026-10')
            ->assertOk()->assertJsonPath('summary.total', 1)->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('monthly.0.id', $this->outsider->id);
    }

    public function test_member_cannot_use_filters_to_view_someone_elses_history(): void
    {
        $this->actingAs($this->member)->get('/attendance?month=2026-10&member_id='.$this->outsider->id)
            ->assertOk()->assertViewIs('attendance.my-history')
            ->assertViewHas('records', fn ($records) => $records->count() === 3 && $records->every(fn ($r) => $r->user_id === $this->member->id));
    }
}
