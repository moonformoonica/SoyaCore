<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Customer;
use App\Models\Loyalty;
use App\Models\Menu;
use App\Models\Transaksi;
use App\Support\NomorWa;
use App\Support\OpsiMinuman;
use App\Support\WaktuToko;
use Illuminate\Support\Facades\DB;

class OrderService
{
    /**
     * @param  array{nama: string, nomor_wa: string, metode_bayar?: string|null, items: array<int, array{menu_id: mixed, qty: mixed, level_sugar?: string|null, level_ice?: string|null}>}  $data
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

            // Pelanggan yang mendaftar lewat SoyaScan sama barunya dengan yang
            // didaftarkan kasir, jadi bonus pendaftarannya lewat jalur yang sama.
            Loyalty::bukaUntuk($customer);

            $total = 0;
            foreach ($items as [$menu, $qty]) {
                $total += $menu->harga * $qty;
            }

            $transaksi = Transaksi::create([
                'customer_id' => $customer->id,
                // Tidak ada kasir pembuat: pesanan disusun sendiri oleh
                // pelanggan. `dibayar_oleh` yang nanti terisi saat kasir
                // menerimanya di konter.
                'user_id' => null,
                'sumber' => 'self_order',
                'kode_pesanan' => $this->generateKodePesananSelfOrder(),
                'total' => $total,
                'metode_bayar' => $data['metode_bayar'] ?? null,
                'status' => 'pending',
            ]);

            foreach ($items as [$menu, $qty, $sugar, $ice]) {
                $transaksi->detailTransaksi()->create([
                    'menu_id' => $menu->id,
                    'qty' => $qty,
                    'harga_satuan' => $menu->harga,
                    'subtotal' => $menu->harga * $qty,
                    'is_reward' => false,
                    'level_sugar' => $sugar,
                    'level_ice' => $ice,
                    'sumber' => 'self_order',
                ]);
            }

            return $transaksi;
        });
    }

    private function generateKodePesananSelfOrder(): string
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(?)', [crc32('kode_pesanan_self_order')]);
        }

        // Penomoran harian mengikuti hari WIB, sama seperti seluruh laporan.
        $urutan = Transaksi::where('kode_pesanan', 'like', '#A%')
            ->where('created_at', '>=', WaktuToko::awalHari(WaktuToko::tanggalHariIni()))
            ->count() + 1;

        return '#A'.str_pad((string) $urutan, 2, '0', STR_PAD_LEFT);
    }

    /**
     * @return list<array{0: Menu, 1: int, 2: ?string, 3: ?string}>
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

            $sugar = $item['level_sugar'] ?? null;
            $ice = $item['level_ice'] ?? null;

            OpsiMinuman::pastikanBoleh($menu->ukuran, $sugar, $ice, $menu->nama.' ('.($menu->ukuran ?: 'tanpa ukuran').')');

            $hasil[] = [$menu, (int) $qty, $sugar, $ice];
        }

        return $hasil;
    }
}
