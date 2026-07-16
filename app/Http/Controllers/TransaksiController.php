<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Requests\BayarRequest;
use App\Http\Requests\StoreTransaksiRequest;
use App\Http\Requests\TerapkanDiskonRequest;
use App\Http\Resources\TransaksiResource;
use App\Models\Transaksi;
use App\Services\TransaksiService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TransaksiController extends Controller
{
    public function __construct(private readonly TransaksiService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Transaksi::with(['customer', 'user', 'detailTransaksi.menu'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('tanggal')) {
            $query->whereDate('created_at', $request->query('tanggal'));
        }

        return TransaksiResource::collection($query->paginate(15));
    }

    public function store(StoreTransaksiRequest $request): TransaksiResource
    {
        $data = $request->validated();

        $customer = $this->service->findOrCreateCustomer($data['customer'] ?? null);

        $transaksi = Transaksi::create([
            'customer_id' => $customer?->id,
            'user_id' => $request->user()->id,
            'kode_pesanan' => $this->service->generateKodePesanan(),
            'total' => 0,
            'status' => 'pending',
        ]);

        return new TransaksiResource($transaksi->load(['customer', 'user', 'detailTransaksi.menu']));
    }

    public function show(Transaksi $transaksi): TransaksiResource
    {
        return new TransaksiResource($transaksi->load(['customer', 'user', 'detailTransaksi.menu']));
    }

    public function diskon(TerapkanDiskonRequest $request, Transaksi $transaksi): TransaksiResource
    {
        $this->service->pastikanPending($transaksi);

        $data = $request->validated();
        $this->service->terapkanDiskon($transaksi, $data['tipe'], $data['nilai']);

        return new TransaksiResource($transaksi->load(['customer', 'user', 'detailTransaksi.menu']));
    }

    public function bayar(BayarRequest $request, Transaksi $transaksi): TransaksiResource
    {
        $this->service->pastikanPending($transaksi);

        if (! $transaksi->detailTransaksi()->exists()) {
            throw new ApiException(
                'items_kosong',
                'Transaksi belum punya item — tambahkan item dulu sebelum pembayaran.',
                422,
            );
        }

        $transaksi->update([
            'metode_bayar' => $request->validated('metode_bayar'),
            'status' => 'lunas',
            'waktu_lunas' => now(),
            'point_earned' => 1, // update tabel loyalty adalah scope LoyalSeed (M3)
        ]);

        return new TransaksiResource($transaksi->load(['customer', 'user', 'detailTransaksi.menu']));
    }

    public function batal(Transaksi $transaksi): TransaksiResource
    {
        $this->service->pastikanPending($transaksi);

        $transaksi->update(['status' => 'batal']);

        return new TransaksiResource($transaksi->load(['customer', 'user', 'detailTransaksi.menu']));
    }
}
