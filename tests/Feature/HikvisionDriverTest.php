<?php

namespace Tests\Feature;

use App\Biometric\Drivers\HikvisionDriver;
use App\Biometric\PunchLog;
use App\Models\BiometricDevice;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class HikvisionDriverTest extends TestCase
{
    private function device(): BiometricDevice
    {
        return new BiometricDevice(['brand' => 'hikvision', 'settings' => ['timezone' => 'Asia/Karachi']]);
    }

    private function event(array $overrides = []): array
    {
        return array_replace_recursive([
            'ipAddress' => '192.168.1.64',
            'dateTime'  => '2026-09-28T09:00:00+05:00',
            'eventType' => 'AccessControllerEvent',
            'AccessControllerEvent' => [
                'majorEventType'   => 5,
                'subEventType'     => 75,
                'employeeNoString' => '42',
                'attendanceStatus' => 'checkIn',
            ],
        ], $overrides);
    }

    private function jsonRequest(array $body): Request
    {
        return Request::create('/x', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($body));
    }

    public function test_parses_raw_json_access_event(): void
    {
        $logs = app(HikvisionDriver::class)->parse($this->jsonRequest($this->event()), $this->device());

        $this->assertCount(1, $logs);
        $this->assertSame('42', $logs[0]->employeeId);
        $this->assertSame('2026-09-28 09:00:00', $logs[0]->time->format('Y-m-d H:i:s'));
        $this->assertSame(PunchLog::IN, $logs[0]->type);
    }

    public function test_parses_multipart_event_log_and_ignores_images(): void
    {
        $request = Request::create('/x', 'POST', ['event_log' => json_encode($this->event([
            'AccessControllerEvent' => ['attendanceStatus' => 'checkOut'],
        ]))], [], ['Picture' => UploadedFile::fake()->image('face.jpg')]);

        $logs = app(HikvisionDriver::class)->parse($request, $this->device());

        $this->assertCount(1, $logs);
        $this->assertSame(PunchLog::OUT, $logs[0]->type);
    }

    public function test_skips_non_attendance_events(): void
    {
        $doorAlarm = $this->event(['AccessControllerEvent' => ['majorEventType' => 2]]);
        $noEmployee = $this->event();
        unset($noEmployee['AccessControllerEvent']['employeeNoString']);

        $driver = app(HikvisionDriver::class);
        $this->assertSame([], $driver->parse($this->jsonRequest($doorAlarm), $this->device()));
        $this->assertSame([], $driver->parse($this->jsonRequest($noEmployee), $this->device()));
    }

    public function test_falls_back_to_numeric_employee_no_and_null_type(): void
    {
        $e = $this->event();
        unset($e['AccessControllerEvent']['employeeNoString'], $e['AccessControllerEvent']['attendanceStatus']);
        $e['AccessControllerEvent']['employeeNo'] = 7;

        $logs = app(HikvisionDriver::class)->parse($this->jsonRequest($e), $this->device());

        $this->assertSame('7', $logs[0]->employeeId);
        $this->assertNull($logs[0]->type);
    }

    public function test_falls_back_to_numeric_employee_no_when_string_is_empty(): void
    {
        $e = $this->event();
        $e['AccessControllerEvent']['employeeNoString'] = '';
        $e['AccessControllerEvent']['employeeNo']       = 7;

        $logs = app(HikvisionDriver::class)->parse($this->jsonRequest($e), $this->device());

        $this->assertSame('7', $logs[0]->employeeId);
    }

    public function test_failed_face_auth_sub_event_is_ignored(): void
    {
        $e = $this->event(['AccessControllerEvent' => ['subEventType' => 76]]);

        $this->assertSame([], app(HikvisionDriver::class)->parse($this->jsonRequest($e), $this->device()));
    }

    public function test_expired_card_sub_event_is_ignored(): void
    {
        $e = $this->event(['AccessControllerEvent' => ['subEventType' => 13]]);

        $this->assertSame([], app(HikvisionDriver::class)->parse($this->jsonRequest($e), $this->device()));
    }

    public function test_other_pass_sub_event_is_recorded(): void
    {
        $e = $this->event(['AccessControllerEvent' => ['subEventType' => 38]]);

        $logs = app(HikvisionDriver::class)->parse($this->jsonRequest($e), $this->device());

        $this->assertCount(1, $logs);
    }

    public function test_failure_and_timeout_codes_are_rejected(): void
    {
        $driver = app(HikvisionDriver::class);

        foreach ([42, 44, 39, 76, 13] as $sub) {
            $e = $this->event(['AccessControllerEvent' => ['subEventType' => $sub]]);

            $this->assertSame([], $driver->parse($this->jsonRequest($e), $this->device()), "subEventType {$sub} should be rejected");
        }
    }

    public function test_break_out_attendance_status_maps_to_out(): void
    {
        $e = $this->event(['AccessControllerEvent' => ['attendanceStatus' => 'breakOut']]);

        $logs = app(HikvisionDriver::class)->parse($this->jsonRequest($e), $this->device());

        $this->assertSame(PunchLog::OUT, $logs[0]->type);
    }

    public function test_event_log_sent_as_uploaded_file_is_parsed(): void
    {
        $file = UploadedFile::fake()->createWithContent('event_log.json', json_encode($this->event()));

        $request = Request::create('/x', 'POST', [], [], ['event_log' => $file]);

        $logs = app(HikvisionDriver::class)->parse($request, $this->device());

        $this->assertCount(1, $logs);
    }

    public function test_utc_time_is_converted_to_gym_wall_clock(): void
    {
        $logs = app(HikvisionDriver::class)->parse($this->jsonRequest($this->event(['dateTime' => '2026-09-28T04:00:00Z'])), $this->device());

        $this->assertSame('2026-09-28 09:00:00', $logs[0]->time->format('Y-m-d H:i:s'));
    }

    public function test_garbage_body_returns_nothing(): void
    {
        $request = Request::create('/x', 'POST', [], [], [], ['CONTENT_TYPE' => 'text/plain'], 'hello');

        $this->assertSame([], app(HikvisionDriver::class)->parse($request, $this->device()));
    }

    public function test_unparseable_event_time_is_skipped(): void
    {
        $logs = app(HikvisionDriver::class)->parse(
            $this->jsonRequest($this->event(['dateTime' => 'garbage'])),
            $this->device()
        );

        $this->assertSame([], $logs);
    }

    public function test_setup_steps_with_scheme_shows_host_and_port(): void
    {
        $device = new BiometricDevice(['brand' => 'hikvision']);
        $device->forceFill(['webhook_token' => 'TOKEN123']);

        $steps = app(HikvisionDriver::class)->setupSteps($device, 'https://gym.example.com');

        $joined = implode("\n", $steps);
        $this->assertStringContainsString('gym.example.com', $joined);
        $this->assertStringContainsString('443', $joined);
        $this->assertStringContainsString('/api/biometric/hook/TOKEN123', $joined);
    }

    public function test_setup_steps_falls_back_to_base_url_when_host_cannot_be_parsed(): void
    {
        $device = new BiometricDevice(['brand' => 'hikvision']);
        $device->forceFill(['webhook_token' => 'TOKEN123']);

        $steps = app(HikvisionDriver::class)->setupSteps($device, 'gym.example.com');

        $joined = implode("\n", $steps);
        $this->assertStringContainsString('gym.example.com', $joined);
        $this->assertStringContainsString('Port: 80', $joined);
        $this->assertStringContainsString('/api/biometric/hook/TOKEN123', $joined);
    }
}
