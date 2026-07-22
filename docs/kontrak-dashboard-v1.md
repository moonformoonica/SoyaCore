# Kontrak API Dashboard & Laporan SoyaCore — v1

> **Status: v1 — 16 Juli 2026.** Kontrak integrasi untuk frontend dashboard
> manager (Ghefira). Semua endpoint bersifat **read-only reporting**, dihitung
> dari layer data historis terpisah (`laporan_*`), tidak menyentuh POS live.

Semua endpoint di bawah prefix `/api` dan wajib login (Sanctum).

## Hak Akses per Role

Sebagian dashboard kini boleh dibuka **kasir**, sisanya tetap manager-only.

| Endpoint | kasir | manager |
|---|:---:|:---:|
| `GET /api/dashboard/meta` | ✅ | ✅ |
| `GET /api/dashboard/ringkasan` | ✅ | ✅ |
| `GET /api/dashboard/produk-terlaris` | ✅ | ✅ |
| `GET /api/dashboard/time-series` | ❌ | ✅ |
| `GET /api/dashboard/revenue-ukuran` | ❌ | ✅ |
| `GET /api/dashboard/platform` | ❌ | ✅ |
| `GET /api/dashboard/loyalty` | ❌ | ✅ |
| `GET /api/dashboard/rfm` | ❌ | ✅ |
| `GET /api/dashboard/switch` | ❌ | ✅ |
| `GET /api/laporan/export` | ❌ | ✅ |

Prinsipnya: kasir boleh memantau **performa harian**, tapi tidak boleh melihat
data **per-pelanggan** (loyalty, RFM, switch) maupun meng-export laporan.

> **Catatan buat frontend.** Jangan arahkan kasir ke halaman yang isinya
> endpoint manager-only — hasilnya cuma layar kosong dengan error 403.
> Role ada di `GET /api/me` (field `user.role`), pakai itu untuk menyembunyikan
> menu/tab-nya. Kalau kasir mengetik URL-nya langsung, redirect ke halaman
> Pesanan.

## Autentikasi

Sertakan header dari hasil `POST /api/login`:

```
Authorization: Bearer <token>
```

- Tanpa token / token invalid → `401 { "error": "unauthenticated", "message": "..." }`
- Role tidak mencukupi (mis. kasir buka `/rfm`) → `403 { "error": "tidak_berwenang", "message": "..." }`

## Cakupan Data

Data historis tersedia **2026-06-01 → 2026-07-30** (882 baris level-item).
Di luar rentang itu memang sah kosong. Pakai `GET /api/dashboard/meta` untuk
membatasi/memberi hint pada date-picker.

## Query Params Umum (endpoint yang bisa difilter tanggal)

| Param | Nilai | Default | Keterangan |
|---|---|---|---|
| `grain` | `harian` \| `mingguan` \| `bulanan` \| `tahunan` | `harian` | Ukuran bucket time-series |
| `start` | `YYYY-MM-DD` | tanggal_min data | Awal window (inklusif) |
| `end` | `YYYY-MM-DD` | tanggal_max data | Akhir window (inklusif) |

**Validasi** (gagal → `422 { "error": "validasi_gagal", "message": "...", "details": {...} }`):

- `grain` harus salah satu nilai di atas.
- `start`/`end` harus format `YYYY-MM-DD`.
- `end` tidak boleh lebih awal dari `start`.
- Minggu memakai standar ISO (Senin–Minggu).

## Kontrak Envelope & Empty-State (endpoint 1–6)

Setiap endpoint yang bisa difilter tanggal **selalu** balas `200` — termasuk
window tanpa data (mis. Agustus 2026) — dengan envelope konsisten:

```json
{
  "periode": { "grain": "harian", "start": "2026-06-01", "end": "2026-07-30" },
  "data_tersedia": true,
  "data": { }
}
```

- Window tanpa transaksi → `data_tersedia: false`, KPI numerik `0`, array `[]`.
- Tidak pernah mengembalikan field `null` untuk angka. Tidak pernah `404`/`500`
  hanya karena window kosong.

---

## 0. GET /api/dashboard/meta

Meta cakupan data (dihitung **live**). Tidak memakai envelope.

**Response `200`:**

```json
{
  "tanggal_min": "2026-06-01",
  "tanggal_max": "2026-07-30",
  "total_baris": 882,
  "ukuran": ["1000 ml", "250 ml", "500 ml", "Cup", "Hot", "Large", "Pack", "Reguler"],
  "platform": ["GoFood", "GrabFood", "QRIS", "ShopeeFood", "Transfer", "Tunai"],
  "segmen": ["Butuh Perhatian", "Loyal", "Pelanggan Baru", "Potensial"]
}
```

---

## 1. GET /api/dashboard/ringkasan

KPI ringkas untuk window. `data` berisi objek KPI.

**Response `200`:**

```json
{
  "periode": { "grain": "harian", "start": "2026-06-01", "end": "2026-07-30" },
  "data_tersedia": true,
  "data": {
    "total_revenue": 26257000,
    "total_transaksi": 882,
    "total_qty": 1078,
    "rata_rata_transaksi": 29770,
    "total_poin": 1071,
    "pelanggan_unik": 345
  }
}
```

> Catatan: `total_transaksi` = jumlah baris item dalam window (di dataset ini
> tiap transaksi satu item). `rata_rata_transaksi` = `round(total_revenue / total_transaksi)`.

---

## 2. GET /api/dashboard/time-series

Deret waktu di-bucket sesuai `grain`. Urut ascending; **bucket kosong di-skip**
(tidak ada baris untuk hari/minggu/bulan tanpa transaksi).

**Contoh `?grain=bulanan`:**

```json
{
  "periode": { "grain": "bulanan", "start": "2026-06-01", "end": "2026-07-30" },
  "data_tersedia": true,
  "data": [
    { "periode": "2026-06", "revenue": 12831000, "transaksi": 419, "qty": 526 },
    { "periode": "2026-07", "revenue": 13426000, "transaksi": 463, "qty": 552 }
  ]
}
```

Format field `periode` per grain: `harian` → `YYYY-MM-DD`, `mingguan` →
tanggal Senin awal minggu (`YYYY-MM-DD`), `bulanan` → `YYYY-MM`, `tahunan` → `YYYY`.

---

## 3. GET /api/dashboard/revenue-ukuran

Group by `ukuran` dalam window, urut `total_revenue` desc. Saat window = rentang
penuh, hasil ini **identik** dengan tabel referensi `laporan_revenue_ukuran`.

> **KHUSUS MINUMAN.** Dessert & cookies (ukuran `Cup` dan `Pack`) tidak dihitung
> di endpoint ini. Jadi jumlah `total_revenue` di sini **lebih kecil** dari
> `data.total_revenue` di `/ringkasan` — Rp 21.192.000 vs Rp 26.257.000 untuk
> periode penuh; selisih Rp 5.065.000 adalah revenue dessert & cookies. Ini
> disengaja, bukan salah hitung. Endpoint lain (`/ringkasan`,
> `/produk-terlaris`, `/platform`) tetap menghitung semua item.
>
> Field `catatan` dikirim khusus di endpoint ini — tampilkan apa adanya di
> dekat chart supaya user paham cakupannya.

```json
{
  "periode": { "grain": "harian", "start": "2026-06-01", "end": "2026-07-30" },
  "data_tersedia": true,
  "catatan": "Khusus minuman — dessert & cookies (Cup/Pack) tidak termasuk.",
  "data": [
    { "ukuran": "Reguler", "jumlah_terjual": 360, "total_revenue": 8047000, "jumlah_transaksi": 323, "rata_rata_transaksi": 24913 },
    { "ukuran": "Large", "jumlah_terjual": 193, "total_revenue": 5182000, "jumlah_transaksi": 173, "rata_rata_transaksi": 29954 }
  ]
}
```

---

## 4. GET /api/dashboard/produk-terlaris

Produk terlaris dalam window. Params tambahan: `limit` (default `10`, 1–100),
`by` = `qty` \| `revenue` (default `qty`).

**Contoh `?by=revenue&limit=3`:**

```json
{
  "periode": { "grain": "harian", "start": "2026-06-01", "end": "2026-07-30" },
  "data_tersedia": true,
  "data": [
    { "nama_produk": "Soya Original", "rasa": "Original", "qty": 272, "revenue": 7226000, "transaksi": 209 },
    { "nama_produk": "Soya Royal Belgian", "rasa": "Royal Belgian", "qty": 105, "revenue": 3426000, "transaksi": 99 },
    { "nama_produk": "Soya Tahwa Kembang Tahu", "rasa": "Tahwa Kembang Tahu", "qty": 204, "revenue": 3060000, "transaksi": 140 }
  ]
}
```

---

## 5. GET /api/dashboard/platform

Group by `platform` dalam window (kolom mentah — campur metode bayar & channel
delivery), urut `revenue` desc.

```json
{
  "periode": { "grain": "harian", "start": "2026-06-01", "end": "2026-07-30" },
  "data_tersedia": true,
  "data": [
    { "platform": "QRIS", "transaksi": 345, "revenue": 9811000, "qty": 433 },
    { "platform": "GrabFood", "transaksi": 159, "revenue": 5174000, "qty": 178 },
    { "platform": "Tunai", "transaksi": 179, "revenue": 4694000, "qty": 220 },
    { "platform": "ShopeeFood", "transaksi": 132, "revenue": 4019000, "qty": 148 },
    { "platform": "Transfer", "transaksi": 62, "revenue": 2469000, "qty": 94 },
    { "platform": "GoFood", "transaksi": 5, "revenue": 90000, "qty": 5 }
  ]
}
```

---

## 6. GET /api/dashboard/loyalty

Poin loyalty dalam window. `data.top_pelanggan` urut poin desc; params `limit`
(default `10`).

```json
{
  "periode": { "grain": "harian", "start": "2026-06-01", "end": "2026-07-30" },
  "data_tersedia": true,
  "data": {
    "total_poin": 1071,
    "top_pelanggan": [
      { "nama_pelanggan": "Nonita", "poin": 24, "transaksi": 10 },
      { "nama_pelanggan": "Nia", "poin": 23, "transaksi": 18 },
      { "nama_pelanggan": "Sharen", "poin": 20, "transaksi": 17 }
    ]
  }
}
```

---

## 7. GET /api/dashboard/rfm

**Statis periode-penuh** — TANPA param tanggal. Filter opsional `?segmen=<nama>`.
`ringkasan_segmen` dihitung dari seluruh snapshot (bukan hasil filter).

```json
{
  "periode_label": "1 Jun 2026 – 30 Jul 2026",
  "ringkasan_segmen": {
    "Pelanggan Baru": 122,
    "Butuh Perhatian": 108,
    "Potensial": 94,
    "Loyal": 21
  },
  "data": [
    {
      "id": 7, "nama_pelanggan": "Aden", "recency": 27, "frequency": 17,
      "total_pcs_dibeli": 18, "monetary": 513000, "total_poin_loyalty": 348,
      "frequency_skor": 17.4, "r_score": 3, "f_score": 4, "m_score": 4,
      "rfm_total": 11, "segmen": "Loyal"
    }
  ]
}
```

Nilai `segmen`: `Loyal`, `Potensial`, `Butuh Perhatian`, `Pelanggan Baru`.

> **BREAKING (data revisi Juni–Juli 2026).** Penamaan segmen berubah:
> `Pelanggan Loyal` → `Loyal`, `Pelanggan Potensial` → `Potensial`, dan
> `Hampir Hilang` **dihapus**, diganti `Pelanggan Baru`. Kalau frontend
> meng-hardcode nama/warna segmen, sesuaikan. Lebih aman ambil daftarnya
> dari `GET /api/dashboard/meta` (field `segmen`) yang selalu live dari DB.

Tiga field baru di objek data:

| Field | Arti |
|---|---|
| `total_pcs_dibeli` | Jumlah pcs. Beda dari `frequency` yang menghitung **kunjungan** — 1 kunjungan bisa banyak pcs. |
| `total_poin_loyalty` | Akumulasi poin LoyalSeed (1 poin per Rp 1.000; item non-minuman tidak dapat poin). |
| `frequency_skor` | Frekuensi terbobot = `0,6 × frequency + 0,4 × total_pcs_dibeli`. Desimal. Dasar `f_score`, supaya pembeli borongan tidak kalah dari yang sering datang tapi beli sedikit. |

---

## 8. GET /api/dashboard/switch

**Statis periode-penuh** — TANPA param tanggal. Filter opsional substring
`?rekomendasi=<teks>` (mis. `?rekomendasi=Large`).

```json
{
  "periode_label": "1 Jun 2026 – 30 Jul 2026",
  "data": [
    {
      "id": 1, "nama_pelanggan": "Sharen", "rasa_favorit": "Choco Maniac",
      "ukuran_saat_ini": "Reguler", "beli_reguler": 7, "beli_large": 10,
      "beli_botol": 0, "total_transaksi": 17, "qty_per_kunjungan": 1.0,
      "total_belanja": 512000, "rekomendasi": "Switch ke Large — frekuensi tinggi, mulai dari Large"
    }
  ]
}
```

---

## 9. GET /api/laporan/export

Download workbook `.xlsx` multi-sheet. Params sama: `grain`/`start`/`end`.

- **Response:** `200` dengan `Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`
  dan `Content-Disposition: attachment`.
- **Nama file:** `Laporan_SoyaCore_{grain}_{start}_{end}.xlsx`
  (mis. `Laporan_SoyaCore_harian_2026-06-01_2026-07-30.xlsx`).

**Sheet (berurutan):**

| Sheet | Isi | Scope |
|---|---|---|
| Ringkasan | Blok KPI + label periode & grain | window |
| Detail Transaksi | Baris `laporan_transaksi` (header Bahasa Indonesia) | window |
| Revenue per Ukuran | Group by ukuran + catatan cakupan | window, **minuman saja** |
| Time Series | Bucket sesuai grain | window |
| RFM Pelanggan | Snapshot RFM + catatan periode tetap | statis periode-penuh |
| Rekomendasi Switch | Snapshot switch + catatan periode tetap | statis periode-penuh |

Kolom uang berupa integer rupiah. Window kosong tetap menghasilkan `.xlsx` valid
(sebagian besar kosong, hanya header).

Sheet **Revenue per Ukuran** dan **RFM Pelanggan** diawali satu baris catatan
cakupan, jadi **header tabel ada di baris 2 dan data mulai baris 3** — bukan
header di baris 1 seperti sheet lain. Penting kalau file ini dibaca ulang
otomatis (mis. `pandas.read_excel(..., skiprows=1)`).

Sheet **RFM Pelanggan** memakai 12 kolom: Nama Pelanggan, Recency (hari),
Kunjungan, Total Pcs, Monetary (Rp), Total Poin, Skor Frekuensi, R, F, M,
RFM Total, Segmen.

---

## Ringkasan Kode Error

| Kode | HTTP | Kapan |
|---|---|---|
| `unauthenticated` | 401 | Tanpa/invalid token |
| `tidak_berwenang` | 403 | Role tidak mencukupi (lihat tabel Hak Akses per Role) |
| `validasi_gagal` | 422 | `grain` tak dikenal, format tanggal salah, `end` < `start`, dll. |
