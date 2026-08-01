<?php

namespace App\Http\Controllers;

use App\Http\Requests\LaporanKasirRequest;
use App\Services\LaporanKasirQuery;
use Illuminate\Http\JsonResponse;

/**
 * Laporan yang menyandingkan akun kasir berdampingan, manager tidak perlu
 * membuka satu per satu lalu membandingkan sendiri.
 */
class LaporanKasirController extends Controller
{
    public function __construct(private readonly LaporanKasirQuery $query) {}

    public function index(LaporanKasirRequest $request): JsonResponse
    {
        [$mulai, $selesai] = $request->rentang();

        return response()->json($this->query->laporan($mulai, $selesai));
    }
}
