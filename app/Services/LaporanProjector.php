<?php

namespace App\Services;

use App\Models\LaporanTransaksi;
use App\Models\Transaksi;
use App\Support\WaktuToko;

/**
 * Menyalin transaksi POS live ke layer laporan (`laporan_transaksi`).
 *
 * MASALAH YANG DISELESAIKAN. Dashboard membaca `laporan_transaksi`, tabel yang
 * sampai sekarang hanya diisi CSV historis. Tabel POS live (`transaksi` +
 * `detail_transaksi`) tidak pernah dibaca dashboard sama sekali, jadi pesanan
 * baru memang tidak akan pernah muncul di sana. Ini bukan bug polling atau
 * cache, tapi dua sumber data yang terpisah.
 *
 * KENAPA PROYEKSI, BUKAN UNION. Kalau dashboard membaca UNION dua tabel, setiap
 * query agregasi (ringkasan, time series, produk terlaris, revenue per ukuran,
 * export, RFM) harus ditulis dua kali dan dijaga tetap sama selamanya. Dengan
 * memproyeksikan, satu layer query yang sudah ada langsung ikut hidup.
 *
 * KENAPA SINKRON, BUKAN QUEUED JOB. Laporan harus bisa di-export real-time.
 * Kalau proyeksinya antre di queue, transaksi yang baru dibayar tidak muncul di
 * file Excel yang di-download satu menit kemudian, dan tidak ada yang sadar
 * datanya tertinggal, karena tidak ada error yang muncul. Beban satu
 * `updateOrCreate` per item tidak sepadan dengan risiko itu.
 *
 * BATAS DATA, DAN INI BUKAN CACAT:
 * - Baris impor CSV Juni–Juli 2026 (`kode` berawalan `TR-`): kasir `null`,
 *   karena data lama memang tidak merekam kasir.
 * - Baris hasil proyeksi (`kode` berawalan `TRX-`): kasir WAJIB terisi.
 *
 * Baris CSV tidak pernah disentuh class ini, prefix `kode` yang berbeda
 * membuat keduanya hidup berdampingan di satu tabel.
 */
class LaporanProjector
{
    /**
     * Menulis ulang seluruh baris proyeksi milik satu transaksi dari kondisi
     * TERKINI-nya, termasuk pembatalan yang sudah pernah terjadi.
     *
     * Idempoten dan self-correcting: dipanggil berapa kali pun hasilnya sama,
     * jadi `bayar()` yang ter-submit dua kali tidak menggandakan omzet, dan
     * pembatalan cukup memanggil method yang sama tanpa perlu tahu
     * baris mana yang harus diubah.
     */
    public function sinkronkan(Transaksi $transaksi): void
    {
        if (! $this->layakDilaporkan($transaksi)) {
            $this->hapus($transaksi);

            return;
        }

        $transaksi->loadMissing(['customer', 'user', 'dibayarOleh', 'detailTransaksi.menu', 'pembatalan.items']);

        $baris = $this->susunBaris($transaksi);

        // Baris yang tidak lagi seharusnya ada (item dihapus dari transaksi,
        // atau qty-nya habis dibatalkan) dibuang lebih dulu, supaya omzetnya
        // benar-benar turun dan bukan cuma berhenti bertambah.
        LaporanTransaksi::query()
            ->where('kode', 'like', $this->prefix($transaksi).'%')
            ->whereNotIn('kode', array_keys($baris))
            ->delete();

        foreach ($baris as $kode => $atribut) {
            LaporanTransaksi::updateOrCreate(['kode' => $kode], $atribut);
        }
    }

    /**
     * Membuang seluruh proyeksi satu transaksi, dipakai saat transaksi
     * dibatalkan penuh, supaya omzet dashboard ikut terkoreksi.
     */
    public function hapus(Transaksi $transaksi): void
    {
        LaporanTransaksi::query()
            ->where('kode', 'like', $this->prefix($transaksi).'%')
            ->delete();
    }

    /**
     * Hanya transaksi yang benar-benar sudah jadi penjualan yang dilaporkan.
     * `pending` belum jadi penjualan, `batal` sudah bukan penjualan lagi.
     */
    private function layakDilaporkan(Transaksi $transaksi): bool
    {
        return $transaksi->waktu_lunas !== null
            && in_array($transaksi->status, ['lunas', 'batal_sebagian'], true);
    }

    private function prefix(Transaksi $transaksi): string
    {
        return LaporanTransaksi::PREFIX_POS.$transaksi->id.'-';
    }

    /**
     * @return array<string, array<string, mixed>> Dikunci `kode`.
     */
    private function susunBaris(Transaksi $transaksi): array
    {
        $dibatalkan = $this->rekapPembatalanPerItem($transaksi);
        $kasir = $transaksi->kasirTerhitung();
        $tanggal = WaktuToko::tanggal($transaksi->waktu_lunas);

        $baris = [];

        foreach ($transaksi->detailTransaksi as $detail) {
            $rekap = $dibatalkan[$detail->id] ?? ['qty' => 0, 'nilai' => 0];

            $qty = (int) $detail->qty - $rekap['qty'];
            if ($qty <= 0) {
                continue; // item habis dibatalkan, tidak ada penjualan yang tersisa
            }

            // Nilai bersih SETELAH diskon, dikurangi bagian yang dibatalkan.
            // Memakai harga mentah membuat omzet lebih besar dari yang pernah
            // benar-benar tercatat.
            $nilai = max(0, (int) $detail->subtotal - (int) $detail->diskon_nilai - $rekap['nilai']);

            $baris[$this->prefix($transaksi).$detail->id] = [
                'tanggal' => $tanggal,
                'platform' => $transaksi->metode_bayar,
                'nama_pelanggan' => $transaksi->customer?->nama,
                'no_wa' => $transaksi->customer?->no_wa,
                'nama_produk' => $detail->menu?->nama ?? '(menu terhapus)',
                'rasa' => $detail->menu?->rasa,
                'ukuran' => $detail->menu?->ukuran,
                'qty' => $qty,
                'harga_satuan' => (int) $detail->harga_satuan,
                'total' => $nilai,
                // Item reward (`is_reward`, subtotal 0) ikut diproyeksikan
                // dengan total 0: qty terjual harus jujur, dan minuman gratis
                // tetap mengonsumsi stok.
                'poin_loyalty' => 0, // diisi setelah jumlah baris diketahui
                'kasir_user_id' => $kasir?->id,
                'kasir_nama' => $kasir?->nama,
                'catatan' => $detail->catatan,
            ];
        }

        return $this->bagiPoin($baris, $this->poinEfektif($transaksi));
    }

    /**
     * Poin earn dibagi rata ke seluruh item, sisa pembagiannya ditaruh di item
     * terakhir, supaya `SUM(poin_loyalty)` di laporan tetap sama dengan poin
     * yang benar-benar diberikan ke pelanggan.
     *
     * @param  array<string, array<string, mixed>>  $baris
     * @return array<string, array<string, mixed>>
     */
    private function bagiPoin(array $baris, int $poin): array
    {
        $jumlah = count($baris);
        if ($jumlah === 0) {
            return $baris;
        }

        $dasar = intdiv($poin, $jumlah);
        $sisa = $poin - ($dasar * $jumlah);

        $ke = 0;
        foreach ($baris as $kode => $atribut) {
            $ke++;
            $baris[$kode]['poin_loyalty'] = $ke === $jumlah ? $dasar + $sisa : $dasar;
        }

        return $baris;
    }

    /**
     * Poin yang masih benar-benar dipegang pelanggan dari transaksi ini: poin
     * earn dikurangi yang sudah ditarik lewat pembatalan. Tanpa pengurangan
     * ini, total poin di dashboard terus menghitung poin yang sudah dicabut.
     */
    private function poinEfektif(Transaksi $transaksi): int
    {
        $ditarik = (int) $transaksi->pembatalan->sum('poin_ditarik');

        return max(0, (int) $transaksi->point_earned - $ditarik);
    }

    /**
     * Qty dan nilai yang sudah dibatalkan per `detail_transaksi`, dijumlahkan
     * LINTAS semua pembatalan transaksi ini, bukan hanya yang terakhir.
     *
     * @return array<int, array{qty: int, nilai: int}>
     */
    private function rekapPembatalanPerItem(Transaksi $transaksi): array
    {
        $rekap = [];

        foreach ($transaksi->pembatalan as $pembatalan) {
            foreach ($pembatalan->items as $item) {
                $id = (int) $item->detail_transaksi_id;
                $rekap[$id] ??= ['qty' => 0, 'nilai' => 0];
                $rekap[$id]['qty'] += (int) $item->qty;
                $rekap[$id]['nilai'] += (int) $item->nilai_dibatalkan;
            }
        }

        return $rekap;
    }
}
