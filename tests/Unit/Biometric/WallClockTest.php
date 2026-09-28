<?php

namespace Tests\Unit\Biometric;

use App\Biometric\WallClock;
use Carbon\Carbon;
use Tests\TestCase;

class WallClockTest extends TestCase
{
    public function test_offset_time_keeps_gym_wall_clock(): void
    {
        $t = WallClock::fromInstant(Carbon::parse('2026-09-28T09:00:00+05:00'), 'Asia/Karachi');

        $this->assertSame('2026-09-28 09:00:00', $t->format('Y-m-d H:i:s'));
        $this->assertSame(config('app.timezone'), $t->timezoneName);
    }

    public function test_utc_instant_is_shown_in_gym_time(): void
    {
        $t = WallClock::fromInstant(Carbon::parse('2026-09-28T04:00:00Z'), 'Asia/Karachi');

        $this->assertSame('2026-09-28 09:00:00', $t->format('Y-m-d H:i:s'));
    }

    public function test_unix_timestamp(): void
    {
        $ts = Carbon::parse('2026-09-28T04:00:00Z')->timestamp;

        $this->assertSame('2026-09-28 09:00:00', WallClock::fromUnix($ts, 'Asia/Karachi')->format('Y-m-d H:i:s'));
    }

    public function test_unix_timestamp_with_fractional_seconds(): void
    {
        $ts = Carbon::parse('2026-09-28T04:00:00Z')->timestamp + 0.5;

        $t = WallClock::fromUnix($ts, 'Asia/Karachi');

        $this->assertSame('2026-09-28 09:00:00', $t->format('Y-m-d H:i:s'));
    }

    public function test_naive_string_is_taken_as_is(): void
    {
        $t = WallClock::parse('2026-09-28 09:00:00', 'Asia/Karachi');

        $this->assertSame('2026-09-28 09:00:00', $t->format('Y-m-d H:i:s'));
        $this->assertSame(config('app.timezone'), $t->timezoneName);
    }

    public function test_parse_detects_colon_offset(): void
    {
        $t = WallClock::parse('2026-09-28T09:00:00+05:00', 'Asia/Karachi');

        $this->assertSame('2026-09-28 09:00:00', $t->format('Y-m-d H:i:s'));
        $this->assertSame(config('app.timezone'), $t->timezoneName);
    }

    public function test_parse_detects_uppercase_z(): void
    {
        $t = WallClock::parse('2026-09-28T04:00:00Z', 'Asia/Karachi');

        $this->assertSame('2026-09-28 09:00:00', $t->format('Y-m-d H:i:s'));
        $this->assertSame(config('app.timezone'), $t->timezoneName);
    }

    public function test_parse_detects_lowercase_z(): void
    {
        $t = WallClock::parse('2026-09-28 04:00:00z', 'Asia/Karachi');

        $this->assertSame('2026-09-28 09:00:00', $t->format('Y-m-d H:i:s'));
        $this->assertSame(config('app.timezone'), $t->timezoneName);
    }

    public function test_parse_detects_offset_without_colon(): void
    {
        $t = WallClock::parse('2026-09-28 09:00:00+0500', 'Asia/Karachi');

        $this->assertSame('2026-09-28 09:00:00', $t->format('Y-m-d H:i:s'));
        $this->assertSame(config('app.timezone'), $t->timezoneName);
    }

    public function test_parse_detects_short_offset_across_date_boundary(): void
    {
        $t = WallClock::parse('2026-09-27 23:00:00-05', 'Asia/Karachi');

        $this->assertSame('2026-09-28 09:00:00', $t->format('Y-m-d H:i:s'));
        $this->assertSame(config('app.timezone'), $t->timezoneName);
    }

    public function test_parse_detects_gmt_suffix(): void
    {
        $t = WallClock::parse('2026-09-28 04:00:00 GMT', 'Asia/Karachi');

        $this->assertSame('2026-09-28 09:00:00', $t->format('Y-m-d H:i:s'));
        $this->assertSame(config('app.timezone'), $t->timezoneName);
    }

    public function test_parse_date_only_has_no_zone(): void
    {
        $t = WallClock::parse('2026-09-28', 'Asia/Karachi');

        $this->assertSame('2026-09-28 00:00:00', $t->format('Y-m-d H:i:s'));
        $this->assertSame(config('app.timezone'), $t->timezoneName);
    }
}
