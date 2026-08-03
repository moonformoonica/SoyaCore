<?php

namespace App\Services;

use App\Models\LaporanTransaksi;
use App\Models\Pembatalan;
use App\Models\Transaksi;
use App\Support\WaktuToko;

/**
 * Rekap tanggal × kasir untuk sheet `Rekap Kasir` di export Excel.
 *
 * DUA SUMBER, DAN INI DISENGAJA:
 *
 * - Kolom uang, qty, dan poin diberikan diambil dari `laporan_transaksi`,
 *   tabel yang SAMA dengan yang dibaca sheet `Ringkasan` dan dashboard. Dengan
 *   begitu totalnya cocok by construction, bukan karena dua rumus terpisah
 *   kebetulan menghasilkan angka yang sama.
 * - Kolom diskon, poin ditukar, dan pembatalan diambil dari tabel POS live,
 *   karena `laporan_transaksi` memang tidak menyimpannya.
 *
 * BARIS TANPA KASIR TETAP DIMASUKKAN, berlabel `— (data historis)`. Kalau
 * dibuang, total sheet ini tidak akan pernah cocok dengan `Ringkasan`, dan
 * manager yang menjumlahkan sendiri akan mengira ada data hilang. Angka yang
 * tidak bisa direkonsiliasi lebih berbahaya daripada satu baris berlabel jujur.
 */
class RekapKasirHarian
{
    public const LABEL_HISTORIS = '— (data historis)';

    public const LABEL_TOTAL_TANGGAL = 'TOTAL';

    public const LABEL_TOTAL_SEMUA = 'TOTAL KESELURUHAN';

    /**
     * Satu baris per kombinasi tanggal × kasir, diurutkan tanggal lalu nama,
     * dengan baris TOTAL di akhir tiap tanggal dan satu total keseluruhan,
     * supaya manager membaca file ini tanpa membuat pivot sendiri.
     *
     * @return list<array<string, mixed>>
     */
    public function baris(?string $start, ?string $end, ?int $kasirUserId = null): array
    {
        $rekap = $this->dariLaporan($start, $end, $kasirUserId);
        $this->tempelkanDataPos($rekap, $start, $end, $kasirUserId);

        ksort($rekap);

        $hasil = [];
        $totalSemua = $this->kosong('', self::LABEL_TOTAL_SEMUA);

        foreach ($this->kelompokkanPerTanggal($rekap) as $tanggal => $barisTanggal) {
            usort($barisTanggal, fn ($a, $b) => strcmp((string) $a['kasir'], (string) $b['kasir']));

            $totalTanggal = $this->kosong($tanggal, self::LABEL_TOTAL_TANGGAL);

            foreach ($barisTanggal as $baris) {
                $hasil[] = $this->lengkapi($baris);
                $this->akumulasi($totalTanggal, $baris);
                $this->akumulasi($totalSemua, $baris);
            }

            // TOTAL per tanggal hanya ditulis kalau hari itu memang punya lebih
            // dari satu kasir. Menjumlahkan satu baris bukan total, itu salinan
            // persis baris di atasnya, dan periode Juni-Juli yang seluruhnya
            // data historis (satu "kasir" saja per tanggal) menghasilkan
            // enam puluh baris TOTAL berturut-turut yang tidak menjawab apa pun
            // sambil menenggelamkan baris datanya.
            if (count($barisTanggal) > 1) {
                $hasil[] = $this->lengkapi($totalTanggal);
            }
        }

        if ($hasil !== []) {
            $hasil[] = $this->lengkapi($totalSemua);
        }

        return $hasil;
    }

    /**
     * @param  array<string, array<string, mixed>>  $rekap
     * @return array<string, list<array<string, mixed>>>
     */
    private function kelompokkanPerTanggal(array $rekap): array
    {
        $perTanggal = [];
        foreach ($rekap as $baris) {
            $perTanggal[$baris['tanggal']][] = $baris;
        }

        return $perTanggal;
    }

    /**
     * @return array<string, array<string, mixed>> Dikunci "tanggal|user_id".
     */
    private function dariLaporan(?string $start, ?string $end, ?int $kasirUserId): array
    {
        $query = LaporanTransaksi::query();

        if ($start !== null) {
            $query->whereDate('tanggal', '>=', $start);
        }
        if ($end !== null) {
            $query->whereDate('tanggal', '<=', $end);
        }
        if ($kasirUserId !== null) {
            $query->where('kasir_user_id', $kasirUserId);
        }

        $rekap = [];
        $transaksiUnik = [];

        foreach ($query->cursor() as $row) {
            $tanggal = $row->tanggal->format('Y-m-d');
            $kunci = $tanggal.'|'.((int) $row->kasir_user_id);

            $rekap[$kunci] ??= $this->kosong($tanggal, $row->kasir_nama ?? self::LABEL_HISTORIS, $row->kasir_user_id);

            $rekap[$kunci]['total_qty'] += (int) $row->qty;
            $rekap[$kunci]['total_omzet'] += (int) $row->total;
            $rekap[$kunci]['poin_diberikan'] += (int) $row->poin_loyalty;

            // `platform` berisi metode bayar untuk baris POS, dan campuran
            // channel untuk baris CSV historis (Shopee/GoJek/…). Yang bukan
            // cash/qris tidak masuk kedua kolom itu tapi tetap ada di Total
            // Omzet, jadi keduanya tidak selalu berjumlah sama dengan total,
            // dan itu memang apa adanya datanya.
            $metode = mb_strtolower((string) $row->platform);
            if ($metode === 'cash' || $metode === 'qris') {
                $rekap[$kunci][$metode] += (int) $row->total;
            }

            // Satu transaksi POS tersebar ke beberapa baris item, jadi
            // "jumlah transaksi" harus dihitung dari transaksi uniknya, bukan
            // dari jumlah baris.
            $transaksiUnik[$kunci][$this->kunciTransaksi((string) $row->kode)] = true;
        }

        foreach ($transaksiUnik as $kunci => $daftar) {
            $rekap[$kunci]['jumlah_transaksi'] = count($daftar);
        }

        return $rekap;
    }

    /**
     * `TRX-12-345` (baris proyeksi POS) berasal dari transaksi `TRX-12`.
     * Baris impor CSV punya `kode` unik per baris, jadi ia jadi kuncinya
     * sendiri.
     */
    private function kunciTransaksi(string $kode): string
    {
        if (! str_starts_with($kode, LaporanTransaksi::PREFIX_POS)) {
            return $kode;
        }

        $bagian = explode('-', $kode);

        return $bagian[0].'-'.($bagian[1] ?? '');
    }

    /**
     * Kolom yang tidak ada di layer laporan: diskon, poin ditukar, dan
     * pembatalan. Hanya berlaku untuk transaksi POS, baris CSV historis
     * memang tidak punya padanannya, dan dibiarkan 0.
     *
     * @param  array<string, array<string, mixed>>  $rekap
     */
    private function tempelkanDataPos(array &$rekap, ?string $start, ?string $end, ?int $kasirUserId): void
    {
        $transaksi = Transaksi::query()
            ->withSum('detailTransaksi as diskon_kotor', 'diskon_nilai')
            ->whereNotNull('waktu_lunas')
            ->whereIn('status', ['lunas', 'batal_sebagian']);

        $this->batasiWaktu($transaksi, 'waktu_lunas', $start, $end);

        if ($kasirUserId !== null) {
            $transaksi->where('dibayar_oleh', $kasirUserId);
        }

        foreach ($transaksi->get() as $t) {
            $kunci = WaktuToko::tanggal($t->waktu_lunas).'|'.((int) $t->dibayar_oleh);
            if (! isset($rekap[$kunci])) {
                continue; // tidak terproyeksi (mis. semua itemnya sudah dibatalkan)
            }

            $rekap[$kunci]['total_diskon'] += (int) $t->diskon_kotor;
            $rekap[$kunci]['poin_ditukar'] += (int) $t->poin_ditukar;
        }

        // Pembatalan diatribusikan ke akun yang MEMPROSESnya, dan ditaruh pada
        // tanggal dokumen pembatalannya, bukan tanggal penjualan aslinya.
        $pembatalan = Pembatalan::query()->with('user:id,nama');
        $this->batasiWaktu($pembatalan, 'created_at', $start, $end);

        if ($kasirUserId !== null) {
            $pembatalan->where('user_id', $kasirUserId);
        }

        foreach ($pembatalan->get() as $p) {
            $tanggal = WaktuToko::tanggal($p->created_at);
            $kunci = $tanggal.'|'.((int) $p->user_id);

            $rekap[$kunci] ??= $this->kosong($tanggal, $p->user?->nama ?? self::LABEL_HISTORIS, $p->user_id);

            $rekap[$kunci]['jumlah_pembatalan']++;
            $rekap[$kunci]['nilai_dibatalkan'] += (int) $p->nilai_dibatalkan;
        }
    }

    private function batasiWaktu(mixed $query, string $kolom, ?string $start, ?string $end): void
    {
        if ($start !== null) {
            $query->where($kolom, '>=', WaktuToko::awalHari($start));
        }
        if ($end !== null) {
            $query->where($kolom, '<=', WaktuToko::akhirHari($end));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function kosong(string $tanggal, string $kasir, ?int $kasirUserId = null): array
    {
        return [
            'tanggal' => $tanggal,
            'kasir' => $kasir,
            'kasir_user_id' => $kasirUserId,
            'jumlah_transaksi' => 0,
            'total_qty' => 0,
            'total_omzet' => 0,
            'cash' => 0,
            'qris' => 0,
            'total_diskon' => 0,
            'poin_diberikan' => 0,
            'poin_ditukar' => 0,
            'jumlah_pembatalan' => 0,
            'nilai_dibatalkan' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $total
     * @param  array<string, mixed>  $baris
     */
    private function akumulasi(array &$total, array $baris): void
    {
        foreach ([
            'jumlah_transaksi', 'total_qty', 'total_omzet', 'cash', 'qris',
            'total_diskon', 'poin_diberikan', 'poin_ditukar',
            'jumlah_pembatalan', 'nilai_dibatalkan',
        ] as $kolom) {
            $total[$kolom] += $baris[$kolom];
        }
    }

    /**
     * @param  array<string, mixed>  $baris
     * @return array<string, mixed>
     */
    private function lengkapi(array $baris): array
    {
        $baris['rata_rata_transaksi'] = $baris['jumlah_transaksi'] > 0
            ? (int) round($baris['total_omzet'] / $baris['jumlah_transaksi'])
            : 0;

        return $baris;
    }
}
