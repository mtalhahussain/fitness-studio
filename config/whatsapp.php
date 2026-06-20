<?php

return [
    'api' => [
        'token' => env('WHATSAPP_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'version' => env('WHATSAPP_VERSION', 'v20.0'),
        'base_url' => 'https://graph.facebook.com',
        'timeout' => (int) env('WHATSAPP_TIMEOUT', 15),
    ],

    'reminders' => [
        'template_name' => env('WHATSAPP_PAYMENT_DUE_TEMPLATE', 'payment_due'),
        'template_language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'en_US'),
        'unpaid_statuses' => ['pending', 'unpaid'],
        'template_body' => 'Dear member, your payment is due. Please clear your dues to avoid service interruption.',
    ],
];
