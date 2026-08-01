<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Requests\StoreOrderRequest;
use App\Models\PengaturanToko;
use App\Models\Transaksi;
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
                'level_sugar_label' => OpsiMinuman::labelSugar($d->level_sugar, $d->menu->rasa),
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

    /**
     * Status sebuah pesanan — dipanggil berulang oleh layar "Menunggu
     * Pembayaran" di SoyaScan (tiap 4 detik) supaya layar pelanggan berubah
     * sendiri begitu kasir menandai lunas.
     *
     * SENGAJA HANYA MENGEMBALIKAN `status`, tidak lebih. Kode pesanan pendek
     * dan berurutan (`#A01`, `#A02`, `#K001`), jadi siapa pun bisa menebaknya
     * dari luar tanpa pernah memesan. Nama pelanggan, nomor WA, dan rincian
     * item TIDAK boleh ikut di sini — endpoint ini publik tanpa auth. Kalau
     * suatu saat SoyaScan butuh rincian pesanan setelah lunas, itu harus lewat
     * jalur lain yang mengikat pelanggan ke pesanannya (mis. token sekali
     * pakai yang dikembalikan `POST /api/order`), bukan dengan menambah field
     * di sini.
     *
     * Nilai `status` yang mungkin muncul: `pending`, `lunas`, `batal`,
     * `batal_sebagian`.
     */
    public function status(string $kodePesanan): JsonResponse
    {
        $kode = $this->normalisasiKode($kodePesanan);

        // Penomoran di-reset tiap hari (lihat OrderService dan
        // TransaksiService), jadi `#A01` hari ini dan `#A01` kemarin
        // dua-duanya ada di tabel. Yang dimaksud pemanggil selalu yang
        // terbaru — `first()` polos di sini akan mengembalikan status pesanan
        // kemarin dan layar pelanggan akan langsung salah.
        $transaksi = Transaksi::query()
            ->where('kode_pesanan', $kode)
            ->orderByDesc('id')
            ->first();

        if ($transaksi === null) {
            throw new ApiException(
                'pesanan_tidak_ditemukan',
                "Pesanan {$kode} tidak ditemukan.",
                404,
            );
        }

        return response()->json(['status' => $transaksi->status]);
    }

    /**
     * Menerima `#A01`, `A01`, maupun `a01`. Tanda `#` adalah pemisah fragment
     * di URL, jadi pemanggil harus meng-encode-nya jadi `%23` — yang paling
     * gampang terlewat saat menyusun URL-nya, dan akibatnya kode terkirim
     * tanpa `#` sama sekali. Daripada memaksa SoyaScan menebak bentuk yang
     * benar, bentuk apa pun dinormalkan di sini.
     */
    private function normalisasiKode(string $kode): string
    {
        $bersih = mb_strtoupper(trim($kode));

        return str_starts_with($bersih, '#') ? $bersih : '#'.$bersih;
    }
}
