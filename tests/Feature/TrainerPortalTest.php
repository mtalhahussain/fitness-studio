<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Gym;
use App\Models\MemberTrainingPeriod;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TrainerPortalTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;
    private User $trainer;
    private User $assigned;   // via trainer_member
    private User $training;   // via member_training_periods
    private User $stranger;   // same gym, not assigned

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(LicenseService::class, fn ($m) => $m->shouldReceive('check')->andReturn(true));

        foreach (['trainer', 'member'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->gym = Gym::create(['name' => 'Test Gym', 'slug' => 'test-gym', 'email' => 'gym@test.local', 'status' => 'active']);

        $this->trainer  = $this->makeUser('Tariq Trainer', 'trainer');
        $this->assigned = $this->makeUser('Ali Assigned', 'member');
        $this->training = $this->makeUser('Bilal Training', 'member');
        $this->stranger = $this->makeUser('Zara Stranger', 'member');

        DB::table('trainer_member')->insert([
            'gym_id' => $this->gym->id, 'trainer_id' => $this->trainer->id,
            'member_id' => $this->assigned->id, 'is_active' => true, 'assigned_at' => now(),
        ]);

        MemberTrainingPeriod::create([
            'gym_id' => $this->gym->id, 'trainer_id' => $this->trainer->id,
            'member_id' => $this->training->id, 'start_date' => today(), 'status' => 'active',
        ]);
    }

    private function makeUser(string $name, string $role): User
    {
        $user = User::create([
            'gym_id'   => $this->gym->id,
            'name'     => $name,
            'email'    => str()->slug($name) . '@test.local',
            'password' => 'secret',
            'status'   => 'active',
        ]);
        $user->assignRole($role);

        return $user;
    }

    public function test_members_page_lists_both_assignment_sources_only(): void
    {
        Attendance::create(['gym_id' => $this->gym->id, 'user_id' => $this->assigned->id, 'check_in_time' => now(), 'source' => 'manual']);

        $this->actingAs($this->trainer)->get('/my/members')
            ->assertOk()
            ->assertSee('Ali Assigned')
            ->assertSee('Bilal Training')
            ->assertDontSee('Zara Stranger')
            ->assertSee('✓ In gym', false);
    }

    public function test_dashboard_shows_assigned_members(): void
    {
        $this->actingAs($this->trainer)->get('/dashboard')
            ->assertOk()
            ->assertSee('Ali Assigned')
            ->assertSee('Bilal Training')
            ->assertDontSee('Zara Stranger');
    }

    public function test_trainer_can_view_assigned_member_attendance(): void
    {
        Attendance::create([
            'gym_id' => $this->gym->id, 'user_id' => $this->training->id,
            'check_in_time' => now()->startOfMonth()->setTime(9, 0), 'check_out_time' => now()->startOfMonth()->setTime(10, 30),
            'source' => 'manual',
        ]);

        $this->actingAs($this->trainer)->get("/my/members/{$this->training->id}")
            ->assertOk()
            ->assertSee('Bilal Training')
            ->assertSee('1h 30m');
    }

    public function test_trainer_cannot_view_unassigned_member(): void
    {
        $this->actingAs($this->trainer)->get("/my/members/{$this->stranger->id}")->assertForbidden();
    }

    public function test_members_cannot_open_trainer_pages(): void
    {
        $this->actingAs($this->assigned)->get('/my/members')->assertForbidden();
    }

    public function test_trainer_can_add_session_for_assigned_member(): void
    {
        $this->actingAs($this->trainer)->post('/my/sessions', [
            'member_id' => $this->assigned->id, 'title' => 'Cardio', 'session_type' => 'personal',
            'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'), 'duration_mins' => 60,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('training_sessions', [
            'trainer_id' => $this->trainer->id, 'member_id' => $this->assigned->id, 'title' => 'Cardio', 'status' => 'scheduled',
        ]);
    }

    public function test_weekly_repeat_creates_multiple_sessions(): void
    {
        $this->actingAs($this->trainer)->post('/my/sessions', [
            'member_id' => $this->training->id, 'title' => 'Strength Training', 'session_type' => 'personal',
            'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'), 'duration_mins' => 60, 'repeat_weeks' => 4,
        ])->assertSessionHas('success', '4 weekly sessions scheduled.');

        $this->assertSame(4, TrainingSession::where('member_id', $this->training->id)->count());
    }

    public function test_cannot_add_session_for_unassigned_member(): void
    {
        $this->actingAs($this->trainer)->post('/my/sessions', [
            'member_id' => $this->stranger->id, 'title' => 'Cardio', 'session_type' => 'personal',
            'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'), 'duration_mins' => 60,
        ])->assertSessionHasErrors('member_id');

        $this->assertSame(0, TrainingSession::count());
    }

    public function test_overlapping_session_is_rejected_with_friendly_error(): void
    {
        $at = now()->addDay()->setTime(10, 0);
        $payload = fn ($time) => [
            'member_id' => $this->assigned->id, 'title' => 'HIIT', 'session_type' => 'personal',
            'scheduled_at' => $time->format('Y-m-d\TH:i'), 'duration_mins' => 60,
        ];

        $this->actingAs($this->trainer)->post('/my/sessions', $payload($at));
        $this->actingAs($this->trainer)->post('/my/sessions', $payload($at->copy()->addMinutes(30)))
            ->assertSessionHas('error');
        // Back-to-back is fine
        $this->actingAs($this->trainer)->post('/my/sessions', $payload($at->copy()->addHour()))
            ->assertSessionHas('success');

        $this->assertSame(2, TrainingSession::count());
    }

    public function test_trainer_can_mark_own_session_done_but_not_others(): void
    {
        $other = $this->makeUser('Other Trainer', 'trainer');
        $mine  = TrainingSession::create(['gym_id' => $this->gym->id, 'trainer_id' => $this->trainer->id, 'member_id' => $this->assigned->id, 'title' => 'Yoga', 'scheduled_at' => now()->subHour(), 'status' => 'scheduled']);
        $their = TrainingSession::create(['gym_id' => $this->gym->id, 'trainer_id' => $other->id, 'title' => 'Group', 'scheduled_at' => now()->subHour(), 'status' => 'scheduled']);

        $this->actingAs($this->trainer)->patch("/my/sessions/{$mine->id}/status", ['status' => 'completed'])->assertSessionHas('success');
        $this->actingAs($this->trainer)->patch("/my/sessions/{$their->id}/status", ['status' => 'completed'])->assertForbidden();

        $this->assertSame('completed', $mine->fresh()->status);
        $this->assertSame('scheduled', $their->fresh()->status);
    }

    public function test_sessions_page_renders_tabs(): void
    {
        TrainingSession::create(['gym_id' => $this->gym->id, 'trainer_id' => $this->trainer->id, 'member_id' => $this->assigned->id, 'title' => 'Morning Leg Blast', 'scheduled_at' => now()->addDays(2), 'status' => 'scheduled']);

        $this->actingAs($this->trainer)->get('/my/sessions')->assertOk()->assertSee('Morning Leg Blast');
        $this->actingAs($this->trainer)->get('/my/sessions?tab=past')->assertOk()->assertDontSee('Morning Leg Blast');
    }
}
