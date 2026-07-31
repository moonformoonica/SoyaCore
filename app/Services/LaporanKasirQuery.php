<?php

namespace App\Services;

use App\Models\Pembatalan;
use App\Models\Transaksi;
use App\Support\WaktuToko;
use Illuminate\Database\Eloquent\Collection;

/**
 * Laporan perbandingan antar akun kasir.
 *
 * ATURAN ATRIBUSI, dipakai konsisten di seluruh class ini:
 *
 * 1. Penjualan dihitung ke akun kasir yang MENYELESAIKAN pembayaran
 *    (`dibayar_oleh`) — di titik itulah transaksi benar-benar terjadi.
 *    `user_id` tetap merekam siapa yang menyusun pesanannya, jadi tidak ada
 *    informasi yang hilang saat pesanan berpindah tangan.
 * 2. Rentang dipotong berdasarkan `waktu_lunas`, BUKAN `created_at` —
 *    transaksi yang dibuat kemarin malam dan dibayar pagi ini masuk ke hari
 *    ini. Batas harinya WIB, lewat {@see WaktuToko}.
 * 3. Transaksi `pending` tidak masuk laporan kasir mana pun. Ia belum jadi
 *    penjualan.
 * 4. Transaksi yang dibatalkan PENUH keluar dari laporan; yang dibatalkan
 *    sebagian tetap masuk dengan nilai yang sudah dikurangi.
 *
 * DUA ANGKA PEMBATALAN YANG BEDA MAKNANYA, dan ini gampang tertukar:
 * - `nilai_dibatalkan` + `jumlah_pembatalan` = pembatalan yang DIPROSES akun
 *   ini (pembatalan.user_id). Ini angka akuntabilitas — pembatalan berlebih
 *   dari satu akun adalah pola yang perlu terlihat.
 * - Pengurang `total_omzet` = pembatalan atas PENJUALAN akun ini, siapa pun
 *   yang memprosesnya. Kalau pengurangnya ikut mengejar akun pemroses, omzet
 *   akun yang tidak pernah menerima uangnya bisa jadi minus dan `meta` tidak
 *   lagi bisa direkonsiliasi dengan dashboard.
 *
 * Pada kasus normal (kasir yang sama menjual dan membatalkan) kedua angka itu
 * sama besar, jadi bedanya cuma terasa saat pembatalan menyeberangi akun.
 */
class LaporanKasirQuery
{
    /** Label untuk baris tanpa akun kasir — lihat catatan di {@see self::baris()}. */
    public const LABEL_TANPA_AKUN = '— (tanpa akun)';

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function laporan(?string $mulai, ?string $selesai): array
    {
        $transaksi = $this->transaksiTerhitung($mulai, $selesai);
        $pembatalan = $this->pembatalanDiproses($mulai, $selesai);

        $baris = [];

        foreach ($transaksi->groupBy(fn (Transaksi $t) => (int) $t->dibayar_oleh) as $kunci => $grup) {
            $baris[(int) $kunci] = $this->baris((int) $kunci, $grup);
        }

        // Akun yang tidak menutup transaksi apa pun tapi memproses pembatalan
        // tetap harus muncul — kalau tidak, satu-satunya jejak akun yang cuma
        // membatalkan justru hilang dari laporan.
        foreach ($pembatalan->groupBy(fn (Pembatalan $p) => (int) $p->user_id) as $kunci => $grup) {
            $baris[(int) $kunci] ??= $this->barisKosong((int) $kunci, $grup->first()->user?->nama);

            $baris[(int) $kunci]['jumlah_pembatalan'] = $grup->count();
            $baris[(int) $kunci]['nilai_dibatalkan'] = (int) $grup->sum('nilai_dibatalkan');
        }

        $baris = array_values($baris);
        usort($baris, fn ($a, $b) => $b['total_omzet'] <=> $a['total_omzet']);

        return ['data' => $baris, 'meta' => $this->meta($baris, $mulai, $selesai)];
    }

    /**
     * @return Collection<int, Transaksi>
     */
    private function transaksiTerhitung(?string $mulai, ?string $selesai): Collection
    {
        $query = Transaksi::query()
            ->with('dibayarOleh:id,nama')
            ->withSum('detailTransaksi as qty_kotor', 'qty')
            ->withSum('detailTransaksi as diskon_kotor', 'diskon_nilai')
            ->withSum('pembatalan as nilai_dibatalkan_kotor', 'nilai_dibatalkan')
            ->withSum('pembatalan as poin_ditarik_kotor', 'poin_ditarik')
            ->withSum('pembatalanItem as qty_dibatalkan_kotor', 'qty')
            ->whereNotNull('waktu_lunas')
            ->whereIn('status', ['lunas', 'batal_sebagian']);

        if ($mulai !== null) {
            $query->where('waktu_lunas', '>=', WaktuToko::awalHari($mulai));
        }
        if ($selesai !== null) {
            $query->where('waktu_lunas', '<=', WaktuToko::akhirHari($selesai));
        }

        return $query->get();
    }

    /**
     * Pembatalan yang DIPROSES dalam rentang ini (berdasarkan waktu dokumen
     * pembatalannya, bukan tanggal penjualan aslinya) — itu pertanyaan yang
     * dijawab kolom akuntabilitas: "akun ini membatalkan berapa kali periode
     * ini".
     *
     * @return Collection<int, Pembatalan>
     */
    private function pembatalanDiproses(?string $mulai, ?string $selesai): Collection
    {
        $query = Pembatalan::query()->with('user:id,nama');

        if ($mulai !== null) {
            $query->where('created_at', '>=', WaktuToko::awalHari($mulai));
        }
        if ($selesai !== null) {
            $query->where('created_at', '<=', WaktuToko::akhirHari($selesai));
        }

        return $query->get();
    }

    /**
     * `user_id` 0 pada kunci grup berarti `dibayar_oleh` null. Seharusnya tidak
     * pernah terjadi untuk transaksi baru — `bayar()` selalu punya user
     * terautentikasi — tapi barisnya tetap ditampilkan berlabel jelas, bukan
     * dibuang: total yang tidak bisa direkonsiliasi lebih berbahaya daripada
     * satu baris berlabel jujur.
     *
     * @param  Collection<int, Transaksi>  $grup
     * @return array<string, mixed>
     */
    private function baris(int $userId, Collection $grup): array
    {
        $omzet = 0;
        $qty = 0;
        $diskon = 0;
        $poinDiberikan = 0;
        $poinDitukar = 0;
        $dibuatKasirLain = 0;
        $metode = [
            'cash' => ['jumlah' => 0, 'total' => 0],
            'qris' => ['jumlah' => 0, 'total' => 0],
        ];

        foreach ($grup as $t) {
            $nilaiBersih = max(0, (int) $t->total - (int) $t->nilai_dibatalkan_kotor);

            $omzet += $nilaiBersih;
            $qty += max(0, (int) $t->qty_kotor - (int) $t->qty_dibatalkan_kotor);
            $diskon += (int) $t->diskon_kotor;
            $poinDiberikan += max(0, (int) $t->point_earned - (int) $t->poin_ditarik_kotor);
            $poinDitukar += (int) $t->poin_ditukar;

            // Transaksi yang omzetnya masuk ke akun ini tapi disusun akun lain.
            // Inilah angka yang menunjukkan serah terima pesanan saat
            // pergantian; tanpa itu laporan Kasir 2 terlihat seolah semua
            // pesanan dia yang buat. Pesanan SoyaScan (`user_id` null) tidak
            // dihitung — memang tidak ada kasir lain yang membuatnya.
            if ($t->user_id !== null && (int) $t->user_id !== $userId) {
                $dibuatKasirLain++;
            }

            if (isset($metode[$t->metode_bayar])) {
                $metode[$t->metode_bayar]['jumlah']++;
                $metode[$t->metode_bayar]['total'] += $nilaiBersih;
            }
        }

        $jumlah = $grup->count();

        return [
            'user_id' => $userId === 0 ? null : $userId,
            'nama' => $grup->first()->dibayarOleh?->nama ?? self::LABEL_TANPA_AKUN,
            'jumlah_transaksi' => $jumlah,
            'total_omzet' => $omzet,
            'total_qty' => $qty,
            'rata_rata_transaksi' => $jumlah > 0 ? (int) round($omzet / $jumlah) : 0,
            'rincian_metode_bayar' => $metode,
            // Diskon dicatat sebesar yang BENAR-BENAR diberikan saat transaksi
            // terjadi, tidak dikurangi pembatalan: potongannya memang pernah
            // diberikan, dan angka ini dipakai mengevaluasi kebijakan diskon.
            'total_diskon' => $diskon,
            'total_poin_diberikan' => $poinDiberikan,
            'total_poin_ditukar' => $poinDitukar,
            'jumlah_pembatalan' => 0,
            'nilai_dibatalkan' => 0,
            'jumlah_transaksi_dibuat_kasir_lain' => $dibuatKasirLain,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function barisKosong(int $userId, ?string $nama): array
    {
        return [
            'user_id' => $userId === 0 ? null : $userId,
            'nama' => $nama ?? self::LABEL_TANPA_AKUN,
            'jumlah_transaksi' => 0,
            'total_omzet' => 0,
            'total_qty' => 0,
            'rata_rata_transaksi' => 0,
            'rincian_metode_bayar' => [
                'cash' => ['jumlah' => 0, 'total' => 0],
                'qris' => ['jumlah' => 0, 'total' => 0],
            ],
            'total_diskon' => 0,
            'total_poin_diberikan' => 0,
            'total_poin_ditukar' => 0,
            'jumlah_pembatalan' => 0,
            'nilai_dibatalkan' => 0,
            'jumlah_transaksi_dibuat_kasir_lain' => 0,
        ];
    }

    /**
     * Total seluruh kasir, supaya angka laporan ini bisa direkonsiliasi dengan
     * dashboard tanpa manager menjumlahkan barisnya sendiri.
     *
     * @param  list<array<string, mixed>>  $baris
     * @return array<string, mixed>
     */
    private function meta(array $baris, ?string $mulai, ?string $selesai): array
    {
        $jumlahTransaksi = array_sum(array_column($baris, 'jumlah_transaksi'));
        $totalOmzet = array_sum(array_column($baris, 'total_omzet'));

        return [
            'tanggal_mulai' => $mulai,
            'tanggal_selesai' => $selesai,
            'jumlah_kasir' => count($baris),
            'jumlah_transaksi' => $jumlahTransaksi,
            'total_omzet' => $totalOmzet,
            'total_qty' => array_sum(array_column($baris, 'total_qty')),
            'rata_rata_transaksi' => $jumlahTransaksi > 0 ? (int) round($totalOmzet / $jumlahTransaksi) : 0,
            'total_diskon' => array_sum(array_column($baris, 'total_diskon')),
            'total_poin_diberikan' => array_sum(array_column($baris, 'total_poin_diberikan')),
            'total_poin_ditukar' => array_sum(array_column($baris, 'total_poin_ditukar')),
            'jumlah_pembatalan' => array_sum(array_column($baris, 'jumlah_pembatalan')),
            'nilai_dibatalkan' => array_sum(array_column($baris, 'nilai_dibatalkan')),
        ];
    }
}
