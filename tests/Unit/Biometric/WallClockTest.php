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

    public function test_naive_string_is_taken_as_is(): void
    {
        $this->assertSame('2026-09-28 09:00:00', WallClock::parse('2026-09-28 09:00:00', 'Asia/Karachi')->format('Y-m-d H:i:s'));
    }
}
