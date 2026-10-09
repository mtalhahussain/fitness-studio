<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\Invoice;
use App\Models\User;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class POSInvoiceTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_rejects_membership_items_and_labels_existing_invoice_types(): void
    {
        $this->mock(LicenseService::class, fn ($m) => $m->shouldReceive('check')->andReturn(true));
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $gym = Gym::create(['name' => 'Gym', 'slug' => 'pos-gym', 'email' => 'gym@test.local', 'status' => 'active']);
        $owner = User::create(['gym_id' => $gym->id, 'name' => 'Owner', 'email' => 'owner@test.local', 'password' => 'secret', 'status' => 'active']);
        $owner->assignRole('owner');
        $member = User::create(['gym_id' => $gym->id, 'name' => 'Member', 'email' => 'member@test.local', 'password' => 'secret', 'status' => 'active']);
        $member->assignRole('member');
        $this->actingAs($owner)->postJson('/pos/invoices', ['user_id' => $member->id, 'items' => [['name' => 'Plan', 'quantity' => 1, 'unit_price' => 100, 'item_type' => 'plan']]])
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.item_type');
        $this->assertDatabaseCount('invoices', 0);

        foreach (['membership' => ['plan'], 'pos' => ['product', 'custom'], 'mixed' => ['plan', 'product']] as $type => $items) {
            $invoice = Invoice::create(['gym_id' => $gym->id, 'user_id' => $member->id, 'invoice_number' => 'TEST-'.$type, 'subtotal' => 100, 'total_amount' => 100, 'status' => 'unpaid']);
            foreach ($items as $item) {
                $invoice->items()->create(['item_type' => $item, 'name' => 'Item', 'quantity' => 1, 'unit_price' => 100, 'subtotal' => 100]);
            }
            $this->assertSame($type, $invoice->fresh()->invoice_type);
            $this->getJson('/pos/invoices/'.$invoice->id)->assertOk()->assertJsonPath('invoice.invoice_type', $type);
        }
        $this->getJson('/pos')->assertOk()->assertJsonCount(3, 'invoices');
        $this->get('/pos')->assertOk()->assertDontSee('Membership Plans')->assertSee('Add Products');
        $this->postJson('/pos/invoices', ['user_id' => $member->id, 'items' => [['name' => 'Water', 'quantity' => 2, 'unit_price' => 50, 'item_type' => 'custom']]])
            ->assertCreated()->assertJsonPath('invoice.invoice_type', 'pos');
    }
}
