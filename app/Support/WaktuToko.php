<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Satu-satunya tempat di SoyaCore yang boleh mengubah "waktu" menjadi
 * "tanggal".
 *
 * Kenapa harus terpusat: `config('app.timezone')` masih `'UTC'` sementara toko
 * beroperasi di WIB (UTC+7). Akibatnya `whereDate('created_at', ...)` memotong
 * hari pada pukul 07:00 WIB — transaksi pukul 06:00 pagi tercatat sebagai
 * penjualan tanggal SEBELUMNYA. Selama aturan zona ditulis ulang di tiap
 * query, satu tempat yang lupa sudah cukup membuat angka dashboard tidak
 * pernah cocok dengan daftar transaksi, dan bedanya tidak memicu error apa pun.
 *
 * Aturan yang dijaga class ini, berlaku di seluruh aplikasi:
 *
 * > Transaksi yang terjadi pada suatu hari masuk ke tanggal hari itu menurut
 * > WIB, apa pun isi `config('app.timezone')`.
 *
 * Kolom `laporan_transaksi.tanggal` sengaja bertipe `date` dan sudah berisi
 * tanggal WIB hasil proyeksi, jadi query di atasnya cukup membandingkan string
 * tanggal — bukan tugas class ini.
 */
class WaktuToko
{
    public const ZONA = 'Asia/Jakarta';

    /**
     * Preset rentang tanggal yang dipakai bersama oleh daftar transaksi
     * (Blok A2) dan laporan kasir (Blok C3), supaya "7 hari terakhir" tidak
     * pernah berarti dua hal berbeda di dua halaman.
     */
    public const PRESET = ['hari_ini', 'kemarin', '7_hari', '30_hari', 'bulan_ini'];

    public static function sekarang(): Carbon
    {
        return Carbon::now(self::ZONA);
    }

    public static function tanggalHariIni(): string
    {
        return self::sekarang()->toDateString();
    }

    /**
     * Tanggal WIB dari sebuah kolom datetime tersimpan. Null diteruskan apa
     * adanya supaya pemanggil tidak perlu menjaga null-check sendiri.
     */
    public static function tanggal(?DateTimeInterface $waktu): ?string
    {
        return $waktu === null
            ? null
            : Carbon::instance($waktu)->setTimezone(self::ZONA)->toDateString();
    }

    /**
     * Batas bawah sebuah tanggal WIB, dikembalikan dalam zona aplikasi supaya
     * bisa langsung dibandingkan dengan kolom datetime di database.
     */
    public static function awalHari(string $tanggal): Carbon
    {
        return Carbon::parse($tanggal, self::ZONA)
            ->startOfDay()
            ->setTimezone(config('app.timezone'));
    }

    public static function akhirHari(string $tanggal): Carbon
    {
        return Carbon::parse($tanggal, self::ZONA)
            ->endOfDay()
            ->setTimezone(config('app.timezone'));
    }

    /**
     * Rentang inklusif sebuah preset, dalam format `Y-m-d` menurut WIB.
     *
     * `7_hari`/`30_hari` menghitung hari ini sebagai salah satu harinya —
     * "7 hari terakhir" yang berisi 8 tanggal membuat total laporan tidak bisa
     * dicocokkan dengan apa pun.
     *
     * @return array{0: string, 1: string}|null Null = preset tidak dikenal.
     */
    public static function rentangPreset(string $preset): ?array
    {
        $hariIni = self::sekarang()->startOfDay();

        return match ($preset) {
            'hari_ini' => [$hariIni->toDateString(), $hariIni->toDateString()],
            'kemarin' => [
                $hariIni->copy()->subDay()->toDateString(),
                $hariIni->copy()->subDay()->toDateString(),
            ],
            '7_hari' => [$hariIni->copy()->subDays(6)->toDateString(), $hariIni->toDateString()],
            '30_hari' => [$hariIni->copy()->subDays(29)->toDateString(), $hariIni->toDateString()],
            'bulan_ini' => [$hariIni->copy()->startOfMonth()->toDateString(), $hariIni->toDateString()],
            default => null,
        };
    }
}
