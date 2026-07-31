<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Requests\TambahItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Http\Resources\TransaksiResource;
use App\Models\DetailTransaksi;
use App\Models\Menu;
use App\Models\Transaksi;
use App\Services\TransaksiService;
use App\Support\OpsiMinuman;

class TransaksiItemController extends Controller
{
    public function __construct(private readonly TransaksiService $service) {}

    public function store(TambahItemRequest $request, Transaksi $transaksi): TransaksiResource
    {
        $this->service->pastikanPending($transaksi);

        $data = $request->validated();
        $menu = Menu::find($data['menu_id']);

        if ($menu === null || ! $menu->is_active) {
            throw new ApiException(
                'menu_tidak_tersedia',
                "Menu dengan id {$data['menu_id']} tidak tersedia atau sudah tidak aktif.",
                422,
            );
        }

        OpsiMinuman::pastikanBoleh(
            $menu->ukuran,
            $data['level_sugar'] ?? null,
            $data['level_ice'] ?? null,
            $menu->nama.' ('.($menu->ukuran ?: 'tanpa ukuran').')',
        );

        // Item digabung hanya kalau opsi peracikannya juga sama. Dua gelas
        // Original dengan level sugar berbeda adalah dua instruksi berbeda buat
        // barista, jadi menggabungkannya jadi satu baris qty 2 akan menghapus
        // salah satu permintaan pelanggan.
        /** @var DetailTransaksi|null $item */
        $item = $transaksi->detailTransaksi()
            ->where('menu_id', $menu->id)
            ->where('is_reward', false)
            ->where('level_sugar', $data['level_sugar'] ?? null)
            ->where('level_ice', $data['level_ice'] ?? null)
            ->first();

        if ($item !== null) {
            $item->update(array_merge([
                'qty' => $item->qty + $data['qty'],
                'subtotal' => ($item->qty + $data['qty']) * $item->harga_satuan,
            ], $this->fieldTambahan($data)));
        } else {
            $transaksi->detailTransaksi()->create(array_merge([
                'menu_id' => $menu->id,
                'qty' => $data['qty'],
                'harga_satuan' => $menu->harga, // snapshot harga saat ini
                'subtotal' => $data['qty'] * $menu->harga,
                'sumber' => 'kasir',
            ], $this->fieldTambahan($data)));
        }

        $this->service->recalculateTotals($transaksi);

        return new TransaksiResource($transaksi->load(['customer', 'user', 'dibayarOleh', 'detailTransaksi.menu']));
    }

    public function update(UpdateItemRequest $request, Transaksi $transaksi, int $item): TransaksiResource
    {
        $this->service->pastikanPending($transaksi);

        $detail = $transaksi->detailTransaksi()->findOrFail($item);

        $data = $request->validated();

        $detail->loadMissing('menu');
        OpsiMinuman::pastikanBoleh(
            $detail->menu?->ukuran,
            $data['level_sugar'] ?? null,
            $data['level_ice'] ?? null,
            ($detail->menu?->nama ?? 'Item ini').' ('.($detail->menu?->ukuran ?: 'tanpa ukuran').')',
        );

        $detail->update(array_merge([
            'qty' => $data['qty'],
            'subtotal' => $data['qty'] * $detail->harga_satuan,
        ], $this->fieldTambahan($data)));

        $this->service->recalculateTotals($transaksi);

        return new TransaksiResource($transaksi->load(['customer', 'user', 'dibayarOleh', 'detailTransaksi.menu']));
    }

    public function destroy(Transaksi $transaksi, int $item): TransaksiResource
    {
        $this->service->pastikanPending($transaksi);

        $transaksi->detailTransaksi()->findOrFail($item)->delete();

        $this->service->recalculateTotals($transaksi);

        return new TransaksiResource($transaksi->load(['customer', 'user', 'dibayarOleh', 'detailTransaksi.menu']));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fieldTambahan(array $data): array
    {
        return array_intersect_key($data, array_flip(['platform', 'catatan', 'level_sugar', 'level_ice']));
    }
}
