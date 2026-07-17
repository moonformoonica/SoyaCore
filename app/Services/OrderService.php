<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Customer;
use App\Models\Loyalty;
use App\Models\Menu;
use App\Models\Transaksi;
use App\Support\NomorWa;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Self-order dari SoyaScan (M3). Prinsip kontrak v1: client TIDAK PERNAH
 * mengirim harga — total dihitung 100% di server dari menu.harga saat itu,
 * lalu di-snapshot ke detail_transaksi.harga_satuan.
 */
class OrderService
{
    /**
     * @param  array{nama: string, nomor_wa: string, nomor_meja: string, items: array<int, array{menu_id: mixed, qty: mixed}>}  $data
     */
    public function buatOrder(array $data): Transaksi
    {
        $noWa = NomorWa::normalisasi($data['nomor_wa']);
        if (strlen($noWa) < 8) {
            throw new ApiException('nomor_wa_invalid', 'Format nomor WhatsApp tidak valid.', 422);
        }

        $items = $this->validasiItems($data['items'] ?? []);

        return DB::transaction(function () use ($data, $noWa, $items) {
            // Upsert customer by nomor WA ternormalisasi; nama di-update ke
            // nilai terbaru yang dikirim.
            $customer = Customer::updateOrCreate(
                ['no_wa' => $noWa],
                ['nama' => $data['nama']],
            );

            // Pastikan baris loyalty ada (poin=0) — poin TIDAK bertambah di
            // sini; earning hanya terjadi saat Tandai Lunas (anti-fraud M1).
            Loyalty::firstOrCreate(['customer_id' => $customer->id], ['poin' => 0]);

            $total = 0;
            foreach ($items as [$menu, $qty]) {
                $total += $menu->harga * $qty;
            }

            $transaksi = Transaksi::create([
                'customer_id' => $customer->id,
                'user_id' => null, // belum ada kasir yang menangani
                'kode_pesanan' => $this->generateKodePesananSelfOrder(),
                'total' => $total,
                'status' => 'pending',
            ]);

            foreach ($items as [$menu, $qty]) {
                $transaksi->detailTransaksi()->create([
                    'menu_id' => $menu->id,
                    'qty' => $qty,
                    'harga_satuan' => $menu->harga, // snapshot harga saat ini
                    'subtotal' => $menu->harga * $qty,
                    'is_reward' => false,
                    'sumber' => 'self_order',
                    'nomor_meja' => $data['nomor_meja'],
                ]);
            }

            return $transaksi;
        });
    }

    /**
     * Kode pesanan self-order: #A + counter reset harian (hari Asia/Jakarta).
     * #A01..#A99, lalu lanjut #A100 dst tanpa crash (padding 2 digit hanya
     * berlaku sampai 99). Tidak unique global — boleh berulang di hari lain.
     *
     * Race-safe: pg_advisory_xact_lock men-serialisasi generate+insert antar
     * request bersamaan (lock lepas otomatis saat transaction commit).
     * Di sqlite (testing) lock ini tidak tersedia dan tidak dibutuhkan
     * (test berjalan single-connection).
     */
    private function generateKodePesananSelfOrder(): string
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(?)', [crc32('kode_pesanan_self_order')]);
        }

        $awalHariJakarta = Carbon::now('Asia/Jakarta')->startOfDay()->setTimezone(config('app.timezone'));

        $urutan = Transaksi::where('kode_pesanan', 'like', '#A%')
            ->where('created_at', '>=', $awalHariJakarta)
            ->count() + 1;

        return '#A'.str_pad((string) $urutan, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Validasi items sesuai kode error kontrak v1: items_kosong,
     * qty_invalid, menu_tidak_tersedia.
     *
     * @return list<array{0: Menu, 1: int}>
     */
    private function validasiItems(array $items): array
    {
        if ($items === []) {
            throw new ApiException('items_kosong', 'Pesanan harus berisi minimal satu item.', 422);
        }

        $hasil = [];
        foreach ($items as $item) {
            $qty = $item['qty'] ?? null;
            if (! is_numeric($qty) || (int) $qty < 1 || (int) $qty != $qty) {
                throw new ApiException('qty_invalid', 'Jumlah (qty) tiap item harus bilangan bulat minimal 1.', 422);
            }

            $menuId = $item['menu_id'] ?? null;
            $menu = is_numeric($menuId) ? Menu::find((int) $menuId) : null;
            if ($menu === null || ! $menu->is_active) {
                $label = is_numeric($menuId) ? $menuId : '?';
                throw new ApiException('menu_tidak_tersedia', "Menu dengan id {$label} tidak tersedia atau sudah tidak aktif.", 422);
            }

            $hasil[] = [$menu, (int) $qty];
        }

        return $hasil;
    }
}
