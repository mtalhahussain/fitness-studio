<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\Invoice;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberFiltersTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;
    private User $owner;
    private MembershipPlan $monthly;
    private MembershipPlan $yearly;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(LicenseService::class, fn ($m) => $m->shouldReceive('check')->andReturn(true));
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $this->gym   = Gym::create(['name' => 'Test Gym', 'slug' => 'test-gym', 'email' => 'gym@test.local', 'status' => 'active']);
        $this->owner = $this->makeUser('Owais Owner', 'owner');

        $this->monthly = MembershipPlan::create(['gym_id' => $this->gym->id, 'name' => 'Monthly', 'type' => 'monthly', 'duration_days' => 30, 'price' => 3000, 'is_active' => true]);
        $this->yearly  = MembershipPlan::create(['gym_id' => $this->gym->id, 'name' => 'Yearly', 'type' => 'yearly', 'duration_days' => 365, 'price' => 30000, 'is_active' => true]);
    }

    private function makeUser(string $name, string $role, array $extra = []): User
    {
        $user = User::create(array_merge([
            'gym_id' => $this->gym->id, 'name' => $name, 'email' => str()->slug($name) . '@test.local',
            'password' => 'secret', 'status' => 'active',
        ], $extra));
        $user->assignRole($role);

        return $user;
    }

    private function giveMembership(User $member, MembershipPlan $plan, string $endDate, ?string $invoiceStatus = null): void
    {
        static $n = 0;
        $invoice = $invoiceStatus ? Invoice::create([
            'gym_id' => $this->gym->id, 'user_id' => $member->id, 'invoice_number' => 'INV-' . (++$n),
            'subtotal' => $plan->price, 'tax_amount' => 0, 'discount_amount' => 0, 'total_amount' => $plan->price,
            'status' => $invoiceStatus,
        ]) : null;

        Membership::create([
            'gym_id' => $this->gym->id, 'user_id' => $member->id, 'plan_id' => $plan->id,
            'start_date' => now()->subMonth()->toDateString(), 'end_date' => $endDate,
            'status' => 'active', 'amount_paid' => 0, 'invoice_id' => $invoice?->id,
        ]);
    }

    private function names(array $query): array
    {
        $res = $this->actingAs($this->owner)->getJson('/members?' . http_build_query($query))->assertOk();

        return collect($res->json('members'))->pluck('name')->sort()->values()->all();
    }

    public function test_members_filters(): void
    {
        $active   = $this->makeUser('Ali Active', 'member');
        $expiring = $this->makeUser('Bilal Expiring', 'member');
        $expired  = $this->makeUser('Chand Expired', 'member', ['status' => 'inactive']);
        $none     = $this->makeUser('Dua None', 'member', ['status' => 'suspended']);

        $this->giveMembership($active, $this->yearly, now()->addMonths(6)->toDateString(), 'paid');
        $this->giveMembership($expiring, $this->monthly, now()->addDays(3)->toDateString(), 'unpaid');
        $this->giveMembership($expired, $this->monthly, now()->subDays(2)->toDateString(), 'paid');

        $this->assertSame(['Ali Active'], $this->names(['member_id' => $active->id]));
        $this->assertSame(['Dua None'], $this->names(['status' => 'suspended']));
        $this->assertSame(['Ali Active', 'Bilal Expiring'], $this->names(['membership' => 'active']));
        $this->assertSame(['Bilal Expiring'], $this->names(['membership' => 'expiring']));
        $this->assertSame(['Chand Expired'], $this->names(['membership' => 'expired']));
        $this->assertSame(['Dua None'], $this->names(['membership' => 'none']));
        $this->assertSame(['Bilal Expiring'], $this->names(['plan_id' => $this->monthly->id])); // expired monthly excluded
        $this->assertSame(['Bilal Expiring'], $this->names(['payment' => 'due']));
        $this->assertSame(['Ali Active'], $this->names(['payment' => 'paid']));
        $this->assertSame(['Ali Active', 'Bilal Expiring'], $this->names(['membership' => 'active', 'search' => 'i']));
    }

    public function test_joined_date_range_and_validation(): void
    {
        $old = $this->makeUser('Old Member', 'member');
        $old->forceFill(['created_at' => '2025-01-10 10:00:00'])->save();
        $this->makeUser('New Member', 'member');

        $this->assertSame(['Old Member'], $this->names(['joined_from' => '2025-01-01', 'joined_to' => '2025-01-31']));

        $this->actingAs($this->owner)->getJson('/members?joined_from=2025-02-01&joined_to=2025-01-01')
            ->assertStatus(422)->assertJsonValidationErrors('joined_to');
        $this->actingAs($this->owner)->getJson('/members?membership=bogus')->assertStatus(422);
    }

    public function test_members_page_renders_filter_bar(): void
    {
        $this->makeUser('Ali Active', 'member');

        $this->actingAs($this->owner)->get('/members')->assertOk()
            ->assertSee('All Members')
            ->assertSee('Expiring in 7 days')
            ->assertSee('Joined Between');
    }
}
