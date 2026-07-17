<?php

/**
 * CORS untuk preview lokal bareng SoyaScan (dev server Vite Ghefira).
 * Sengaja TANPA wildcard '*' supaya gampang diperketat sebelum deploy.
 *
 * Catatan ngrok: request lewat tunnel tetap membawa Origin asli browser
 * Ghefira (mis. http://localhost:5173), jadi daftar di bawah tetap berlaku
 * sama — tidak perlu menambahkan domain ngrok ke sini.
 */

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    // Origin dev server Vite (default port 5173)
    'allowed_origins' => [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
    ],

    // Vite diakses lewat IP LAN (mis. buka dari HP saat tes QR di WiFi yang sama)
    'allowed_origins_patterns' => [
        '#^http://192\.168\.\d{1,3}\.\d{1,3}:5173$#',
        '#^http://10\.\d{1,3}\.\d{1,3}\.\d{1,3}:5173$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Endpoint publik self-order tidak memakai cookie session — biarkan false
    'supports_credentials' => false,

];
