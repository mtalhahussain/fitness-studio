<?php

namespace Tests\Feature;

use App\Biometric\Drivers\GenericWebhookDriver;
use App\Biometric\PunchLog;
use App\Models\BiometricDevice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Tests\TestCase;

class GenericWebhookDriverTest extends TestCase
{
    private function device(array $settings): BiometricDevice
    {
        return new BiometricDevice(['brand' => 'generic', 'settings' => $settings + ['timezone' => 'Asia/Karachi']]);
    }

    private function req(array $body): Request
    {
        return Request::create('/x', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($body));
    }

    public function test_nested_records_path_and_iso_time(): void
    {
        $device = $this->device(['records_path' => 'data.events', 'employee_field' => 'user.id', 'time_field' => 'at', 'time_format' => 'iso']);
        $body   = ['data' => ['events' => [
            ['user' => ['id' => 'A1'], 'at' => '2026-09-28T04:00:00Z'],
            ['user' => ['id' => 'A2'], 'at' => '2026-09-28T05:00:00Z'],
        ]]];

        $logs = app(GenericWebhookDriver::class)->parse($this->req($body), $device);

        $this->assertCount(2, $logs);
        $this->assertSame('A1', $logs[0]->employeeId);
        $this->assertSame('2026-09-28 09:00:00', $logs[0]->time->format('Y-m-d H:i:s'));
    }

    public function test_single_object_body_with_unix_time_and_in_out_mapping(): void
    {
        $device = $this->device([
            'employee_field' => 'emp', 'time_field' => 'ts', 'time_format' => 'unix',
            'type_field' => 'dir', 'type_in_value' => 'I', 'type_out_value' => 'O',
        ]);
        $ts = Carbon::parse('2026-09-28T04:00:00Z')->timestamp;

        $logs = app(GenericWebhookDriver::class)->parse($this->req(['emp' => 9, 'ts' => $ts, 'dir' => 'O']), $device);

        $this->assertCount(1, $logs);
        $this->assertSame('9', $logs[0]->employeeId);
        $this->assertSame('2026-09-28 09:00:00', $logs[0]->time->format('Y-m-d H:i:s'));
        $this->assertSame(PunchLog::OUT, $logs[0]->type);

        $inLogs = app(GenericWebhookDriver::class)->parse($this->req(['emp' => 9, 'ts' => $ts, 'dir' => 'I']), $device);
        $this->assertSame(PunchLog::IN, $inLogs[0]->type);
    }

    public function test_unix_ms_and_auto_formats(): void
    {
        $ms = Carbon::parse('2026-09-28T04:00:00Z')->getTimestampMs();
        $driver = app(GenericWebhookDriver::class);

        $a = $driver->parse($this->req(['e' => 1, 't' => $ms]), $this->device(['employee_field' => 'e', 'time_field' => 't', 'time_format' => 'unix_ms']));
        $b = $driver->parse($this->req(['e' => 1, 't' => '2026-09-28 09:00:00']), $this->device(['employee_field' => 'e', 'time_field' => 't', 'time_format' => 'auto']));
        $c = $driver->parse($this->req(['e' => 1, 't' => (string) intdiv($ms, 1000)]), $this->device(['employee_field' => 'e', 'time_field' => 't', 'time_format' => 'auto']));

        foreach ([$a, $b, $c] as $logs) {
            $this->assertSame('2026-09-28 09:00:00', $logs[0]->time->format('Y-m-d H:i:s'));
        }
    }

    public function test_wrong_mapping_yields_nothing(): void
    {
        $device = $this->device(['employee_field' => 'nope', 'time_field' => 'at', 'time_format' => 'iso']);

        $this->assertSame([], app(GenericWebhookDriver::class)->parse($this->req(['emp' => 1, 'at' => '2026-09-28 09:00:00']), $device));
    }

    public function test_unparseable_time_skips_record(): void
    {
        $device = $this->device(['employee_field' => 'e', 'time_field' => 't', 'time_format' => 'iso']);

        $this->assertSame([], app(GenericWebhookDriver::class)->parse($this->req(['e' => 1, 't' => 'not a date']), $device));
    }

    public function test_secret_check(): void
    {
        $driver = app(GenericWebhookDriver::class);
        $device = $this->device(['employee_field' => 'e', 'time_field' => 't', 'time_format' => 'auto', 'secret_header' => 'X-Secret', 'secret_value' => 's3cret']);

        $good = $this->req([]); $good->headers->set('X-Secret', 's3cret');
        $bad  = $this->req([]); $bad->headers->set('X-Secret', 'wrong');

        $this->assertTrue($driver->secretMatches($good, $device));
        $this->assertFalse($driver->secretMatches($bad, $device));
        $this->assertFalse($driver->secretMatches($this->req([]), $device)); // no header sent at all
        $this->assertTrue($driver->secretMatches($this->req([]), $this->device(['employee_field' => 'e', 'time_field' => 't', 'time_format' => 'auto'])));
    }

    public function test_secret_fails_closed_when_header_configured_but_no_value_saved(): void
    {
        $driver = app(GenericWebhookDriver::class);
        $device = $this->device(['employee_field' => 'e', 'time_field' => 't', 'time_format' => 'auto', 'secret_header' => 'X-Secret']);

        $request = $this->req([]);
        $request->headers->set('X-Secret', ''); // even an empty header must not match

        $this->assertFalse($driver->secretMatches($request, $device));
        $this->assertFalse($driver->secretMatches($this->req([]), $device));
    }

    public function test_non_scalar_employee_or_time_is_skipped(): void
    {
        $device = $this->device(['employee_field' => 'user.id', 'time_field' => 'at', 'time_format' => 'iso']);
        $body   = ['user' => ['id' => ['nested' => 'array']], 'at' => '2026-09-28T04:00:00Z'];

        $this->assertSame([], app(GenericWebhookDriver::class)->parse($this->req($body), $device));
    }

    public function test_employee_zero_is_kept_as_valid_id(): void
    {
        $device = $this->device(['employee_field' => 'e', 'time_field' => 't', 'time_format' => 'iso']);

        $logs = app(GenericWebhookDriver::class)->parse($this->req(['e' => 0, 't' => '2026-09-28T04:00:00Z']), $device);

        $this->assertCount(1, $logs);
        $this->assertSame('0', $logs[0]->employeeId);
    }

    public function test_form_encoded_body_is_accepted(): void
    {
        $device  = $this->device(['employee_field' => 'emp', 'time_field' => 'at', 'time_format' => 'iso']);
        $request = Request::create('/x', 'POST', ['emp' => '5', 'at' => '2026-09-28 09:00:00']);

        $logs = app(GenericWebhookDriver::class)->parse($request, $device);

        $this->assertCount(1, $logs);
        $this->assertSame('5', $logs[0]->employeeId);
    }

    public function test_setup_steps_mention_secret_header_and_webhook_url(): void
    {
        $device = $this->device(['employee_field' => 'e', 'time_field' => 't', 'time_format' => 'auto', 'secret_header' => 'X-Secret', 'secret_value' => 's3cret']);
        $device->forceFill(['webhook_token' => 'TOK']);

        $steps = app(GenericWebhookDriver::class)->setupSteps($device, 'https://example.com');

        $this->assertTrue(collect($steps)->contains(fn ($s) => str_contains($s, 'X-Secret')));
        $this->assertTrue(collect($steps)->contains(fn ($s) => str_contains($s, '/api/biometric/hook/TOK')));
    }

    public function test_type_field_as_array_still_parses_punch_with_null_type(): void
    {
        $device = $this->device([
            'employee_field' => 'e', 'time_field' => 't', 'time_format' => 'iso',
            'type_field' => 'meta', 'type_in_value' => 'I',
        ]);
        $body = ['e' => 1, 't' => '2026-09-28T04:00:00Z', 'meta' => ['nested' => true]];

        $logs = app(GenericWebhookDriver::class)->parse($this->req($body), $device);

        $this->assertCount(1, $logs);
        $this->assertNull($logs[0]->type);
    }

    public function test_missing_type_field_with_empty_in_value_does_not_match(): void
    {
        $device = $this->device([
            'employee_field' => 'e', 'time_field' => 't', 'time_format' => 'iso',
            'type_field' => 'dir', 'type_in_value' => '',
        ]);
        $body = ['e' => 1, 't' => '2026-09-28T04:00:00Z']; // no 'dir' key at all

        $logs = app(GenericWebhookDriver::class)->parse($this->req($body), $device);

        $this->assertCount(1, $logs);
        $this->assertNull($logs[0]->type);
    }

    public function test_unix_format_with_non_numeric_raw_is_skipped(): void
    {
        $device = $this->device(['employee_field' => 'e', 'time_field' => 't', 'time_format' => 'unix']);

        $this->assertSame([], app(GenericWebhookDriver::class)->parse($this->req(['e' => 1, 't' => 'abc']), $device));
    }

    public function test_unix_ms_format_with_non_numeric_raw_is_skipped(): void
    {
        $device = $this->device(['employee_field' => 'e', 'time_field' => 't', 'time_format' => 'unix_ms']);

        $this->assertSame([], app(GenericWebhookDriver::class)->parse($this->req(['e' => 1, 't' => 'abc']), $device));
    }

    public function test_auto_format_detects_fourteen_digit_date_string(): void
    {
        $device = $this->device(['employee_field' => 'e', 'time_field' => 't', 'time_format' => 'auto']);

        $logs = app(GenericWebhookDriver::class)->parse($this->req(['e' => 1, 't' => '20260928090000']), $device);

        $this->assertCount(1, $logs);
        $this->assertSame('2026-09-28 09:00:00', $logs[0]->time->format('Y-m-d H:i:s'));
    }

    public function test_auto_format_detects_millisecond_timestamps(): void
    {
        $ms     = Carbon::parse('2026-09-28T04:00:00Z')->getTimestampMs();
        $device = $this->device(['employee_field' => 'e', 'time_field' => 't', 'time_format' => 'auto']);

        $logs = app(GenericWebhookDriver::class)->parse($this->req(['e' => 1, 't' => $ms]), $device);

        $this->assertCount(1, $logs);
        $this->assertSame('2026-09-28 09:00:00', $logs[0]->time->format('Y-m-d H:i:s'));
    }

    public function test_auto_format_with_zero_is_skipped_by_sanity_floor(): void
    {
        $device = $this->device(['employee_field' => 'e', 'time_field' => 't', 'time_format' => 'auto']);

        $this->assertSame([], app(GenericWebhookDriver::class)->parse($this->req(['e' => 1, 't' => 0]), $device));
    }

    public function test_boolean_and_whitespace_employee_is_skipped(): void
    {
        $device = $this->device(['employee_field' => 'e', 'time_field' => 't', 'time_format' => 'iso']);

        foreach ([true, false, '  '] as $bad) {
            $logs = app(GenericWebhookDriver::class)->parse($this->req(['e' => $bad, 't' => '2026-09-28T04:00:00Z']), $device);
            $this->assertSame([], $logs, 'employee value ' . var_export($bad, true) . ' should be skipped');
        }
    }

    public function test_query_string_params_are_not_used_as_form_fallback(): void
    {
        $device  = $this->device(['employee_field' => 'e', 'time_field' => 't', 'time_format' => 'iso']);
        $request = Request::create('/x?e=7&t=2026-09-28%2009:00:00', 'POST', [], [], [], [], 'garbage');

        $this->assertSame([], app(GenericWebhookDriver::class)->parse($request, $device));
    }
}
