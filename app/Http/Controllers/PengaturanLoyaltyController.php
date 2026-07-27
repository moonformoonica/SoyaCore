<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Requests\UpdateKatalogRedeemRequest;
use App\Http\Requests\UpdatePengaturanLoyaltyRequest;
use App\Models\KatalogRedeem;
use App\Models\PengaturanLoyalty;
use App\Support\LoyaltyRedemptionCatalog;
use Illuminate\Http\JsonResponse;

/**
 * Pengaturan Loyalty & LoyalSeed (halaman Settings manager).
 *
 * Sebelum ini rate poin dan biaya poin tiap reward hanya ada sebagai angka
 * di kode, jadi revisi sekecil apa pun harus lewat deploy. Controller ini
 * yang membuat keduanya bisa diubah manager.
 *
 * Baca: kasir & manager (kasir butuh rate untuk menampilkan estimasi poin,
 * dan butuh katalog untuk merender tombol redeem).
 * Tulis: manager saja — dijaga middleware 'role:manager' di routes.
 */
class PengaturanLoyaltyController extends Controller
{
    /**
     * Nilai belanja contoh di response. Sengaja Rp 50.000 supaya angkanya
     * gampang dicek manual saat verifikasi ("bayar 50rb, poin nambah berapa").
     */
    private const CONTOH_BELANJA = 50000;

    /**
     * GET /api/pengaturan/loyalty
     */
    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->payloadPengaturan(PengaturanLoyalty::current())]);
    }

    /**
     * PATCH /api/pengaturan/loyalty — manager only.
     *
     * Tidak retroaktif: saldo poin pelanggan dan point_earned transaksi lama
     * tidak disentuh. Rate baru hanya berlaku untuk transaksi yang ditandai
     * lunas SETELAH ini.
     */
    public function update(UpdatePengaturanLoyaltyRequest $request): JsonResponse
    {
        $pengaturan = PengaturanLoyalty::query()->orderBy('id')->first() ?? new PengaturanLoyalty;

        $pengaturan->fill([
            'rupiah_per_poin' => (int) $request->validated('rupiah_per_poin'),
            'updated_by' => $request->user()->id,
        ])->save();

        return response()->json(['data' => $this->payloadPengaturan($pengaturan)]);
    }

    /**
     * GET /api/pengaturan/loyalty/katalog
     */
    public function katalog(): JsonResponse
    {
        $rate = PengaturanLoyalty::rupiahPerPoin();

        return response()->json([
            'data' => array_values(array_map(
                fn (array $item) => $this->payloadItem($item, $rate),
                LoyaltyRedemptionCatalog::all(),
            )),
            'meta' => ['rupiah_per_poin' => $rate],
        ]);
    }

    /**
     * PATCH /api/pengaturan/loyalty/katalog/{kode} — manager only.
     *
     * Hanya `poin` dan `is_active` yang bisa diubah; struktur item (tipe,
     * persen, menu gratis) tetap milik LoyaltyRedemptionCatalog. Baris
     * override baru dibuat saat kode ini pertama kali diedit.
     */
    public function updateKatalog(UpdateKatalogRedeemRequest $request, string $kode): JsonResponse
    {
        $item = LoyaltyRedemptionCatalog::find($kode);

        if ($item === null) {
            throw new ApiException(
                'kode_redeem_invalid',
                "Kode redeem '{$kode}' tidak ada di katalog. Kode yang tersedia: "
                    .implode(', ', LoyaltyRedemptionCatalog::kodeTersedia()).'.',
                404,
            );
        }

        $data = $request->validated();
        $baris = KatalogRedeem::firstOrNew(['kode' => $kode]);

        $baris->fill([
            // yang tidak dikirim dipertahankan: nilai override lama kalau
            // barisnya sudah ada, kalau belum jatuh ke bawaan kode
            'poin' => $data['poin'] ?? $baris->poin ?? $item['poin_default'],
            'is_active' => $data['is_active'] ?? $baris->is_active ?? true,
            'updated_by' => $request->user()->id,
        ])->save();

        $rate = PengaturanLoyalty::rupiahPerPoin();

        return response()->json([
            'data' => $this->payloadItem(LoyaltyRedemptionCatalog::find($kode), $rate),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadPengaturan(PengaturanLoyalty $pengaturan): array
    {
        $rate = (int) $pengaturan->rupiah_per_poin;

        return [
            'rupiah_per_poin' => $rate,
            'rupiah_per_poin_default' => PengaturanLoyalty::RUPIAH_PER_POIN_DEFAULT,
            'batas' => [
                'min' => PengaturanLoyalty::RUPIAH_PER_POIN_MIN,
                'max' => PengaturanLoyalty::RUPIAH_PER_POIN_MAX,
            ],
            // contoh terhitung, bukan teks statis — supaya UI Settings bisa
            // menampilkan akibat perubahan tanpa menduplikasi rumusnya
            'contoh' => [
                'belanja' => self::CONTOH_BELANJA,
                'poin_didapat' => intdiv(self::CONTOH_BELANJA, $rate),
            ],
            'diperbarui_pada' => $pengaturan->updated_at?->toIso8601String(),
            'diperbarui_oleh' => $pengaturan->updated_by === null
                ? null
                : $pengaturan->updatedBy?->nama,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function payloadItem(array $item, int $rate): array
    {
        return [
            'kode' => $item['kode'],
            'label' => $item['label'],
            'tipe' => $item['tipe'],
            'poin' => $item['poin'],
            'poin_default' => $item['poin_default'],
            'is_active' => $item['is_active'],
            'persen' => $item['persen'] ?? null,
            'min_subtotal' => $item['min_subtotal'] ?? 0,
            'menu_gratis' => $item['tipe'] === 'gratis_menu' ? $item['menu'] : null,
            // Rupiah belanja yang harus dikumpulkan pelanggan untuk menebus
            // reward ini pada rate yang berlaku sekarang. Ini angka yang
            // menentukan reward-nya masuk akal atau tidak: kalau rate naik
            // 10x tanpa poin katalog diturunkan, angka ini ikut naik 10x dan
            // praktis tidak ada pelanggan yang sampai.
            'setara_belanja' => $item['poin'] * $rate,
            'diubah_pada' => $item['diubah_pada'],
        ];
    }
}
