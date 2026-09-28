<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\User;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;
    private User $owner;
    private User $trainer;
    private User $otherTrainer;
    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(LicenseService::class, fn ($m) => $m->shouldReceive('check')->andReturn(true));
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $this->gym = Gym::create(['name' => 'Test Gym', 'slug' => 'test-gym', 'email' => 'gym@test.local', 'status' => 'active']);

        $this->owner        = $this->makeUser('owner');
        $this->trainer      = $this->makeUser('trainer');
        $this->otherTrainer = $this->makeUser('trainer');
        $this->member       = $this->makeUser('member');
    }

    private function makeUser(string $role): User
    {
        static $n = 0;
        $n++;
        $user = User::create([
            'gym_id' => $this->gym->id, 'name' => ucfirst($role) . " {$n}",
            'email' => "{$role}{$n}@test.local", 'password' => 'secret', 'status' => 'active',
        ]);
        $user->assignRole($role);

        return $user;
    }

    /** Owner/admin-only web routes. Placeholder {trainer} is replaced with a real trainer id. */
    public static function managementWebRoutes(): array
    {
        return [
            'plans'            => ['GET',  '/plans'],
            'create plan'      => ['POST', '/plans'],
            'members'          => ['GET',  '/members'],
            'create member'    => ['POST', '/members'],
            'check-in'         => ['POST', '/attendance/check-in'],
            'trainers'         => ['GET',  '/trainers'],
            'create trainer'   => ['POST', '/trainers'],
            'delete trainer'   => ['DELETE', '/trainers/{trainer}'],
            'trainer schedule' => ['GET',  '/trainers/{trainer}/schedule'],
            'trainer session'  => ['POST', '/trainers/{trainer}/sessions'],
            'biometric'        => ['GET',  '/biometric/devices'],
            'pos'              => ['GET',  '/pos'],
            'pos invoice'      => ['POST', '/pos/invoices'],
            'reports'          => ['GET',  '/reports'],
            'commission report'=> ['GET',  '/reports/commissions'],
            'whatsapp'         => ['GET',  '/whatsapp-reminders'],
            'gyms (admin)'     => ['GET',  '/gyms'],
        ];
    }

    #[DataProvider('managementWebRoutes')]
    public function test_trainer_and_member_are_blocked_from_management_web_routes(string $method, string $uri): void
    {
        $uri = str_replace('{trainer}', (string) $this->otherTrainer->id, $uri);

        foreach ([$this->trainer, $this->member] as $user) {
            $this->actingAs($user)->json($method, $uri)->assertForbidden();
        }
    }

    public function test_owner_can_open_management_pages(): void
    {
        // Some owner pages use MySQL-only SQL (MONTH, CURDATE) that SQLite can't run,
        // so only assert that the role gate lets the owner through.
        foreach (['/plans', '/members', '/trainers', '/reports', '/pos', '/biometric/devices'] as $uri) {
            $this->assertNotSame(403, $this->actingAs($this->owner)->get($uri)->status(), $uri);
        }
    }

    public function test_owner_cannot_open_admin_or_trainer_only_pages(): void
    {
        $this->actingAs($this->owner)->get('/gyms')->assertForbidden();
        $this->actingAs($this->owner)->get('/my/members')->assertForbidden();
    }

    public function test_trainer_sees_only_own_commission(): void
    {
        $this->actingAs($this->trainer)->get("/trainers/{$this->trainer->id}/commission")->assertOk();
        $this->actingAs($this->trainer)->get("/trainers/{$this->otherTrainer->id}/commission")->assertForbidden();
        $this->actingAs($this->trainer)->getJson("/trainers/{$this->otherTrainer->id}/earnings")->assertForbidden();
    }

    public function test_member_cannot_see_any_commission_or_trainer_workspace(): void
    {
        $this->actingAs($this->member)->get("/trainers/{$this->trainer->id}/commission")->assertForbidden();
        $this->actingAs($this->member)->get('/my/sessions')->assertForbidden();
    }

    public function test_every_role_can_open_dashboard_and_own_attendance(): void
    {
        foreach ([$this->trainer, $this->member] as $user) {
            $this->actingAs($user)->get('/dashboard')->assertOk();
            $this->actingAs($user)->get('/attendance')->assertOk();
        }
        // Owner dashboard/attendance use MySQL-only SQL — just check the gate.
        $this->assertNotSame(403, $this->actingAs($this->owner)->get('/dashboard')->status());
        $this->assertNotSame(403, $this->actingAs($this->owner)->get('/attendance')->status());
    }

    // ── API ───────────────────────────────────────────────────────────────────

    public static function managementApiRoutes(): array
    {
        return [
            'dashboard'        => ['GET',    '/api/dashboard'],
            'create plan'      => ['POST',   '/api/membership-plans'],
            'members'          => ['GET',    '/api/members'],
            'trainers'         => ['GET',    '/api/trainers'],
            'delete trainer'   => ['DELETE', '/api/trainers/{trainer}'],
            'assign member'    => ['POST',   '/api/trainers/{trainer}/assign-member'],
            'upcoming (gym)'   => ['GET',    '/api/sessions/upcoming'],
            'reports'          => ['GET',    '/api/reports/revenue'],
            'pos products'     => ['GET',    '/api/pos/products'],
            'pos invoices'     => ['GET',    '/api/pos/invoices'],
        ];
    }

    #[DataProvider('managementApiRoutes')]
    public function test_trainer_and_member_are_blocked_from_management_api(string $method, string $uri): void
    {
        $uri = str_replace('{trainer}', (string) $this->otherTrainer->id, $uri);

        foreach ([$this->trainer, $this->member] as $user) {
            Sanctum::actingAs($user);
            $this->json($method, $uri)->assertForbidden();
        }
    }

    public function test_trainer_api_is_limited_to_own_schedule(): void
    {
        Sanctum::actingAs($this->trainer);
        $this->getJson("/api/trainers/{$this->trainer->id}/schedule")->assertOk();
        $this->getJson("/api/trainers/{$this->otherTrainer->id}/schedule")->assertForbidden();
        $this->getJson("/api/trainers/{$this->otherTrainer->id}/members")->assertForbidden();

        Sanctum::actingAs($this->member);
        $this->getJson("/api/trainers/{$this->trainer->id}/schedule")->assertForbidden();
    }

    public function test_member_can_list_plans_via_api(): void
    {
        Sanctum::actingAs($this->member);
        $this->getJson('/api/membership-plans')->assertOk();
    }

    public function test_trainer_cannot_check_in_someone_else_via_api(): void
    {
        Sanctum::actingAs($this->trainer);
        $this->postJson('/api/attendance/check-in', ['user_id' => $this->member->id])->assertCreated();

        $this->assertDatabaseHas('attendances', ['user_id' => $this->trainer->id]);
        $this->assertDatabaseMissing('attendances', ['user_id' => $this->member->id]);
    }
}
