<?php

/**
 * CORS untuk preview lokal bareng SoyaScan (dev server Vite Ghefira).
 * Sengaja TANPA wildcard '*' supaya gampang diperketat sebelum deploy.
 *
 * DUA KEADAAN NGROK YANG BEDA, dan ini pernah salah dicatat di sini:
 *
 * 1. Ghefira membuka SoyaScan di komputernya sendiri (`localhost:5173`) dan
 *    hanya API SoyaCore yang lewat tunnel. Origin yang terkirim tetap
 *    `http://localhost:5173`, jadi daftar di bawah sudah cukup.
 * 2. SoyaScan-nya IKUT di-tunnel supaya bisa dibuka orang lain. Begitu halaman
 *    itu diakses lewat `https://xxx.ngrok-free.dev`, Origin-nya berubah jadi
 *    domain ngrok, bukan localhost lagi, dan seluruh panggilan API akan
 *    diblokir browser tanpa pesan yang jelas di layar.
 *
 * Pola di bawah menutup keadaan kedua. Dibatasi ke domain ngrok saja, bukan
 * `*`, supaya sembarang situs tetap tidak bisa memanggil API ini dari browser
 * pengunjung.
 */

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    // Origin dev server Vite (default port 5173)
    'allowed_origins' => [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
    ],

    'allowed_origins_patterns' => [
        // Vite diakses lewat IP LAN (mis. buka dari HP saat tes QR di WiFi yang sama)
        '#^http://192\.168\.\d{1,3}\.\d{1,3}:5173$#',
        '#^http://10\.\d{1,3}\.\d{1,3}\.\d{1,3}:5173$#',

        // SoyaScan yang ikut di-tunnel ngrok. Domainnya berubah tiap sesi
        // untuk akun gratis tanpa static domain, jadi yang dicocokkan polanya,
        // bukan satu domain tetap yang harus diedit tiap kali preview.
        '#^https://[a-z0-9-]+\.ngrok-free\.(app|dev)$#',
        '#^https://[a-z0-9-]+\.ngrok\.app$#',
        '#^https://[a-z0-9-]+\.ngrok\.io$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Endpoint publik self-order tidak memakai cookie session, biarkan false
    'supports_credentials' => false,

];
