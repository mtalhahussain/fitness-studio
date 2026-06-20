<?php

namespace App\Services;

use App\Models\Gym;

class GymDomainResolver
{
    public function resolveByHost(?string $host): ?Gym
    {
        $host = strtolower(trim((string) $host));

        if ($host === '' || $this->isCentralHost($host)) {
            return null;
        }

        $gym = Gym::query()->where('domain', $host)->first();
        if ($gym) {
            return $gym;
        }

        $baseDomain = (string) config('tenancy.base_domain', '');
        if ($baseDomain === '' || ! str_ends_with($host, '.' . $baseDomain)) {
            return null;
        }

        $subdomain = substr($host, 0, -strlen('.' . $baseDomain));

        if ($subdomain === '' || str_contains($subdomain, '.')) {
            return null;
        }

        return Gym::query()->where('subdomain', $subdomain)->first();
    }

    public function isCentralHost(string $host): bool
    {
        $centralDomains = (array) config('tenancy.central_domains', []);

        return in_array(strtolower($host), $centralDomains, true);
    }
}
