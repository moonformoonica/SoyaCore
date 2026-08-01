<?php

namespace App\Services;

use App\Models\LaporanTransaksi;
use App\Support\KalenderIndonesia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class LaporanQuery
{
    private const UKURAN_NON_MINUMAN = ['Cup', 'Pack'];

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
        );

        $hasil = [];
        foreach ($buckets as $label => $angka) {
            $hasil[] = [
                'ukuran' => $label,
                'jumlah_terjual' => $angka['jumlah_terjual'],
                'total_revenue' => $angka['total_revenue'],
                'jumlah_transaksi' => $angka['jumlah_transaksi'],
                'rata_rata_transaksi' => $angka['jumlah_transaksi'] > 0
                    ? (int) round($angka['total_revenue'] / $angka['jumlah_transaksi'])
                    : 0,
            ];
        }

        usort($hasil, fn ($a, $b) => $b['total_revenue'] <=> $a['total_revenue']);

        return $hasil;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function produkTerlaris(?string $start, ?string $end, string $by, int $limit): array
    {
        $order = $by === 'revenue' ? 'sum(total)' : 'sum(qty)';

        $rows = $this->base($start, $end)
            ->selectRaw('nama_produk, rasa, sum(qty) as qty, sum(total) as revenue, count(*) as transaksi')
            ->groupBy('nama_produk', 'rasa')
            ->orderByRaw($order.' desc')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'nama_produk' => $r->nama_produk,
            'rasa' => $r->rasa,
            'qty' => (int) $r->qty,
            'revenue' => (int) $r->revenue,
            'transaksi' => (int) $r->transaksi,
        ])->all();
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
     * @return array<string, array<string, int>>
     */
    private function gabungBucketTidakDiketahui(
        $rows,
        string $kolom,
        callable $angka,
        bool $sembunyikan,
    ): array {
        $buckets = [];

        foreach ($rows as $row) {
            $nilai = $row->{$kolom};
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
