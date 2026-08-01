# SoyaCore, POS & Loyalty GresSOY

Backend Laravel 12 untuk kasir (POS), self-order SoyaScan, program loyalty
LoyalSeed, dan dashboard/laporan manager.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link      # WAJIB, lihat catatan di bawah
```

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

---

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
