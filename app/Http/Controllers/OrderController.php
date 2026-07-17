<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

/**
 * Endpoint publik self-order (SoyaScan) — tanpa auth, pelanggan belum login.
 */
class OrderController extends Controller
{
    public function __construct(private readonly OrderService $service) {}

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $transaksi = $this->service->buatOrder($request->validated());
        $transaksi->load('detailTransaksi.menu');

        $nomorMeja = $transaksi->detailTransaksi->first()?->nomor_meja;

        return response()->json([
            'kode_pesanan' => $transaksi->kode_pesanan,
            'status' => $transaksi->status,
            'nomor_meja' => $nomorMeja,
            'total' => $transaksi->total,
            'items' => $transaksi->detailTransaksi->map(fn ($d) => [
                'nama_menu' => $d->menu->nama,
                'qty' => $d->qty,
                'harga_satuan' => $d->harga_satuan,
                'subtotal' => $d->subtotal,
            ])->values(),
            'pesan' => "Pesanan diterima! Silakan bayar di kasir (Cash/QRIS) dengan menyebutkan kode pesanan {$transaksi->kode_pesanan}.",
        ], 201);
    }
}
