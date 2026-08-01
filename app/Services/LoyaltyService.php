<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Loyalty;
use App\Models\Menu;
use App\Models\PengaturanLoyalty;
use App\Models\Transaksi;
use App\Support\LoyaltyRedemptionCatalog;
use Illuminate\Support\Facades\DB;

class LoyaltyService
{
    public function __construct(private readonly TransaksiService $transaksiService) {}

    public function earnPoinFor(Transaksi $transaksi): void
    {
        if ($transaksi->loyalty_applied_at !== null) {
            return;
        }

        DB::transaction(function () use ($transaksi) {
            $rupiahPerPoin = PengaturanLoyalty::rupiahPerPoin();
            $poinDidapat = intdiv((int) $transaksi->total, $rupiahPerPoin);

            if ($transaksi->customer_id !== null) {
                $loyalty = $this->loyaltyTerkunci($transaksi->customer_id);

                // Dinolkan dulu kalau sudah lewat masa berlaku, supaya poin
                // yang seharusnya hangus tidak ikut hidup lagi karena ada
                // transaksi baru.
                $loyalty->hanguskanBilaKedaluwarsa();

                $loyalty->poin += $poinDidapat;
                // Jam kedaluwarsa di-reset tiap transaksi: yang masih belanja
                // tidak pernah kehilangan poin.
                $loyalty->poin_kedaluwarsa_pada = now()->addMonths(Loyalty::BULAN_KEDALUWARSA);
                $loyalty->save();
            }

            $transaksi->forceFill([
                'point_earned' => $poinDidapat,
                'rupiah_per_poin' => $rupiahPerPoin,
                'loyalty_applied_at' => now(),
            ])->save();
        });
    }

    public function redeemPoin(Transaksi $transaksi, string $kodeRedeem): Transaksi
    {
        $this->transaksiService->pastikanPending($transaksi);

        if ($transaksi->kode_redeem !== null) {
            throw new ApiException(
                'transaksi_sudah_redeem',
                "Transaksi ini sudah redeem '{$transaksi->kode_redeem}', hanya satu redemption per transaksi. Kalau salah pilih, batalkan transaksi dan buat baru.",
                409,
            );
        }

        $item = LoyaltyRedemptionCatalog::find($kodeRedeem);
        if ($item === null) {
            throw new ApiException(
                'kode_redeem_invalid',
                "Kode redeem '{$kodeRedeem}' tidak ada di katalog.",
                422,
            );
        }

        if ($item['is_active'] === false) {
            throw new ApiException(
                'kode_redeem_nonaktif',
                "{$item['label']} sedang dinonaktifkan oleh manager dan belum bisa ditukar.",
                422,
            );
        }

        if ($transaksi->customer_id === null) {
            throw new ApiException(
                'transaksi_tanpa_customer',
                'Redeem poin butuh customer terdaftar di transaksi ini (tambahkan customer saat membuat transaksi).',
                422,
            );
        }

        DB::transaction(function () use ($transaksi, $kodeRedeem, $item) {
            $loyalty = $this->loyaltyTerkunci($transaksi->customer_id);
            $loyalty->hanguskanBilaKedaluwarsa();

            if ($loyalty->poin < $item['poin']) {
                $kurang = $item['poin'] - $loyalty->poin;
                throw new ApiException(
                    'poin_kurang',
                    "Poin kurang {$kurang} untuk {$item['label']} (butuh {$item['poin']}, saat ini {$loyalty->poin} poin).",
                    422,
                );
            }

            if ($item['tipe'] === 'diskon') {
                $subtotal = (int) $transaksi->detailTransaksi()->sum('subtotal');

                if ($subtotal < $item['min_subtotal']) {
                    throw new ApiException(
                        'minimal_pembelian_kurang',
                        "{$item['label']} butuh minimal pembelian Rp ".number_format($item['min_subtotal'], 0, ',', '.').', subtotal saat ini Rp '.number_format($subtotal, 0, ',', '.').'.',
                        422,
                    );
                }

                $this->transaksiService->terapkanDiskon(
                    $transaksi,
                    'custom_persen',
                    $item['persen'],
                    $item['maks_potongan'],
                );
            } else {
                $menu = $this->cariMenuGratis($item);

                if ($menu === null) {
                    throw new ApiException(
                        'menu_reward_tidak_tersedia',
                        "Menu reward untuk {$item['label']} tidak ditemukan/nonaktif di database. Hubungi manager untuk mengecek data menu.",
                        422,
                    );
                }

                $transaksi->detailTransaksi()->create([
                    'menu_id' => $menu->id,
                    'qty' => 1,
                    'harga_satuan' => $menu->harga,
                    'subtotal' => 0,
                    'is_reward' => true,
                    'sumber' => $transaksi->detailTransaksi()->value('sumber') ?? 'kasir',
                ]);

                $this->transaksiService->recalculateTotals($transaksi);
            }

            $loyalty->poin -= $item['poin'];
            $loyalty->save();

            $transaksi->forceFill([
                'kode_redeem' => $kodeRedeem,
                'poin_ditukar' => $item['poin'],
                // Disimpan supaya recalculateTotals() tetap tahu plafonnya saat
                // kasir menambah/mengubah item setelah redeem.
                'maks_potongan' => $item['maks_potongan'],
            ])->save();
        });

        return $transaksi;
    }

    private function loyaltyTerkunci(int $customerId): Loyalty
    {
        Loyalty::firstOrCreate(['customer_id' => $customerId], ['poin' => 0]);

        return Loyalty::where('customer_id', $customerId)->lockForUpdate()->first();
    }

    /**
     * Harga reguler menu reward menurut database, dipakai halaman pengaturan
     * untuk menghitung Rp/poin efektif item gratis_menu. Diambil live dengan
     * pencarian yang sama seperti saat redeem, jadi angka yang dilihat manager
     * tidak pernah beda dengan yang benar-benar diberikan ke pelanggan.
     * Null = menunya tidak ada/nonaktif di database.
     *
     * @param  array<string, mixed>  $item
     */
    public function hargaMenuGratis(array $item): ?int
    {
        $menu = $this->cariMenuGratis($item);

        return $menu === null ? null : (int) $menu->harga;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function cariMenuGratis(array $item): ?Menu
    {
        $ukuranDiterima = array_map(mb_strtolower(...), $item['ukuran']);

        $kandidat = Menu::query()
            ->where('is_active', true)
            ->whereRaw('LOWER(nama) = ?', [mb_strtolower($item['menu'])])
            ->whereHas('kategori', fn ($q) => $q->whereRaw('LOWER(nama) = ?', [mb_strtolower($item['kategori'])]))
            ->whereIn(DB::raw('LOWER(ukuran)'), $ukuranDiterima)
            ->get();

        return $kandidat->sortBy(
            fn (Menu $m) => array_search(mb_strtolower((string) $m->ukuran), $ukuranDiterima, true)
        )->first();
    }
}
