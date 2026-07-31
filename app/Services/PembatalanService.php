<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\DetailTransaksi;
use App\Models\Loyalty;
use App\Models\Pembatalan;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Pembatalan / koreksi pesanan yang salah — BUKAN pengembalian uang.
 * Lihat docs/pembatalan-pesanan.md.
 *
 * PRINSIP. Transaksi asli tidak pernah dihapus atau diubah isinya. Ia hanya
 * berubah status, dan pembatalannya dicatat sebagai dokumen tersendiri, supaya
 * selalu bisa ditelusuri siapa membatalkan apa, kapan, dan kenapa. Nilai yang
 * gugur tetap wajib dicatat karena omzet dashboard dan laporan kasir harus ikut
 * terkoreksi.
 *
 * PERILAKU POIN — dua jenis poin yang gampang dianggap sama:
 *
 * | Jenis poin            | Kapan berubah                        | Saat pembatalan            |
 * | --------------------- | ------------------------------------ | -------------------------- |
 * | earn (dari belanja)   | saat LUNAS (`loyalty_applied_at`)    | ditarik proporsional       |
 * | redeem (tukar reward) | saat REDEEM, walau masih pending     | dikembalikan utuh          |
 *
 * Aturan finalnya otomatis benar untuk kedua kasus:
 *
 * > Tarik poin earn hanya jika `loyalty_applied_at !== null`.
 * > Kembalikan poin redeem kapan pun `kode_redeem` terisi.
 *
 * Sebelum perbaikan ini, `redeemPoin()` sudah memotong saldo pelanggan saat
 * redeem sementara `batal()` cuma mengubah status — jadi pelanggan yang menukar
 * 350 poin lalu pesanannya dibatalkan sebelum bayar kehilangan poinnya DAN
 * tidak mendapat minumannya.
 */
class PembatalanService
{
    public function __construct(
        private readonly TransaksiService $transaksiService,
        private readonly LaporanProjector $projector,
    ) {}

    /**
     * `$items` kosong = pembatalan PENUH.
     *
     * @param  list<array{detail_transaksi_id: int, qty: int}>  $items
     */
    public function batalkan(Transaksi $transaksi, User $user, string $alasan, array $items = []): Pembatalan
    {
        $penuh = $items === [];

        $this->pastikanBisaDibatalkan($transaksi, $penuh);

        return DB::transaction(function () use ($transaksi, $user, $alasan, $items, $penuh) {
            $transaksi->load(['detailTransaksi', 'pembatalan.items']);

            // Saldo poin dikunci lebih dulu, sebelum apa pun dihitung: tanpa itu
            // dua pembatalan yang tiba bersamaan bisa sama-sama membaca saldo
            // lama dan mengembalikan poin redeem dua kali.
            $loyalty = $this->loyaltyTerkunci($transaksi);

            $rincian = $penuh
                ? $this->rincianPenuh($transaksi)
                : $this->rincianSebagian($transaksi, $items);

            // `$rincian` boleh kosong pada pembatalan penuh: pesanan yang
            // ditinggalkan sebelum satu pun item ditambahkan tetap harus bisa
            // dibatalkan. Pembatalan sebagian tidak pernah sampai ke sini
            // dengan rincian kosong — `items` yang kosong berarti pembatalan
            // penuh.
            $nilaiDibatalkan = array_sum(array_column($rincian, 'nilai'));

            $poinDitarik = $this->tarikPoinEarn($transaksi, $loyalty, $nilaiDibatalkan);
            $poinDikembalikan = $this->kembalikanPoinRedeem($transaksi, $loyalty, $penuh, $rincian);

            $pembatalan = Pembatalan::create([
                'transaksi_id' => $transaksi->id,
                'user_id' => $user->id,
                'alasan' => $alasan,
                'nilai_dibatalkan' => $nilaiDibatalkan,
                'poin_ditarik' => $poinDitarik,
                'poin_dikembalikan' => $poinDikembalikan,
            ]);

            foreach ($rincian as $baris) {
                $pembatalan->items()->create([
                    'detail_transaksi_id' => $baris['detail']->id,
                    'qty' => $baris['qty'],
                    'nilai_dibatalkan' => $baris['nilai'],
                ]);
            }

            // Total transaksi hanya dihitung ulang pada pembatalan SEBAGIAN.
            // Pada pembatalan penuh, angka yang tersimpan justru harus tetap
            // seperti apa adanya — ia catatan tentang penjualan seperti apa
            // yang dibatalkan, bukan tagihan yang masih akan dibayar.
            if (! $penuh && $poinDikembalikan > 0) {
                $this->transaksiService->recalculateTotals($transaksi);
            }

            $transaksi->forceFill([
                'status' => $this->statusSetelahPembatalan($transaksi, $penuh, $rincian),
            ])->save();

            // Omzet dashboard & laporan kasir ikut turun di detik yang sama.
            $penuh
                ? $this->projector->hapus($transaksi)
                : $this->projector->sinkronkan($transaksi->fresh());

            return $pembatalan->load(['items.detailTransaksi.menu', 'user']);
        });
    }

    private function pastikanBisaDibatalkan(Transaksi $transaksi, bool $penuh): void
    {
        if ($transaksi->status === 'batal') {
            throw new ApiException(
                'transaksi_sudah_batal',
                "Transaksi {$transaksi->kode_pesanan} sudah dibatalkan seluruhnya dan tidak bisa dibatalkan lagi.",
                409,
            );
        }

        // Pesanan yang belum dibayar dikoreksi lewat endpoint item
        // (`PATCH`/`DELETE /api/transaksi/{id}/items/{item}`), yang sudah
        // menghitung ulang totalnya dengan benar. Kalau pembatalan sebagian
        // diizinkan di sini, transaksi pending akan punya `total` yang tidak
        // lagi sama dengan yang harus dibayar pelanggan — dan kasir menagih
        // angka yang salah.
        if ($transaksi->status === 'pending' && ! $penuh) {
            throw new ApiException(
                'pembatalan_sebagian_butuh_lunas',
                "Transaksi {$transaksi->kode_pesanan} belum dibayar — koreksi itemnya lewat ubah/hapus item pesanan, bukan pembatalan sebagian. Pembatalan sebagian hanya untuk transaksi yang sudah lunas.",
                422,
            );
        }
    }

    /**
     * Seluruh sisa yang belum pernah dibatalkan.
     *
     * Nilainya dihitung sebagai SISA (nilai bersih item dikurangi yang sudah
     * dibatalkan), bukan lewat rumus proporsional — supaya penjumlahan seluruh
     * pembatalan sebuah item selalu pas dengan nilai aslinya, tanpa residu
     * pembulatan yang menempel di omzet selamanya.
     *
     * @return list<array{detail: DetailTransaksi, qty: int, nilai: int}>
     */
    private function rincianPenuh(Transaksi $transaksi): array
    {
        $sudah = $this->rekapSudahDibatalkan($transaksi);

        $rincian = [];
        foreach ($transaksi->detailTransaksi as $detail) {
            $sisaQty = (int) $detail->qty - ($sudah[$detail->id]['qty'] ?? 0);
            if ($sisaQty <= 0) {
                continue;
            }

            $rincian[] = [
                'detail' => $detail,
                'qty' => $sisaQty,
                'nilai' => max(0, $this->nilaiBersih($detail) - ($sudah[$detail->id]['nilai'] ?? 0)),
            ];
        }

        return $rincian;
    }

    /**
     * @param  list<array{detail_transaksi_id: int, qty: int}>  $items
     * @return list<array{detail: DetailTransaksi, qty: int, nilai: int}>
     */
    private function rincianSebagian(Transaksi $transaksi, array $items): array
    {
        $sudah = $this->rekapSudahDibatalkan($transaksi);
        $milikTransaksi = $transaksi->detailTransaksi->keyBy('id');

        $rincian = [];
        foreach ($items as $item) {
            $id = (int) $item['detail_transaksi_id'];
            $qty = (int) $item['qty'];

            /** @var DetailTransaksi|null $detail */
            $detail = $milikTransaksi->get($id);
            if ($detail === null) {
                throw new ApiException(
                    'item_bukan_milik_transaksi',
                    "Item dengan id {$id} bukan bagian dari transaksi {$transaksi->kode_pesanan}.",
                    422,
                );
            }

            $sudahQty = $sudah[$id]['qty'] ?? 0;
            $sisaQty = (int) $detail->qty - $sudahQty;

            // Dijaga LINTAS semua pembatalan sebelumnya, bukan hanya request
            // ini — tiga kali membatalkan 1 dari qty 2 harus tetap ditolak.
            if ($qty > $sisaQty) {
                throw new ApiException(
                    'qty_pembatalan_melebihi',
                    "Qty pembatalan item {$id} melebihi sisa yang belum dibatalkan (diminta {$qty}, sisa {$sisaQty} dari qty asli {$detail->qty}).",
                    422,
                );
            }

            $rincian[] = [
                'detail' => $detail,
                'qty' => $qty,
                'nilai' => $this->nilaiSebagian($detail, $qty, $sisaQty, $sudah[$id]['nilai'] ?? 0),
            ];
        }

        return $rincian;
    }

    /**
     * Nilai per item dihitung SETELAH diskon:
     *
     *     nilai = (subtotal - diskon_nilai) × (qty_dibatalkan ÷ qty_asli)
     *
     * Memakai `harga_satuan` mentah membuat omzet terkoreksi lebih besar
     * daripada yang pernah tercatat, dan dashboard jadi minus.
     *
     * Kalau pembatalan ini menghabiskan sisa qty item, dipakai nilai sisanya
     * secara persis — jadi residu pembulatan tidak tertinggal sebagai omzet
     * beberapa rupiah yang tidak bisa dihilangkan.
     */
    private function nilaiSebagian(DetailTransaksi $detail, int $qty, int $sisaQty, int $sudahNilai): int
    {
        $bersih = $this->nilaiBersih($detail);
        $sisaNilai = max(0, $bersih - $sudahNilai);

        if ($qty >= $sisaQty) {
            return $sisaNilai;
        }

        $qtyAsli = max(1, (int) $detail->qty);

        return min((int) round($bersih * $qty / $qtyAsli), $sisaNilai);
    }

    private function nilaiBersih(DetailTransaksi $detail): int
    {
        return max(0, (int) $detail->subtotal - (int) $detail->diskon_nilai);
    }

    /**
     * @return array<int, array{qty: int, nilai: int}>
     */
    private function rekapSudahDibatalkan(Transaksi $transaksi): array
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

    /**
     * Poin earn hanya ada kalau transaksinya sudah lunas — `loyalty_applied_at`
     * yang mengunci itu. Transaksi pending yang dibatalkan tidak menarik apa
     * pun, karena poinnya memang belum pernah diberikan.
     *
     * Saldo boleh menjadi 0 tapi TIDAK boleh negatif: kalau pelanggan sudah
     * membelanjakan poinnya, kekurangannya ditanggung toko. Menagih poin
     * negatif memicu komplain yang lebih mahal daripada selisihnya. Yang
     * dicatat adalah poin yang BENAR-BENAR ditarik, bukan yang seharusnya —
     * supaya laporan tidak mengklaim menarik poin yang tidak pernah kembali.
     */
    private function tarikPoinEarn(Transaksi $transaksi, ?Loyalty $loyalty, int $nilaiDibatalkan): int
    {
        if ($transaksi->loyalty_applied_at === null || $loyalty === null) {
            return 0;
        }

        $poinEarn = (int) $transaksi->point_earned;
        if ($poinEarn <= 0) {
            return 0;
        }

        $sudahDitarik = (int) $transaksi->pembatalan->sum('poin_ditarik');
        $sisaPoin = max(0, $poinEarn - $sudahDitarik);

        $totalAwal = (int) $transaksi->total;
        $proporsional = $totalAwal > 0
            ? (int) floor($poinEarn * $nilaiDibatalkan / $totalAwal) // dibulatkan ke bawah: memihak pelanggan
            : $sisaPoin;

        $ditarik = min($proporsional, $sisaPoin, (int) $loyalty->poin);

        if ($ditarik > 0) {
            $loyalty->poin -= $ditarik;
            $loyalty->save();
        }

        return max(0, $ditarik);
    }

    /**
     * Poin redeem dikembalikan UTUH setiap kali pembatalan menggugurkan
     * redemption-nya. Yang menggugurkan hanya dua hal:
     *
     * - pembatalan penuh, selalu; atau
     * - pembatalan sebagian yang menyertakan item reward (`is_reward`).
     *
     * Pembatalan sebagian yang tidak menyentuh item reward TIDAK mengembalikan
     * poin — rewardnya memang tetap diterima pelanggan.
     *
     * @param  list<array{detail: DetailTransaksi, qty: int, nilai: int}>  $rincian
     */
    private function kembalikanPoinRedeem(Transaksi $transaksi, ?Loyalty $loyalty, bool $penuh, array $rincian): int
    {
        if ($transaksi->kode_redeem === null) {
            return 0;
        }

        $menyentuhReward = false;
        foreach ($rincian as $baris) {
            if ($baris['detail']->is_reward) {
                $menyentuhReward = true;
                break;
            }
        }

        if (! $penuh && ! $menyentuhReward) {
            return 0;
        }

        $poin = (int) $transaksi->poin_ditukar;

        if ($loyalty !== null && $poin > 0) {
            // Masa berlaku ikut diperpanjang: poin yang kembali karena kesalahan
            // pesanan tidak boleh langsung hangus gara-gara jam kedaluwarsa
            // lama masih menempel.
            $loyalty->poin += $poin;
            $loyalty->poin_kedaluwarsa_pada = now()->addMonths(Loyalty::BULAN_KEDALUWARSA);
            $loyalty->save();
        }

        // Kalau tidak dikosongkan, diskon dari reward yang sudah digugurkan
        // akan tetap menempel pada transaksi, dan `recalculateTotals()`
        // berikutnya masih memakai plafon reward yang sudah tidak berlaku.
        $transaksi->forceFill([
            'kode_redeem' => null,
            'poin_ditukar' => 0,
            'maks_potongan' => null,
        ])->save();

        return $poin;
    }

    /**
     * Pembatalan sebagian yang ternyata menghabiskan seluruh sisa item berakhir
     * sebagai `batal`, bukan `batal_sebagian` — transaksi tanpa satu pun item
     * tersisa bukan "sebagian".
     *
     * @param  list<array{detail: DetailTransaksi, qty: int, nilai: int}>  $rincian
     */
    private function statusSetelahPembatalan(Transaksi $transaksi, bool $penuh, array $rincian): string
    {
        if ($penuh) {
            return 'batal';
        }

        $dibatalkanSekarang = [];
        foreach ($rincian as $baris) {
            $id = $baris['detail']->id;
            $dibatalkanSekarang[$id] = ($dibatalkanSekarang[$id] ?? 0) + $baris['qty'];
        }

        $sudah = $this->rekapSudahDibatalkan($transaksi);

        foreach ($transaksi->detailTransaksi as $detail) {
            $terpakai = ($sudah[$detail->id]['qty'] ?? 0) + ($dibatalkanSekarang[$detail->id] ?? 0);

            if ($terpakai < (int) $detail->qty) {
                return 'batal_sebagian';
            }
        }

        return 'batal';
    }

    private function loyaltyTerkunci(Transaksi $transaksi): ?Loyalty
    {
        if ($transaksi->customer_id === null) {
            return null;
        }

        Loyalty::firstOrCreate(['customer_id' => $transaksi->customer_id], ['poin' => 0]);

        return Loyalty::where('customer_id', $transaksi->customer_id)->lockForUpdate()->first();
    }
}
