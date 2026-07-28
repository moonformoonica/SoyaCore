<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Requests\BayarRequest;
use App\Http\Requests\RedeemPoinRequest;
use App\Http\Requests\StoreTransaksiRequest;
use App\Http\Requests\TerapkanDiskonRequest;
use App\Http\Resources\TransaksiResource;
use App\Models\Transaksi;
use App\Services\LoyaltyService;
use App\Services\TransaksiService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class TransaksiController extends Controller
{
    public function __construct(
        private readonly TransaksiService $service,
        private readonly LoyaltyService $loyaltyService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Transaksi::with(['customer', 'user', 'detailTransaksi.menu'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('tanggal')) {
            $query->whereDate('created_at', $request->query('tanggal'));
        }

        // Dipakai kartu statistik kasir untuk scope "transaksi milik sendiri".
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->query('user_id'));
        }

        $perPage = min((int) $request->query('per_page', 15), 200) ?: 15;

        return TransaksiResource::collection($query->paginate($perPage));
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

    public function redeemPoin(RedeemPoinRequest $request, Transaksi $transaksi): TransaksiResource
    {
        $this->loyaltyService->redeemPoin($transaksi, $request->validated('kode_redeem'));

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

        DB::transaction(function () use ($request, $transaksi) {
            $transaksi->update([
                'metode_bayar' => $request->validated('metode_bayar'),
                'status' => 'lunas',
                'waktu_lunas' => now(),
                'user_id' => $request->user()->id,
            ]);

            $this->loyaltyService->earnPoinFor($transaksi);
        });

        return new TransaksiResource($transaksi->load(['customer', 'user', 'detailTransaksi.menu']));
    }

    public function batal(Transaksi $transaksi): TransaksiResource
    {
        $this->service->pastikanPending($transaksi);

        $transaksi->update(['status' => 'batal']);

        return new TransaksiResource($transaksi->load(['customer', 'user', 'detailTransaksi.menu']));
    }
}
