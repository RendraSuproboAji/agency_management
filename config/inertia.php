<?php

return [
    /*
     * Proyek ini memakai React dengan direktori resources/js/Pages (huruf
     * besar), sementara bawaan Inertia menunjuk resources/js/pages dan
     * ekstensi Vue. Tanpa penyetelan ini, assertInertia()->component()
     * selalu gagal karena berkasnya "tidak ditemukan".
     */
    'pages' => [
        'paths' => [resource_path('js/Pages')],
        'extensions' => ['jsx'],
    ],

    'testing' => [
        'ensure_pages_exist' => true,
    ],
];
