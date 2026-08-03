<?php

namespace App\Http\Controllers;

use App\Http\Requests\LaporanRequest;
use App\Models\LaporanTransaksi;
use App\Services\LaporanQuery;
use App\Services\LoyaltyService;
use App\Services\RfmQuery;
use App\Services\SwitchQuery;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    private const PERIODE_LABEL = '1 Jun 2026 - 30 Jul 2026';

    public function __construct(
        private readonly LaporanQuery $query,
        private readonly RfmQuery $rfm,
        private readonly SwitchQuery $switch,
    ) {}

    public function meta(): JsonResponse
    {
        [$min, $max] = $this->query->resolveWindow(null, null);

        return response()->json([
            'tanggal_min' => $min,
            'tanggal_max' => $max,
            'total_baris' => LaporanTransaksi::count(),
            'ukuran' => LaporanTransaksi::query()->whereNotNull('ukuran')->distinct()->orderBy('ukuran')->pluck('ukuran'),
            'platform' => LaporanTransaksi::query()->whereNotNull('platform')->distinct()->orderBy('platform')->pluck('platform'),
            // Daftar tetap dari RfmQuery, bukan `distinct` dari tabel snapshot.
            // Segmen yang kebetulan sedang kosong tetap harus muncul di
            // dropdown filter, kalau tidak manager mengira pilihannya hilang.
            'segmen' => RfmQuery::SEGMEN,
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

        return $this->envelope(
            $request->grain(), $start, $end, $ada,
            $this->query->revenueUkuran($start, $end, $request->sembunyikanTidakDiketahui()),
            'Khusus minuman, dessert & cookies (Cup/Pack) tidak termasuk.',
        );
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

        return $this->envelope(
            $request->grain(), $start, $end, $ada,
            $this->query->platform($start, $end, $request->sembunyikanTidakDiketahui()),
        );
    }

    public function loyalty(LaporanRequest $request, LoyaltyService $loyaltyService): JsonResponse
    {
        [$start, $end] = $this->query->resolveWindow($request->startInput(), $request->endInput());
        $ada = $this->query->adaData($start, $end);

        $data = $this->query->loyalty($start, $end, $request->limitOr(10));

        // Poin yang DIDAPAT pelanggan dibaca dari proyeksi `laporan_transaksi`,
        // sedangkan penukaran hanya tercatat di `transaksi.poin_ditukar`, jadi
        // angka ini datang dari sumber lain dan ditempelkan di sini. Rentangnya
        // sama supaya keduanya menjawab periode yang sama.
        $data['reward_ditukar'] = $loyaltyService->rewardDitukar($start, $end);

        return $this->envelope($request->grain(), $start, $end, $ada, $data);
    }

    /**
     * DIHITUNG dari `laporan_transaksi`, tidak lagi dibaca dari snapshot
     * `laporan_rfm`. Tabel snapshot itu tidak pernah berubah setelah CSV
     * di-impor, jadi pelanggan yang baru belanja hari ini tidak pernah muncul
     * dan recency pelanggan lama tidak pernah bertambah. Rinciannya di
     * {@see RfmQuery}.
     */
    public function rfm(LaporanRequest $request): JsonResponse
    {
        // Ikut rentang tanggal halaman Laporan. Sebelumnya seluruh RFM dihitung
        // dari SELURUH isi `laporan_transaksi` apa pun tanggal yang dipasang,
        // jadi kartu segmen, donut, dan tabelnya diam saja sementara grafik di
        // atasnya berubah. Dua angka di satu layar yang menjawab periode
        // berbeda tanpa ada yang menjelaskan.
        [$start, $end] = $this->query->resolveWindow($request->startInput(), $request->endInput());

        $segmen = $request->validated()['segmen'] ?? null;

        $semua = $this->rfm->semua($start, $end);

        // Ringkasan segmen dihitung dari SELURUH pelanggan di rentang itu, bukan
        // dari hasil yang sudah tersaring segmen. Kalau ikut tersaring, donut
        // chart-nya berubah jadi satu potong penuh begitu manager memilih satu
        // segmen.
        $ringkasanSegmen = $this->rfm->ringkasanSegmen($semua);

        $data = $segmen === null || $segmen === ''
            ? $semua
            : array_values(array_filter($semua, fn ($b) => $b['segmen'] === $segmen));

        return response()->json([
            'periode_label' => $this->rfm->periode($start, $end) ?? self::PERIODE_LABEL,
            'data_tersedia' => $semua !== [],
            'ringkasan_segmen' => $ringkasanSegmen,
            'data' => $data,
        ]);
    }

    /**
     * DIHITUNG dari `laporan_transaksi`, tidak lagi dibaca dari snapshot
     * `laporan_switch`. Alasan dan batasannya di {@see SwitchQuery}.
     */
    public function switch(LaporanRequest $request): JsonResponse
    {
        // Ikut rentang tanggal halaman Laporan, alasannya sama dengan rfm().
        [$start, $end] = $this->query->resolveWindow($request->startInput(), $request->endInput());

        $rekomendasi = trim((string) ($request->validated()['rekomendasi'] ?? ''));

        $data = $this->switch->semua($start, $end);

        if ($rekomendasi !== '') {
            // Pencarian bebas huruf besar-kecil, sama seperti perilaku
            // `LIKE` di SQLite sebelumnya, supaya mengetik "upsize" dan
            // "Upsize" memberi hasil yang sama.
            $data = array_values(array_filter(
                $data,
                fn ($b) => mb_stripos($b['rekomendasi'], $rekomendasi) !== false,
            ));
        }

        return response()->json([
            'periode_label' => $this->switch->periode($start, $end) ?? self::PERIODE_LABEL,
            'data' => $data,
        ]);
    }

    /**
     * Envelope konsisten untuk endpoint yang bisa difilter tanggal.
     * $catatan diisi kalau cakupan data endpoint itu perlu dijelaskan
     * ke user (mis. revenue per ukuran yang khusus minuman).
     *
     * @param  array<string, mixed>|list<mixed>  $data
     */
    private function envelope(string $grain, ?string $start, ?string $end, bool $adaData, array $data, ?string $catatan = null): JsonResponse
    {
        return response()->json(array_filter([
            'periode' => ['grain' => $grain, 'start' => $start, 'end' => $end],
            'data_tersedia' => $adaData,
            'catatan' => $catatan,
            'data' => $data,
        ], fn ($v) => $v !== null));
    }
}
