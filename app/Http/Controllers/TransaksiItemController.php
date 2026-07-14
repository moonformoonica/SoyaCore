<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Requests\TambahItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Http\Resources\TransaksiResource;
use App\Models\Menu;
use App\Models\Transaksi;
use App\Services\TransaksiService;

class TransaksiItemController extends Controller
{
    public function __construct(private readonly TransaksiService $service)
    {
    }

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

        // Menu yang sama (non-reward) digabung: qty ditambah, snapshot
        // harga_satuan yang lama dipertahankan selama transaksi pending.
        $item = $transaksi->detailTransaksi()
            ->where('menu_id', $menu->id)
            ->where('is_reward', false)
            ->first();

        if ($item !== null) {
            $item->update([
                'qty' => $item->qty + $data['qty'],
                'subtotal' => ($item->qty + $data['qty']) * $item->harga_satuan,
            ]);
        } else {
            $transaksi->detailTransaksi()->create([
                'menu_id' => $menu->id,
                'qty' => $data['qty'],
                'harga_satuan' => $menu->harga, // snapshot harga saat ini
                'subtotal' => $data['qty'] * $menu->harga,
            ]);
        }

        $this->service->recalculateTotals($transaksi);

        return new TransaksiResource($transaksi->load(['customer', 'user', 'detailTransaksi.menu']));
    }

    public function update(UpdateItemRequest $request, Transaksi $transaksi, int $item): TransaksiResource
    {
        $this->service->pastikanPending($transaksi);

        $detail = $transaksi->detailTransaksi()->findOrFail($item);

        $qty = $request->validated('qty');
        $detail->update([
            'qty' => $qty,
            'subtotal' => $qty * $detail->harga_satuan,
        ]);

        $this->service->recalculateTotals($transaksi);

        return new TransaksiResource($transaksi->load(['customer', 'user', 'detailTransaksi.menu']));
    }

    public function destroy(Transaksi $transaksi, int $item): TransaksiResource
    {
        $this->service->pastikanPending($transaksi);

        $transaksi->detailTransaksi()->findOrFail($item)->delete();

        $this->service->recalculateTotals($transaksi);

        return new TransaksiResource($transaksi->load(['customer', 'user', 'detailTransaksi.menu']));
    }
}
