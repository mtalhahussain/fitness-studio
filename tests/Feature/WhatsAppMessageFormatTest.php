<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\User;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Reminder message format is configured by the admin per gym, not by the owner. */
class WhatsAppMessageFormatTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(LicenseService::class, fn ($m) => $m->shouldReceive('check')->andReturn(true));
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $this->gym = Gym::create(['name' => 'Test Gym', 'slug' => 'test-gym', 'email' => 'gym@test.local', 'status' => 'active']);
    }

    private function makeUser(string $role, ?int $gymId): User
    {
        $user = User::create([
            'gym_id' => $gymId, 'name' => ucfirst($role), 'email' => "{$role}@test.local",
            'password' => 'secret', 'status' => 'active',
        ]);
        $user->assignRole($role);

        return $user;
    }

    public function test_admin_sets_message_format_in_gym_whatsapp_settings(): void
    {
        $admin = $this->makeUser('admin', null);

        $this->actingAs($admin)->postJson("/gyms/{$this->gym->id}/whatsapp", [
            'whatsapp_enabled'          => false,
            'whatsapp_message_template' => 'Salam {name}, invoice {invoice_number} due hai.',
        ])->assertOk();

        $this->assertSame('Salam {name}, invoice {invoice_number} due hai.', $this->gym->fresh()->whatsapp_message_template);

        $this->actingAs($admin)->get("/gyms/{$this->gym->id}/whatsapp")
            ->assertOk()->assertSee('Reminder Message Format');
    }

    public function test_owner_can_no_longer_change_message_format(): void
    {
        $owner = $this->makeUser('owner', $this->gym->id);

        $this->actingAs($owner)->postJson('/whatsapp-reminders/template', ['whatsapp_message_template' => 'x'])
            ->assertNotFound();
        $this->assertNull($this->gym->fresh()->whatsapp_message_template);
    }
}
