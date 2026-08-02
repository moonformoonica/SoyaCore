<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Menyetel `APP_URL` dan `SOYASCAN_URL` di `.env` ke domain tunnel, lalu
 * mengembalikannya ke alamat lokal setelah selesai.
 *
 * KENAPA PERLU PERINTAH SENDIRI. Dua nilai itu ikut tercetak ke dalam data,
 * bukan cuma dipakai saat merender halaman:
 *
 * - `APP_URL` menjadi dasar `Storage::url()`, jadi alamat gambar QRIS yang
 *   dikirim ke SoyaScan. Kalau isinya masih `127.0.0.1:8000`, tiga orang lain
 *   yang membuka link tunnel akan meminta gambar itu ke komputernya sendiri
 *   dan hanya melihat gambar rusak.
 * - `SOYASCAN_URL` di-encode ke QR menu meja. QR yang dicetak dari sesi
 *   preview akan mengarah ke alamat lokal Monica selamanya.
 *
 * Keduanya gampang lupa dikembalikan, dan lupa itu tidak memunculkan error apa
 * pun sampai ada yang mencetak QR-nya. Karena itu ada `--pulihkan`.
 */
class SiapkanPreview extends Command
{
    protected $signature = 'preview:url
        {domain? : Domain tunnel, mis. abcd-1-2-3.ngrok-free.app}
        {--pulihkan : Kembalikan ke http://127.0.0.1:8000}';

    protected $description = 'Arahkan APP_URL & SOYASCAN_URL ke domain tunnel (atau kembalikan ke lokal).';

    private const LOKAL = 'http://127.0.0.1:8000';

    public function handle(): int
    {
        $berkas = base_path('.env');

        if (! is_writable($berkas)) {
            $this->error('.env tidak bisa ditulis.');

            return self::FAILURE;
        }

        $dasar = $this->option('pulihkan') ? self::LOKAL : $this->dasarDariArgumen();

        if ($dasar === null) {
            return self::FAILURE;
        }

        $isi = file_get_contents($berkas);
        $isi = $this->setel($isi, 'APP_URL', $dasar);
        $isi = $this->setel($isi, 'SOYASCAN_URL', $dasar.'/scan');

        file_put_contents($berkas, $isi);

        // Tanpa ini, nilai lama masih dipegang config cache dan perubahannya
        // seolah tidak terjadi.
        $this->call('config:clear');

        $this->newLine();
        $this->line("  APP_URL      = <fg=green>{$dasar}</>");
        $this->line("  SOYASCAN_URL = <fg=green>{$dasar}/scan</>");
        $this->newLine();

        if (! $this->option('pulihkan')) {
            $this->warn('  Jangan lupa `php artisan preview:url --pulihkan` setelah selesai,');
            $this->warn('  supaya QR menu tidak ikut mencetak alamat tunnel yang sudah mati.');
            $this->newLine();
        }

        return self::SUCCESS;
    }

    private function dasarDariArgumen(): ?string
    {
        $domain = trim((string) $this->argument('domain'));

        if ($domain === '') {
            $this->error('Domain tunnel wajib diisi. Contoh: php artisan preview:url abcd.ngrok-free.app');

            return null;
        }

        // Menerima domain polos maupun URL penuh, dan garis miring di ujung
        // dibuang: `https://x.ngrok-free.app/` menghasilkan `.../scan` ganda
        // garis miring yang tidak cocok dengan route mana pun.
        $domain = preg_replace('#^https?://#i', '', $domain);
        $domain = rtrim((string) $domain, '/');

        return 'https://'.$domain;
    }

    private function setel(string $isi, string $kunci, string $nilai): string
    {
        $baris = $kunci.'='.$nilai;

        // Baris yang sudah ada diganti di tempatnya supaya komentar penjelas di
        // atasnya tidak ikut hilang.
        if (preg_match('/^'.preg_quote($kunci, '/').'=.*$/m', $isi)) {
            return preg_replace('/^'.preg_quote($kunci, '/').'=.*$/m', $baris, $isi);
        }

        return rtrim($isi)."\n".$baris."\n";
    }
}
