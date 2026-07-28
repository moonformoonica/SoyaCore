<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Requests\UpdateKatalogRedeemRequest;
use App\Http\Requests\UpdatePengaturanLoyaltyRequest;
use App\Models\KatalogRedeem;
use App\Models\PengaturanLoyalty;
use App\Support\LoyaltyRedemptionCatalog;
use Illuminate\Http\JsonResponse;

class PengaturanLoyaltyController extends Controller
{

    private const CONTOH_BELANJA = 50000;

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
        $baris = KatalogRedeem::firstOrNew(['kode' => $kode]);

        $baris->fill([
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
            'setara_belanja' => $item['poin'] * $rate,
            'diubah_pada' => $item['diubah_pada'],
        ];
    }
}
