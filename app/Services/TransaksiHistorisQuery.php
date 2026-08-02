<?php

namespace App\Services;

use App\Http\Requests\IndexTransaksiRequest;
use App\Models\LaporanTransaksi;
use Illuminate\Support\Carbon;

/**
 * Transaksi Juni-Juli 2026 hasil impor CSV, disusun ulang menjadi baris daftar
 * transaksi.
 *
 * KENAPA TIDAK DARI TABEL `transaksi`. Data lama tidak pernah melewati SoyaCore
 * sehingga tidak punya baris di sana; yang ada hanya `laporan_transaksi`, satu
 * baris PER ITEM. Baris-baris dengan `kode` yang sama disatukan di sini menjadi
 * satu transaksi, supaya halaman Transaksi memperlihatkan riwayat penuh, bukan
 * cuma sejak SoyaCore dipakai.
 *
 * BARIS INI READ-ONLY, dan itu bukan pembatasan yang dipaksakan. Data impor
 * tidak punya id transaksi, tidak punya kasir, dan tidak punya rincian
 * pembayaran, jadi Detail, Batalkan, maupun ubah item memang tidak ada yang
 * bisa dikerjakan. `id` sengaja dikirim `null` dan `historis` `true` supaya
 * frontend mematikan tombolnya, bukan menampilkan tombol yang pasti gagal.
 *
 * DIPROSES DI MEMORI, bukan lewat UNION SQL. Kedua sumber berbeda bentuk
 * (satu baris per transaksi vs satu baris per item), dan `laporan_transaksi`
 * berisi ratusan baris untuk satu kedai, bukan jutaan. UNION yang memaksakan
 * dua bentuk itu jadi satu query akan jauh lebih sulit dibaca daripada
 * keuntungan kecepatannya di skala ini.
 */
class TransaksiHistorisQuery
{
    public const SUMBER = 'historis';

    public const LABEL = 'Impor CSV';

    /**
     * Satu entri per transaksi historis, bentuknya mengikuti TransaksiResource.
     *
     * @return list<array<string, mixed>>
     */
    public function daftar(IndexTransaksiRequest $request): array
    {
        if (! $this->relevan($request)) {
            return [];
        }

        $baris = [];

        foreach ($this->queryTerfilter($request)->cursor() as $row) {
            $kode = (string) $row->kode;

            $baris[$kode] ??= [
                'id' => null,
                'historis' => true,
                'kode_pesanan' => $kode,
                'status' => 'lunas',
                'sumber' => self::SUMBER,
                'sumber_label' => trim((string) $row->platform) ?: self::LABEL,
                'customer' => [
                    'id' => null,
                    'nama' => trim((string) $row->nama_pelanggan) ?: null,
                    'no_wa' => trim((string) $row->no_wa) ?: null,
                ],
                'kasir_pembuat' => null,
                'kasir_penyelesai' => $this->kasir($row),
                'kasir' => $this->kasir($row),
                'items' => [],
                'subtotal' => 0,
                'diskon_persen' => 0,
                'diskon_nilai' => 0,
                'total' => 0,
                'metode_bayar' => $this->metodeBayar((string) $row->platform),
                'kode_redeem' => null,
                'poin_ditukar' => 0,
                'point_earned' => 0,
                'rupiah_per_poin' => null,
                'waktu_lunas' => $this->waktu($row->tanggal),
                'created_at' => $this->waktu($row->tanggal),
                'qty' => 0,
            ];

            $baris[$kode]['items'][] = [
                'id' => null,
                'nama_menu' => trim((string) $row->nama_produk),
                'rasa' => trim((string) $row->rasa) ?: null,
                'ukuran' => trim((string) $row->ukuran) ?: null,
                'qty' => (int) $row->qty,
                'harga_satuan' => (int) $row->harga_satuan,
                'subtotal' => (int) $row->total,
                'diskon_persen' => 0,
                'diskon_nilai' => 0,
                'is_reward' => false,
                'level_sugar' => null,
                'level_sugar_label' => null,
                'level_ice' => null,
                'level_ice_label' => null,
            ];

            $baris[$kode]['subtotal'] += (int) $row->total;
            $baris[$kode]['total'] += (int) $row->total;
            $baris[$kode]['point_earned'] += (int) $row->poin_loyalty;
            $baris[$kode]['qty'] += (int) $row->qty;
        }

        return $this->saringSetelahDikelompokkan(array_values($baris), $request);
    }

    /**
     * Ringkasan baris historis yang lolos filter, untuk dijumlahkan dengan
     * ringkasan transaksi POS.
     *
     * @param  list<array<string, mixed>>  $baris
     * @return array{jumlah_transaksi: int, total_omzet: int, total_qty: int}
     */
    public function ringkasan(array $baris): array
    {
        return [
            'jumlah_transaksi' => count($baris),
            'total_omzet' => array_sum(array_column($baris, 'total')),
            'total_qty' => array_sum(array_column($baris, 'qty')),
        ];
    }

    /**
     * Filter yang mustahil dipenuhi baris historis. Dijawab lebih awal supaya
     * seluruh tabel tidak perlu dibaca hanya untuk dibuang.
     */
    private function relevan(IndexTransaksiRequest $request): bool
    {
        // Semua baris impor sudah lunas, jadi filter status selain itu
        // menghasilkan nol baris historis.
        $status = $request->nilai('status');
        if ($status !== null && $status !== 'lunas') {
            return false;
        }

        // `sumber` hanya mengenal kasir dan self_order untuk data POS. Baris
        // historis bukan keduanya.
        $sumber = $request->nilai('sumber');
        if ($sumber !== null && $sumber !== self::SUMBER) {
            return false;
        }

        // Tidak ada redeem poin pada data impor.
        if ($request->adaRedeem() === true) {
            return false;
        }

        // Filter kasir menyasar akun SoyaCore; baris impor tidak punya.
        foreach (['user_id', 'dibuat_oleh', 'dibayar_oleh'] as $kunci) {
            if ($request->nilai($kunci) !== null) {
                return false;
            }
        }

        return true;
    }

    private function queryTerfilter(IndexTransaksiRequest $request)
    {
        [$mulai, $selesai] = $request->rentang();

        $query = LaporanTransaksi::query()
            // Baris proyeksi POS punya padanannya sendiri di tabel `transaksi`,
            // jadi hanya baris impor CSV yang diambil di sini. Tanpa saringan
            // ini, transaksi POS akan tampil dua kali di daftar.
            ->where('kode', 'not like', LaporanTransaksi::PREFIX_POS.'%');

        if ($mulai !== null) {
            $query->whereDate('tanggal', '>=', $mulai);
        }
        if ($selesai !== null) {
            $query->whereDate('tanggal', '<=', $selesai);
        }

        $metode = $request->nilai('metode_bayar');
        if ($metode !== null) {
            $query->whereRaw('lower(platform) = ?', [$metode]);
        }

        return $query;
    }

    /**
     * Filter yang baru bisa dinilai setelah baris item disatukan jadi satu
     * transaksi: total belanja dan pencarian bebas.
     *
     * @param  list<array<string, mixed>>  $baris
     * @return list<array<string, mixed>>
     */
    private function saringSetelahDikelompokkan(array $baris, IndexTransaksiRequest $request): array
    {
        $min = $request->nilai('total_min');
        $max = $request->nilai('total_max');
        $cari = trim((string) $request->nilai('cari'));

        return array_values(array_filter($baris, function (array $b) use ($min, $max, $cari) {
            if ($min !== null && $b['total'] < $min) {
                return false;
            }
            if ($max !== null && $b['total'] > $max) {
                return false;
            }
            if ($cari === '') {
                return true;
            }

            // Kode pesanan, nama pelanggan, dan nomor WA, sama seperti
            // pencarian pada daftar POS. Nomor WA jarang terisi di data impor,
            // tapi ikut dicocokkan kalau ada.
            foreach ([$b['kode_pesanan'], $b['customer']['nama'], $b['customer']['no_wa']] as $nilai) {
                if ($nilai !== null && mb_stripos((string) $nilai, $cari) !== false) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * @return array{id: int|null, nama: string}|null
     */
    private function kasir(LaporanTransaksi $row): ?array
    {
        $nama = trim((string) $row->kasir_nama);

        return $nama === '' ? null : ['id' => $row->kasir_user_id, 'nama' => $nama];
    }

    /**
     * `platform` pada data impor bercampur: ada metode bayar (`cash`, `QRIS`)
     * dan ada kanal penjualan (`GrabFood`, `ShopeeFood`). Hanya dua yang
     * pertama yang punya arti sebagai metode bayar.
     */
    private function metodeBayar(string $platform): ?string
    {
        $bersih = mb_strtolower(trim($platform));

        return in_array($bersih, ['cash', 'qris'], true) ? $bersih : null;
    }

    private function waktu(mixed $tanggal): ?string
    {
        return $tanggal === null ? null : Carbon::parse($tanggal)->startOfDay()->toIso8601String();
    }
}
