<?php

namespace App\Http\Controllers;

use App\Http\Requests\LaporanRequest;
use App\Models\LaporanRfm;
use App\Models\LaporanSwitch;
use App\Models\LaporanTransaksi;
use App\Services\LaporanQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Label periode tetap untuk laporan snapshot (RFM & Switch).
     */
    private const PERIODE_LABEL = '1 Jun 2026 – 30 Jul 2026';

    public function __construct(private readonly LaporanQuery $query) {}

    /**
     * Meta cakupan data — dihitung LIVE (tidak di-hardcode). Helper untuk
     * date-range picker frontend.
     */
    public function meta(): JsonResponse
    {
        [$min, $max] = $this->query->resolveWindow(null, null);

        return response()->json([
            'tanggal_min' => $min,
            'tanggal_max' => $max,
            'total_baris' => LaporanTransaksi::count(),
            'ukuran' => LaporanTransaksi::query()->whereNotNull('ukuran')->distinct()->orderBy('ukuran')->pluck('ukuran'),
            'platform' => LaporanTransaksi::query()->whereNotNull('platform')->distinct()->orderBy('platform')->pluck('platform'),
            'segmen' => LaporanRfm::query()->distinct()->orderBy('segmen')->pluck('segmen'),
        ]);
    }

    public function ringkasan(LaporanRequest $request): JsonResponse
    {
        [$start, $end] = $this->query->resolveWindow($request->startInput(), $request->endInput());
        $ada = $this->query->adaData($start, $end);

        return $this->envelope($request->grain(), $start, $end, $ada, $this->query->ringkasan($start, $end));
    }

    public function timeSeries(LaporanRequest $request): JsonResponse
    {
        [$start, $end] = $this->query->resolveWindow($request->startInput(), $request->endInput());
        $ada = $this->query->adaData($start, $end);

        return $this->envelope($request->grain(), $start, $end, $ada, $this->query->timeSeries($start, $end, $request->grain()));
    }

    public function revenueUkuran(LaporanRequest $request): JsonResponse
    {
        [$start, $end] = $this->query->resolveWindow($request->startInput(), $request->endInput());
        $ada = $this->query->adaData($start, $end);

        return $this->envelope($request->grain(), $start, $end, $ada, $this->query->revenueUkuran($start, $end));
    }

    public function produkTerlaris(LaporanRequest $request): JsonResponse
    {
        [$start, $end] = $this->query->resolveWindow($request->startInput(), $request->endInput());
        $ada = $this->query->adaData($start, $end);
        $data = $this->query->produkTerlaris($start, $end, $request->by(), $request->limitOr(10));

        return $this->envelope($request->grain(), $start, $end, $ada, $data);
    }

    public function platform(LaporanRequest $request): JsonResponse
    {
        [$start, $end] = $this->query->resolveWindow($request->startInput(), $request->endInput());
        $ada = $this->query->adaData($start, $end);

        return $this->envelope($request->grain(), $start, $end, $ada, $this->query->platform($start, $end));
    }

    public function loyalty(LaporanRequest $request): JsonResponse
    {
        [$start, $end] = $this->query->resolveWindow($request->startInput(), $request->endInput());
        $ada = $this->query->adaData($start, $end);

        return $this->envelope($request->grain(), $start, $end, $ada, $this->query->loyalty($start, $end, $request->limitOr(10)));
    }

    /**
     * Statis periode-penuh. Filter opsional ?segmen=. ringkasan_segmen
     * dihitung dari seluruh snapshot (bukan hasil filter).
     */
    public function rfm(Request $request): JsonResponse
    {
        $segmen = $request->query('segmen');

        $data = LaporanRfm::query()
            ->when($segmen, fn ($q) => $q->where('segmen', $segmen))
            ->orderByDesc('rfm_total')
            ->orderBy('nama_pelanggan')
            ->get();

        $ringkasanSegmen = LaporanRfm::query()
            ->selectRaw('segmen, count(*) as jumlah')
            ->groupBy('segmen')
            ->orderByDesc('jumlah')
            ->pluck('jumlah', 'segmen');

        return response()->json([
            'periode_label' => self::PERIODE_LABEL,
            'ringkasan_segmen' => $ringkasanSegmen,
            'data' => $data,
        ]);
    }

    /**
     * Statis periode-penuh. Filter opsional substring ?rekomendasi=.
     */
    public function switch(Request $request): JsonResponse
    {
        $rekomendasi = $request->query('rekomendasi');

        $data = LaporanSwitch::query()
            ->when($rekomendasi, fn ($q) => $q->where('rekomendasi', 'like', '%'.$rekomendasi.'%'))
            ->orderByDesc('total_belanja')
            ->orderBy('nama_pelanggan')
            ->get();

        return response()->json([
            'periode_label' => self::PERIODE_LABEL,
            'data' => $data,
        ]);
    }

    /**
     * Envelope konsisten untuk endpoint yang bisa difilter tanggal.
     *
     * @param  array<string, mixed>|list<mixed>  $data
     */
    private function envelope(string $grain, ?string $start, ?string $end, bool $adaData, array $data): JsonResponse
    {
        return response()->json([
            'periode' => ['grain' => $grain, 'start' => $start, 'end' => $end],
            'data_tersedia' => $adaData,
            'data' => $data,
        ]);
    }
}
