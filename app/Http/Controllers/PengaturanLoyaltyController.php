<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Requests\UpdateKatalogRedeemRequest;
use App\Http\Requests\UpdatePengaturanLoyaltyRequest;
use App\Models\KatalogRedeem;
use App\Models\PengaturanLoyalty;
use App\Services\LoyaltyService;
use App\Support\LoyaltyRedemptionCatalog;
use Illuminate\Http\JsonResponse;

class PengaturanLoyaltyController extends Controller
{
    private const CONTOH_BELANJA = 50000;

    public function __construct(private readonly LoyaltyService $loyaltyService) {}

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->payloadPengaturan(PengaturanLoyalty::current())]);
    }

    public function update(UpdatePengaturanLoyaltyRequest $request): JsonResponse
    {
        $pengaturan = PengaturanLoyalty::query()->orderBy('id')->first() ?? new PengaturanLoyalty;

        $pengaturan->fill([
            'rupiah_per_poin' => (int) $request->validated('rupiah_per_poin'),
            'updated_by' => $request->user()->id,
        ])->save();

        return response()->json(['data' => $this->payloadPengaturan($pengaturan)]);
    }

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

        // Plafon potongan cuma punya arti di voucher diskon. Menolaknya di sini
        // lebih jujur daripada menyimpan angka yang tidak pernah dibaca.
        if (array_key_exists('maks_potongan', $data) && $item['tipe'] !== 'diskon') {
            throw new ApiException(
                'maks_potongan_tidak_berlaku',
                "{$item['label']} bertipe {$item['tipe']} — plafon potongan hanya berlaku untuk reward voucher diskon.",
                422,
            );
        }

        $baris = KatalogRedeem::firstOrNew(['kode' => $kode]);

        // Null dipertahankan sebagai null: kolom yang tidak pernah disetel
        // manager tetap "ikut nilai bawaan", bukan disalin jadi angka mati.
        $baris->fill([
            'poin' => $data['poin'] ?? $baris->poin ?? $item['poin_default'],
            'is_active' => $data['is_active'] ?? $baris->is_active ?? true,
            'maks_potongan' => $data['maks_potongan'] ?? $baris->maks_potongan,
            'min_subtotal' => $data['min_subtotal'] ?? $baris->min_subtotal,
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
            'maks_potongan' => $item['maks_potongan'] ?? null,
            'menu_gratis' => $item['tipe'] === 'gratis_menu' ? $item['menu'] : null,
            'setara_belanja' => $item['poin'] * $rate,
            'rupiah_per_poin_efektif' => $this->rupiahPerPoinEfektif($item),
            'diubah_pada' => $item['diubah_pada'],
        ];
    }

    /**
     * Rupiah maksimal yang didapat pelanggan per 1 poin yang dibayarkan.
     *
     * Ini kolom cek manager: acuannya nilai 1 poin = Rp 50, jadi angka DI ATAS
     * 50 berarti reward kemurahan (pelanggan dapat lebih banyak daripada
     * rupiah yang dia kumpulkan untuk membelinya). Nilai reward diambil dari
     * plafon potongan untuk voucher diskon, dan dari harga menu live untuk
     * reward gratis minuman — bukan angka hardcode, supaya naiknya harga menu
     * langsung terlihat di sini.
     *
     * @param  array<string, mixed>  $item
     */
    private function rupiahPerPoinEfektif(array $item): ?float
    {
        $poin = (int) $item['poin'];

        if ($poin <= 0) {
            return null;
        }

        $nilai = $item['tipe'] === 'diskon'
            ? $item['maks_potongan'] ?? null
            : $this->loyaltyService->hargaMenuGratis($item);

        return $nilai === null ? null : round($nilai / $poin, 1);
    }
}
