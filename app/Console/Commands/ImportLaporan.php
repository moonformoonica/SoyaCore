<?php

namespace App\Console\Commands;

use App\Services\LaporanImporter;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

/**
 * Refresh data reporting setelah CSV di database/seeders/data/ diganti.
 *
 * Alur update data rutin:
 *   1. Timpa CSV di database/seeders/data/ dengan hasil olahan terbaru.
 *   2. php artisan laporan:import
 *   3. Selesai — dashboard & export Excel ikut terbarui otomatis karena
 *      keduanya membaca dari tabel laporan_*, bukan dari file CSV.
 */
class ImportLaporan extends Command
{
    protected $signature = 'laporan:import
                            {--dir= : Folder CSV alternatif (default: database/seeders/data)}';

    protected $description = 'Impor ulang CSV laporan ke tabel laporan_* (aman diulang)';

    public function handle(): int
    {
        $dir = $this->option('dir');

        $this->info('Mengimpor CSV laporan'.($dir ? " dari {$dir}" : '').' ...');

        try {
            $hasil = (new LaporanImporter($dir))->import();
        } catch (RuntimeException $e) {
            // Header tidak cocok / file hilang — pesannya sudah menjelaskan
            // kolom mana yang bermasalah, jadi tampilkan apa adanya.
            $this->newLine();
            $this->error($e->getMessage());
            $this->newLine();
            $this->warn('Tidak ada data yang diubah untuk tabel yang gagal. Perbaiki CSV-nya lalu jalankan ulang.');

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error('Impor gagal: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(
            ['Tabel', 'Baris'],
            collect($hasil)->map(fn (int $n, string $t) => [$t, number_format($n, 0, ',', '.')])->values(),
        );

        if (in_array(0, $hasil, true)) {
            $this->warn('Ada tabel yang terisi 0 baris — cek lagi isi CSV-nya.');
        }

        $this->info('Selesai. Dashboard & export Excel otomatis memakai data ini.');

        return self::SUCCESS;
    }
}
