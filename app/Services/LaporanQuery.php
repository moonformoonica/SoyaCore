<?php

namespace App\Services;

use App\Models\LaporanTransaksi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class LaporanQuery
{

    private const UKURAN_NON_MINUMAN = ['Cup', 'Pack'];

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
     * @return list<array{periode: string, revenue: int, transaksi: int, qty: int}>
     */
    public function timeSeries(?string $start, ?string $end, string $grain): array
    {
        $rows = $this->base($start, $end)
            ->orderBy('tanggal')
            ->get(['tanggal', 'total', 'qty']);

        $buckets = [];
        foreach ($rows as $row) {
            $key = $this->bucketKey($row->tanggal, $grain);
            if (! isset($buckets[$key])) {
                $buckets[$key] = ['periode' => $key, 'revenue' => 0, 'transaksi' => 0, 'qty' => 0];
            }
            $buckets[$key]['revenue'] += (int) $row->total;
            $buckets[$key]['transaksi'] += 1;
            $buckets[$key]['qty'] += (int) $row->qty;
        }

        ksort($buckets);

        return array_values($buckets);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function revenueUkuran(?string $start, ?string $end): array
    {
        $rows = $this->base($start, $end)
            ->whereNotIn('ukuran', self::UKURAN_NON_MINUMAN)
            ->selectRaw('ukuran, sum(qty) as jumlah_terjual, sum(total) as total_revenue, count(*) as jumlah_transaksi')
            ->groupBy('ukuran')
            ->orderByRaw('sum(total) desc')
            ->get();

        return $rows->map(function ($r) {
            $jumlahTransaksi = (int) $r->jumlah_transaksi;
            $totalRevenue = (int) $r->total_revenue;

            return [
                'ukuran' => $r->ukuran,
                'jumlah_terjual' => (int) $r->jumlah_terjual,
                'total_revenue' => $totalRevenue,
                'jumlah_transaksi' => $jumlahTransaksi,
                'rata_rata_transaksi' => $jumlahTransaksi > 0 ? (int) round($totalRevenue / $jumlahTransaksi) : 0,
            ];
        })->all();
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
    public function platform(?string $start, ?string $end): array
    {
        $rows = $this->base($start, $end)
            ->selectRaw('platform, count(*) as transaksi, sum(total) as revenue, sum(qty) as qty')
            ->groupBy('platform')
            ->orderByRaw('sum(total) desc')
            ->get();

        return $rows->map(fn ($r) => [
            'platform' => $r->platform,
            'transaksi' => (int) $r->transaksi,
            'revenue' => (int) $r->revenue,
            'qty' => (int) $r->qty,
        ])->all();
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
}
