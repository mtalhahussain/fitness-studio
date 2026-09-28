<?php

namespace App\Biometric\Drivers;

use App\Biometric\Contracts\BiometricDriver;
use App\Biometric\PunchLog;
use App\Biometric\WallClock;
use App\Models\BiometricDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/** ZKTeco ADMS / iClock push. Also eSSL and ZK-based Realtime machines. */
class ZKTecoDriver implements BiometricDriver
{
    public function key(): string        { return 'zkteco'; }
    public function label(): string      { return 'ZKTeco / eSSL / Realtime'; }
    public function identifiesBy(): string { return 'serial'; }

    public function acknowledge(): Response
    {
        return response('OK', 200);
    }

    public function settingsRules(): array
    {
        return [];
    }

    public function setupSteps(BiometricDevice $device, string $baseUrl): array
    {
        $host = parse_url($baseUrl, PHP_URL_HOST) ?: $baseUrl;
        $port = parse_url($baseUrl, PHP_URL_PORT) ?: (str_starts_with($baseUrl, 'https') ? 443 : 80);

        $portLine = $port === 443
            ? "Server port: {$port} (if the machine won't connect over HTTPS, try port 80 / HTTP)."
            : "Server port: {$port}";

        return [
            'On the machine open COMM → Cloud Server Setting (ADMS).',
            "Server address: {$host}",
            $portLine,
            'Set the URL path to exactly /api/biometric/push (do not leave it empty).',
            "The machine's serial number must be exactly: " . ($device->serial_number ?: '—'),
            "Enroll each member on the machine with their Biometric Code (or User ID) as the Employee Number.",
        ];
    }

    public function parse(Request $request, BiometricDevice $device): array
    {
        $contentType = $request->header('Content-Type', '');
        $raw         = $request->getContent();

        if (str_contains($contentType, 'json') || str_starts_with(trim($raw), '{') || str_starts_with(trim($raw), '[')) {
            $rows = $this->parseJson($request);
        } elseif (str_contains($contentType, 'xml') || str_starts_with(trim($raw), '<')) {
            $rows = $this->parseXml($raw);
        } elseif ($request->has('table') || $request->has('Stamp')) {
            $rows = $this->parseFormPost($request);
        } else {
            $rows = [];
        }

        $tz = $device->timezone();

        $logs = [];

        foreach ($rows as $r) {
            try {
                $logs[] = new PunchLog((string) $r['employee_id'], WallClock::parse((string) $r['time'], $tz));
            } catch (\Throwable $e) {
                Log::warning('ZKTeco: unparseable punch time skipped', [
                    'employee_id' => $r['employee_id'] ?? null,
                    'time'        => $r['time'] ?? null,
                    'device_id'   => $device->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        return $logs;
    }

    /**
     * JSON format — PUSH SDK v3 (G3, SpeedFace series etc.)
     * { "records": [{ "employee_id": "5", "time": "2026-05-12 09:00:00", "type": 0 }] }
     */
    private function parseJson(Request $request): array
    {
        $logs = [];

        $records = $request->input('records')
            ?? $request->input('attendance_log')
            ?? (is_array($request->all()) ? $request->all() : []);

        foreach ((array) $records as $rec) {
            $logs[] = [
                'employee_id' => $rec['employee_id'] ?? $rec['EnrollNumber'] ?? $rec['user_id'] ?? null,
                'time'        => $rec['time'] ?? $rec['LogTime'] ?? $rec['timestamp'] ?? null,
            ];
        }

        return array_filter($logs, fn ($l) => $l['employee_id'] && $l['time']);
    }

    /**
     * XML format — iClock protocol (F18, K40, MA300, UA860 etc.)
     * <Log><row pin="5" time="2026-05-12 09:00:00" status="0" /></Log>
     */
    private function parseXml(string $raw): array
    {
        $logs = [];

        try {
            $xml = simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NOERROR);
            if (! $xml) return [];

            $rows = $xml->row ?? $xml->record ?? $xml->Log->row ?? [];

            foreach ($rows as $row) {
                $attrs = (array) $row->attributes();
                $attr  = $attrs['@attributes'] ?? [];

                $logs[] = [
                    'employee_id' => $attr['pin'] ?? $attr['uid'] ?? $attr['EnrollNumber'] ?? null,
                    'time'        => $attr['time'] ?? $attr['LogTime'] ?? null,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('ZKTeco XML parse error: ' . $e->getMessage());
        }

        return array_filter($logs, fn ($l) => $l['employee_id'] && $l['time']);
    }

    /**
     * Form-POST format — iClock legacy (table=ATTLOG&Stamp=...)
     * "5\t2026-05-12 09:00:00\t0\t1\t\t0\n..."
     */
    private function parseFormPost(Request $request): array
    {
        $logs  = [];
        $stamp = $request->input('Stamp', '');

        foreach (explode("\n", trim($stamp)) as $line) {
            $line = trim($line);
            if (! $line) continue;

            $parts = preg_split('/\t+/', $line);
            if (count($parts) < 2) continue;

            $logs[] = [
                'employee_id' => $parts[0] ?? null,
                'time'        => $parts[1] ?? null,
            ];
        }

        return array_filter($logs, fn ($l) => $l['employee_id'] && $l['time']);
    }
}
