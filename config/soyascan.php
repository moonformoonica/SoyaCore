<?php

return [

    /*
    |--------------------------------------------------------------------------
    | URL publik SoyaScan
    |--------------------------------------------------------------------------
    |
    | Alamat yang di-encode ke QR menu meja. Sengaja lewat config + env, BUKAN
    | di-hardcode di kode: QR yang sudah dicetak dan ditempel di meja tidak bisa
    | ditarik lagi, jadi domainnya harus bisa dipastikan benar per environment
    | sebelum dicetak, bukan ikut apa pun yang tertulis di source code.
    |
    | Fallback ke APP_URL supaya development lokal tidak perlu setelan tambahan.
    |
    */

    'url' => env('SOYASCAN_URL', env('APP_URL', 'http://localhost')),

];
