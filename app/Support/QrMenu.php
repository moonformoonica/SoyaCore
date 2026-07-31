<?php

namespace App\Support;

use App\Exceptions\ApiException;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * QR code untuk scan menu SoyaScan.
 *
 * SVG dibuat lewat simplesoftwareio/simple-qrcode. PNG TIDAK: renderer PNG
 * paket itu bergantung pada ekstensi Imagick, yang tidak terpasang di
 * environment ini dan sering juga tidak ada di container produksi PHP standar.
 * Daripada endpoint-nya gagal 500 tepat saat manager mau mencetak, PNG digambar
 * langsung dari matriks QR memakai GD.
 *
 * Karena itu SVG jadi default: ia tidak butuh ekstensi apa pun dan tetap tajam
 * saat dicetak sebesar apa pun.
 */
class QrMenu
{
    /** Modul kosong di sekeliling QR. 4 adalah minimum menurut spesifikasi QR. */
    private const QUIET_ZONE = 4;

    public const UKURAN_DEFAULT = 512;

    public const UKURAN_MAKS = 2048;

    /**
     * Alamat yang di-encode. Diambil dari config supaya QR yang sudah dicetak
     * dan ditempel di meja tidak pernah menunjuk ke domain staging.
     */
    public static function url(): string
    {
        return rtrim((string) config('soyascan.url'), '/');
    }

    public static function svg(int $ukuran): string
    {
        return (string) QrCode::format('svg')
            ->size($ukuran)
            ->margin(1)
            ->generate(self::url());
    }

    /**
     * @throws ApiException kalau tidak ada backend gambar yang bisa dipakai
     */
    public static function png(int $ukuran): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            throw new ApiException(
                'format_png_tidak_didukung',
                'Server ini belum punya ekstensi GD, jadi QR PNG tidak bisa dibuat. Pakai format=svg — hasilnya justru lebih tajam saat dicetak.',
                503,
            );
        }

        $matriks = Encoder::encode(self::url(), ErrorCorrectionLevel::M(), Encoder::DEFAULT_BYTE_MODE_ECODING)
            ->getMatrix();

        $modul = $matriks->getWidth();
        $total = $modul + (2 * self::QUIET_ZONE);

        // Skala dibulatkan ke bawah ke bilangan bulat: QR dengan lebar modul
        // pecahan menghasilkan tepi kabur yang bikin gagal di-scan.
        $skala = max(1, intdiv($ukuran, $total));
        $sisi = $total * $skala;

        $gambar = imagecreatetruecolor($sisi, $sisi);
        $putih = imagecolorallocate($gambar, 255, 255, 255);
        $hitam = imagecolorallocate($gambar, 0, 0, 0);
        imagefilledrectangle($gambar, 0, 0, $sisi - 1, $sisi - 1, $putih);

        for ($y = 0; $y < $modul; $y++) {
            for ($x = 0; $x < $modul; $x++) {
                if ($matriks->get($x, $y) !== 1) {
                    continue;
                }

                $kiri = ($x + self::QUIET_ZONE) * $skala;
                $atas = ($y + self::QUIET_ZONE) * $skala;

                imagefilledrectangle($gambar, $kiri, $atas, $kiri + $skala - 1, $atas + $skala - 1, $hitam);
            }
        }

        ob_start();
        imagepng($gambar);
        $isi = (string) ob_get_clean();
        imagedestroy($gambar);

        return $isi;
    }
}
