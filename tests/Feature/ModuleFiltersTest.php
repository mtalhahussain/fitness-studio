<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\Invoice;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Services\LicenseService;
use App\Services\PaymentDueReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Filters on POS invoices, trainers and WhatsApp reminders. */
class ModuleFiltersTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(LicenseService::class, fn ($m) => $m->shouldReceive('check')->andReturn(true));
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $this->gym   = Gym::create(['name' => 'Test Gym', 'slug' => 'test-gym', 'email' => 'gym@test.local', 'status' => 'active']);
        $this->owner = $this->makeUser('Owais Owner', 'owner');
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

    private function invoice(User $member, array $itemTypes, string $status = 'unpaid', ?string $createdAt = null, ?string $dueDate = null): Invoice
    {
        static $n = 0;
        $invoice = Invoice::create([
            'gym_id' => $this->gym->id, 'user_id' => $member->id, 'invoice_number' => 'INV-' . (++$n),
            'subtotal' => 1000, 'tax_amount' => 0, 'discount_amount' => 0, 'total_amount' => 1000,
            'status' => $status, 'due_date' => $dueDate,
        ]);
        foreach ($itemTypes as $type) {
            $invoice->items()->create(['item_type' => $type, 'name' => $type, 'unit_price' => 500, 'quantity' => 1, 'subtotal' => 500]);
        }
        if ($createdAt) {
            $invoice->forceFill(['created_at' => $createdAt])->save();
        }

        return $invoice;
    }

    private function posNumbers(array $query): array
    {
        $res = $this->actingAs($this->owner)->getJson('/pos?' . http_build_query($query))->assertOk();

        return collect($res->json('invoices'))->pluck('invoice_number')->sort()->values()->all();
    }

    public function test_pos_invoice_filters_and_pagination(): void
    {
        $ali  = $this->makeUser('Ali', 'member');
        $sara = $this->makeUser('Sara', 'member');

        $membership = $this->invoice($ali, ['plan'], 'paid', '2026-01-05 10:00:00');
        $sale       = $this->invoice($ali, ['product'], 'unpaid', '2026-02-10 10:00:00');
        $mixed      = $this->invoice($sara, ['plan', 'custom'], 'unpaid', '2026-02-20 10:00:00');

        $this->assertSame([$membership->invoice_number, $sale->invoice_number], $this->posNumbers(['member_id' => $ali->id]));
        $this->assertSame([$membership->invoice_number], $this->posNumbers(['invoice_type' => 'membership']));
        $this->assertSame([$sale->invoice_number], $this->posNumbers(['invoice_type' => 'pos']));
        $this->assertSame([$mixed->invoice_number], $this->posNumbers(['invoice_type' => 'mixed']));
        $this->assertSame([$sale->invoice_number, $mixed->invoice_number], $this->posNumbers(['date_from' => '2026-02-01', 'date_to' => '2026-02-28']));
        $this->assertSame([$sale->invoice_number], $this->posNumbers(['member_id' => $ali->id, 'status' => 'unpaid']));

        $res = $this->actingAs($this->owner)->getJson('/pos?per_page=2&page=2')->assertOk();
        $this->assertSame(['current_page' => 2, 'last_page' => 2, 'total' => 3], $res->json('pagination'));

        $this->actingAs($this->owner)->getJson('/pos?date_from=2026-03-01&date_to=2026-02-01')->assertStatus(422);
        $this->actingAs($this->owner)->get('/pos')->assertOk()->assertSee('All Members')->assertSee('Invoice Date');
    }

    public function test_trainer_filters(): void
    {
        $boxer = $this->makeUser('Bilal Boxer', 'trainer');
        $yogi  = $this->makeUser('Yasmin Yogi', 'trainer', ['status' => 'inactive']);
        $old   = $this->makeUser('Omar NoProfile', 'trainer');
        TrainerProfile::create(['user_id' => $boxer->id, 'gym_id' => $this->gym->id, 'specialization' => 'Boxing', 'compensation_mode' => 'salary', 'base_salary' => 40000]);
        TrainerProfile::create(['user_id' => $yogi->id, 'gym_id' => $this->gym->id, 'specialization' => 'Yoga', 'compensation_mode' => 'commission']);

        $names = function (array $query) {
            $res = $this->actingAs($this->owner)->getJson('/trainers?' . http_build_query($query))->assertOk();
            return collect($res->json('trainers'))->pluck('name')->sort()->values()->all();
        };

        $this->assertSame(['Bilal Boxer'], $names(['trainer_id' => $boxer->id]));
        $this->assertSame(['Yasmin Yogi'], $names(['specialization' => 'Yoga']));
        $this->assertSame(['Yasmin Yogi'], $names(['status' => 'inactive']));
        $this->assertSame(['Bilal Boxer'], $names(['compensation_mode' => 'salary']));
        $this->assertSame(['Omar NoProfile', 'Yasmin Yogi'], $names(['compensation_mode' => 'commission']));

        $this->actingAs($this->owner)->get('/trainers')->assertOk()->assertSee('All Trainers')->assertSee('All Specializations');
    }

    public function test_whatsapp_reminders_member_filter(): void
    {
        $ali  = $this->makeUser('Ali', 'member', ['phone' => '03001234567']);
        $sara = $this->makeUser('Sara', 'member', ['phone' => '03007654321']);
        $due  = now()->startOfMonth()->addDays(2)->toDateString();
        $aliInvoice = $this->invoice($ali, ['plan'], 'unpaid', null, $due);
        $this->invoice($sara, ['plan'], 'unpaid', null, $due);

        $service = app(PaymentDueReminderService::class);

        $this->assertCount(2, $service->getDueInvoicesForOwner($this->gym->id, []));
        $this->assertSame([$aliInvoice->id], $service->getDueInvoicesForOwner($this->gym->id, ['member_id' => $ali->id])->pluck('id')->all());
    }
}
