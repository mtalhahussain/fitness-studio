<?php

namespace App\Biometric;

use Carbon\Carbon;

/**
 * Attendance stores the machine's wall-clock time labelled with the app timezone
 * (that is how ZKTeco punches have always been saved). These helpers turn any
 * machine time into that same form.
 */
final class WallClock
{
    /** A real instant (has an offset, or is UTC) → wall-clock time in $deviceTz. */
    public static function fromInstant(Carbon $instant, string $deviceTz): Carbon
    {
        return $instant->copy()->setTimezone($deviceTz)->shiftTimezone(config('app.timezone'));
    }

    public static function fromUnix(int|float $seconds, string $deviceTz): Carbon
    {
        return self::fromInstant(Carbon::createFromTimestamp($seconds, 'UTC'), $deviceTz);
    }

    /** A string that may or may not carry an offset. Without one it is already wall-clock. */
    public static function parse(string $value, string $deviceTz): Carbon
    {
        $hasOffset = (bool) preg_match('/(Z|[+-]\d{2}:?\d{2})$/', trim($value));

        return $hasOffset
            ? self::fromInstant(Carbon::parse($value), $deviceTz)
            : Carbon::parse($value, config('app.timezone'));
    }
}
