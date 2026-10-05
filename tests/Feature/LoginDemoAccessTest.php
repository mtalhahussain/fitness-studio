<?php

namespace Tests\Feature;

use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginDemoAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(LicenseService::class, fn ($m) => $m->shouldReceive('check')->andReturn(true));
        config(['app.demo_access_key' => 'secret-demo-key']);
    }

    public function test_demo_credentials_hidden_by_default(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertDontSee('owner@demogym.com');
    }

    public function test_demo_credentials_hidden_with_wrong_key(): void
    {
        $this->get('/login?demo=wrong')
            ->assertOk()
            ->assertDontSee('owner@demogym.com');
    }

    public function test_demo_credentials_shown_with_correct_key(): void
    {
        $this->get('/login?demo=secret-demo-key')
            ->assertOk()
            ->assertSee('owner@demogym.com');
    }

    public function test_demo_access_persists_in_session_after_redirect_back(): void
    {
        $this->get('/login?demo=secret-demo-key');

        $this->get('/login')->assertSee('owner@demogym.com');
    }

    public function test_demo_credentials_never_shown_when_key_not_configured(): void
    {
        config(['app.demo_access_key' => null]);

        $this->get('/login?demo=')
            ->assertOk()
            ->assertDontSee('owner@demogym.com');
    }
}
