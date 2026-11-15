# SoyaCore, POS & Loyalty GresSOY

Backend Laravel 13 untuk kasir (POS), self-order SoyaScan, program loyalty
LoyalSeed, dan dashboard/laporan manager.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link      # WAJIB, lihat catatan di bawah
```

## Menjalankan dengan Docker

Pastikan `.env` berisi `APP_KEY`. Buat kunci aplikasi dengan
`php artisan key:generate` jika belum ada. Docker menggunakan PostgreSQL lokal
di jaringan internal. Jika `DB_PASSWORD` tidak ada di `.env`, Docker memakai
kata sandi pengembangan `soyacore-local`; jangan gunakan nilai ini di
production. Lalu jalankan:

```bash
docker compose up --build -d
```

Docker menjalankan Laravel di `http://localhost:8080` dan PostgreSQL di
container `db`. Migrasi berjalan otomatis saat aplikasi mulai. Lihat kedua
container di Docker Desktop pada halaman **Containers**.

Data PostgreSQL tersimpan di volume `soyacore-db` dan tetap ada setelah
container berhenti. Untuk mematikan aplikasi tanpa menghapus data, jalankan:

```bash
docker compose down
```

Seeder tidak berjalan otomatis. Untuk mengisi data awal, jalankan
`docker compose exec app php artisan db:seed`.

### `php artisan storage:link` wajib dijalankan

Gambar QRIS merchant disimpan di disk `public`
(`storage/app/public/qris`) dan dilayani lewat symlink
`public/storage`. Tanpa `storage:link`, `qris_url` yang dikirim API akan
mengembalikan **404** dan halaman pembayaran SoyaScan menampilkan gambar rusak,
tanpa error apa pun di sisi backend. Jalankan sekali per environment, termasuk
setelah deploy ke server baru.

## Command perawatan

### `php artisan laporan:proyeksi-ulang`

Membangun ulang proyeksi seluruh transaksi POS yang sudah jadi penjualan ke tabel
`laporan_transaksi` (sumber data dashboard & export Excel).

Dipakai kalau proyeksi pernah gagal, misalnya deploy tepat di tengah transaksi,
atau kalau rumus proyeksinya berubah. **Idempoten**: aman dijalankan berkali-kali,
hasilnya selalu sama.

Command ini **tidak menyentuh** baris impor CSV historis: yang dihapus dan ditulis
ulang hanya baris dengan `kode` berawalan `TRX-`. Baris CSV berawalan `TR-`.

### `php artisan laporan:import`

Impor ulang data CSV historis (Juni–Juli 2026). Lihat
[`docs/update-data-laporan.md`](docs/update-data-laporan.md).

## Konfigurasi khusus

| Env                | Default              | Keterangan                                                                 |
| ------------------ | -------------------- | -------------------------------------------------------------------------- |
| `SOYASCAN_URL`     | ikut `APP_URL`       | URL publik SoyaScan yang di-encode ke QR menu meja. Pastikan benar **sebelum** QR dicetak, QR yang sudah ditempel tidak bisa ditarik lagi. |

> **Catatan zona waktu.** `config('app.timezone')` sengaja dibiarkan `UTC`.
> Seluruh konversi waktu→tanggal (filter transaksi, tanggal laporan, penomoran
> `kode_pesanan` harian) memakai helper `App\Support\WaktuToko` yang memaksa
> **WIB**, jadi tanggal penjualan tidak pernah ikut zona server. Jangan memakai
> `whereDate()` pada kolom datetime, pakai helper itu.

## Dokumentasi

| Dokumen                                                              | Isi                                                 |
| -------------------------------------------------------------------- | --------------------------------------------------- |
| [`docs/kontrak-api-v1.md`](docs/kontrak-api-v1.md)                   | Kontrak self-order SoyaScan (locked)                |
| [`docs/kontrak-api-kasir-v1-draft.md`](docs/kontrak-api-kasir-v1-draft.md) | Endpoint internal kasir                        |
| [`docs/kontrak-dashboard-v1.md`](docs/kontrak-dashboard-v1.md)       | Endpoint dashboard & laporan                        |
| [`docs/prompt-revisi-frontend-ghefira.md`](docs/prompt-revisi-frontend-ghefira.md) | **Brief kerja frontend** (urutan blok A–J) |
| [`docs/revisi-frontend-v13.md`](docs/revisi-frontend-v13.md)         | Rincian payload per perubahan frontend v1.3          |
| [`docs/laporan-kasir.md`](docs/laporan-kasir.md)                     | Atribusi per akun kasir, laporan & export per kasir |
| [`docs/pembatalan-pesanan.md`](docs/pembatalan-pesanan.md)           | Pembatalan/koreksi pesanan (bukan refund)           |
| [`docs/pengaturan-loyalty.md`](docs/pengaturan-loyalty.md)           | Rate poin & katalog redeem                          |
| [`docs/pengaturan-profil-toko.md`](docs/pengaturan-profil-toko.md)   | Profil akun & info toko                             |

## Test

```bash
php artisan test
./vendor/bin/pint
```
