# Deploy SoyaCore ke Render (M3)

> Status: siap dipakai. `Dockerfile` + `render.yaml` sudah diuji lokal —
> image berhasil build, konek ke Supabase, dan endpoint dashboard + export
> `.xlsx` berjalan dari dalam container.

## Kenapa Docker?

Render **tidak punya runtime PHP native**, jadi deploy-nya lewat Docker.
Ini juga yang menyelamatkan kita dari jebakan `ext-gd` / `ext-zip`: keduanya
di-install eksplisit di `Dockerfile`, sehingga tidak perlu utak-atik php.ini
di server.

## Langkah Deploy

1. **Push repo ke GitHub** (branch `master`).
2. Render Dashboard → **New → Blueprint** → pilih repo `SoyaCore`.
   Render otomatis membaca `render.yaml`.
3. Isi environment variable yang bertanda `sync: false` di dashboard Render
   (**Environment**) — nilai-nilai ini sengaja TIDAK disimpan di repo:

   | Key | Ambil dari |
   |---|---|
   | `APP_KEY` | `.env` lokal (`APP_KEY=base64:...`) atau `php artisan key:generate --show` |
   | `APP_URL` | URL Render setelah service dibuat, mis. `https://soyacore-api.onrender.com` |
   | `DB_HOST` | Supabase → Connect (mis. `aws-1-ap-south-1.pooler.supabase.com`) |
   | `DB_DATABASE` | `postgres` |
   | `DB_USERNAME` | `postgres.<project-ref>` |
   | `DB_PASSWORD` | password database Supabase |

   `DB_PORT` sudah di-set `6543` di `render.yaml` (**transaction pooler** —
   jangan diganti ke 5432).
4. Deploy. Cek log: harus muncul `Configuration cached`, `Nothing to migrate`,
   lalu Apache `resuming normal operations`.
5. Tes: `GET https://<app>.onrender.com/up` → harus `200`.

## Yang Dijalankan Otomatis Saat Boot

`docker/entrypoint.sh` menjalankan, berurutan:

1. Arahkan Apache ke `$PORT` yang diberikan Render.
2. `php artisan config:cache` + `view:cache` — **di runtime**, bukan saat build,
   karena env var dari Render baru tersedia saat container jalan.
3. `php artisan migrate --force` — idempotent; kalau DB sudah up-to-date,
   hasilnya `Nothing to migrate`.

**`route:cache` sengaja tidak dijalankan** karena `routes/web.php` masih memakai
Closure (`Route::get('/', function () ...`), dan Closure tidak bisa di-serialize.

**Seeder tidak dijalankan otomatis.** Supabase adalah DB eksternal yang datanya
sudah diisi dari laptop. Kalau di-seed tiap boot, data akan ditulis ulang terus.
Untuk mengisi data (cukup sekali, dari laptop):

```bash
php artisan db:seed                        # user + menu asli Gressoy
php artisan db:seed --class=LaporanSeeder  # 4 CSV laporan (882 baris dst.)
```

## ⚠️ Wajib: Keep-Alive (kalau tidak, terasa lemot)

Render free tier **tidur setelah ~15 menit idle** → request pertama setelah
tidur bisa **~50 detik**. Supabase free tier juga pause setelah ~7 hari idle
(ini yang dulu bikin error `tenant/user not found`).

Solusi gratis: ping `/up` tiap 10 menit pakai **cron-job.org** atau
**UptimeRobot**:

```
URL      : https://<app>.onrender.com/up
Interval : 10 menit
```

Satu ping ini menjaga dua-duanya tetap hidup (Render tidak tidur, dan karena
`/up` menyentuh app — bukan DB — pertimbangkan endpoint yang menyentuh DB
sesekali kalau Supabase tetap pause).

## Region — Jangan Salah Pilih

`render.yaml` sudah mengunci `region: singapore`. Alasannya: Supabase project
ini ada di **`aws-1-ap-south-1` (Mumbai)**, dan Singapore adalah region Render
terdekat. Memilih Oregon/Frankfurt bisa menambah ~200ms **per query** — dan
endpoint dashboard melakukan beberapa query agregat per request, jadi efeknya
berlipat.

Kalau nanti Supabase dipindah ke Singapore (`ap-southeast-1`) — lebih dekat ke
Purwokerto dan ke app — latency-nya makin kecil lagi. Data kita 100%
reproducible dari seeder, jadi pindah region relatif murah; lakukan **sebelum**
deploy, bukan sesudah.

## Verifikasi Lokal (opsional, sebelum push)

Bisa menguji image yang sama persis dengan yang akan jalan di Render:

```bash
docker build -t soyacore-test .
docker run -d --name soyacore-run -p 8124:10000 -e PORT=10000 --env-file .env soyacore-test
curl -i http://127.0.0.1:8124/up          # harus 200
docker logs soyacore-run                  # cek config cached + migrate
docker rm -f soyacore-run
```
