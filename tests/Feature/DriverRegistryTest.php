<?php

namespace Tests\Feature;

use App\Biometric\Contracts\BiometricDriver;
use App\Biometric\DriverRegistry;
use Tests\TestCase;

class DriverRegistryTest extends TestCase
{
    public function test_lists_configured_brands_with_labels(): void
    {
        $options = app(DriverRegistry::class)->options();

        $this->assertSame(['zkteco', 'hikvision', 'generic'], array_keys($options));
        $this->assertSame('ZKTeco / eSSL / Realtime', $options['zkteco']);
    }

    public function test_resolves_driver_instances(): void
    {
        $driver = app(DriverRegistry::class)->get('zkteco');

        $this->assertInstanceOf(BiometricDriver::class, $driver);
        $this->assertSame('zkteco', $driver->key());
    }

    public function test_unknown_brand_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(DriverRegistry::class)->get('nope');
    }
}
