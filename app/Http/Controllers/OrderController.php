<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Models\PengaturanToko;
use App\Services\OrderService;
use App\Support\OpsiMinuman;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $service) {}

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $transaksi = $this->service->buatOrder($request->validated());
        $transaksi->load('detailTransaksi.menu');

        $payload = [
            'kode_pesanan' => $transaksi->kode_pesanan,
            'status' => $transaksi->status,
            // PERUBAHAN KONTRAK: `nomor_meja` sudah tidak ada lagi di response.
            'total' => $transaksi->total,
            'metode_bayar' => $transaksi->metode_bayar, // null kalau pelanggan tidak memilih

            'items' => $transaksi->detailTransaksi->map(fn ($d) => [
                'nama_menu' => $d->menu->nama,
                'ukuran' => $d->menu->ukuran,
                'qty' => $d->qty,
                'harga_satuan' => $d->harga_satuan,
                'subtotal' => $d->subtotal,
                'level_sugar' => $d->level_sugar,
                'level_sugar_label' => OpsiMinuman::labelSugar($d->level_sugar),
                'level_ice' => $d->level_ice,
                'level_ice_label' => OpsiMinuman::labelIce($d->level_ice),
            ])->values(),
            'pesan' => "Pesanan diterima! Silakan bayar di kasir (Cash/QRIS) dengan menyebutkan kode pesanan {$transaksi->kode_pesanan}.",
        ];

        // Disertakan HANYA saat pembayarannya QRIS, supaya halaman pembayaran
        // SoyaScan bisa langsung menampilkan gambarnya tanpa request kedua.
        // `null` isinya kalau manager belum pernah mengunggah QRIS-nya.
        if ($transaksi->metode_bayar === 'qris') {
            $payload['qris_url'] = PengaturanToko::current()->qrisUrl();
        }

        return response()->json($payload, 201);
    }
}
