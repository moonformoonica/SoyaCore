<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexPembatalanRequest;
use App\Http\Requests\StorePembatalanRequest;
use App\Http\Resources\PembatalanResource;
use App\Models\Loyalty;
use App\Models\Pembatalan;
use App\Models\Transaksi;
use App\Services\PembatalanService;
use App\Support\WaktuToko;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Pembatalan / koreksi pesanan yang salah. BUKAN pengembalian uang — lihat
 * docs/pembatalan-pesanan.md.
 */
class PembatalanController extends Controller
{
    public function __construct(private readonly PembatalanService $service) {}

    public function store(StorePembatalanRequest $request, Transaksi $transaksi): JsonResponse
    {
        $pembatalan = $this->service->batalkan(
            $transaksi,
            $request->user(),
            $request->validated('alasan'),
            $request->items(),
        );

        return response()->json([
            'data' => new PembatalanResource($pembatalan),
            'status_transaksi' => $transaksi->fresh()->status,
            // Kasir perlu menyebutkan saldo terkini ke pelanggan saat itu juga —
            // kalau tidak ada di response, dia harus membuka halaman lain
            // sementara pelanggannya masih berdiri di depan konter.
            'saldo_poin_pelanggan' => $this->saldoPoin($transaksi),
        ], 201);
    }

    /**
     * Riwayat pembatalan satu transaksi.
     */
    public function index(Transaksi $transaksi): AnonymousResourceCollection
    {
        return PembatalanResource::collection(
            $transaksi->pembatalan()
                ->with(['user', 'items.detailTransaksi.menu'])
                ->orderByDesc('id')
                ->get()
        );
    }

    /**
     * Semua pembatalan (manager) — dipotong tanggal dokumen pembatalannya,
     * bukan tanggal penjualan aslinya, karena yang ditanya adalah "siapa
     * membatalkan apa periode ini".
     */
    public function semua(IndexPembatalanRequest $request): AnonymousResourceCollection
    {
        $query = Pembatalan::query()
            ->with(['user', 'transaksi', 'items.detailTransaksi.menu'])
            // Diurutkan `id`, bukan `created_at`: dua pembatalan pada detik
            // yang sama (koreksi beberapa item berurutan) urutannya jadi
            // sembarang kalau memakai timestamp saja.
            ->orderByDesc('id');

        [$mulai, $selesai] = $request->rentang();

        if ($mulai !== null) {
            $query->where('created_at', '>=', WaktuToko::awalHari($mulai));
        }
        if ($selesai !== null) {
            $query->where('created_at', '<=', WaktuToko::akhirHari($selesai));
        }
        if ($request->userId() !== null) {
            $query->where('user_id', $request->userId());
        }

        // Ringkasan dihitung dari query SEBELUM paginate() menempelkan
        // limit/offset ke builder-nya, supaya angkanya mencakup seluruh hasil
        // filter — bukan cuma halaman yang sedang dibuka.
        $meta = [
            'jumlah_pembatalan' => $query->clone()->count(),
            'nilai_dibatalkan' => (int) $query->clone()->sum('nilai_dibatalkan'),
            'poin_ditarik' => (int) $query->clone()->sum('poin_ditarik'),
            'poin_dikembalikan' => (int) $query->clone()->sum('poin_dikembalikan'),
        ];

        return PembatalanResource::collection($query->paginate($request->perPage()))
            ->additional(['meta' => $meta]);
    }

    private function saldoPoin(Transaksi $transaksi): ?int
    {
        if ($transaksi->customer_id === null) {
            return null; // walk-in, tidak punya saldo poin
        }

        return Loyalty::where('customer_id', $transaksi->customer_id)->first()?->poinBerlaku();
    }
}
