<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Nama hari dan bulan berbahasa Indonesia untuk label grafik tren.
 *
 * Sengaja di-hardcode, TIDAK memakai `Carbon::locale('id')` maupun
 * `strftime()`: locale Indonesia sering tidak terpasang di container produksi,
 * dan kalau tidak ada, hasilnya diam-diam kembali ke bahasa Inggris. Kegagalan
 * seperti itu tidak melempar error — ia hanya muncul sebagai "Mon, 28 Jul" di
 * dashboard manager, biasanya setelah deploy.
 */
class KalenderIndonesia
{
    /** @var array<int, string> Indeks = ISO day-of-week (1 Senin … 7 Minggu). */
    public const HARI = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu',
    ];

    /** @var array<int, string> */
    public const HARI_SINGKAT = [
        1 => 'Sen',
        2 => 'Sel',
        3 => 'Rab',
        4 => 'Kam',
        5 => 'Jum',
        6 => 'Sab',
        7 => 'Min',
    ];

    /** @var array<int, string> */
    public const BULAN = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    /** @var array<int, string> */
    public const BULAN_SINGKAT = [
        1 => 'Jan',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Apr',
        5 => 'Mei',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Agu',
        9 => 'Sep',
        10 => 'Okt',
        11 => 'Nov',
        12 => 'Des',
    ];

    public static function namaHari(Carbon $tanggal): string
    {
        return self::HARI[$tanggal->isoWeekday()];
    }

    /** Contoh: `28 Jul`. */
    public static function tanggalSingkat(Carbon $tanggal): string
    {
        return $tanggal->day.' '.self::BULAN_SINGKAT[$tanggal->month];
    }

    /** Contoh: `Sen, 28 Jul`. */
    public static function labelHarian(Carbon $tanggal): string
    {
        return self::HARI_SINGKAT[$tanggal->isoWeekday()].', '.self::tanggalSingkat($tanggal);
    }

    /** Contoh: `28 Jul – 3 Agu`. `$awal` adalah hari Senin bucket-nya. */
    public static function labelMingguan(Carbon $awal): string
    {
        $akhir = $awal->copy()->addDays(6);

        return self::tanggalSingkat($awal).' – '.self::tanggalSingkat($akhir);
    }

    /** Contoh: `Juli 2026`. */
    public static function labelBulanan(Carbon $tanggal): string
    {
        return self::BULAN[$tanggal->month].' '.$tanggal->year;
    }
}
