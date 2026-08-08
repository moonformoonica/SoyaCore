<?php

namespace App\Support;

use App\Services\RfmQuery;

/**
 * Pola treatment tiap segmen RFM.
 *
 * MASALAH YANG DISELESAIKAN. `GET /api/dashboard/rfm` sebelumnya hanya
 * mengembalikan nama segmen dan jumlah anggotanya. Manager jadi tahu siapa
 * masuk segmen mana, tapi tidak tahu harus berbuat apa, dan segmentasi yang
 * tidak berujung tindakan sama saja dengan tidak ada.
 *
 * SUMBER ANGKA REWARD. Kode reward di sini menunjuk katalog nyata di
 * {@see LoyaltyRedemptionCatalog}, bukan saran umum, supaya rekomendasinya
 * langsung bisa dieksekusi kasir tanpa menerjemahkan apa pun.
 *
 * YANG PALING MUDAH SALAH: segmen Loyal justru TIDAK diberi diskon. Mereka
 * sudah membeli tanpa insentif, jadi diskon di sana tidak mengubah perilaku,
 * ia hanya memotong margin dari penjualan yang toh akan tetap terjadi. Anggaran
 * diskon dihemat untuk segmen yang perilakunya memang masih bisa digeser.
 *
 * BUKAN MESIN OTOMATIS. Class ini hanya menyajikan polanya. Tidak ada
 * pengiriman promo otomatis, eksekusinya manual lewat WhatsApp dan itu
 * keputusan manager.
 */
class SegmenTreatment
{
    /**
     * Kunci HARUS sama persis dengan nilai yang dihasilkan
     * {@see RfmQuery::segmen()}. Ketidakcocokan satu huruf membuat segmen itu
     * kehilangan treatment-nya tanpa error apa pun, cuma kolom kosong di
     * dashboard.
     *
     * @var array<string, array<string, mixed>>
     */
    private const PETA = [
        'Loyal' => [
            'prioritas' => 1,
            'karakteristik' => 'Sering datang, nilai belanja tinggi, dan baru saja bertransaksi.',
            'tujuan' => 'Pertahankan. Jangan diganggu dengan penawaran yang tidak mereka butuhkan.',
            'treatment' => [
                'Beri akses awal ke menu baru dan minta pendapatnya, mereka pelanggan yang paling paham produk.',
                'Apresiasi personal saat datang, sebut namanya dan menu favoritnya.',
                'JANGAN beri diskon. Mereka sudah membeli tanpa insentif, jadi diskon di sini murni memotong margin tanpa mengubah apa pun.',
            ],
            'reward_disarankan' => 'gratis_coffee_kopi',
            'alasan_reward' => 'Reward berupa produk terasa sebagai apresiasi, sementara diskon terasa sebagai transaksi.',
        ],
        'Potensial' => [
            'prioritas' => 2,
            'karakteristik' => 'Frekuensi menengah dan recency masih bagus. Kandidat terkuat untuk naik jadi Loyal.',
            'tujuan' => 'Naikkan frekuensi kedatangan, bukan nilai belanja per kunjungan.',
            'treatment' => [
                'Dorong dengan reward yang cepat diraih, bukan yang besar. Target yang terasa jauh justru membuat orang berhenti mengumpulkan.',
                'Ingatkan sisa poin menuju reward terdekat saat membayar.',
            ],
            'reward_disarankan' => 'diskon_10',
            'alasan_reward' => 'Tier termurah (100 poin) sehingga terasa dapat diraih dalam beberapa kunjungan.',
        ],
        'Pelanggan Baru' => [
            'prioritas' => 3,
            'karakteristik' => 'Baru satu kali transaksi. Kebiasaan belum terbentuk dan paling gampang hilang.',
            'tujuan' => 'Amankan kunjungan KEDUA. Di situlah titik putus terbesar.',
            'treatment' => [
                'Pastikan kasir menyebutkan bonus 50 poin pendaftaran, saldo awal membuat reward pertama terasa dekat.',
                'Sebutkan bahwa poin berlaku 12 bulan sejak transaksi terakhir, supaya tidak terasa mendesak tapi tetap ada alasan kembali.',
            ],
            'reward_disarankan' => 'diskon_10',
            'alasan_reward' => 'Dengan bonus 50 poin pendaftaran, tier 100 poin tinggal satu kunjungan lagi.',
        ],
        'Butuh Perhatian' => [
            'prioritas' => 4,
            'karakteristik' => 'Pernah sering membeli, tapi sudah lama tidak datang. Masih ingat merek, kebiasaannya yang putus.',
            'tujuan' => 'Reaktivasi sebelum benar-benar lupa.',
            'treatment' => [
                'Butuh dorongan lebih besar daripada segmen lain, karena kebiasaannya sudah terputus.',
                'Pengingat poin akan kedaluwarsa adalah pemicu paling wajar, isinya informasi yang memang berguna, bukan promosi.',
                'Hubungi lewat WhatsApp secara personal, bukan blast, jumlah orangnya masih terkelola.',
            ],
            'reward_disarankan' => 'diskon_30',
            'alasan_reward' => 'Nilainya cukup besar untuk membenarkan perjalanan kembali ke toko.',
        ],
    ];

    /**
     * Treatment satu segmen. Segmen yang tidak dikenal mengembalikan `null`,
     * BUKAN entri kosong, supaya pemanggil bisa membedakan "belum ada polanya"
     * dari "polanya kosong".
     *
     * @return array<string, mixed>|null
     */
    public static function untuk(string $segmen): ?array
    {
        $item = self::PETA[$segmen] ?? null;

        return $item === null ? null : ['segmen' => $segmen] + $item;
    }

    /**
     * Seluruh segmen, terurut prioritas penanganan.
     *
     * Mengikuti {@see RfmQuery::SEGMEN} sebagai daftar induk supaya segmen baru
     * yang ditambahkan di sana langsung ketahuan belum punya treatment (muncul
     * sebagai entri hilang di sini), bukan diam-diam tidak pernah tampil.
     *
     * @return list<array<string, mixed>>
     */
    public static function semua(): array
    {
        $hasil = [];

        foreach (RfmQuery::SEGMEN as $segmen) {
            $item = self::untuk($segmen);

            if ($item !== null) {
                $hasil[] = $item;
            }
        }

        usort($hasil, fn ($a, $b) => $a['prioritas'] <=> $b['prioritas']);

        return $hasil;
    }

    /**
     * Ringkasan segmen yang sudah ditempeli treatment-nya, bentuk yang dipakai
     * kartu segmen di dashboard.
     *
     * Segmen dengan nol anggota tetap ikut: manager perlu melihat bahwa
     * "Butuh Perhatian" kosong, dan itu kabar baik yang hilang kalau barisnya
     * tidak muncul sama sekali.
     *
     * @param  array<string, int>  $jumlahPerSegmen
     * @return list<array<string, mixed>>
     */
    public static function denganJumlah(array $jumlahPerSegmen): array
    {
        $total = array_sum($jumlahPerSegmen);

        return array_map(
            function (array $item) use ($jumlahPerSegmen, $total) {
                $jumlah = $jumlahPerSegmen[$item['segmen']] ?? 0;

                return $item + [
                    'jumlah_pelanggan' => $jumlah,
                    'persen' => $total > 0 ? round($jumlah * 100 / $total, 1) : 0.0,
                ];
            },
            self::semua(),
        );
    }
}
