<?php

namespace App\Http\Controllers;

use App\Exports\LaporanExport;
use App\Http\Requests\LaporanRequest;
use App\Models\User;
use App\Services\LaporanQuery;
use App\Services\RekapKasirHarian;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    public function __construct(
        private readonly LaporanQuery $query,
        private readonly RekapKasirHarian $rekapKasir,
    ) {}

    public function export(LaporanRequest $request): BinaryFileResponse
    {
        [$start, $end] = $this->query->resolveWindow($request->startInput(), $request->endInput());
        $grain = $request->grain();

        $kasirUserId = $request->kasirUserId();
        $kasirNama = $kasirUserId === null ? null : User::find($kasirUserId)?->nama;

        $filename = sprintf(
            'Laporan_SoyaCore_%s_%s_%s%s.xlsx',
            $grain,
            $start ?? 'awal',
            $end ?? 'akhir',
            // Nama kasir masuk ke nama file supaya beberapa export yang
            // di-download berurutan tidak saling menimpa di folder Downloads,
            // dan masih bisa dibedakan seminggu kemudian.
            $kasirNama === null ? '' : '_'.Str::slug($kasirNama, '_'),
        );

        $export = new LaporanExport(
            $grain, $start, $end, $this->query, $this->rekapKasir, $kasirUserId, $kasirNama,
        );

        return Excel::download($export, $filename);
    }
}
