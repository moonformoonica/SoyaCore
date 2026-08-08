<?php

use App\Services\LaporanProjector;
use App\Support\PlatformPembayaran;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menyatukan dua kosakata metode bayar yang terlanjur tercampur di
 * `laporan_transaksi.platform`.
 *
 * GEJALANYA. Filter platform di dashboard menampilkan `QRIS` (dari impor CSV)
 * dan `qris` (dari proyeksi POS) sebagai dua entri terpisah, begitu pula
 * `Tunai` dan `cash`. Angka QRIS yang sebenarnya terpecah dua tanpa ada yang
 * sadar, karena tidak ada error yang muncul.
 *
 * Sumbernya sudah ditutup di {@see LaporanProjector}, yang kini
 * menormalkan lewat {@see PlatformPembayaran} sebelum menulis.
 * Migrasi ini membereskan baris yang terlanjur masuk sebelum perbaikan itu.
 *
 * PEMETAANNYA SENGAJA DITULIS ULANG DI SINI, tidak memanggil
 * PlatformPembayaran. Migrasi adalah catatan sejarah: ia harus menghasilkan
 * hasil yang sama kapan pun dijalankan ulang, sedangkan class Support boleh
 * berubah kapan saja. Menggantungkan migrasi lama pada kode yang masih hidup
 * membuat `migrate:fresh` di masa depan diam-diam menghasilkan data berbeda.
 */
return new class extends Migration
{
    /**
     * Kunci dibandingkan case-insensitive, jadi `cash`, `Cash`, dan `CASH`
     * sama-sama tertangani.
     *
     * @var array<string, string>
     */
    private const PETA = [
        'cash' => 'Tunai',
        'qris' => 'QRIS',
        'tunai' => 'Tunai',
    ];

    public function up(): void
    {
        // Dikerjakan per nilai unik, bukan per baris: jumlah nilai `platform`
        // yang berbeda hanya segelintir, sementara barisnya ribuan.
        $nilai = DB::table('laporan_transaksi')
            ->select('platform')
            ->whereNotNull('platform')
            ->distinct()
            ->pluck('platform');

        foreach ($nilai as $lama) {
            $baru = self::PETA[mb_strtolower(trim((string) $lama))] ?? null;

            if ($baru === null || $baru === $lama) {
                continue;
            }

            DB::table('laporan_transaksi')
                ->where('platform', $lama)
                ->update(['platform' => $baru]);
        }
    }

    /**
     * Sengaja tidak melakukan apa-apa.
     *
     * Normalisasi ini menggabungkan beberapa nilai menjadi satu, jadi informasi
     * "baris ini dulunya `qris` huruf kecil" memang sudah tidak ada untuk
     * dipulihkan. Menebak arah baliknya, misalnya mengembalikan seluruh `QRIS`
     * menjadi `qris`, justru akan merusak 345 baris CSV historis yang sejak
     * awal sudah benar. Rollback yang tidak melakukan apa-apa lebih jujur
     * daripada rollback yang merusak.
     */
    public function down(): void
    {
        //
    }
};
