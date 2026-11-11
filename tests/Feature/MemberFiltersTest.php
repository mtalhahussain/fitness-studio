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

    public function test_future_plan_keeps_current_plan_until_its_start_date(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-10 12:00:00'));
        $member = $this->makeUser('Scheduled Member', 'member');
        $this->giveMembership($member, $this->monthly, '2026-11-30');

        $this->actingAs($this->owner)->postJson('/members/'.$member->id.'/membership', [
            'plan_id' => $this->yearly->id, 'start_date' => '2026-12-01', 'amount_paid' => 0,
        ])->assertOk()->assertJsonPath('message', 'Membership scheduled successfully.');

        $this->assertSame($this->monthly->id, $member->activeMembership()->first()->plan_id);
        $this->getJson('/members?member_id='.$member->id)->assertOk()
            ->assertJsonPath('members.0.active_membership.plan_id', $this->monthly->id)
            ->assertJsonPath('members.0.upcoming_memberships.0.plan_id', $this->yearly->id);

        $this->travelTo(\Carbon\Carbon::parse('2026-12-01 00:00:00'));
        $this->assertSame($this->yearly->id, $member->activeMembership()->first()->plan_id);
        $this->assertCount(0, $member->upcomingMemberships()->get());
    }

    public function test_overlapping_future_plan_is_rejected_without_creating_an_invoice(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-10 12:00:00'));
        $member = $this->makeUser('Overlap Member', 'member');
        $this->giveMembership($member, $this->monthly, '2026-12-05');
        $invoices = Invoice::count();
        $this->actingAs($this->owner)->postJson('/members/'.$member->id.'/membership', [
            'plan_id' => $this->yearly->id, 'start_date' => '2026-12-01', 'amount_paid' => 0,
        ])->assertUnprocessable()->assertJsonValidationErrors('start_date');
        $this->assertSame($invoices, Invoice::count());
        $this->assertSame(1, $member->memberships()->count());
    }

    public function test_plan_change_is_blocked_until_active_dues_are_cleared(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-10 12:00:00'));
        $member = $this->makeUser('Due Member', 'member');
        $this->giveMembership($member, $this->monthly, '2026-12-05', null, 0); // PKR 3,000 outstanding

        // Blocked while the active membership has dues.
        $this->actingAs($this->owner)->postJson('/members/'.$member->id.'/membership', [
            'plan_id' => $this->yearly->id, 'start_date' => '2026-12-06', 'amount_paid' => 0,
        ])->assertStatus(422)->assertJsonPath('message', 'Clear pending dues of PKR 3,000 before changing the plan.');
        $this->assertSame(1, $member->memberships()->count());

        // Clear the dues, then the plan change goes through.
        $this->actingAs($this->owner)->postJson('/members/'.$member->id.'/pay-balance', [
            'amount' => 3000, 'method' => 'cash',
        ])->assertOk();

        $this->actingAs($this->owner)->postJson('/members/'.$member->id.'/membership', [
            'plan_id' => $this->yearly->id, 'start_date' => '2026-12-06', 'amount_paid' => 0,
        ])->assertOk();
        $this->assertSame(2, $member->memberships()->count());
    }

    public function test_future_only_membership_is_not_active_early(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-10 12:00:00'));
        $member = $this->makeUser('Future Member', 'member');
        $this->actingAs($this->owner)->postJson('/members/'.$member->id.'/membership', [
            'plan_id' => $this->monthly->id, 'start_date' => '2026-12-01', 'amount_paid' => 0,
        ])->assertOk();
        $this->assertNull($member->activeMembership()->first());
        $this->getJson('/members?member_id='.$member->id.'&membership=active')
            ->assertOk()->assertJsonCount(0, 'members');
    }

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

    // amountPaid defaults to the full price (settled) so tests that aren't about
    // dues don't trip the "clear pending dues first" block. Pass 0 for a due membership.
    private function giveMembership(User $member, MembershipPlan $plan, string $endDate, ?string $invoiceStatus = null, ?float $amountPaid = null): void
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
            'status' => 'active', 'amount_paid' => $amountPaid ?? $plan->price, 'invoice_id' => $invoice?->id,
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
