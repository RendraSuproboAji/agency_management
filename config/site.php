<?php

return [
    'name' => env('SITE_NAME', 'Agency Management'),
    'tagline' => env('SITE_TAGLINE', 'Manajemen jasa immersive 3D reconstruction'),
    'description' => env('SITE_DESCRIPTION', 'Kelola request klien, sesi pengambilan gambar, produksi, dan deliverable 3D dalam satu tempat.'),

    // Berapa lama data mentah ditahan setelah seluruh deliverable disetujui.
    // Bisa dikecualikan per klien lewat clients.raw_retention_days.
    'raw_retention_days' => (int) env('RAW_RETENTION_DAYS', 90),

    // Identitas penerbit dokumen (kop penawaran & invoice).
    'company' => [
        'address' => env('COMPANY_ADDRESS', ''),
        'phone' => env('COMPANY_PHONE', ''),
        'email' => env('COMPANY_EMAIL', ''),
        'bank' => [
            'name' => env('COMPANY_BANK_NAME', ''),
            'account' => env('COMPANY_BANK_ACCOUNT', ''),
            'holder' => env('COMPANY_BANK_HOLDER', ''),
        ],
    ],
];
