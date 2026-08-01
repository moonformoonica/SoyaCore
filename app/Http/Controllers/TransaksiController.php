<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Requests\BayarRequest;
use App\Http\Requests\IndexTransaksiRequest;
use App\Http\Requests\RedeemPoinRequest;
use App\Http\Requests\StoreTransaksiRequest;
use App\Http\Requests\TerapkanDiskonRequest;
use App\Http\Resources\TransaksiResource;
use App\Models\Transaksi;
use App\Services\DaftarTransaksiQuery;
use App\Services\LaporanProjector;
use App\Services\LoyaltyService;
use App\Services\PembatalanService;
use App\Services\TransaksiService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class TransaksiController extends Controller
{
    /** Dipakai kalau `POST /api/transaksi/{id}/batal` (alias lama) tidak mengirim alasan. */
    private const ALASAN_ENDPOINT_LAMA = 'Dibatalkan lewat endpoint lama';

    public function __construct(
        private readonly TransaksiService $service,
        private readonly LoyaltyService $loyaltyService,
        private readonly DaftarTransaksiQuery $daftar,
        private readonly LaporanProjector $projector,
        private readonly PembatalanService $pembatalanService,
    ) {}

    public function index(IndexTransaksiRequest $request): AnonymousResourceCollection
    {
        $base = $this->daftar->bangun($request);

        // Ringkasan dihitung dari query yang sama SEBELUM paginate()
        // menempelkan limit/offset, angkanya harus mencakup seluruh hasil
        // filter, bukan halaman yang sedang dibuka.
        $ringkasan = $this->daftar->ringkasan($base);

        $arah = $request->urut() === 'terlama' ? 'asc' : 'desc';

        $paginator = $base->clone()
            ->with(['customer', 'user', 'dibayarOleh', 'detailTransaksi.menu'])
            // `id` sebagai tie-breaker: dua transaksi pada detik yang sama
            // tanpa ini bisa bertukar posisi antar halaman dan membuat satu
            // baris tampil dua kali sementara baris lain hilang.
            ->orderBy('created_at', $arah)
            ->orderBy('id', $arah)
            ->paginate($request->perPage());

        return TransaksiResource::collection($paginator)->additional(['meta' => $ringkasan]);
    }

    public function store(StoreTransaksiRequest $request): TransaksiResource
    {
        $data = $request->validated();

        $customer = $this->service->findOrCreateCustomer($data['customer'] ?? null);

        $transaksi = Transaksi::create([
            'customer_id' => $customer?->id,
            'user_id' => $request->user()->id,
            'sumber' => 'kasir',
            'kode_pesanan' => $this->service->generateKodePesanan(),
            'total' => 0,
            'status' => 'pending',
        ]);

        return new TransaksiResource($this->muat($transaksi));
    }

    public function show(Transaksi $transaksi): TransaksiResource
    {
        return new TransaksiResource($this->muat($transaksi));
    }

    public function diskon(TerapkanDiskonRequest $request, Transaksi $transaksi): TransaksiResource
    {
        $this->service->pastikanPending($transaksi);

        $data = $request->validated();
        $this->service->terapkanDiskon($transaksi, $data['tipe'], $data['nilai']);

        return new TransaksiResource($this->muat($transaksi));
    }

    public function redeemPoin(RedeemPoinRequest $request, Transaksi $transaksi): TransaksiResource
    {
        $this->loyaltyService->redeemPoin($transaksi, $request->validated('kode_redeem'));

        return new TransaksiResource($this->muat($transaksi));
    }

    public function bayar(BayarRequest $request, Transaksi $transaksi): TransaksiResource
    {
        $this->service->pastikanPending($transaksi);

        if (! $transaksi->detailTransaksi()->exists()) {
            throw new ApiException(
                'items_kosong',
                'Transaksi belum punya item, tambahkan item dulu sebelum pembayaran.',
                422,
            );
        }

        DB::transaction(function () use ($request, $transaksi) {
            $transaksi->update([
                'metode_bayar' => $request->validated('metode_bayar'),
                'status' => 'lunas',
                'waktu_lunas' => now(),
                // `user_id` SENGAJA tidak ditulis di sini. Versi sebelumnya
                // menimpanya dengan akun yang menandai lunas, sehingga pada
                // pergantian akun (Kasir 1 membuat pesanan, Kasir 2 menutup
                // pembayaran) jejak Kasir 1 hilang tanpa error apa pun.
                'dibayar_oleh' => $request->user()->id,
            ]);

            $this->loyaltyService->earnPoinFor($transaksi);

            // Sinkron, di dalam transaksi database yang sama, bukan queued
            // job. Laporan harus bisa di-export real-time; proyeksi yang antre
            // membuat transaksi baru tidak muncul di file Excel yang
            // di-download semenit kemudian, tanpa ada yang menyadarinya.
            $this->projector->sinkronkan($transaksi->refresh());
        });

        return new TransaksiResource($this->muat($transaksi));
    }

    /**
     * Alias lama pembatalan PENUH, dipertahankan supaya frontend yang sudah
     * jalan tidak rusak. Sekarang ikut melewati alur pembatalan baru:
     * mengembalikan poin redeem, mencatat dokumen pembatalan, dan
     * menyinkronkan proyeksi laporan.
     */
    public function batal(Request $request, Transaksi $transaksi): TransaksiResource
    {
        $this->pembatalanService->batalkan(
            $transaksi,
            $request->user(),
            // Alasan boleh kosong di alias ini, diisi teks tetap supaya
            // kolomnya tetap jujur soal dari mana pembatalannya datang.
            self::ALASAN_ENDPOINT_LAMA,
        );

        return new TransaksiResource($this->muat($transaksi->refresh()));
    }

    private function muat(Transaksi $transaksi): Transaksi
    {
        return $transaksi->load(['customer', 'user', 'dibayarOleh', 'detailTransaksi.menu']);
    }
}
