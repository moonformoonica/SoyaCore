<?php

namespace App\Http\Controllers;

use App\Exports\LaporanExport;
use App\Http\Requests\LaporanRequest;
use App\Services\LaporanQuery;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    public function __construct(private readonly LaporanQuery $query) {}


    public function export(LaporanRequest $request): BinaryFileResponse
    {
        [$start, $end] = $this->query->resolveWindow($request->startInput(), $request->endInput());
        $grain = $request->grain();

        $filename = sprintf(
            'Laporan_SoyaCore_%s_%s_%s.xlsx',
            $grain,
            $start ?? 'awal',
            $end ?? 'akhir',
        );

        return Excel::download(new LaporanExport($grain, $start, $end, $this->query), $filename);
    }
}
