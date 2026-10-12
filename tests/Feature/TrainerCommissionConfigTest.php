<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\TrainerCommissionConfig;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Services\LicenseService;
use App\Services\TrainerCommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TrainerCommissionConfigTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;
    private User $owner;
    private User $trainer;
    private TrainerCommissionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(LicenseService::class, fn ($m) => $m->shouldReceive('check')->andReturn(true));
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $this->gym = Gym::create(['name' => 'Test Gym', 'slug' => 'test-gym', 'email' => 'gym@test.local', 'status' => 'active']);
        $this->owner   = $this->makeUser('Owais Owner', 'owner');
        $this->trainer = $this->makeUser('Tariq Trainer', 'trainer');
        TrainerProfile::create(['user_id' => $this->trainer->id, 'gym_id' => $this->gym->id, 'specialization' => 'Boxing']);
        $this->service = app(TrainerCommissionService::class);
    }

    private function makeUser(string $name, string $role): User
    {
        $user = User::create([
            'gym_id' => $this->gym->id, 'name' => $name, 'email' => str()->slug($name) . '@test.local',
            'password' => 'secret', 'status' => 'active',
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function set(float $rate, string $from, ?string $to = null, bool $gymDefault = false): TrainerCommissionConfig
    {
        return $this->service->setCommissionConfig($this->gym->id, [
            'trainer_id'      => $gymDefault ? null : $this->trainer->id,
            'commission_rate' => $rate,
            'effective_from'  => $from,
            'effective_to'    => $to,
        ]);
    }

    private function rate(string $date): float
    {
        return $this->service->getCommissionRate($this->gym->id, $this->trainer->id, $date);
    }

    public function test_open_ended_rate_continues_indefinitely(): void
    {
        $this->set(40, '2026-01-01');

        $this->assertSame(40.0, $this->rate('2026-01-01'));
        $this->assertSame(40.0, $this->rate('2030-06-15'));
        $this->assertSame(50.0, $this->rate('2025-12-31')); // before any config → system default
    }

    public function test_temporary_range_overrides_then_ongoing_rate_resumes(): void
    {
        $base = $this->set(40, '2026-01-01');
        $this->set(60, '2026-03-01', '2026-03-31'); // monthly promo
        $this->set(70, '2026-05-04', '2026-05-10'); // weekly promo

        $this->assertSame(40.0, $this->rate('2026-02-28'));
        $this->assertSame(60.0, $this->rate('2026-03-01'));
        $this->assertSame(60.0, $this->rate('2026-03-31'));
        $this->assertSame(40.0, $this->rate('2026-04-01'));
        $this->assertSame(70.0, $this->rate('2026-05-07'));
        $this->assertSame(40.0, $this->rate('2026-05-11'));

        $this->assertNull($base->fresh()->effective_to, 'ongoing rate must not be cut by a temporary range');
    }

    public function test_new_ongoing_rate_closes_previous_and_keeps_history(): void
    {
        $old = $this->set(40, '2026-01-01');
        $this->set(45, '2026-06-01');

        $this->assertSame('2026-05-31', $old->fresh()->effective_to->toDateString());
        $this->assertSame(40.0, $this->rate('2026-05-31'));
        $this->assertSame(45.0, $this->rate('2026-06-01'));
        $this->assertSame(2, TrainerCommissionConfig::where('trainer_id', $this->trainer->id)->count());
    }

    public function test_backdated_ongoing_rate_ends_before_later_one(): void
    {
        $this->set(45, '2026-06-01');
        $earlier = $this->set(40, '2026-01-01');

        $this->assertSame('2026-05-31', $earlier->effective_to->toDateString());
        $this->assertSame(40.0, $this->rate('2026-03-01'));
        $this->assertSame(45.0, $this->rate('2026-07-01'));
    }

    public function test_overlapping_temporary_ranges_rejected(): void
    {
        $this->set(60, '2026-03-01', '2026-03-31');

        $this->expectException(ValidationException::class);
        $this->set(65, '2026-03-20', '2026-04-10');
    }

    public function test_trainer_rate_wins_over_gym_default_and_gym_default_is_fallback(): void
    {
        $this->set(30, '2026-01-01', null, true);
        $this->set(40, '2026-03-01', '2026-03-31');

        $this->assertSame(30.0, $this->rate('2026-02-01'));
        $this->assertSame(40.0, $this->rate('2026-03-15'));
        $this->assertSame(30.0, $this->rate('2026-04-01'));
    }

    public function test_history_lists_all_configs_with_status(): void
    {
        $this->set(40, '2020-01-01');
        $this->set(45, '2021-01-01');
        $this->set(60, now()->addMonth()->toDateString(), now()->addMonths(2)->toDateString());

        $history = $this->service->getConfigHistory($this->gym->id, $this->trainer->id);

        $this->assertCount(3, $history);
        $this->assertEqualsCanonicalizing(
            ['expired', 'active', 'scheduled'],
            $history->pluck('status')->all()
        );
    }

    public function test_owner_sets_temporary_rate_and_history_shows_on_commission_page(): void
    {
        $this->actingAs($this->owner)->postJson('/commission-config', [
            'trainer_id' => $this->trainer->id, 'commission_rate' => 40, 'effective_from' => '2026-01-01',
        ])->assertOk();
        $this->actingAs($this->owner)->postJson('/commission-config', [
            'trainer_id' => $this->trainer->id, 'commission_rate' => 65,
            'effective_from' => '2026-03-01', 'effective_to' => '2026-03-01', // single-day range allowed
        ])->assertOk();

        $this->assertSame(65.0, $this->rate('2026-03-01'));
        $this->assertSame(40.0, $this->rate('2026-03-02'));

        $this->actingAs($this->trainer)->get("/trainers/{$this->trainer->id}/commission")
            ->assertOk()
            ->assertSee('Commission Rate History')
            ->assertSee('Temporary')
            ->assertSee('Owais Owner');
    }

    public function test_compensation_mode_makes_its_fields_required(): void
    {
        $url = "/trainers/{$this->trainer->id}";

        $this->actingAs($this->owner)->putJson($url, ['compensation_mode' => 'hourly', 'hourly_rate' => null])
            ->assertStatus(422)->assertJsonValidationErrors('hourly_rate');
        $this->actingAs($this->owner)->putJson($url, ['compensation_mode' => 'salary', 'base_salary' => ''])
            ->assertStatus(422)->assertJsonValidationErrors('base_salary');
        $this->actingAs($this->owner)->putJson($url, ['compensation_mode' => 'mixed'])
            ->assertStatus(422)->assertJsonValidationErrors('base_salary');

        $this->actingAs($this->owner)->putJson($url, ['compensation_mode' => 'commission'])->assertOk();
        $this->actingAs($this->owner)->putJson($url, ['compensation_mode' => 'hourly', 'hourly_rate' => 1500])->assertOk();
        $this->actingAs($this->owner)->putJson($url, ['compensation_mode' => 'mixed', 'base_salary' => 40000])->assertOk();
    }
}
