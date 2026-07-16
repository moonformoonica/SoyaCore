#!/bin/sh
set -e

PORT="${PORT:-10000}"

# Render menentukan port lewat $PORT saat runtime, jadi Apache diarahkan di sini
# (bukan saat build, karena nilainya belum diketahui waktu image dibuat).
sed -i "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Cache config & view HARUS di runtime, setelah env var dari Render tersedia —
# kalau di-cache saat build, nilainya keburu kosong dan app gagal konek DB.
php artisan config:cache
php artisan view:cache

# CATATAN: route:cache sengaja TIDAK dijalankan. routes/web.php masih memakai
# Closure ("Route::get('/', function () ...")), dan Laravel tidak bisa
# men-serialize Closure -> route:cache akan error. Hapus closure itu dulu
# kalau nanti mau mengaktifkannya.

# Jalankan migration yang masih pending. Idempotent: kalau DB sudah up-to-date
# (mis. sudah di-migrate dari laptop), ini tidak melakukan apa-apa.
php artisan migrate --force

# Seeder TIDAK dijalankan di sini. Data menu & laporan diisi sekali dari laptop
# (`php artisan db:seed` / `--class=LaporanSeeder`) karena Supabase memang
# database eksternal — kalau di-seed tiap boot, datanya ditulis ulang terus.

exec apache2-foreground
