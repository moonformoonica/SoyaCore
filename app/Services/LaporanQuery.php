<?php

namespace App\Services;

use App\Models\LaporanTransaksi;
use App\Models\Menu;
use App\Support\GolonganUkuran;
use App\Support\KalenderIndonesia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class LaporanQuery
{
    private const UKURAN_NON_MINUMAN = ['Cup', 'Pack'];

    /**
     * Grain yang mengagregasi per NAMA HARI, bukan per tanggal: seluruh Senin
     * dijumlahkan menjadi satu bucket.
     *
     * Menjawab pertanyaan yang berbeda dari grain `harian`. `harian` menjawab
     * "bagaimana penjualan bergerak dari hari ke hari", grain ini menjawab
     * "hari apa yang paling ramai".
     */
    public const GRAIN_HARI_DALAM_MINGGU = 'hari_dalam_minggu';

    public const ARAH_TERTINGGI = 'tertinggi';

    public const ARAH_TERENDAH = 'terendah';

    /**
     * Label untuk baris yang nilai pengelompokannya kosong (`NULL` atau string
     * kosong). Tanpa label, frontend menerima key kosong dan chart-nya
     * merendernya sebagai batang tak bernama, inilah bucket "other" yang
     * diminta hilang.
     *
     * Datanya TIDAK dihapus dari database: menyembunyikannya adalah keputusan
     * tampilan, dan angka ringkasan harus tetap bisa direkonsiliasi dengan
     * total keseluruhan.
     */
    public const LABEL_TIDAK_DIKETAHUI = 'Tidak diketahui';

    /**
     * Filter kasir opsional. Diterapkan di {@see self::base()} supaya SATU
     * sekali pasang otomatis berlaku ke seluruh agregasi, ringkasan, time
     * series, revenue per ukuran, produk terlaris, tanpa tiap method perlu
     * tahu ada filter ini.
     */
    private ?int $kasirUserId = null;

    /**
     * Salinan yang tersaring ke satu akun kasir. Mengembalikan instance baru,
     * bukan mengubah yang ini: LaporanQuery diinject ke controller dari
     * container, dan menyimpan state filter di sana akan bocor ke request lain
     * pada worker yang sama.
     */
    public function untukKasir(?int $kasirUserId): self
    {
        $klon = clone $this;
        $klon->kasirUserId = $kasirUserId;

        return $klon;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    public function resolveWindow(?string $start, ?string $end): array
    {
        $start ??= LaporanTransaksi::min('tanggal');
        $end ??= LaporanTransaksi::max('tanggal');

        $start = $start ? Carbon::parse($start)->toDateString() : null;
        $end = $end ? Carbon::parse($end)->toDateString() : null;

        return [$start, $end];
    }

    public function adaData(?string $start, ?string $end): bool
    {
        return $this->base($start, $end)->exists();
    }

    /**
     * @return array<string, int>
     */
    public function ringkasan(?string $start, ?string $end): array
    {
        $base = $this->base($start, $end);

        $totalRevenue = (int) $base->clone()->sum('total');
        $totalTransaksi = (int) $base->clone()->count();
        $totalQty = (int) $base->clone()->sum('qty');
        $totalPoin = (int) $base->clone()->sum('poin_loyalty');
        $pelangganUnik = (int) $base->clone()->distinct()->count('nama_pelanggan');

        return [
            'total_revenue' => $totalRevenue,
            'total_transaksi' => $totalTransaksi,
            'total_qty' => $totalQty,
            'rata_rata_transaksi' => $totalTransaksi > 0 ? (int) round($totalRevenue / $totalTransaksi) : 0,
            'total_poin' => $totalPoin,
            'pelanggan_unik' => $pelangganUnik,
        ];
    }

    /**
     * @return list<array{periode: string, periode_label: string, hari: ?string, revenue: int, transaksi: int, qty: int}>
     */
    public function timeSeries(?string $start, ?string $end, string $grain): array
    {
        if ($grain === self::GRAIN_HARI_DALAM_MINGGU) {
            return $this->timeSeriesHariDalamMinggu($start, $end);
        }

        $rows = $this->base($start, $end)
            ->orderBy('tanggal')
            ->get(['tanggal', 'total', 'qty']);

        $buckets = [];
        foreach ($rows as $row) {
            $key = $this->bucketKey($row->tanggal, $grain);
            $buckets[$key] ??= $this->bucketKosong($key, $grain);
            $buckets[$key]['revenue'] += (int) $row->total;
            $buckets[$key]['transaksi'] += 1;
            $buckets[$key]['qty'] += (int) $row->qty;
        }

        $buckets = $this->isiPeriodeKosong($buckets, $start, $end, $grain);

        ksort($buckets);

        return array_values($buckets);
    }

    /**
     * Tren per NAMA HARI: seluruh Senin dijumlahkan menjadi satu bucket.
     *
     * Ditulis sebagai jalur tersendiri, bukan menumpang mesin bucket tanggal di
     * atas. Mesin itu berasumsi setiap key adalah tanggal yang bisa di-parse
     * dan di-increment (lihat {@see self::awalBucket()} dan
     * {@see self::isiPeriodeKosong()}), sementara key di sini adalah nomor hari
     * 1-7. Memaksakannya lewat sana berarti menambah cabang khusus di empat
     * method sekaligus.
     *
     * KENAPA ADA `rata_rata_per_hari`. Total mentah antar hari TIDAK sebanding:
     * dalam rentang 9 minggu, sebuah hari bisa muncul 9 kali sementara hari
     * lain 10 kali, semata karena posisi tanggal awal dan akhir. Membaca total
     * mentah membuat hari yang kebetulan muncul lebih sering terlihat lebih
     * ramai padahal tidak. Pembaginya jumlah kemunculan hari itu di KALENDER
     * rentang tersebut, bukan jumlah hari yang ada transaksinya, supaya hari
     * buka yang nihil penjualan tetap ikut menekan rata-ratanya.
     *
     * @return list<array<string, mixed>>
     */
    private function timeSeriesHariDalamMinggu(?string $start, ?string $end): array
    {
        $rows = $this->base($start, $end)->get(['tanggal', 'total', 'qty']);

        if ($rows->isEmpty()) {
            // Konsisten dengan isiPeriodeKosong(): rentang tanpa data sama
            // sekali mengembalikan array kosong, bukan tujuh batang nol yang
            // menyamarkan bahwa memang tidak ada datanya.
            return [];
        }

        $buckets = [];
        foreach (KalenderIndonesia::HARI as $iso => $nama) {
            $buckets[$iso] = [
                'periode' => (string) $iso,
                'periode_label' => $nama,
                'hari' => $nama,
                'revenue' => 0,
                'transaksi' => 0,
                'qty' => 0,
            ];
        }

        foreach ($rows as $row) {
            $iso = $row->tanggal->isoWeekday();
            $buckets[$iso]['revenue'] += (int) $row->total;
            $buckets[$iso]['transaksi'] += 1;
            $buckets[$iso]['qty'] += (int) $row->qty;
        }

        $kemunculan = $this->kemunculanHari(
            $start ?? $rows->min('tanggal')->toDateString(),
            $end ?? $rows->max('tanggal')->toDateString(),
        );

        foreach ($buckets as $iso => $bucket) {
            $jumlahHari = $kemunculan[$iso] ?? 0;

            $buckets[$iso]['jumlah_hari'] = $jumlahHari;
            $buckets[$iso]['rata_rata_per_hari'] = $jumlahHari > 0
                ? (int) round($bucket['revenue'] / $jumlahHari)
                : 0;
        }

        // Kunci 1-7 mengikuti ISO (Senin=1), jadi ksort() menghasilkan urutan
        // Senin sampai Minggu. Mengurutkan berdasarkan nama hari akan
        // menghasilkan Jumat, Kamis, Minggu, Rabu, Sabtu, Selasa, Senin.
        ksort($buckets);

        return array_values($buckets);
    }

    /**
     * Berapa kali tiap hari (ISO 1-7) muncul di kalender antara dua tanggal,
     * inklusif di kedua ujung.
     *
     * @return array<int, int>
     */
    private function kemunculanHari(string $start, string $end): array
    {
        $kursor = Carbon::parse($start)->startOfDay();
        $batas = Carbon::parse($end)->startOfDay();

        $kemunculan = array_fill_keys(array_keys(KalenderIndonesia::HARI), 0);

        while ($kursor->lessThanOrEqualTo($batas)) {
            $kemunculan[$kursor->isoWeekday()]++;
            $kursor->addDay();
        }

        return $kemunculan;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function revenueUkuran(?string $start, ?string $end, bool $sembunyikanTidakDiketahui = false): array
    {
        $rows = $this->base($start, $end)
            // Dessert & cookies (`Cup`/`Pack`) sengaja di luar cakupan endpoint
            // ini. Baris ber-`ukuran` NULL adalah hal ketiga, ukurannya
            // memang tidak terekam, dan harus tetap ikut supaya totalnya bisa
            // direkonsiliasi. `NOT IN` sendirian membuangnya diam-diam, karena
            // `NULL NOT IN (...)` bernilai NULL, bukan true.
            ->where(fn (Builder $q) => $q
                ->whereNull('ukuran')
                ->orWhereNotIn('ukuran', self::UKURAN_NON_MINUMAN))
            ->selectRaw('ukuran, sum(qty) as jumlah_terjual, sum(total) as total_revenue, count(*) as jumlah_transaksi')
            ->groupBy('ukuran')
            ->get();

        $buckets = $this->gabungBucketTidakDiketahui(
            $rows,
            'ukuran',
            fn ($r) => [
                'jumlah_terjual' => (int) $r->jumlah_terjual,
                'total_revenue' => (int) $r->total_revenue,
                'jumlah_transaksi' => (int) $r->jumlah_transaksi,
            ],
            $sembunyikanTidakDiketahui,
            // Impor CSV menulis `250 ml`, katalog menu menulis `250ml`. Tanpa
            // penyeragaman ini keduanya tampil sebagai dua batang terpisah, dan
            // pertanyaan "ukuran berapa ml yang paling sering keluar" terjawab
            // salah, satu ukuran terbelah jadi dua potongan kecil.
            GolonganUkuran::labelBaku(...),
        );

        $hasil = [];
        foreach ($buckets as $label => $angka) {
            $hasil[] = [
                'ukuran' => $label,
                'golongan' => GolonganUkuran::dari($label),
                'jumlah_terjual' => $angka['jumlah_terjual'],
                'total_revenue' => $angka['total_revenue'],
                'jumlah_transaksi' => $angka['jumlah_transaksi'],
                'rata_rata_transaksi' => $angka['jumlah_transaksi'] > 0
                    ? (int) round($angka['total_revenue'] / $angka['jumlah_transaksi'])
                    : 0,
            ];
        }

        return $this->urutkanPerGolongan($this->tempelPersenGolongan($hasil));
    }

    /**
     * Total per golongan (cup / botol / lainnya) untuk sebuah rentang.
     *
     * Dihitung dari hasil {@see self::revenueUkuran()} yang sama, BUKAN dengan
     * query terpisah. Dua query yang menjawab pertanyaan yang sama pasti akan
     * lepas sinkron begitu salah satunya diubah, dan gejalanya berupa subtotal
     * yang tidak sama dengan penjumlahan barisnya di halaman yang sama.
     *
     * @return list<array<string, mixed>>
     */
    public function revenueGolongan(?string $start, ?string $end, bool $sembunyikanTidakDiketahui = false): array
    {
        $total = [];

        foreach ($this->revenueUkuran($start, $end, $sembunyikanTidakDiketahui) as $baris) {
            $golongan = $baris['golongan'];

            $total[$golongan] ??= [
                'golongan' => $golongan,
                'jumlah_terjual' => 0,
                'total_revenue' => 0,
                'jumlah_transaksi' => 0,
                'ukuran_terlaris' => null,
            ];

            $total[$golongan]['jumlah_terjual'] += $baris['jumlah_terjual'];
            $total[$golongan]['total_revenue'] += $baris['total_revenue'];
            $total[$golongan]['jumlah_transaksi'] += $baris['jumlah_transaksi'];

            // Barisnya sudah terurut jumlah terjual menurun di dalam golongan,
            // jadi yang pertama masuk adalah yang paling sering keluar. Inilah
            // angka yang diminta: "ukuran berapa ml yang paling sering keluar".
            $total[$golongan]['ukuran_terlaris'] ??= $baris['ukuran'];
        }

        return array_values($total);
    }

    /**
     * Porsi tiap ukuran DI DALAM golongannya sendiri, bukan terhadap seluruh
     * penjualan. Porsi inilah yang menjawab "ukuran berapa ml yang paling
     * sering keluar", karena membandingkan 250ml dengan Reguler tidak berarti
     * apa-apa, keduanya kemasan yang berbeda peruntukan.
     *
     * @param  list<array<string, mixed>>  $hasil
     * @return list<array<string, mixed>>
     */
    private function tempelPersenGolongan(array $hasil): array
    {
        $totalGolongan = [];
        foreach ($hasil as $baris) {
            $totalGolongan[$baris['golongan']] = ($totalGolongan[$baris['golongan']] ?? 0) + $baris['jumlah_terjual'];
        }

        foreach ($hasil as $i => $baris) {
            $pembagi = $totalGolongan[$baris['golongan']] ?? 0;

            $hasil[$i]['persen_dari_golongan'] = $pembagi > 0
                ? round($baris['jumlah_terjual'] * 100 / $pembagi, 1)
                : 0.0;
        }

        return $hasil;
    }

    /**
     * Golongan lebih dulu (cup, botol, lainnya), lalu jumlah terjual MENURUN di
     * dalam golongan itu, supaya ukuran yang paling sering keluar terbaca di
     * baris pertama tanpa perlu dicari.
     *
     * @param  list<array<string, mixed>>  $hasil
     * @return list<array<string, mixed>>
     */
    private function urutkanPerGolongan(array $hasil): array
    {
        $urutanGolongan = array_flip(GolonganUkuran::semua());

        usort($hasil, function (array $a, array $b) use ($urutanGolongan) {
            $selisih = ($urutanGolongan[$a['golongan']] ?? PHP_INT_MAX)
                <=> ($urutanGolongan[$b['golongan']] ?? PHP_INT_MAX);

            if ($selisih !== 0) {
                return $selisih;
            }

            return [$b['jumlah_terjual'], $b['total_revenue']]
                <=> [$a['jumlah_terjual'], $a['total_revenue']];
        });

        return $hasil;
    }

    /**
     * @return list<array<string, mixed>>
     */
    /**
     * @param  string  $arah  {@see self::ARAH_TERTINGGI} (terlaris) atau
     *                        {@see self::ARAH_TERENDAH} (kurang diminati).
     * @param  bool  $sertakanNol  Ikutkan menu aktif yang belum pernah terjual.
     * @return list<array<string, mixed>>
     */
    public function produkTerlaris(
        ?string $start,
        ?string $end,
        string $by,
        int $limit,
        string $arah = self::ARAH_TERTINGGI,
        bool $sertakanNol = false,
    ): array {
        $rows = $this->base($start, $end)
            ->selectRaw('nama_produk, rasa, sum(qty) as qty, sum(total) as revenue, count(*) as transaksi')
            ->groupBy('nama_produk', 'rasa')
            ->get();

        $hasil = $rows->map(fn ($r) => [
            'nama_produk' => (string) $r->nama_produk,
            'rasa' => $r->rasa,
            'qty' => (int) $r->qty,
            'revenue' => (int) $r->revenue,
            'transaksi' => (int) $r->transaksi,
        ])->all();

        if ($sertakanNol) {
            $hasil = $this->tambahMenuTanpaPenjualan($hasil);
        }

        return array_slice($this->urutkanProduk($hasil, $by, $arah), 0, $limit);
    }

    /**
     * Pengurutan dan pemotongan dilakukan di PHP, bukan lewat `ORDER BY ...
     * LIMIT` di SQL.
     *
     * Alasannya bukan selera: begitu `sertakan_nol` aktif, daftar menu tanpa
     * penjualan digabungkan setelah query, jadi SQL tidak pernah melihat baris
     * itu dan pemotongannya akan salah. Menaruh pengurutan di satu tempat untuk
     * kedua mode menjamin urutan dan tie-breaker-nya identik. Jumlah barisnya
     * kecil, satu baris per kombinasi produk+rasa, bukan per transaksi.
     *
     * @param  list<array<string, mixed>>  $hasil
     * @return list<array<string, mixed>>
     */
    private function urutkanProduk(array $hasil, string $by, string $arah): array
    {
        $kunci = $by === 'revenue' ? 'revenue' : 'qty';
        $naik = $arah === self::ARAH_TERENDAH;

        usort($hasil, function (array $a, array $b) use ($kunci, $naik) {
            $selisih = $naik
                ? $a[$kunci] <=> $b[$kunci]
                : $b[$kunci] <=> $a[$kunci];

            if ($selisih !== 0) {
                return $selisih;
            }

            // Tie-breaker WAJIB, bukan kerapian. Di daftar terendah sangat
            // banyak produk bernilai sama (belasan produk sama-sama qty 1);
            // tanpa pengurutan kedua, urutan batang ditentukan database dan
            // berubah-ubah tiap kali halaman dimuat, sehingga chart terlihat
            // bergoyang padahal datanya tidak berubah.
            return [$a['nama_produk'], (string) $a['rasa']]
                <=> [$b['nama_produk'], (string) $b['rasa']];
        });

        return $hasil;
    }

    /**
     * Menu aktif yang TIDAK punya satu pun baris penjualan di rentang ini,
     * ditambahkan dengan qty 0.
     *
     * Tanpa ini, grafik "kurang diminati" tidak pernah bisa benar: menu yang
     * tak pernah laku sama sekali tidak punya baris di `laporan_transaksi`,
     * sehingga justru yang paling kurang diminati adalah yang tidak kelihatan.
     * Gejalanya, batang terendah di grafik bernilai 1, bukan 0, dan itu terbaca
     * seolah semua menu laku.
     *
     * Digabungkan di PHP karena `laporan_transaksi` menyimpan nama produk
     * sebagai teks (snapshot), tanpa foreign key ke `menu`, jadi tidak ada
     * kolom yang bisa di-JOIN dengan aman.
     *
     * @param  list<array<string, mixed>>  $hasil
     * @return list<array<string, mixed>>
     */
    private function tambahMenuTanpaPenjualan(array $hasil): array
    {
        $sudahAda = [];
        foreach ($hasil as $baris) {
            $sudahAda[$this->kunciProduk($baris['nama_produk'], $baris['rasa'])] = true;
        }

        // Satu menu punya beberapa baris (satu per ukuran) dengan nama & rasa
        // yang sama, sementara laporan mengelompokkan per nama+rasa. Penjaga
        // $sudahAda sekaligus membuang duplikat itu.
        foreach (Menu::query()->where('is_active', true)->get(['nama', 'rasa']) as $menu) {
            $kunci = $this->kunciProduk((string) $menu->nama, $menu->rasa);

            if (isset($sudahAda[$kunci])) {
                continue;
            }

            $sudahAda[$kunci] = true;

            $hasil[] = [
                'nama_produk' => (string) $menu->nama,
                'rasa' => $menu->rasa,
                'qty' => 0,
                'revenue' => 0,
                'transaksi' => 0,
            ];
        }

        return $hasil;
    }

    private function kunciProduk(string $nama, ?string $rasa): string
    {
        return mb_strtolower(trim($nama)).'|'.mb_strtolower(trim((string) $rasa));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function platform(?string $start, ?string $end, bool $sembunyikanTidakDiketahui = false): array
    {
        $rows = $this->base($start, $end)
            ->selectRaw('platform, count(*) as transaksi, sum(total) as revenue, sum(qty) as qty')
            ->groupBy('platform')
            ->get();

        $buckets = $this->gabungBucketTidakDiketahui(
            $rows,
            'platform',
            fn ($r) => [
                'transaksi' => (int) $r->transaksi,
                'revenue' => (int) $r->revenue,
                'qty' => (int) $r->qty,
            ],
            $sembunyikanTidakDiketahui,
        );

        $hasil = [];
        foreach ($buckets as $label => $angka) {
            $hasil[] = ['platform' => $label] + $angka;
        }

        usort($hasil, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

        return $hasil;
    }

    /**
     * @return array{total_poin: int, top_pelanggan: list<array<string, mixed>>}
     */
    public function loyalty(?string $start, ?string $end, int $limit): array
    {
        $totalPoin = (int) $this->base($start, $end)->sum('poin_loyalty');

        $top = $this->base($start, $end)
            ->selectRaw('nama_pelanggan, sum(poin_loyalty) as poin, count(*) as transaksi')
            ->whereNotNull('nama_pelanggan')
            ->groupBy('nama_pelanggan')
            ->orderByRaw('sum(poin_loyalty) desc')
            ->limit($limit)
            ->get();

        return [
            'total_poin' => $totalPoin,
            'top_pelanggan' => $top->map(fn ($r) => [
                'nama_pelanggan' => $r->nama_pelanggan,
                'poin' => (int) $r->poin,
                'transaksi' => (int) $r->transaksi,
            ])->all(),
        ];
    }

    public function base(?string $start, ?string $end): Builder
    {
        $query = LaporanTransaksi::query();

        if ($start !== null) {
            $query->whereDate('tanggal', '>=', $start);
        }
        if ($end !== null) {
            $query->whereDate('tanggal', '<=', $end);
        }

        if ($this->kasirUserId !== null) {
            $query->where('kasir_user_id', $this->kasirUserId);
        }

        return $query;
    }

    private function bucketKey(Carbon $tanggal, string $grain): string
    {
        return match ($grain) {
            'mingguan' => $tanggal->copy()->startOfWeek()->format('Y-m-d'), // ISO: Senin
            'bulanan' => $tanggal->format('Y-m'),
            'tahunan' => $tanggal->format('Y'),
            default => $tanggal->format('Y-m-d'), // harian
        };
    }

    /**
     * `periode` (key mentah) TIDAK dihapus, ia yang dipakai sorting dan
     * sebagai key stabil di frontend. `periode_label` dan `hari` ditambahkan di
     * sebelahnya sebagai teks siap tampil.
     *
     * @return array{periode: string, periode_label: string, hari: ?string, revenue: int, transaksi: int, qty: int}
     */
    private function bucketKosong(string $key, string $grain): array
    {
        $awal = $this->awalBucket($key, $grain);

        return [
            'periode' => $key,
            'periode_label' => match ($grain) {
                'mingguan' => KalenderIndonesia::labelMingguan($awal),
                'bulanan' => KalenderIndonesia::labelBulanan($awal),
                'tahunan' => $key,
                default => KalenderIndonesia::labelHarian($awal),
            },
            // Nama hari hanya bermakna pada grain harian. Bucket mingguan
            // mencakup tujuh hari sekaligus, jadi mengisinya akan menyesatkan.
            'hari' => $grain === 'harian' ? KalenderIndonesia::namaHari($awal) : null,
            'revenue' => 0,
            'transaksi' => 0,
            'qty' => 0,
        ];
    }

    private function awalBucket(string $key, string $grain): Carbon
    {
        return match ($grain) {
            'bulanan' => Carbon::createFromFormat('Y-m-d', $key.'-01')->startOfDay(),
            'tahunan' => Carbon::createFromFormat('Y-m-d', $key.'-01-01')->startOfDay(),
            default => Carbon::createFromFormat('Y-m-d', $key)->startOfDay(),
        };
    }

    /**
     * Hari (atau minggu/bulan/tahun) tanpa transaksi tetap dikeluarkan sebagai
     * bucket bernilai 0, bukan dilewati. Grafik tren yang melompati hari kosong
     * menyambung dua titik yang berjauhan dan membaca naik-turun yang tidak
     * pernah terjadi.
     *
     * @param  array<string, array<string, mixed>>  $buckets
     * @return array<string, array<string, mixed>>
     */
    private function isiPeriodeKosong(array $buckets, ?string $start, ?string $end, string $grain): array
    {
        // Rentang yang sama sekali tidak punya transaksi tetap mengembalikan
        // array kosong, bukan deretan bucket bernilai 0. Yang dibutuhkan grafik
        // adalah hari kosong DI ANTARA hari yang ada isinya; membangun grafik
        // rata nol untuk rentang tanpa data sama sekali cuma menyembunyikan
        // fakta itu, dan `data_tersedia: false` sudah menyampaikannya.
        if ($buckets === []) {
            return [];
        }

        // Batas dari filter kalau ada; kalau tidak, dari data yang benar-benar
        // ada.
        $start ??= min(array_keys($buckets));
        $end ??= max(array_keys($buckets));

        $kursor = $this->awalBucket($this->bucketKey(Carbon::parse($start), $grain), $grain);
        $batas = Carbon::parse($end);

        $langkah = match ($grain) {
            'mingguan' => fn (Carbon $c) => $c->addWeek(),
            'bulanan' => fn (Carbon $c) => $c->addMonth(),
            'tahunan' => fn (Carbon $c) => $c->addYear(),
            default => fn (Carbon $c) => $c->addDay(),
        };

        while ($kursor->lessThanOrEqualTo($batas)) {
            $key = $this->bucketKey($kursor, $grain);
            $buckets[$key] ??= $this->bucketKosong($key, $grain);
            $langkah($kursor);
        }

        return $buckets;
    }

    /**
     * Menggabungkan baris ber-nilai kosong (`NULL` maupun string kosong)
     * menjadi satu bucket berlabel {@see self::LABEL_TIDAK_DIKETAHUI}.
     *
     * Digabung di PHP, bukan dengan `COALESCE` di SQL, karena NULL dan '' bisa
     * sama-sama ada dan `GROUP BY` menghasilkan dua baris terpisah untuk hal
     * yang sama.
     *
     * @param  Collection<int, Model>  $rows
     * @param  callable(mixed): array<string, int>  $angka
     * @param  ?callable(?string): ?string  $normalisasi  Menyeragamkan ejaan
     *                                                    sebelum dikelompokkan.
     *                                                    `null` = pakai apa
     *                                                    adanya, dipakai kolom
     *                                                    yang sudah dinormalkan
     *                                                    saat ditulis (mis.
     *                                                    `platform`).
     * @return array<string, array<string, int>>
     */
    private function gabungBucketTidakDiketahui(
        $rows,
        string $kolom,
        callable $angka,
        bool $sembunyikan,
        ?callable $normalisasi = null,
    ): array {
        $buckets = [];

        foreach ($rows as $row) {
            $nilai = $row->{$kolom};

            if ($normalisasi !== null) {
                $nilai = $normalisasi($nilai);
            }

            $kosong = $nilai === null || trim((string) $nilai) === '';

            // Dibuang SEBELUM diakumulasi, supaya ia juga tidak ikut
            // menghitung persentase di sisi frontend maupun total di sini.
            if ($kosong && $sembunyikan) {
                continue;
            }

            $label = $kosong ? self::LABEL_TIDAK_DIKETAHUI : (string) $nilai;

            foreach ($angka($row) as $kunci => $jumlah) {
                $buckets[$label][$kunci] = ($buckets[$label][$kunci] ?? 0) + $jumlah;
            }
        }

        return $buckets;
    }
}
