<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Customer;
use App\Models\Loyalty;
use App\Models\Transaksi;
use App\Support\NomorWa;
use App\Support\WaktuToko;
use Illuminate\Support\Collection;

class TransaksiService
{
    public function __construct(private readonly DiskonEngine $diskonEngine) {}

    public function recalculateTotals(Transaksi $transaksi): Transaksi
    {
        $items = $transaksi->detailTransaksi()->get();

        $persen = (int) $items->max('diskon_persen');

        if ($persen > 0) {
            // Diskon persen diturunkan ulang tiap kali item berubah. Tanpa
            // membawa plafonnya ke sini, kasir yang menambah item setelah
            // redeem membuat potongan ikut membengkak dan plafon terlewati —
            // persis celah yang ditutup kolom transaksi.maks_potongan.
            $hasil = $this->diskonEngine->hitung(
                (int) $items->sum('subtotal'),
                'custom_persen',
                $persen,
                $transaksi->maks_potongan,
            );

            $this->tulisHasilDiskon($items, $hasil);
        } else {
            $nominal = min((int) $items->sum('diskon_nilai'), (int) $items->sum('subtotal'));
            $this->tulisDiskonNominal($items, $nominal);
        }

        return $this->simpanTotal($transaksi, $items);
    }

    /**
     * `$maksPotongan` hanya diisi jalur redeem poin. Diskon manual kasir tetap
     * tanpa plafon — itu wewenang penuh kasir/manager.
     */
    public function terapkanDiskon(Transaksi $transaksi, string $tipe, int $nilai, ?int $maksPotongan = null): Transaksi
    {
        // Diskon sifatnya menimpa, bukan menumpuk. Kalau transaksi sudah
        // redeem, diskon manual akan menghapus potongan reward yang poinnya
        // terlanjur terpotong (rugi pelanggan) atau menggantinya dengan yang
        // lebih besar (rugi toko). Jalur redeem sendiri lolos karena
        // kode_redeem baru ditulis SETELAH diskonnya diterapkan.
        if ($transaksi->kode_redeem !== null) {
            throw new ApiException(
                'diskon_terkunci_redeem',
                "Transaksi ini sudah redeem '{$transaksi->kode_redeem}' — diskon manual tidak bisa ditumpuk di atas diskon reward. Kalau memang perlu diskon lain, batalkan transaksi dan buat baru.",
                409,
            );
        }

        $items = $transaksi->detailTransaksi()->get();
        $subtotal = (int) $items->sum('subtotal');

        $hasil = $this->diskonEngine->hitung($subtotal, $tipe, $nilai, $maksPotongan);

        $this->tulisHasilDiskon($items, $hasil);

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
        // Batas harinya WIB, bukan `whereDate()` yang memakai zona server:
        // dengan `app.timezone` = UTC, penomoran akan berganti pukul 07.00 WIB
        // dan pesanan pagi melanjutkan nomor kemarin.
        $urutanHariIni = Transaksi::where('kode_pesanan', 'like', '#K%')
            ->where('created_at', '>=', WaktuToko::awalHari(WaktuToko::tanggalHariIni()))
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

        $customer = Customer::firstOrCreate(
            ['no_wa' => $noWa],
            ['nama' => $data['nama']],
        );

        Loyalty::bukaUntuk($customer);

        return $customer;
    }

    /**
     * @param  array{diskon_persen: int, diskon_nilai: int}  $hasil
     */
    private function tulisHasilDiskon(Collection $items, array $hasil): void
    {
        if ($hasil['diskon_persen'] > 0) {
            $this->tulisDiskonPersen($items, $hasil['diskon_persen']);

            return;
        }

        $this->tulisDiskonNominal($items, $hasil['diskon_nilai']);
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
