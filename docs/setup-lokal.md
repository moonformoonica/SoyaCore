# Setup Lokal SoyaCore

> Untuk siapa pun yang menjalankan SoyaCore di komputernya sendiri.
> Kalau yang dicari adalah cara menyambungkan SoyaScan (React/Vite) ke API
> SoyaCore, lanjut ke `docs/local-preview-setup.md` setelah setup ini beres.

## Aturan utama: jangan pakai Supabase saat ngoding

Database production ada di Supabase, region AWS `ap-south-1` (Mumbai). Diukur
dari Indonesia:

| Operasi                  | Waktu             |
| ------------------------ | ----------------- |
| Connect ke Supabase      | ~1.700 ms         |
| Satu query `select 1`    | 600 – 1.200 ms    |

Halaman dashboard SoyaCore memanggil **9 endpoint API sekaligus**
(`ringkasan`, `meta`, `time-series`, `rfm`, `switch`, `platform`,
`produk-terlaris`, `revenue-ukuran`, `loyalty`), masing-masing membuka koneksi
sendiri dan menjalankan beberapa query. Hasilnya halaman menggantung puluhan
detik. Ini murni jarak jaringan — bukan kode aplikasinya.

Development lokal karena itu memakai **SQLite**, yang menjawab di bawah 1 ms.

---

## Langkah setup

### 1. Dependensi

```bash
composer install
npm install
```

### 2. File `.env`

```bash
cp .env.example .env      # Windows PowerShell: Copy-Item .env.example .env
php artisan key:generate
```

`.env.example` sudah benar apa adanya — SQLite, `SESSION_DRIVER=file`,
`CACHE_STORE=file`, `QUEUE_CONNECTION=sync`. **Jangan** menyalin `.env` milik
orang lain yang berisi kredensial Supabase; itu justru sumber lemotnya.

### 3. Database

```bash
php artisan migrate --seed
```

File `database/database.sqlite` dibuat otomatis (dan memang di-ignore git, jadi
isinya tidak pernah ikut ter-commit). Seeder mengisi 6 kategori, 93 baris menu
asli GresSOY, dan dua akun:

| Email                    | Password   | Role    |
| ------------------------ | ---------- | ------- |
| `manager@gressoy.test`   | `password` | manager |
| `kasir@gressoy.test`     | `password` | kasir   |

Data ini **terpisah dari data Supabase**. Kalau butuh data produksi di lokal,
tarik manual sekali lalu impor — jangan menyambungkan aplikasi langsung ke sana.

### 4. Aktifkan OPcache

Tanpa OPcache, PHP mem-parse dan meng-compile ulang ribuan file Laravel pada
setiap request. Ini setelan per-komputer, jadi tidak bisa dibagikan lewat repo —
tiap orang harus melakukannya sendiri sekali.

Cari lokasi `php.ini`:

```bash
php --ini      # baca baris "Loaded Configuration File"
```

Tambahkan di akhir file itu:

```ini
[opcache]
zend_extension=php_opcache.dll   ; Linux/Mac: zend_extension=opcache.so
opcache.enable=1
; artisan serve berjalan di SAPI CLI, jadi baris ini WAJIB — tanpanya
; OPcache tidak aktif sama sekali saat development.
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
; Kombinasi ini membuat perubahan kode langsung terbaca tanpa restart server.
opcache.validate_timestamps=1
opcache.revalidate_freq=0
opcache.save_comments=1

realpath_cache_size=4096K
realpath_cache_ttl=600
```

Verifikasi:

```bash
php -i | grep "Opcode Caching"        # Windows: php -i | findstr "Opcode Caching"
# harus muncul: Opcode Caching => Up and Running
```

### 5. Jalankan

Dua terminal terpisah:

```bash
php artisan serve        # terminal 1
npm run dev              # terminal 2 — WAJIB, asset dilayani Vite dev server
```

Tanpa `npm run dev`, halaman gagal render karena `public/build/manifest.json`
belum ada (folder itu di-ignore git).

Buka http://127.0.0.1:8000/login — perhatikan `/` sendiri memang 404, tidak ada
route di sana.

---

## Patokan kecepatan yang wajar

Setelah setup benar, di mesin biasa:

```
Halaman /login                    ~35 ms
POST /api/login                  ~170 ms
/api/dashboard/ringkasan          ~75 ms
endpoint dashboard lainnya     ~37-45 ms masing-masing
```

Kalau jauh lebih lambat dari ini, cek berurutan:

1. `.env` — apakah tanpa sengaja kembali ke `DB_CONNECTION=pgsql` atau
   `SESSION_DRIVER=database`?
2. `php artisan config:clear` — config cache lama masih memegang setelan lama.
3. `php -i | findstr "Opcode Caching"` — OPcache benar-benar hidup?

---

## Saat deploy ke production

Yang berbeda dari setelan lokal:

| Key                 | Lokal    | Production        |
| ------------------- | -------- | ----------------- |
| `DB_CONNECTION`     | `sqlite` | `pgsql` (Supabase) |
| `SESSION_DRIVER`    | `file`   | `database`        |
| `CACHE_STORE`       | `file`   | `database`        |
| `QUEUE_CONNECTION`  | `sync`   | `database`        |
| `BCRYPT_ROUNDS`     | `10`     | `12`              |
| `APP_DEBUG`         | `true`   | `false`           |
| `APP_ENV`           | `local`  | `production`      |

`APP_URL` dan `SOYASCAN_URL` juga harus menunjuk domain production. `SOYASCAN_URL`
sangat penting: alamat itu di-encode ke QR yang dicetak dan ditempel di meja —
salah nilai berarti cetak ulang.
