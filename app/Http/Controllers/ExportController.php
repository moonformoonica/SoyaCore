<?php

namespace App\Http\Controllers;

use App\Exports\LaporanExport;
use App\Exports\LaporanKasirExport;
use App\Exports\TransaksiExport;
use App\Http\Requests\IndexTransaksiRequest;
use App\Http\Requests\LaporanKasirRequest;
use App\Http\Requests\LaporanRequest;
use App\Models\User;
use App\Services\DaftarTransaksiQuery;
use App\Services\LaporanKasirQuery;
use App\Services\LaporanQuery;
use App\Services\RekapKasirHarian;
use App\Services\TransaksiHistorisQuery;
use App\Support\NamaFileLaporan;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Tiga unduhan Excel, satu per halaman yang punya tombol Unduh.
 *
 * ISI FILE MENGIKUTI HALAMAN ASALNYA, bukan satu file serba-ada untuk
 * ketiganya. Manager menekan Unduh sambil menatap satu tabel tertentu, dan
 * file yang berisi tujuh sheet lain memaksa dia mencari lagi tabel yang tadi
 * sudah ada di depan matanya. Nama filenya pun menyebut kategorinya, sehingga
 * tiga unduhan yang berdampingan di folder Downloads bisa dibedakan tanpa
 * dibuka satu per satu.
 */
class ExportController extends Controller
{
    public function __construct(
        private readonly LaporanQuery $query,
        private readonly RekapKasirHarian $rekapKasir,
    ) {}

    /**
     * Halaman Laporan: seluruh sheet analisis (ringkasan, rekap kasir, detail
     * transaksi, revenue per ukuran, time series, RFM, switch).
     */
    public function export(LaporanRequest $request): BinaryFileResponse
    {
        [$start, $end] = $this->query->resolveWindow($request->startInput(), $request->endInput());

        $kasirUserId = $request->kasirUserId();
        $kasirNama = $kasirUserId === null ? null : User::find($kasirUserId)?->nama;

        $export = new LaporanExport(
            $request->grain(), $start, $end, $this->query, $this->rekapKasir, $kasirUserId, $kasirNama,
        );

        return Excel::download($export, NamaFileLaporan::susun(
            'Laporan',
            $start,
            $end,
            // Nama kasir masuk ke nama file supaya beberapa export yang
            // di-download berurutan tidak saling menimpa di folder Downloads,
            // dan masih bisa dibedakan seminggu kemudian.
            $kasirNama === null ? null : Str::slug($kasirNama, '_'),
        ));
    }

    /**
     * Halaman Laporan Kasir: hanya tabel perbandingan antar akun kasir,
     * mengikuti rentang tanggal yang sedang dipasang di layar.
     */
    public function kasir(LaporanKasirRequest $request, LaporanKasirQuery $query): BinaryFileResponse
    {
        [$mulai, $selesai] = $request->rentang();

        $export = new LaporanKasirExport($mulai, $selesai, $query);
        [$mulaiFile, $selesaiFile] = $export->rentang();

        return Excel::download($export, NamaFileLaporan::susun('Laporan Kasir', $mulaiFile, $selesaiFile));
    }

    /**
     * Halaman Transaksi: daftar transaksi hasil filter yang sedang aktif,
     * seluruh barisnya, bukan cuma halaman yang sedang dibuka.
     */
    public function transaksi(
        IndexTransaksiRequest $request,
        DaftarTransaksiQuery $daftar,
        TransaksiHistorisQuery $historis,
    ): BinaryFileResponse {
        $export = new TransaksiExport($request, $daftar, $historis);
        [$mulai, $selesai] = $export->rentang();

        return Excel::download($export, NamaFileLaporan::susun('Laporan Transaksi', $mulai, $selesai));
    }
}
