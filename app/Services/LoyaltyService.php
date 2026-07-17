<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Loyalty;
use App\Models\Menu;
use App\Models\Transaksi;
use App\Support\LoyaltyRedemptionCatalog;
use Illuminate\Support\Facades\DB;

/**
 * LoyalSeed (M3): earning poin + redeem katalog.
 *
 * Prinsip yang dikunci sejak M1:
 * - Poin HANYA bertambah saat status berubah jadi lunas (anti-fraud),
 *   tidak pernah saat order dibuat (pending).
 * - Rate: 1 poin per Rp 1.000 dari total yang benar-benar dibayar
 *   (sudah dikurangi diskon/redeem), pembulatan ke bawah (intdiv).
 * - Redeem hanya saat pending, dan HANYA SATU redemption per transaksi.
 */
class LoyaltyService
{
    public function __construct(private readonly TransaksiService $transaksiService) {}

    /**
     * Dipanggil HANYA dari Tandai Lunas, setelah status resmi lunas.
     * Idempotent: loyalty_applied_at jadi guard supaya pemanggilan kedua
     * tidak menambah poin lagi.
     */
    public function earnPoinFor(Transaksi $transaksi): void
    {
        if ($transaksi->loyalty_applied_at !== null) {
            return;
        }

        DB::transaction(function () use ($transaksi) {
            $poinDidapat = intdiv((int) $transaksi->total, 1000);

            // Transaksi kasir walk-in boleh tanpa customer — poin tetap
            // dicatat di transaksi (audit), tapi tidak ada saldo yang naik.
            if ($transaksi->customer_id !== null) {
                $loyalty = $this->loyaltyTerkunci($transaksi->customer_id);
                $loyalty->poin += $poinDidapat;
                $loyalty->save();
            }

            $transaksi->forceFill([
                'point_earned' => $poinDidapat,
                'loyalty_applied_at' => now(),
            ])->save();
        });
    }

    /**
     * Redeem katalog — dipanggil kasir SEBELUM Tandai Lunas.
     */
    public function redeemPoin(Transaksi $transaksi, string $kodeRedeem): Transaksi
    {
        $this->transaksiService->pastikanPending($transaksi);

        if ($transaksi->kode_redeem !== null) {
            throw new ApiException(
                'transaksi_sudah_redeem',
                "Transaksi ini sudah redeem '{$transaksi->kode_redeem}' — hanya satu redemption per transaksi. Kalau salah pilih, batalkan transaksi dan buat baru.",
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

        if ($transaksi->customer_id === null) {
            throw new ApiException(
                'transaksi_tanpa_customer',
                'Redeem poin butuh customer terdaftar di transaksi ini (tambahkan customer saat membuat transaksi).',
                422,
            );
        }

        DB::transaction(function () use ($transaksi, $kodeRedeem, $item) {
            $loyalty = $this->loyaltyTerkunci($transaksi->customer_id);

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
                        "{$item['label']} butuh minimal pembelian Rp ".number_format($item['min_subtotal'], 0, ',', '.').' — subtotal saat ini Rp '.number_format($subtotal, 0, ',', '.').'.',
                        422,
                    );
                }

                // Pakai engine diskon M2 (persen ditulis per item sesuai
                // skema ERD revisi; total dihitung ulang di dalamnya).
                $this->transaksiService->terapkanDiskon($transaksi, 'custom_persen', $item['persen']);
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
                    // snapshot harga asli untuk laporan nilai item gratis —
                    // TIDAK ditambahkan ke tagihan (subtotal 0)
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
            ])->save();
        });

        return $transaksi;
    }

    /**
     * Ambil baris loyalty milik customer dengan row lock (aman dari race
     * dua request nyaris bersamaan). Dibuat dengan poin=0 kalau belum ada.
     */
    private function loyaltyTerkunci(int $customerId): Loyalty
    {
        Loyalty::firstOrCreate(['customer_id' => $customerId], ['poin' => 0]);

        return Loyalty::where('customer_id', $customerId)->lockForUpdate()->first();
    }

    /**
     * Cari menu reward: kategori + nama + ukuran sesuai katalog,
     * case-insensitive, hanya menu aktif. Kalau lebih dari satu varian
     * cocok, pilih sesuai urutan preferensi ukuran di katalog.
     *
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
