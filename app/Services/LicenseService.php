<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * WARNING (read this before you rely on it):
 * This code ships un-obfuscated in the client's own git repo, on the client's
 * own server. A developer with shell access can simply delete this file, or
 * remove its middleware registration from bootstrap/app.php, and the check is
 * gone. This layer stops casual misuse (non-technical clients poking at
 * config/.env) — it does NOT stop a determined developer with server access.
 * The only real protection for critical business logic is to keep it off this
 * codebase entirely and serve it from your own API.
 */
class LicenseService
{
    private const SERVER_URL = 'https://license.devintek.com/api/validate';

    private const GRACE_DAYS = 3;

    private const CACHE_KEY = 'license_check_result';

    private const LAST_OK_KEY = 'license_last_ok';

    public function check(): bool
    {
        return Cache::remember(self::CACHE_KEY, now()->addHours(12), function () {
            return $this->verify();
        });
    }

    private function verify(): bool
    {
        $key = config('license.key');

        if (empty($key)) {
            return false;
        }

        $publicKeyPath = storage_path('keys/public.pem');

        if (! is_file($publicKeyPath)) {
            return false;
        }

        $publicKey = file_get_contents($publicKeyPath);

        if ($publicKey === false) {
            return false;
        }

        $domain = request()->getHost();

        try {
            $response = Http::timeout(10)->post(self::SERVER_URL, [
                'key' => $key,
                'domain' => $domain,
            ]);

            $body = $response->json();

            $payload = $body['payload'] ?? null;
            $signature = $body['signature'] ?? null;

            if (! is_array($payload) || ! is_string($signature)) {
                return false;
            }

            $decodedSignature = base64_decode($signature, true);

            if ($decodedSignature === false) {
                return false;
            }

            $verified = openssl_verify(
                json_encode($payload),
                $decodedSignature,
                $publicKey,
                OPENSSL_ALGO_SHA256
            );

            if ($verified !== 1) {
                return false;
            }

            $valid = ($payload['valid'] ?? false) === true
                && ($payload['domain'] ?? null) === $domain
                && ($payload['expires_at'] ?? 0) > now()->timestamp;

            if ($valid) {
                Cache::forever(self::LAST_OK_KEY, now()->timestamp);

                return true;
            }

            return false;
        } catch (Throwable $e) {
            Log::warning('License server unreachable: '.$e->getMessage());

            return $this->withinGracePeriod();
        }
    }

    private function withinGracePeriod(): bool
    {
        $lastOk = Cache::get(self::LAST_OK_KEY);

        if (! $lastOk) {
            return false;
        }

        return now()->timestamp - $lastOk <= self::GRACE_DAYS * 86400;
    }
}
