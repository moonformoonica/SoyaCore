<?php

namespace App\Services;

use App\Exceptions\ApiException;

class DiskonEngine
{
    public const PRESET_PERSEN = [10, 20, 50];

    /**
     * `$maksPotongan` null (bawaan) = tanpa plafon, jadi diskon manual kasir
     * berperilaku persis seperti sebelum plafon ada. Plafon hanya dipakai
     * diskon yang berasal dari redeem poin.
     *
     * @return array{diskon_persen: int, diskon_nilai: int}
     */
    public function hitung(int $subtotal, string $tipe, int $nilai, ?int $maksPotongan = null): array
    {
        $hasil = match ($tipe) {
            'preset' => $this->preset($subtotal, $nilai),
            'custom_persen' => $this->customPersen($subtotal, $nilai),
            'custom_nilai' => $this->customNilai($subtotal, $nilai),
            default => throw new ApiException(
                'tipe_diskon_invalid',
                "Tipe diskon '{$tipe}' tidak dikenal. Gunakan: preset, custom_persen, atau custom_nilai.",
                422,
            ),
        };

        return $this->batasi($hasil, $maksPotongan);
    }

    /**
     * Begitu plafon mengikat, diskonnya bukan persen murni lagi, ia jadi
     * potongan nominal. Bentuk hasilnya ikut berubah supaya pemanggil menulis
     * potongan yang sudah dipotong plafon ke tiap item, bukan menghitung ulang
     * persen di subtotal yang sudah membesar.
     *
     * @param  array{diskon_persen: int, diskon_nilai: int}  $hasil
     * @return array{diskon_persen: int, diskon_nilai: int}
     */
    private function batasi(array $hasil, ?int $maksPotongan): array
    {
        if ($maksPotongan === null || $hasil['diskon_nilai'] <= $maksPotongan) {
            return $hasil;
        }

        return ['diskon_persen' => 0, 'diskon_nilai' => $maksPotongan];
    }

    /**
     * @return array{diskon_persen: int, diskon_nilai: int}
     */
    private function preset(int $subtotal, int $nilai): array
    {
        if (! in_array($nilai, self::PRESET_PERSEN, true)) {
            throw new ApiException(
                'diskon_preset_invalid',
                'Diskon preset hanya tersedia 10, 20, atau 50 persen.',
                422,
            );
        }

        return $this->persen($subtotal, $nilai);
    }

    /**
     * @return array{diskon_persen: int, diskon_nilai: int}
     */
    private function customPersen(int $subtotal, int $nilai): array
    {
        if ($nilai < 0 || $nilai > 100) {
            throw new ApiException(
                'diskon_persen_invalid',
                'Diskon persen custom harus di antara 0 sampai 100.',
                422,
            );
        }

        return $this->persen($subtotal, $nilai);
    }

    /**
     * @return array{diskon_persen: int, diskon_nilai: int}
     */
    private function customNilai(int $subtotal, int $nilai): array
    {
        if ($nilai < 0) {
            throw new ApiException(
                'diskon_nilai_invalid',
                'Diskon nominal tidak boleh negatif.',
                422,
            );
        }

        if ($nilai > $subtotal) {
            throw new ApiException(
                'diskon_melebihi_subtotal',
                "Diskon nominal ({$nilai}) tidak boleh melebihi subtotal transaksi saat ini ({$subtotal}).",
                422,
            );
        }

        return ['diskon_persen' => 0, 'diskon_nilai' => $nilai];
    }

    /**
     * @param  array<int|string, int>  $itemSubtotals  [id item => subtotal]
     * @return array<int|string, int> [id item => bagian diskon]
     */
    public function distribusi(array $itemSubtotals, int $nominal): array
    {
        $subtotal = array_sum($itemSubtotals);

        if ($subtotal <= 0 || $nominal <= 0) {
            return array_map(fn () => 0, $itemSubtotals);
        }

        $hasil = [];
        $sisa = $nominal;
        $keys = array_keys($itemSubtotals);
        $terakhir = count($keys) - 1;

        foreach ($keys as $i => $key) {
            if ($i === $terakhir) {
                $hasil[$key] = $sisa;
                break;
            }

            $bagian = intdiv($itemSubtotals[$key] * $nominal, $subtotal);
            $hasil[$key] = $bagian;
            $sisa -= $bagian;
        }

        return $hasil;
    }

    /**
     * @return array{diskon_persen: int, diskon_nilai: int}
     */
    private function persen(int $subtotal, int $persen): array
    {
        return [
            'diskon_persen' => $persen,
            'diskon_nilai' => (int) round($subtotal * $persen / 100),
        ];
    }
}
