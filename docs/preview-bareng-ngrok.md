# Preview Bareng Lewat ngrok

> Untuk sesi bareng berempat: Monica (backend), Ghefira (frontend SoyaScan),
> Kamila (data), dan Aden. Tujuannya satu link yang bisa dibuka semua orang
> tanpa deploy.

## Yang perlu dipahami dulu

**Cuma SATU orang yang menjalankan tunnel.** Tiga orang lain tinggal membuka
link-nya di browser masing-masing, tidak perlu ngrok, tidak perlu install
apa pun, tidak perlu satu WiFi. Yang menjalankan tunnel adalah pemilik
SoyaCore, yaitu Monica.

Ada dua bentuk sesi, dan bedanya menentukan berapa tunnel yang dibutuhkan:

| Bentuk sesi | Yang di-tunnel | Tunnel |
| --- | --- | --- |
| **A. Pakai SoyaCore saja** (dashboard, transaksi, laporan, halaman `/scan` bawaan SoyaCore) | SoyaCore :8000 | 1, dari Monica |
| **B. Pakai SoyaScan React milik Ghefira** | SoyaCore :8000 + SoyaScan :5173 | 2, satu dari Monica, satu dari Ghefira |

Mulai dari **bentuk A**. Bentuk B hanya perlu kalau yang mau ditunjukkan justru
tampilan React Ghefira, bukan halaman scan bawaan SoyaCore.

---

## Bentuk A: satu tunnel, paling sering dipakai

### Langkah Monica

**1. Nyalakan tunnel dan server sekaligus**

```bash
composer run dev:preview
```

Script ini menjalankan `php artisan serve --host=0.0.0.0 --port=8000` bersama
`ngrok http` yang sudah dipatok ke domain tetap
`defamingly-nongelatinizing-payton.ngrok-free.dev`. Domainnya **tidak berubah**
walau di-restart, jadi link-nya boleh disimpan di grup.

**2. Arahkan alamat aplikasi ke domain tunnel**

```bash
php artisan preview:url defamingly-nongelatinizing-payton.ngrok-free.dev
```

**Langkah ini wajib, bukan opsional.** `APP_URL` ikut tercetak ke dalam data,
bukan cuma dipakai merender halaman:

- Alamat gambar **QRIS** dibangun dari `APP_URL`. Kalau isinya masih
  `127.0.0.1:8000`, tiga orang lain yang membuka link akan meminta gambar itu
  ke komputernya masing-masing, dan yang terlihat cuma gambar rusak.
- **QR menu meja** meng-encode `SOYASCAN_URL`. QR yang dicetak dari sesi
  preview akan mengarah ke alamat lokal Monica selamanya.

**3. Jalankan Vite**

Terminal ketiga:

```bash
npm run dev
```

**4. Bagikan link**

```
https://defamingly-nongelatinizing-payton.ngrok-free.dev
```

Domain telanjang itu langsung mengarahkan ke halaman login, jadi tidak perlu
menambahkan `/login` sendiri.

### Langkah tiga orang lainnya

Buka link itu. Halaman peringatan ngrok muncul sekali pada kunjungan pertama,
klik **"Visit Site"**, selesai. Login pakai akun masing-masing:

| Email | Password | Role |
| --- | --- | --- |
| `manager@gressoy.test` | `password` | manager |
| `kasir1@gressoy.test` | `password` | kasir (Andrian) |
| `kasir2@gressoy.test` | `password` | kasir (Evan) |

Untuk mencoba alur self-order, buka `/scan` di link yang sama.

### Setelah selesai

```bash
php artisan preview:url --pulihkan
```

Mengembalikan `APP_URL` dan `SOYASCAN_URL` ke `http://127.0.0.1:8000`. Kalau
lupa, tidak ada error yang muncul, tapi QRIS dan QR menu akan menunjuk domain
tunnel yang sudah mati.

---

## Bentuk B: dua tunnel, kalau SoyaScan React ikut dibuka ramai-ramai

Monica melakukan semua langkah bentuk A. Ghefira menambahkan satu tunnel untuk
dev server Vite-nya sendiri.

### Langkah Ghefira

**1. Daftar ngrok dan pasang authtoken** (sekali seumur hidup)

```bash
ngrok config add-authtoken <token-dari-dashboard-ngrok>
```

**2. Arahkan SoyaScan ke API SoyaCore lewat tunnel Monica**

Di file `.env` project SoyaScan:

```env
VITE_API_BASE_URL=https://defamingly-nongelatinizing-payton.ngrok-free.dev/api
```

Restart `npm run dev` setiap kali mengubah `.env`.

**3. Jalankan Vite lalu tunnel-nya**

```bash
npm run dev -- --host          # --host wajib, tanpa itu Vite cuma dengar di localhost
ngrok http 5173                # terminal terpisah
```

**4. Bagikan URL ngrok milik Ghefira** ke tiga orang lainnya.

> Akun ngrok gratis tanpa static domain mendapat URL acak yang **berubah tiap
> restart**. Kirim ulang URL-nya tiap sesi, atau klaim static domain gratis di
> dashboard ngrok seperti punya Monica.

### Yang sudah disiapkan supaya bentuk B jalan

CORS SoyaCore sudah menerima origin domain ngrok
([config/cors.php](../config/cors.php)). Ini penting dan gampang terlewat:
selama Ghefira membuka SoyaScan di `localhost:5173` miliknya sendiri, Origin
yang terkirim tetap `localhost` sehingga aman. Begitu halaman itu dibuka orang
lain lewat `https://xxx.ngrok-free.app`, Origin-nya berubah jadi domain ngrok,
dan tanpa pola itu seluruh panggilan API akan diblokir browser tanpa pesan yang
jelas di layar.

---

## Kalau ada yang tidak beres

| Gejala | Penyebab | Perbaikan |
| --- | --- | --- |
| Gambar QRIS rusak di komputer orang lain | `APP_URL` masih `127.0.0.1` | `php artisan preview:url <domain>` |
| Halaman kosong / aset gagal dimuat | `npm run dev` belum jalan | Jalankan di terminal terpisah |
| SoyaScan: error CORS di console | Vite belum pakai `--host`, atau origin di luar pola ngrok | Cek [config/cors.php](../config/cors.php) |
| SoyaScan: API 404 | `VITE_API_BASE_URL` lupa akhiran `/api` | Perbaiki lalu restart `npm run dev` |
| Link tiba-tiba mati | Laptop Monica sleep atau WiFi putus | Tunnel ikut mati, jalankan ulang `composer run dev:preview` |
| Status pesanan tidak berubah jadi lunas | Polling menembak tiap 4 detik selama 15 menit lalu berhenti | Reload halaman `/scan` |

**Melihat apa yang lewat tunnel:** buka <http://127.0.0.1:4040> di komputer yang
menjalankan ngrok. Itu inspector bawaan ngrok, isinya seluruh request beserta
response-nya, jauh lebih berguna daripada menebak dari terminal.

---

## Batas yang perlu diketahui

- **Data yang dipakai adalah SQLite di laptop Monica.** Semua orang menulis ke
  database yang sama, jadi transaksi yang dibuat Aden akan terlihat oleh
  Kamila. Itu memang yang diinginkan untuk demo bersama, tapi berarti
  eksperimen siapa pun ikut mengubah angka di dashboard semua orang.
- **Tunnel hidup selama terminal Monica hidup.** Tidak ada yang bisa mengakses
  apa pun setelah laptopnya ditutup.
- **Ini bukan deploy.** Tidak ada yang permanen di sini. Untuk alamat yang
  hidup terus, lihat bagian "Saat deploy ke production" di
  [docs/setup-lokal.md](setup-lokal.md).
