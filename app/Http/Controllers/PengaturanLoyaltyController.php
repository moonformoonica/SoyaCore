<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Requests\StoreKatalogRedeemRequest;
use App\Http\Requests\UpdateKatalogRedeemRequest;
use App\Http\Requests\UpdatePengaturanLoyaltyRequest;
use App\Models\KatalogRedeem;
use App\Models\Menu;
use App\Models\PengaturanLoyalty;
use App\Models\Transaksi;
use App\Services\LoyaltyService;
use App\Support\LoyaltyRedemptionCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

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

    /**
     * Reward baru buatan manager.
     *
     * Sampai sekarang delapan jenis di {@see LoyaltyRedemptionCatalog::defaults()}
     * adalah semuanya yang pernah bisa ada, dan menambah satu reward promo
     * berarti menunggu deploy. Barisnya disimpan lengkap (label, tipe, dan
     * struktur hadiahnya), bukan sebagai override, karena tidak ada definisi di
     * kode yang bisa ditimpanya.
     */
    public function storeKatalog(StoreKatalogRedeemRequest $request): JsonResponse
    {
        $data = $request->validated();
        $diskon = $data['tipe'] === 'diskon';

        $menu = $diskon ? null : Menu::with('kategori')->find($data['menu_id']);

        // DUA PAGAR YANG MENUTUP CELAH SAMA: reward yang tersimpan rapi tapi
        // gagal justru saat pelanggan menukarkannya di depan kasir.
        // LoyaltyService::cariMenuGratis() mencari hadiah dengan syarat
        // `is_active = true` DAN nama kategorinya cocok, jadi menu yang tidak
        // memenuhi keduanya tidak akan pernah bisa jadi hadiah. Dropdown di
        // halaman Loyalty sudah menyaringnya, tapi pagarnya tetap di sini juga:
        // yang menentukan boleh atau tidak bukan tampilan.
        if (! $diskon && $menu?->kategori === null) {
            throw new ApiException(
                'menu_tanpa_kategori',
                'Menu itu belum punya kategori, jadi hadiahnya tidak bisa ditemukan lagi saat ditukarkan. Lengkapi kategorinya di halaman Menu dulu.',
                422,
            );
        }

        if (! $diskon && $menu?->is_active === false) {
            throw new ApiException(
                'menu_nonaktif',
                "Menu '{$menu->nama}' sedang nonaktif, jadi hadiahnya tidak bisa ditemukan saat ditukarkan. Aktifkan dulu menunya di halaman Menu.",
                422,
            );
        }

        $baris = KatalogRedeem::create([
            'kode' => $this->kodeUnik($data['label']),
            'is_custom' => true,
            'label' => $data['label'],
            'tipe' => $data['tipe'],
            'poin' => $data['poin'],
            'is_active' => true,
            'persen' => $diskon ? $data['persen'] : null,
            'maks_potongan' => $diskon ? $data['maks_potongan'] : null,
            'min_subtotal' => $diskon ? ($data['min_subtotal'] ?? 0) : null,
            'kategori' => $menu?->kategori->nama,
            'menu' => $menu?->nama,
            'ukuran' => $menu === null ? null : $this->ejaanUkuran((string) $menu->ukuran),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => $this->payloadItem(
                LoyaltyRedemptionCatalog::find($baris->kode),
                PengaturanLoyalty::rupiahPerPoin(),
            ),
        ], 201);
    }

    /**
     * Hapus permanen reward buatan manager.
     *
     * DUA PENOLAKAN YANG SENGAJA BERBEDA PESANNYA:
     *
     * 1. Reward bawaan tidak bisa dihapus. Logika redeem-nya ada di PHP dan
     *    tidak ikut hilang bersama barisnya, jadi "menghapus" cuma akan
     *    memunculkannya lagi dengan setelan bawaan pada request berikutnya.
     *    Yang dimaksud manager hampir selalu menonaktifkan.
     * 2. Reward yang pernah ditukarkan tidak bisa dihapus. `transaksi.kode_redeem`
     *    menyimpan kodenya, dan menghapus definisinya membuat riwayat penukaran
     *    lama kehilangan artinya tanpa error apa pun. Diarahkan ke nonaktifkan,
     *    aturan yang sama dengan akun kasir yang sudah punya transaksi.
     */
    public function destroyKatalog(string $kode): JsonResponse
    {
        if (LoyaltyRedemptionCatalog::bawaan($kode)) {
            throw new ApiException(
                'reward_bawaan',
                'Reward bawaan tidak bisa dihapus, hanya bisa dinonaktifkan supaya riwayat penukarannya tetap terbaca.',
                422,
            );
        }

        $baris = KatalogRedeem::query()->where('kode', $kode)->where('is_custom', true)->first();

        if ($baris === null) {
            throw new ApiException('reward_tidak_ditemukan', "Reward '{$kode}' tidak ada di katalog.", 404);
        }

        if (Transaksi::query()->where('kode_redeem', $kode)->exists()) {
            throw new ApiException(
                'reward_sudah_dipakai',
                "Reward '{$baris->label}' sudah pernah ditukarkan pelanggan, jadi tidak bisa dihapus. Nonaktifkan saja supaya riwayat penukarannya tetap terbaca.",
                422,
            );
        }

        $baris->delete();

        return response()->json(['message' => "Reward '{$baris->label}' dihapus."]);
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
                "{$item['label']} bertipe {$item['tipe']}, plafon potongan hanya berlaku untuk reward voucher diskon.",
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
     * Kode dibuat dari labelnya, tidak diketik manager.
     *
     * Kode ikut tersimpan di `transaksi.kode_redeem` dan terbaca di riwayat,
     * jadi lebih baik diturunkan dari nama yang sudah dia tulis daripada jadi
     * satu isian lagi yang harus dipikirkan dan bisa bentrok. Akhiran angka
     * dipakai kalau namanya menghasilkan kode yang sudah ada.
     */
    private function kodeUnik(string $label): string
    {
        $dasar = Str::slug($label, '_') ?: 'reward';
        $dipakai = LoyaltyRedemptionCatalog::kodeTersedia();

        if (! in_array($dasar, $dipakai, true)) {
            return $dasar;
        }

        $urutan = 2;
        while (in_array($dasar.'_'.$urutan, $dipakai, true)) {
            $urutan++;
        }

        return $dasar.'_'.$urutan;
    }

    /**
     * Ejaan ukuran yang diterima saat mencari menu hadiah, urut preferensi.
     *
     * "Reguler" dan "Regular" dianggap ejaan yang sama, mengikuti katalog
     * bawaan. Data menu memakai keduanya, dan reward yang cuma menerima satu
     * ejaan akan gagal ditukarkan pada menu yang mengeja versi satunya.
     *
     * @return list<string>
     */
    private function ejaanUkuran(string $ukuran): array
    {
        $alias = match (mb_strtolower($ukuran)) {
            'reguler' => 'Regular',
            'regular' => 'Reguler',
            default => null,
        };

        return $alias === null ? [$ukuran] : [$ukuran, $alias];
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
            // Frontend memakainya untuk memutuskan tombol Hapus: reward bawaan
            // hanya bisa dinonaktifkan, yang kustom boleh dihapus permanen.
            'bawaan' => $item['bawaan'],
            'ukuran' => $item['tipe'] === 'gratis_menu' ? array_values($item['ukuran']) : null,
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
     * reward gratis minuman, bukan angka hardcode, supaya naiknya harga menu
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
