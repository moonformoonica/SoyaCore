<?php

namespace App\Exports;

use App\Exports\Concerns\GayaTabelSoyaCore;
use App\Http\Requests\IndexTransaksiRequest;
use App\Http\Resources\TransaksiResource;
use App\Models\Transaksi;
use App\Services\DaftarTransaksiQuery;
use App\Services\TransaksiHistorisQuery;
use App\Support\WaktuToko;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Unduhan halaman Transaksi: SATU sheet berisi daftar transaksi yang sedang
 * terlihat di layar, mengikuti seluruh filternya.
 *
 * IKUT FILTER, BUKAN CUMA TANGGAL. Halaman Transaksi punya tujuh filter
 * (Sumber, Kasir, Status, Metode, Redeem, pencarian, rentang tanggal) dan
 * manager yang menekan Unduh sesudah menyaring jelas menginginkan hasil
 * saringannya. Karena itu export ini dibangun dari {@see DaftarTransaksiQuery}
 * dan {@see TransaksiHistorisQuery} yang sama persis dipakai
 * `GET /api/transaksi`, bukan dari proyeksi laporan.
 *
 * TANPA PAGINASI. Yang diunduh adalah seluruh baris hasil filter, bukan 15
 * baris halaman yang sedang dibuka. Batas `per_page` di layar ada supaya tabel
 * HTML tetap ringan; file Excel justru dipakai untuk yang tidak muat di layar.
 *
 * SATU BARIS PER TRANSAKSI, bukan per item. Itu yang ditampilkan halaman
 * Transaksi, dan rincian per item sudah punya rumahnya sendiri di sheet
 * "Detail Transaksi" milik {@see LaporanExport}.
 */
class TransaksiExport implements FromArray, WithEvents, WithHeadings, WithTitle
{
    use GayaTabelSoyaCore;

    /** Kolom yang memang tidak punya isi pada baris impor CSV lama. */
    private const KOSONG = '—';

    /** @var list<array<string, mixed>>|null */
    private ?array $baris = null;

    public function __construct(
        private readonly IndexTransaksiRequest $request,
        private readonly DaftarTransaksiQuery $daftar,
        private readonly TransaksiHistorisQuery $historis,
    ) {}

    /**
     * @return list<int>
     */
    protected function kolomAngka(): array
    {
        return [9, 10, 11, 12, 13, 14];
    }

    public function title(): string
    {
        return 'Transaksi';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Id Transaksi', 'Tanggal', 'Waktu', 'Id Pesanan', 'Pelanggan', 'No WhatsApp',
            'Sumber', 'Kasir', 'Total (Rp)', 'Subtotal (Rp)', 'Diskon (Rp)', 'Jumlah Item',
            'Poin Didapat', 'Poin Ditukar', 'Metode Bayar', 'Status',
        ];
    }

    /**
     * @return array<int, array<int, int|string|null>>
     */
    public function array(): array
    {
        return array_map(fn (array $t) => [
            $t['id'] ?? self::KOSONG,
            $this->bagianWaktu($t['created_at'] ?? null, 'Y-m-d'),
            $this->bagianWaktu($t['created_at'] ?? null, 'H:i'),
            $t['kode_pesanan'] ?? self::KOSONG,
            $t['customer']['nama'] ?? 'Umum',
            $t['customer']['no_wa'] ?? self::KOSONG,
            $t['sumber_label'] ?? $t['sumber'] ?? self::KOSONG,
            $this->kasir($t),
            (int) ($t['total'] ?? 0),
            (int) ($t['subtotal'] ?? 0),
            (int) ($t['diskon_nilai'] ?? 0),
            (int) ($t['qty'] ?? 0),
            (int) ($t['point_earned'] ?? 0),
            (int) ($t['poin_ditukar'] ?? 0),
            $this->metodeBayar($t['metode_bayar'] ?? null),
            $this->status($t['status'] ?? null),
        ], $this->baris());
    }

    /**
     * Rentang yang benar-benar terwakili file ini, dipakai menyusun nama
     * filenya. Diambil dari baris hasil filter, bukan dari input manager, jadi
     * unduhan tanpa filter tanggal tetap dinamai dengan tanggal sungguhan.
     *
     * @return array{0: ?string, 1: ?string}
     */
    public function rentang(): array
    {
        [$mulai, $selesai] = $this->request->rentang();

        if ($mulai !== null && $selesai !== null) {
            return [$mulai, $selesai];
        }

        $tanggal = array_filter(array_map(
            fn (array $t) => $this->bagianWaktu($t['created_at'] ?? null, 'Y-m-d'),
            $this->baris(),
        ));

        if ($tanggal === []) {
            return [$mulai, $selesai];
        }

        return [$mulai ?? min($tanggal), $selesai ?? max($tanggal)];
    }

    /**
     * Transaksi POS digabung dengan baris impor CSV lalu diurutkan sebagai satu
     * daftar, sama seperti `TransaksiController::index()`. Urutan pemecah
     * serinya ikut disamakan (`created_at` lalu `kode_pesanan`): seluruh baris
     * historis satu hari punya jam yang identik, dan tanpa pemecah seri urutan
     * file bisa berbeda tiap kali diunduh.
     *
     * @return list<array<string, mixed>>
     */
    private function baris(): array
    {
        if ($this->baris !== null) {
            return $this->baris;
        }

        $arah = $this->request->urut() === 'terlama' ? 'asc' : 'desc';

        $pos = $this->daftar->bangun($this->request)
            ->with(['customer', 'user', 'dibayarOleh', 'detailTransaksi.menu'])
            ->orderBy('created_at', $arah)
            ->orderBy('id', $arah)
            ->get()
            // `qty` ditempelkan di sini, bukan dibaca dari `items`, karena
            // TransaksiResource tidak memuat totalnya dan `items` di dalam
            // hasil `resolve()` masih berupa koleksi resource, bukan array.
            ->map(fn (Transaksi $t) => (new TransaksiResource($t))->resolve($this->request) + [
                'qty' => (int) $t->detailTransaksi->sum('qty'),
            ])
            ->all();

        $gabungan = array_merge($pos, $this->historis->daftar($this->request));

        usort($gabungan, function (array $a, array $b) use ($arah) {
            $kiri = [$a['created_at'] ?? '', $a['kode_pesanan'] ?? ''];
            $kanan = [$b['created_at'] ?? '', $b['kode_pesanan'] ?? ''];

            return $arah === 'asc' ? $kiri <=> $kanan : $kanan <=> $kiri;
        });

        return $this->baris = $gabungan;
    }

    /**
     * Nama kasir seperti yang tampil di layar: penyelesai pembayaran bila ada,
     * jatuh ke penyusun pesanan bila transaksinya belum dibayar. Pesanan yang
     * berpindah tangan ditulis keduanya, informasi yang hilang kalau cuma satu
     * nama yang dibawa.
     *
     * @param  array<string, mixed>  $t
     */
    private function kasir(array $t): string
    {
        $pembuat = $t['kasir_pembuat']['nama'] ?? null;
        $penyelesai = $t['kasir_penyelesai']['nama'] ?? null;

        if ($pembuat !== null && $penyelesai !== null && $pembuat !== $penyelesai) {
            return 'Dibuat '.$pembuat.' · Dibayar '.$penyelesai;
        }

        return $penyelesai ?? $pembuat ?? ($t['kasir']['nama'] ?? self::KOSONG);
    }

    private function metodeBayar(?string $metode): string
    {
        return match ($metode) {
            'cash' => 'Tunai',
            null => self::KOSONG,
            default => mb_strtoupper($metode),
        };
    }

    /**
     * Istilahnya disamakan dengan badge di layar. `batal_sebagian` tidak punya
     * badge sendiri di halaman Transaksi, tapi di Excel ditulis apa adanya:
     * transaksi yang sebagian itemnya dibatalkan bukan hal yang boleh terbaca
     * sama dengan transaksi yang lunas utuh.
     */
    private function status(?string $status): string
    {
        return match ($status) {
            'lunas' => 'Selesai',
            'batal' => 'Batal',
            'batal_sebagian' => 'Batal Sebagian',
            'pending' => 'Proses',
            default => self::KOSONG,
        };
    }

    private function bagianWaktu(?string $iso, string $format): ?string
    {
        return $iso === null ? null : Carbon::parse($iso)->setTimezone(WaktuToko::ZONA)->format($format);
    }
}
