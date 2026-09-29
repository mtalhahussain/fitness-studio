<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Remembers machines that try to connect with a serial number nobody has
 * registered, so the admin can spot a typo'd SN or a machine not added yet.
 * Kept in cache (not DB) — it's diagnostic, short-lived data.
 */
class UnknownBiometricDevices
{
    private const CACHE_KEY = 'biometric_unknown_devices';
    private const KEEP_DAYS = 7;
    private const MAX_ENTRIES = 20;

    public function record(string $serialNumber, ?string $ip, string $endpoint): void
    {
        $serialNumber = mb_substr($serialNumber, 0, 100);

        // Log once per SN per 10 minutes so heartbeats don't flood the log.
        if (Cache::add("biometric_unknown_logged:{$serialNumber}", true, now()->addMinutes(10))) {
            Log::warning('Biometric device with unregistered serial number tried to connect', [
                'serial_number' => $serialNumber,
                'ip'            => $ip,
                'endpoint'      => $endpoint,
            ]);
        }

        $all   = Cache::get(self::CACHE_KEY, []);
        $entry = $all[$serialNumber] ?? ['serial_number' => $serialNumber, 'first_seen_at' => now()->toIso8601String(), 'hits' => 0];

        $entry['last_seen_at'] = now()->toIso8601String();
        $entry['ip']           = $ip;
        $entry['hits']++;

        $all[$serialNumber] = $entry;
        uasort($all, fn ($a, $b) => strcmp($b['last_seen_at'], $a['last_seen_at']));

        Cache::put(self::CACHE_KEY, array_slice($all, 0, self::MAX_ENTRIES, true), now()->addDays(self::KEEP_DAYS));
    }

    /** Most recent first. */
    public function recent(): array
    {
        return array_values(Cache::get(self::CACHE_KEY, []));
    }

    /** Call once a serial number gets registered. */
    public function forget(string $serialNumber): void
    {
        $all = Cache::get(self::CACHE_KEY, []);
        unset($all[$serialNumber]);
        Cache::put(self::CACHE_KEY, $all, now()->addDays(self::KEEP_DAYS));
    }
}
