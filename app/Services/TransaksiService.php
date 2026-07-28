<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Customer;
use App\Models\Transaksi;
use App\Support\NomorWa;
use Illuminate\Support\Collection;

class TransaksiService
{
    public function __construct(private readonly DiskonEngine $diskonEngine) {}

    public function recalculateTotals(Transaksi $transaksi): Transaksi
    {
        $items = $transaksi->detailTransaksi()->get();

        $persen = (int) $items->max('diskon_persen');

        if ($persen > 0) {
            $this->tulisDiskonPersen($items, $persen);
        } else {
            $nominal = min((int) $items->sum('diskon_nilai'), (int) $items->sum('subtotal'));
            $this->tulisDiskonNominal($items, $nominal);
        }

        return $this->simpanTotal($transaksi, $items);
    }

    public function terapkanDiskon(Transaksi $transaksi, string $tipe, int $nilai): Transaksi
    {
        $items = $transaksi->detailTransaksi()->get();
        $subtotal = (int) $items->sum('subtotal');

        $hasil = $this->diskonEngine->hitung($subtotal, $tipe, $nilai);

        if ($hasil['diskon_persen'] > 0) {
            $this->tulisDiskonPersen($items, $hasil['diskon_persen']);
        } else {
            $this->tulisDiskonNominal($items, $hasil['diskon_nilai']);
        }

        return $this->simpanTotal($transaksi, $items);
    }

    public function pastikanPending(Transaksi $transaksi): void
    {
        if ($transaksi->status !== 'pending') {
            throw new ApiException(
                'transaksi_sudah_'.$transaksi->status,
                "Transaksi {$transaksi->kode_pesanan} sudah berstatus '{$transaksi->status}' dan tidak bisa diubah lagi.",
                409,
            );
        }
    }

    public function generateKodePesanan(): string
    {
        $urutanHariIni = Transaksi::where('kode_pesanan', 'like', '#K%')
            ->whereDate('created_at', now()->toDateString())
            ->count() + 1;

        return sprintf('#K%03d', $urutanHariIni);
    }

    /**
     * @param  array{nama: string, no_wa: string}|null  $data
     */
    public function findOrCreateCustomer(?array $data): ?Customer
    {
        if ($data === null) {
            return null;
        }

        $noWa = NomorWa::normalisasi($data['no_wa']);

        if ($noWa === '' || $noWa === '+') {
            throw new ApiException('nomor_wa_invalid', 'Format nomor WhatsApp tidak valid.', 422);
        }

        return Customer::firstOrCreate(
            ['no_wa' => $noWa],
            ['nama' => $data['nama']],
        );
    }

    private function tulisDiskonPersen(Collection $items, int $persen): void
    {
        foreach ($items as $item) {
            $item->forceFill([
                'diskon_persen' => $persen,
                'diskon_nilai' => (int) round($item->subtotal * $persen / 100),
            ])->save();
        }
    }

    private function tulisDiskonNominal(Collection $items, int $nominal): void
    {
        $bagian = $this->diskonEngine->distribusi(
            $items->pluck('subtotal', 'id')->all(),
            $nominal,
        );

        foreach ($items as $item) {
            $item->forceFill([
                'diskon_persen' => 0,
                'diskon_nilai' => $bagian[$item->id] ?? 0,
            ])->save();
        }
    }

    private function simpanTotal(Transaksi $transaksi, Collection $items): Transaksi
    {
        $total = max(0, (int) $items->sum('subtotal') - (int) $items->sum('diskon_nilai'));

        $transaksi->forceFill(['total' => $total])->save();

        return $transaksi;
    }
}
