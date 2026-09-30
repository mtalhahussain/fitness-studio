<?php

namespace Tests\Feature\Iclock;

use App\Models\BiometricDevice;
use App\Models\Gym;
use App\Models\User;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Requests shaped exactly like a ZKTeco push-mode (ADMS) firmware sends them. */
abstract class IclockTestCase extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(LicenseService::class, fn ($m) => $m->shouldReceive('check')->andReturn(true));

        $this->gym = Gym::create(['name' => 'Test Gym', 'slug' => 'test-gym', 'email' => 'gym@test.local']);
    }

    protected function device(array $overrides = []): BiometricDevice
    {
        return BiometricDevice::create(array_merge([
            'gym_id'        => $this->gym->id,
            'serial_number' => 'CKJG123456789',
            'name'          => 'Main Entrance',
            'model'         => 'K70',
            'api_key'       => BiometricDevice::generateApiKey(),
            'is_active'     => true,
        ], $overrides));
    }

    protected function member(string $pin, ?Gym $gym = null): User
    {
        return User::create([
            'gym_id'         => ($gym ?? $this->gym)->id,
            'name'           => "Member {$pin}",
            'email'          => "member{$pin}@test.local",
            'password'       => 'irrelevant',
            'status'         => 'active',
            'biometric_code' => $pin,
        ]);
    }

    /** One ATTLOG line: PIN, time, status, verify, workcode, reserved, reserved. */
    protected function attlogLine(string $pin, string $time, int $status = 0, int $verify = 1): string
    {
        return implode("\t", [$pin, $time, $status, $verify, 0, 0, 0]);
    }

    /** Raw text/plain POST to /iclock/cdata, the way firmware uploads a table. */
    protected function upload(string $sn, string $table, string $body, array $query = []): TestResponse
    {
        $qs = http_build_query(array_merge(['SN' => $sn, 'table' => $table, 'Stamp' => '9999'], $query));

        return $this->call('POST', "/iclock/cdata?{$qs}", [], [], [], [
            'CONTENT_TYPE'    => 'text/plain',
            'HTTP_USER_AGENT' => 'iClock Proxy/1.09',
        ], $body);
    }
}
