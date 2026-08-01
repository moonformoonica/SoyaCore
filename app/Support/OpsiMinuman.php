<?php

namespace App\Support;

use App\Exceptions\ApiException;

/**
 * Opsi peracikan minuman (sugar & ice) beserta aturan ketersediaannya.
 *
 * SATU SUMBER KEBENARAN: FormRequest, MenuResource, dan katalog publik semua
 * membaca dari sini. Daftar yang disalin ke FormRequest akan lepas sinkron
 * dengan daftar yang dikirim ke frontend, dan gejalanya adalah pilihan yang
 * tampil di layar tapi ditolak 422 saat dikirim.
 *
 * Ketersediaan diturunkan dari {@see GolonganUkuran}, BUKAN dari daftar nama
 * menu: menu baru bertambah terus, ukurannya tidak.
 *
 * - `Hot` (cup)                 : sugar saja — es tidak relevan di minuman panas.
 * - `Reguler`, `Large` (cup)    : keduanya, karena diracik per pesanan.
 * - `250ml`–`1000ml` (botol)    : tidak ada. Kemasan botol diproduksi batch,
 *                                 bukan per pesanan, jadi pilihan pelanggan
 *                                 tidak bisa dipenuhi barista.
 * - Dessert & cookies (lainnya) : tidak ada. Bukan minuman.
 */
class OpsiMinuman
{
    /**
     * Pemanis bawaan Gres'Soy: Gula Kelapa, BUKAN gula pasir. Itu salah satu
     * nilai jual produknya, jadi harus disebut ke pelanggan — tidak bisa
     * diasumsikan sudah diketahui dari label "Less Sugar" saja.
     */
    public const JENIS_GULA = 'Gula Kelapa';

    /**
     * Label opsi sengaja hanya berisi AKSI-nya ("Less", "No", "Extra"), bukan
     * "Less Sugar".
     *
     * Alasannya: pemanis tiap menu tidak sama. Sebagian besar memakai Gula
     * Kelapa, tapi Soya Tropical dimaniskan buah/madu — Honey Lemon dengan
     * Special Madu Lemon, Mango Monggo dengan Special Mangga Gandaria. Label
     * "Less Sugar" di Honey Lemon menjanjikan sesuatu yang tidak ada di
     * gelasnya, dan barista tidak tahu apa yang harus dikurangi.
     *
     * Nama pemanis yang benar dikirim per menu lewat {@see self::pemanis()},
     * dipakai frontend sebagai judul kelompok pilihan. Jadi tombolnya tetap
     * pendek (penting di layar HP) sementara pelanggan tetap tahu persis apa
     * yang sedang dia atur.
     *
     * @var array<string, string>
     */
    public const SUGAR = [
        'normal' => 'Normal',
        'less' => 'Less',
        'no' => 'No',
        'extra' => 'Extra',
    ];

    /** @var array<string, string> */
    public const ICE = [
        'normal' => 'Normal',
        'less' => 'Less Ice',
        'no' => 'No Ice',
        'extra' => 'Extra Ice',
    ];

    /**
     * Bentuk `{kode, label}` supaya frontend cukup me-loop hasilnya menjadi
     * tombol/dropdown tanpa memetakan label sendiri.
     *
     * @return list<array{kode: string, label: string}>
     */
    public static function daftarSugar(): array
    {
        return self::daftar(self::SUGAR);
    }

    /**
     * @return list<array{kode: string, label: string}>
     */
    public static function daftarIce(): array
    {
        return self::daftar(self::ICE);
    }

    /**
     * Nama pemanis sebuah menu, diturunkan dari komponen TERAKHIR kolom `rasa`.
     *
     * `rasa` di database memang disusun sebagai daftar komposisi berurutan yang
     * diakhiri pemanisnya:
     *
     *     "Soya Original Premium + Brown Sugar"                 → Gula Kelapa
     *     "Soya Original Premium + Taro Premium + Brown Sugar"  → Gula Kelapa
     *     "Soya Original Premium + Special Madu Lemon"          → Special Madu Lemon
     *     "Soya Original Premium + Special Mangga Gandaria"     → Special Mangga Gandaria
     *
     * Diturunkan dari data, BUKAN dari daftar nama menu yang di-hardcode: menu
     * baru bertambah terus, dan yang pakai pemanis non-gula ikut bertambah.
     * Dengan cara ini menu baru "… + Special Sirup Pandan" otomatis benar tanpa
     * ada yang perlu ingat memperbarui kode.
     *
     * Ejaan gula apa pun ("Brown Sugar", "Gula Aren", …) dinormalkan ke satu
     * nama resmi supaya pelanggan tidak melihat istilah yang berbeda-beda untuk
     * pemanis yang sama.
     */
    public static function pemanis(?string $rasa): string
    {
        $bagian = array_values(array_filter(array_map('trim', explode('+', (string) $rasa))));

        // Tanpa pemisah '+' berarti `rasa` bukan daftar komposisi (mis. deskripsi
        // dessert). Tidak ada pemanis yang bisa disimpulkan — jatuh ke bawaan.
        if (count($bagian) < 2) {
            return self::JENIS_GULA;
        }

        $terakhir = $bagian[count($bagian) - 1];

        return preg_match('/sugar|gula/i', $terakhir) === 1 ? self::JENIS_GULA : $terakhir;
    }

    /**
     * Keterangan pemanis untuk sebuah menu — dipakai frontend sebagai judul
     * kelompok pilihan di atas tombol Normal/Less/No/Extra.
     *
     * `khusus` menjawab satu pertanyaan yang dibutuhkan layar kasir: apakah
     * pemanis menu ini BUKAN gula kelapa bawaan. Dikirim sebagai boolean, bukan
     * dibiarkan frontend membandingkan `jenis !== 'Gula Kelapa'` sendiri —
     * perbandingan string seperti itu langsung salah begitu nama resminya
     * diubah, dan salahnya tidak kelihatan (judulnya cuma hilang/muncul di
     * tempat yang keliru, tanpa error).
     *
     * Kedua layar memakai data yang sama tapi menampilkannya berbeda:
     *
     * | Layar               | Judul pemanis                                  |
     * | ------------------- | ---------------------------------------------- |
     * | SoyaScan (pelanggan) | SELALU — Gula Kelapa adalah nilai jual produk  |
     * | Pemesanan kasir      | Hanya bila `khusus` — kasir sudah hafal bahwa
     *                          bawaannya gula kelapa, jadi mengulangnya di tiap
     *                          item cuma memperlambat input                    |
     *
     * @return array{jenis: string, keterangan: string, khusus: bool}
     */
    public static function keteranganPemanis(?string $rasa): array
    {
        $jenis = self::pemanis($rasa);
        $bawaan = $jenis === self::JENIS_GULA;

        return [
            'jenis' => $jenis,
            'keterangan' => $bawaan
                ? 'Dimaniskan dengan Gula Kelapa, bukan gula pasir.'
                : "Dimaniskan dengan {$jenis}, tanpa tambahan gula.",
            'khusus' => ! $bawaan,
        ];
    }

    /**
     * @return list<string>
     */
    public static function kodeSugar(): array
    {
        return array_keys(self::SUGAR);
    }

    /**
     * @return list<string>
     */
    public static function kodeIce(): array
    {
        return array_keys(self::ICE);
    }

    public static function bisaPilihSugar(?string $ukuran): bool
    {
        return GolonganUkuran::dari($ukuran) === GolonganUkuran::CUP;
    }

    public static function bisaPilihIce(?string $ukuran): bool
    {
        if (! self::bisaPilihSugar($ukuran)) {
            return false;
        }

        return mb_strtolower(trim((string) $ukuran)) !== 'hot';
    }

    /**
     * Menolak pilihan yang tidak relevan, BUKAN mengabaikannya diam-diam:
     * mengirim `level_ice` untuk menu `Hot` atau kemasan botol adalah kesalahan
     * yang harus terlihat di sisi pengirim. Data yang lolos lalu tersimpan
     * membuat barista membaca instruksi yang tidak bisa dia kerjakan.
     *
     * Dipanggil dari SoyaScan (`OrderService`) maupun kasir
     * (`TransaksiItemController`) — kasir harus bisa mencatat hal yang sama
     * seperti pelanggan.
     *
     * @throws ApiException kode `opsi_tidak_tersedia`
     */
    public static function pastikanBoleh(?string $ukuran, ?string $sugar, ?string $ice, string $labelMenu): void
    {
        if ($sugar !== null && ! self::bisaPilihSugar($ukuran)) {
            throw new ApiException(
                'opsi_tidak_tersedia',
                "{$labelMenu} tidak bisa dipilih level sugar-nya — ".self::alasan($ukuran).'.',
                422,
            );
        }

        if ($ice !== null && ! self::bisaPilihIce($ukuran)) {
            throw new ApiException(
                'opsi_tidak_tersedia',
                "{$labelMenu} tidak bisa dipilih level ice-nya — ".self::alasanIce($ukuran).'.',
                422,
            );
        }
    }

    private static function alasan(?string $ukuran): string
    {
        return match (GolonganUkuran::dari($ukuran)) {
            GolonganUkuran::BOTOL => 'kemasan botol diproduksi batch, bukan diracik per pesanan',
            default => 'menu ini bukan minuman yang diracik per pesanan',
        };
    }

    private static function alasanIce(?string $ukuran): string
    {
        if (GolonganUkuran::dari($ukuran) === GolonganUkuran::CUP) {
            return 'ini minuman panas, jadi es tidak relevan';
        }

        return self::alasan($ukuran);
    }

    /**
     * Label LENGKAP untuk nota & tiket barista, mis. `Less Gula Kelapa` atau
     * `Extra Special Madu Lemon`.
     *
     * Di layar pemesanan tombolnya cukup pendek karena ada judul kelompoknya,
     * tapi di nota tidak ada judul apa pun — "Less" sendirian tidak memberi tahu
     * barista apa yang harus dikurangi.
     *
     * `$rasa` boleh null (mis. menunya sudah terhapus); yang keluar aksinya saja.
     */
    public static function labelSugar(?string $kode, ?string $rasa = null): ?string
    {
        if ($kode === null) {
            return null;
        }

        $aksi = self::SUGAR[$kode] ?? null;
        if ($aksi === null) {
            return null;
        }

        return $rasa === null ? $aksi : $aksi.' '.self::pemanis($rasa);
    }

    public static function labelIce(?string $kode): ?string
    {
        return $kode === null ? null : (self::ICE[$kode] ?? null);
    }

    /**
     * @param  array<string, string>  $peta
     * @return list<array{kode: string, label: string}>
     */
    private static function daftar(array $peta): array
    {
        $hasil = [];
        foreach ($peta as $kode => $label) {
            $hasil[] = ['kode' => $kode, 'label' => $label];
        }

        return $hasil;
    }
}
