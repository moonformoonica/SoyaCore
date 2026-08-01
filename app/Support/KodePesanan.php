<?php

namespace App\Support;

use App\Models\Transaksi;
use Illuminate\Support\Facades\DB;

/**
 * Penomoran kode pesanan: `#A00` sampai `#Z99`, di-reset tiap awal minggu.
 *
 * BENTUKNYA. Huruf naik begitu dua digitnya habis: `#A00` … `#A99`, lalu
 * `#B00` … `#B99`, dan seterusnya. Satu minggu memuat 2.600 pesanan sebelum
 * hurufnya kembali ke `#A`, jauh di atas kebutuhan kedai, jadi dalam praktiknya
 * kode tidak pernah terpakai dua kali dalam minggu yang sama.
 *
 * SATU URUTAN UNTUK SEMUA CHANNEL. Sebelumnya huruf menandai asal pesanan
 * (`#A` SoyaScan, `#K` kasir) dan tiap channel punya penghitung sendiri.
 * Sekarang keduanya berbagi satu urutan, sehingga dua pesanan yang dibuat
 * berdekatan tidak pernah memperoleh nomor yang sama persis. Asal pesanan
 * TIDAK hilang: kolom `transaksi.sumber` yang mencatatnya, dan itu memang
 * tempat yang benar. Jangan mengembalikan pembacaan channel dari huruf kode.
 *
 * TIDAK UNIK LINTAS MINGGU. `#A00` minggu ini dan `#A00` minggu lalu
 * dua-duanya ada di tabel. Setiap pencarian berdasarkan kode harus mengambil
 * yang TERBARU, bukan `first()` polos.
 */
class KodePesanan
{
    private const HURUF = 26;

    private const PER_HURUF = 100;

    /** Total kode berbeda dalam satu minggu sebelum berputar kembali ke `#A00`. */
    private const KAPASITAS = self::HURUF * self::PER_HURUF;

    /**
     * Kode berikutnya untuk minggu berjalan.
     *
     * Di PostgreSQL pengambilan nomor dikunci selama transaksi database, supaya
     * dua kasir yang menekan tombol pada detik yang sama tidak menghitung
     * jumlah baris yang sama lalu memperoleh kode kembar.
     */
    public static function berikutnya(): string
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(?)', [crc32('kode_pesanan')]);
        }

        $awalMinggu = WaktuToko::awalMingguIni();

        $terpakai = Transaksi::query()
            ->where('created_at', '>=', $awalMinggu)
            ->pluck('kode_pesanan')
            ->flip();

        // Jumlah baris jadi tebakan awal, lalu digeser sampai menemukan kode
        // yang benar-benar kosong. Berbasis hitungan saja tidak cukup: begitu
        // satu transaksi dihapus di tengah minggu, hitungannya turun dan kode
        // yang sudah dipakai akan diberikan lagi ke pesanan berikutnya. Data
        // berformat lama yang masih tersisa juga ikut terhindari lewat cara ini.
        $urutan = $terpakai->count();
        for ($langkah = 0; $langkah < self::KAPASITAS; $langkah++) {
            $kode = self::dariUrutan($urutan + $langkah);

            if (! $terpakai->has($kode)) {
                return $kode;
            }
        }

        // Seluruh 2.600 kode minggu ini terpakai. Praktis mustahil di kedai,
        // tapi lebih baik mengembalikan kode yang berulang daripada gagal
        // membuat pesanan saat pelanggan sedang mengantre.
        return self::dariUrutan($urutan);
    }

    /**
     * Urutan ke-0 menjadi `#A00`, ke-99 `#A99`, ke-100 `#B00`. Setelah
     * `#Z99` berputar kembali ke `#A00`.
     */
    public static function dariUrutan(int $urutan): string
    {
        $urutan %= self::KAPASITAS;

        $huruf = chr(ord('A') + intdiv($urutan, self::PER_HURUF));
        $angka = str_pad((string) ($urutan % self::PER_HURUF), 2, '0', STR_PAD_LEFT);

        return '#'.$huruf.$angka;
    }

    /**
     * Menerima `#A01`, `A01`, maupun `a01`. Tanda `#` adalah pemisah fragment
     * di URL sehingga sering hilang sebelum sampai ke server, jadi bentuk apa
     * pun dinormalkan di sini.
     */
    public static function normalisasi(string $kode): string
    {
        $bersih = mb_strtoupper(trim($kode));

        return str_starts_with($bersih, '#') ? $bersih : '#'.$bersih;
    }
}
