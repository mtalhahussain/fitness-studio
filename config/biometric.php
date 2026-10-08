<?php

return [
    // Timezone the machines' clocks run in. Used to turn Unix timestamps and
    // offset-carrying times into the wall-clock time we store (see WallClock).
    'timezone' => env('BIOMETRIC_TIMEZONE', 'Asia/Karachi'),

    // Successful individual card/fingerprint/password access events only.
    'acc' => [
        'pass_events' => [0, 1, 2, 14, 16, 17, 18, 19],
        // Schedule managed by portal sync: all day, every day, door 1 only.
        'timezone_id' => 1,
    ],

    // ZKTeco ADMS command queue (device_commands).
    'adms' => [
        // Commands handed out per GET /iclock/getrequest poll (the machine polls every few seconds).
        'commands_per_poll'    => (int) env('BIOMETRIC_ADMS_COMMANDS_PER_POLL', 10),
        // A command sent but never answered (machine rebooted, network drop) is sent again after this.
        'resend_after_minutes' => (int) env('BIOMETRIC_ADMS_RESEND_AFTER_MINUTES', 10),
    ],

    // Brand dropdown, in display order. To add a brand: write a driver class and add a line.
    'drivers' => [
        'zkteco'    => App\Biometric\Drivers\ZKTecoDriver::class,
        'hikvision' => App\Biometric\Drivers\HikvisionDriver::class,
        'generic'   => App\Biometric\Drivers\GenericWebhookDriver::class,
    ],

    // Only codes confirmed as access-granted are listed. If a terminal uses combined modes
    // (card+fingerprint etc.), find its pass code in the 'non-pass access event skipped' log
    // and add it here, then run php artisan config:clear.
    'hikvision' => [
        'pass_sub_events' => [1, 38, 75], // 1 card pass, 38 fingerprint pass, 75 face pass.
    ],

    // Starting points for the Generic Webhook form. UNVERIFIED against real payloads:
    // the UI tells the user to confirm with a test punch and the Last received data panel.
    'generic_presets' => [
        'suprema_biostar2' => [
            'label'          => 'Suprema BioStar 2 (unverified)',
            'records_path'   => 'events',
            'employee_field' => 'user_id.user_id',
            'time_field'     => 'datetime',
            'time_format'    => 'iso',
        ],
        'anviz_crosschex' => [
            'label'          => 'Anviz CrossChex Cloud (unverified)',
            'records_path'   => 'payload.list',
            'employee_field' => 'employee.workno',
            'time_field'     => 'checktime',
            'time_format'    => 'auto',
            'type_field'     => 'checktype',
            'type_in_value'  => '0',
            'type_out_value' => '1',
        ],
    ],
];
