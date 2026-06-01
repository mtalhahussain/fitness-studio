<?php

return [
    /*
     * All modules available in the system.
     * Super Admin can enable/disable these per gym.
     */
    'available' => [
        'members' => [
            'label'       => 'Members & Plans',
            'description' => 'Member management, membership plans, and billing.',
            'icon'        => '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path stroke-linecap="round" d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        ],
        'attendance' => [
            'label'       => 'Attendance',
            'description' => 'Manual check-in/check-out tracking for members.',
            'icon'        => '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><polyline stroke-linecap="round" points="12 6 12 12 16 14"/></svg>',
        ],
        'trainers' => [
            'label'       => 'Trainers & Commissions',
            'description' => 'Trainer management, training sessions, and commission tracking.',
            'icon'        => '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><path stroke-linecap="round" d="M16 11l2 2 4-4"/></svg>',
        ],
        'biometric' => [
            'label'       => 'Biometric Devices',
            'description' => 'Integrate fingerprint/face recognition devices for automated check-in.',
            'icon'        => '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/><path stroke-linecap="round" d="M9 7h6M9 11h4"/></svg>',
        ],
        'pos' => [
            'label'       => 'Point of Sale',
            'description' => 'Sell products, create invoices, and manage payments at the counter.',
            'icon'        => '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>',
        ],
        'reports' => [
            'label'       => 'Reports & Analytics',
            'description' => 'Revenue reports, member analytics, attendance trends, and commission reports.',
            'icon'        => '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
        ],
    ],

    // Default modules enabled when a gym is first created
    'default' => ['members', 'attendance', 'trainers', 'biometric', 'pos', 'reports'],
];
