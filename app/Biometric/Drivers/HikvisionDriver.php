<?php

namespace App\Biometric\Drivers;

use App\Biometric\Contracts\BiometricDriver;
use App\Biometric\PunchLog;
use App\Biometric\WallClock;
use App\Models\BiometricDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hikvision access-control terminals (DS-K1T…) using "HTTP Listening" event push.
 * Body is JSON, either raw or in a multipart part named event_log / AccessControllerEvent
 * (face snapshots come as extra file parts and are ignored).
 */
class HikvisionDriver implements BiometricDriver
{
    private const MAJOR_EVENT_ACCESS = 5;

    public function key(): string          { return 'hikvision'; }
    public function label(): string        { return 'Hikvision'; }
    public function identifiesBy(): string { return 'token'; }

    // Always 200 — otherwise the terminal re-sends the same event in a loop.
    public function acknowledge(): Response
    {
        return response('OK', 200);
    }

    public function settingsRules(): array
    {
        return ['settings.timezone' => ['nullable', 'timezone']];
    }

    public function setupSteps(BiometricDevice $device, string $baseUrl): array
    {
        $url  = rtrim($baseUrl, '/') . '/api/biometric/hook/' . $device->webhook_token;
        $host = parse_url($baseUrl, PHP_URL_HOST) ?: $baseUrl;
        $port = parse_url($baseUrl, PHP_URL_PORT) ?: (str_starts_with($baseUrl, 'https') ? 443 : 80);
        $path = '/api/biometric/hook/' . $device->webhook_token;

        return [
            "Open the terminal's web page (or iVMS-4200) → Configuration → Network → Advanced Settings → HTTP Listening.",
            "Destination IP / domain: {$host}   Port: {$port}",
            "URL: {$path}",
            "(Full URL, if the screen asks for one: {$url})",
            'Protocol: HTTP (use HTTPS only if the firmware supports it). Save.',
            'Under Event / Linkage, make sure Access Control events are sent to the listening host.',
            'Set each member\'s Employee No. on the terminal to their Biometric Code (or User ID).',
        ];
    }

    public function parse(Request $request, BiometricDevice $device): array
    {
        $event = $this->extractEvent($request);
        if (! $event) {
            return [];
        }

        $ace = $event['AccessControllerEvent'] ?? [];
        if ((int) ($ace['majorEventType'] ?? 0) !== self::MAJOR_EVENT_ACCESS) {
            return [];
        }

        $employee = $ace['employeeNoString'] ?? $ace['employeeNo'] ?? null;
        $time     = $event['dateTime'] ?? null;
        if ($employee === null || $employee === '' || ! $time) {
            return [];
        }

        $type = match ($ace['attendanceStatus'] ?? null) {
            'checkIn'  => PunchLog::IN,
            'checkOut' => PunchLog::OUT,
            default    => null,
        };

        try {
            $wallClock = WallClock::parse((string) $time, $device->timezone());
        } catch (\Throwable $e) {
            Log::warning('Hikvision: unparseable event time skipped', [
                'device_id' => $device->id,
                'time'      => $time,
                'error'     => $e->getMessage(),
            ]);

            return [];
        }

        return [new PunchLog((string) $employee, $wallClock, $type)];
    }

    private function extractEvent(Request $request): ?array
    {
        foreach (['event_log', 'AccessControllerEvent'] as $part) {
            $value = $request->input($part);
            if (is_string($value) && ($decoded = json_decode($value, true)) && is_array($decoded)) {
                return $decoded;
            }
        }

        $decoded = json_decode($request->getContent(), true);

        return is_array($decoded) ? $decoded : null;
    }
}
