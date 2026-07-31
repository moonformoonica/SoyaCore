<?php

namespace App\Services;

use App\Http\Requests\IndexTransaksiRequest;
use App\Models\DetailTransaksi;
use App\Models\Transaksi;
use App\Support\NomorWa;
use App\Support\PolaCari;
use App\Support\WaktuToko;
use Illuminate\Database\Eloquent\Builder;

/**
 * Menyusun query daftar transaksi manager dari filter yang sudah tervalidasi.
 *
 * Dipisah dari controller karena dua alasan: filternya belasan buah, dan
 * ringkasan `meta` harus dihitung dari query yang SAMA persis dengan yang
 * menghasilkan daftarnya. Kalau keduanya dibangun terpisah, angka ringkasan
 * bisa menyimpang dari baris yang terlihat tanpa ada yang menyadarinya.
 */
class DaftarTransaksiQuery
{
    public function bangun(IndexTransaksiRequest $request): Builder
    {
        $query = Transaksi::query();

        $this->filterTanggal($query, $request);
        $this->filterAtribut($query, $request);
        $this->filterKasir($query, $request);
        $this->filterPencarian($query, $request);

        return $query;
    }

    /**
     * Ringkasan hasil TERFILTER — bukan seluruh tabel. Inilah yang membuat
     * filter berguna buat manager: angkanya ikut berubah, bukan cuma daftarnya.
     *
     * @return array{jumlah_transaksi: int, total_omzet: int, total_qty: int}
     */
    public function ringkasan(Builder $base): array
    {
        return [
            'jumlah_transaksi' => (int) $base->clone()->count(),
            'total_omzet' => (int) $base->clone()->sum('total'),
            'total_qty' => (int) DetailTransaksi::query()
                ->whereIn('transaksi_id', $base->clone()->select('transaksi.id'))
                ->sum('qty'),
        ];
    }

    /**
     * Batas hari dihitung dalam WIB lewat {@see WaktuToko}, bukan
     * `whereDate()`. `whereDate()` memakai `config('app.timezone')` yang masih
     * UTC, sehingga transaksi pagi (sebelum 07.00 WIB) jatuh ke tanggal
     * sebelumnya — dan manager yang membuka "hari ini" tidak melihatnya.
     */
    private function filterTanggal(Builder $query, IndexTransaksiRequest $request): void
    {
        [$mulai, $selesai] = $request->rentang();

        if ($mulai !== null) {
            $query->where('created_at', '>=', WaktuToko::awalHari($mulai));
        }

        if ($selesai !== null) {
            $query->where('created_at', '<=', WaktuToko::akhirHari($selesai));
        }
    }

    private function filterAtribut(Builder $query, IndexTransaksiRequest $request): void
    {
        foreach (['status', 'sumber', 'metode_bayar'] as $kolom) {
            $nilai = $request->nilai($kolom);
            if ($nilai !== null) {
                $query->where($kolom, $nilai);
            }
        }

        $totalMin = $request->nilai('total_min');
        if ($totalMin !== null) {
            $query->where('total', '>=', (int) $totalMin);
        }

        $totalMax = $request->nilai('total_max');
        if ($totalMax !== null) {
            $query->where('total', '<=', (int) $totalMax);
        }

        $adaRedeem = $request->adaRedeem();
        if ($adaRedeem !== null) {
            $adaRedeem
                ? $query->whereNotNull('kode_redeem')
                : $query->whereNull('kode_redeem');
        }
    }

    private function filterKasir(Builder $query, IndexTransaksiRequest $request): void
    {
        $dibuatOleh = $request->nilai('dibuat_oleh');
        if ($dibuatOleh !== null) {
            $query->where('user_id', (int) $dibuatOleh);
        }

        $dibayarOleh = $request->nilai('dibayar_oleh');
        if ($dibayarOleh !== null) {
            $query->where('dibayar_oleh', (int) $dibayarOleh);
        }

        // Alias lama, dipakai kartu statistik kasir yang sudah jalan.
        // Diperlakukan sebagai `dibayar_oleh` karena ke akun itulah penjualan
        // dihitung, dengan fallback ke pembuat untuk transaksi yang belum
        // dibayar — kalau tidak, pesanan pending kasir itu sendiri menghilang
        // dari kartunya.
        $userId = $request->nilai('user_id');
        if ($userId !== null) {
            $id = (int) $userId;

            $query->where(function (Builder $q) use ($id) {
                $q->where('dibayar_oleh', $id)
                    ->orWhere(fn (Builder $sub) => $sub->whereNull('dibayar_oleh')->where('user_id', $id));
            });
        }
    }

    /**
     * `cari` mencocokkan kode pesanan, nama customer, atau nomor WA customer —
     * satu kotak pencarian untuk ketiganya, karena manager tidak selalu tahu
     * yang mana yang dia pegang.
     *
     * Nomor WA dicocokkan lewat {@see NomorWa::kandidatCari()} sehingga
     * keduanya bekerja:
     *
     * - **nomor lengkap** dalam ejaan apa pun (`0812…`, `+62 812…`, `62812…`)
     * - **potongan nomor**, termasuk 4 digit terakhir (`8122`) — inilah yang
     *   dipakai kasir/manager saat pelanggan hanya menyebut ekor nomornya
     *
     * Pola teks lewat {@see PolaCari} supaya case-insensitive di PostgreSQL dan
     * wildcard yang diketik user tidak bocor jadi wildcard LIKE.
     */
    private function filterPencarian(Builder $query, IndexTransaksiRequest $request): void
    {
        $cari = $request->nilai('cari');
        if ($cari === null || trim($cari) === '') {
            return;
        }

        $pola = PolaCari::teks($cari);
        $kandidatWa = NomorWa::kandidatCari($cari);

        $query->where(function (Builder $q) use ($pola, $kandidatWa) {
            $q->whereRaw('LOWER(kode_pesanan) LIKE ?', [$pola])
                ->orWhereHas('customer', function ($c) use ($pola, $kandidatWa) {
                    $c->whereRaw('LOWER(nama) LIKE ?', [$pola]);

                    foreach ($kandidatWa as $digit) {
                        $c->orWhere('no_wa', 'like', '%'.PolaCari::escape($digit).'%');
                    }
                });
        });
    }
}
