# Local Preview Setup — SoyaCore ↔ SoyaScan

> Untuk Monica (backend) & Ghefira (frontend React/Vite). Tujuan: preview
> alur self-order end-to-end (scan → pilih menu → submit → konfirmasi)
> memakai API SoyaCore yang BENERAN jalan — bukan mock. Tanpa deploy.

## Kapan pakai yang mana?

| Situasi | Pakai |
|---|---|
| Satu WiFi (mis. WFO bareng di Gressoy) | **Opsi A — LAN** (lebih cepat, tidak butuh internet keluar, bisa tes scan QR dari HP) |
| Beda lokasi / WFH | **Opsi B — ngrok** |

Database tetap Supabase (sudah dikonfigurasi di `.env` Monica) — dua opsi
ini hanya soal bagaimana SoyaScan menjangkau API-nya.

---

## Opsi A — LAN (satu WiFi)

**Monica menjalankan:**

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

**Cari IP lokal Monica** (di jaringan WiFi yang sama):

- Windows: `ipconfig` → cari "IPv4 Address" di adapter WiFi, mis. `192.168.1.23`
- Mac/Linux: `ifconfig` atau `ip addr`

**Base URL untuk Ghefira:** `http://192.168.1.23:8000/api` (ganti IP sesuai hasil di atas).

Cocok juga untuk tes scan QR beneran dari HP — HP harus di WiFi yang sama.

---

## Opsi B — ngrok (beda lokasi)

### Setup SEKALI oleh Monica (manual, tidak diotomasi)

1. Daftar akun gratis di **ngrok.com**.
2. Install ngrok CLI (Windows: download installer dari situs ngrok; Mac: `brew install ngrok`).
3. Ambil **authtoken** dari dashboard ngrok, lalu jalankan sekali:
   ```bash
   ngrok config add-authtoken <token>
   ```
   Token disimpan ngrok di config lokal OS — **JANGAN PERNAH** masuk `.env` atau ter-commit ke repo.
4. **Sangat disarankan**: klaim **satu free static domain** di dashboard ngrok
   (menu Domains) — supaya URL publik TIDAK berubah tiap restart, jadi Ghefira
   tidak perlu update env var tiap sesi.

### Menjalankan (tiap sesi)

```bash
composer run dev:preview
```

Script ini menjalankan `php artisan serve --host=0.0.0.0 --port=8000` + `ngrok http 8000` bersamaan
(concurrently, label `server` dan `ngrok` berwarna beda di terminal).

> Kalau sudah klaim static domain, edit script `dev:preview` di
> `composer.json`: ganti `ngrok http 8000 --log=stdout` menjadi
> `ngrok http --url=<nama-domain>.ngrok-free.app 8000 --log=stdout`.

**Melihat URL publik yang aktif:** buka **http://127.0.0.1:4040** di browser
(ngrok local inspector — selalu tersedia selagi ngrok jalan, lebih reliable
daripada membaca output terminal).

**Catatan CORS untuk ngrok:** request lewat tunnel tetap membawa `Origin`
asli dari browser Ghefira (mis. `http://localhost:5173`), jadi konfigurasi
CORS yang sudah ada tetap berlaku sama — tidak perlu penyesuaian tambahan.

**Halaman peringatan ngrok (interstitial):** khas free tier untuk kunjungan
browser langsung pertama kali — klik "Visit Site" sekali, selesai. Request
API biasa (fetch/XHR dari SoyaScan) TIDAK kena interstitial.

---

## Untuk Ghefira (SoyaScan)

**1. Set env var** di project Vite (file `.env`):

```env
# LAN:
VITE_API_BASE_URL=http://192.168.1.23:8000/api
# atau ngrok:
VITE_API_BASE_URL=https://<nama-domain>.ngrok-free.app/api
```

(Nilai persisnya dikirim Monica tiap sesi — kecuali pakai static domain
ngrok, maka URL-nya tetap sama terus.) Restart `npm run dev` setiap ganti
`.env`.

**2. Endpoint yang sudah bisa dites NYATA sekarang** (lihat detail lengkap +
bentuk response di `docs/kontrak-api-v1.md`):

| Endpoint | Auth | Fungsi |
|---|---|---|
| `GET /api/menu` | tanpa auth | Menu aktif per kategori |
| `POST /api/order` | tanpa auth | Buat pesanan self-order |
| `GET /api/loyalty/{nomor_wa}` | tanpa auth | Cek saldo poin (⚠️ bentuk BARU: `{nomor_wa, nama, poin}`) |

**3. Verifikasi koneksi berhasil:** buka Network tab / console di browser,
submit order dari SoyaScan → response harus berisi `kode_pesanan` format
`#A0X` asli dari SoyaCore (bukan data mock).

## Troubleshooting

- **Error CORS di console Ghefira** → origin dev server-nya belum masuk
  `config/cors.php` milik SoyaCore (yang diizinkan: `http://localhost:5173`,
  `http://127.0.0.1:5173`, dan pattern LAN `192.168.x.x:5173` /
  `10.x.x.x:5173`). Monica tambahkan origin-nya lalu restart `artisan serve`.
- **SoyaScan tiba-tiba gagal konek** → cek dua terminal Monica masih hidup
  (laptop sleep/WiFi putus = tunnel mati).
- **URL ngrok berubah** setelah restart → normal untuk random URL; klaim
  static domain (lihat atas) supaya permanen.
