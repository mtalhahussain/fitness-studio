<?php

namespace App\Biometric\Drivers;

use App\Biometric\Contracts\BiometricDriver;
use App\Biometric\PunchLog;
use App\Biometric\WallClock;
use App\Models\BiometricDevice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Any machine or vendor cloud (Suprema BioStar 2, Anviz CrossChex, …) that POSTs JSON.
 * The owner maps fields with dot-paths in the device settings.
 */
class GenericWebhookDriver implements BiometricDriver
{
    public function key(): string          { return 'generic'; }
    public function label(): string        { return 'Other brand (Generic Webhook — Suprema, Anviz, …)'; }
    public function identifiesBy(): string { return 'token'; }

    public function acknowledge(): Response
    {
        return response()->json(['ok' => true]);
    }

    public function settingsRules(): array
    {
        return [
            'settings.records_path'   => ['nullable', 'string', 'max:100'],
            'settings.employee_field' => ['required', 'string', 'max:100'],
            'settings.time_field'     => ['required', 'string', 'max:100'],
            'settings.time_format'    => ['required', 'in:auto,unix,unix_ms,iso'],
            'settings.type_field'     => ['nullable', 'string', 'max:100'],
            'settings.type_in_value'  => ['nullable', 'string', 'max:50'],
            'settings.type_out_value' => ['nullable', 'string', 'max:50'],
            'settings.secret_header'  => ['nullable', 'string', 'max:100', 'required_with:settings.secret_value'],
            'settings.secret_value'   => ['nullable', 'string', 'max:200', 'required_with:settings.secret_header'],
            'settings.timezone'       => ['nullable', 'timezone'],
        ];
    }

    public function setupSteps(BiometricDevice $device, string $baseUrl): array
    {
        $url   = rtrim($baseUrl, '/') . '/api/biometric/hook/' . $device->webhook_token;
        $steps = [
            "In the machine's (or vendor software's) webhook / HTTP push settings, set the URL to: {$url}",
            'Method: POST, body: JSON.',
        ];

        if (! empty($device->settings['secret_header'])) {
            $steps[] = "Add header {$device->settings['secret_header']} with the secret saved on this device.";
        }

        $steps[] = 'Make one test punch, then open "Last received data" below and check the field mapping matches.';
        $steps[] = "Set each member's user/employee ID on the machine to their Biometric Code (or User ID).";

        return $steps;
    }

    public function secretMatches(Request $request, BiometricDevice $device): bool
    {
        $header = $device->settings['secret_header'] ?? null;
        if (! $header) {
            return true;
        }

        return hash_equals((string) ($device->settings['secret_value'] ?? ''), (string) $request->header($header, ''));
    }

    public function parse(Request $request, BiometricDevice $device): array
    {
        $s    = $device->settings ?? [];
        $body = json_decode($request->getContent(), true);

        if (! is_array($body)) {
            // Some vendor clouds post form-encoded fields instead of JSON.
            $fallback = $request->all();
            $body     = ! empty($fallback) ? $fallback : null;
        }

        if (! is_array($body)) {
            return [];
        }

        $records = empty($s['records_path']) ? $body : data_get($body, $s['records_path']);
        if (! is_array($records)) {
            return [];
        }
        if (! array_is_list($records)) {
            $records = [$records]; // single object
        }

        $logs = [];
        foreach ($records as $rec) {
            $employee = data_get($rec, $s['employee_field'] ?? '');
            $rawTime  = data_get($rec, $s['time_field'] ?? '');

            if (! is_scalar($employee) || ! is_scalar($rawTime)) {
                continue;
            }
            if ($employee === '' || $rawTime === '') {
                continue;
            }

            $time = $this->toTime($rawTime, $s['time_format'] ?? 'auto', $device->timezone());
            if (! $time) {
                continue;
            }

            $logs[] = new PunchLog((string) $employee, $time, $this->toType($rec, $s));
        }

        return $logs;
    }

    private function toTime(mixed $raw, string $format, string $tz): ?Carbon
    {
        try {
            return match (true) {
                $format === 'unix'                     => WallClock::fromUnix((float) $raw, $tz),
                $format === 'unix_ms'                  => WallClock::fromUnix(((float) $raw) / 1000, $tz),
                $format === 'auto' && is_numeric($raw) => WallClock::fromUnix(strlen((string) (int) $raw) > 10 ? ((float) $raw) / 1000 : (float) $raw, $tz),
                default                                => WallClock::parse((string) $raw, $tz),
            };
        } catch (\Throwable) {
            return null;
        }
    }

    private function toType(mixed $rec, array $s): ?string
    {
        if (empty($s['type_field'])) {
            return null;
        }

        $value = (string) data_get($rec, $s['type_field'], '');

        return match (true) {
            isset($s['type_in_value'])  && $value === (string) $s['type_in_value']  => PunchLog::IN,
            isset($s['type_out_value']) && $value === (string) $s['type_out_value'] => PunchLog::OUT,
            default => null,
        };
    }
}
