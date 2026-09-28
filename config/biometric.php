<?php

return [
    // Timezone the machines' clocks run in. Used to turn Unix timestamps and
    // offset-carrying times into the wall-clock time we store (see WallClock).
    'timezone' => env('BIOMETRIC_TIMEZONE', 'Asia/Karachi'),
];
