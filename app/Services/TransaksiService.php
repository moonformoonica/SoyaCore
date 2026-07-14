<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Customer;
use App\Models\Transaksi;
use App\Support\NomorWa;

class TransaksiService
{
    /**
     * Satu-satunya tempat penghitungan subtotal/total transaksi —
     * dipanggil setiap kali item atau diskon berubah.
     *
     * subtotal = SUM(detail_transaksi.subtotal)
     * total    = subtotal - diskon_nilai (min 0)
     *
     * Jika diskon persen aktif, diskon_nilai dihitung ulang dari subtotal
     * terbaru. Jika diskon nominal (custom_nilai) aktif dan subtotal turun
     * di bawah nominal (misal item dihapus), diskon_nilai di-clamp ke
     * subtotal supaya total tidak pernah negatif.
     */
    public function recalculateTotals(Transaksi $transaksi): Transaksi
    {
        $subtotal = (int) $transaksi->detailTransaksi()->sum('subtotal');

        if ($transaksi->diskon_persen > 0) {
            $diskonNilai = (int) round($subtotal * $transaksi->diskon_persen / 100);
        } else {
            $diskonNilai = min((int) $transaksi->diskon_nilai, $subtotal);
        }

        $transaksi->forceFill([
            'subtotal' => $subtotal,
            'diskon_nilai' => $diskonNilai,
            'total' => max(0, $subtotal - $diskonNilai),
        ])->save();

        return $transaksi;
    }

    /**
     * Guard status: item/diskon/pembayaran hanya boleh saat 'pending'.
     */
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

    /**
     * Kode pesanan kasir: #K + urutan harian 3 digit (#K001, #K002, ...).
     * Sengaja dibedakan dari format #A23 milik self-order (M3).
     */
    public function generateKodePesanan(): string
    {
        $urutanHariIni = Transaksi::where('sumber', 'kasir')
            ->whereDate('created_at', now()->toDateString())
            ->count() + 1;

        return sprintf('#K%03d', $urutanHariIni);
    }

    /**
     * Find-or-create customer berdasarkan no_wa ternormalisasi.
     *
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
}
