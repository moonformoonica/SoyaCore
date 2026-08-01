<?php

namespace App\Http\Requests\Concerns;

use App\Support\WaktuToko;
use Illuminate\Validation\Rule;

/**
 * Aturan rentang tanggal yang dipakai bersama daftar transaksi (Blok A2) dan
 * laporan kasir (Blok C3).
 *
 * Sengaja satu tempat: kalau "7 hari terakhir" diparse ulang di endpoint kedua,
 * cukup satu selisih off-by-one untuk membuat total laporan kasir tidak pernah
 * cocok dengan daftar transaksi yang sedang dilihat manager di layar sebelah.
 */
trait MemfilterRentangTanggal
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function aturanRentangTanggal(): array
    {
        return [
            // Parameter lama dari halaman transaksi versi pertama. Tetap
            // didukung supaya frontend yang belum diperbarui tidak rusak.
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
            'tanggal_mulai' => ['nullable', 'date_format:Y-m-d'],
            // `after_or_equal` hanya dipasang kalau pembandingnya benar-benar
            // dikirim. Kalau tidak, `after_or_equal:tanggal_mulai` mencoba
            // membaca string 'tanggal_mulai' sebagai tanggal, gagal, dan
            // request yang cuma mengirim batas atas ditolak 422 tanpa sebab
            // yang bisa dijelaskan ke pemakainya.
            'tanggal_selesai' => array_merge(
                ['nullable', 'date_format:Y-m-d'],
                $this->filled('tanggal_mulai') ? ['after_or_equal:tanggal_mulai'] : [],
            ),
            'preset' => ['nullable', Rule::in(WaktuToko::PRESET)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function pesanRentangTanggal(): array
    {
        return [
            'tanggal_selesai.after_or_equal' => 'tanggal_selesai tidak boleh lebih awal dari tanggal_mulai.',
            'preset.in' => 'preset harus salah satu dari: '.implode(', ', WaktuToko::PRESET).'.',
            'tanggal.date_format' => 'tanggal harus berformat YYYY-MM-DD.',
            'tanggal_mulai.date_format' => 'tanggal_mulai harus berformat YYYY-MM-DD.',
            'tanggal_selesai.date_format' => 'tanggal_selesai harus berformat YYYY-MM-DD.',
        ];
    }

    /**
     * Rentang inklusif menurut WIB, `null` = tak dibatasi di sisi itu.
     *
     * Prioritasnya: batas eksplisit menang atas `preset`. Kalau salah satu
     * batas dikirim, preset diabaikan seluruhnya, rentang yang setengah
     * eksplisit setengah preset menghasilkan angka yang tidak bisa dijelaskan
     * ke siapa pun.
     *
     * @return array{0: ?string, 1: ?string}
     */
    public function rentang(): array
    {
        $data = $this->validated();

        $mulai = $data['tanggal_mulai'] ?? null;
        $selesai = $data['tanggal_selesai'] ?? null;

        if ($mulai !== null || $selesai !== null) {
            return [$mulai, $selesai];
        }

        if (isset($data['preset'])) {
            return WaktuToko::rentangPreset($data['preset']) ?? [null, null];
        }

        if (isset($data['tanggal'])) {
            return [$data['tanggal'], $data['tanggal']];
        }

        return [null, null];
    }
}
