# Kontrak API Dashboard & Laporan SoyaCore — v1

> **Status: v1 — 16 Juli 2026.** Kontrak integrasi untuk frontend dashboard
> manager (Ghefira). Semua endpoint bersifat **read-only reporting**, dihitung
> dari layer data historis terpisah (`laporan_*`), tidak menyentuh POS live.

Semua endpoint di bawah prefix `/api`, **manager-only** (Sanctum + `role:manager`).

## Autentikasi

Sertakan header dari hasil `POST /api/login`:

```
Authorization: Bearer <token>
```

- Tanpa token / token invalid → `401 { "error": "unauthenticated", "message": "..." }`
- Token non-manager (mis. kasir) → `403 { "error": "tidak_berwenang", "message": "..." }`

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
  "segmen": ["Butuh Perhatian", "Hampir Hilang", "Pelanggan Loyal", "Pelanggan Potensial"]
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

```json
{
  "periode": { "grain": "harian", "start": "2026-06-01", "end": "2026-07-30" },
  "data_tersedia": true,
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
    "Butuh Perhatian": 111,
    "Pelanggan Potensial": 104,
    "Pelanggan Loyal": 90,
    "Hampir Hilang": 40
  },
  "data": [
    {
      "id": 4, "nama_pelanggan": "Achmad", "recency": 1, "frequency": 4,
      "monetary": 85000, "r_score": 3, "f_score": 3, "m_score": 3,
      "rfm_total": 9, "segmen": "Pelanggan Loyal"
    }
  ]
}
```

Nilai `segmen`: `Pelanggan Loyal`, `Pelanggan Potensial`, `Butuh Perhatian`, `Hampir Hilang`.

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
| Revenue per Ukuran | Group by ukuran | window |
| Time Series | Bucket sesuai grain | window |
| RFM Pelanggan | Snapshot RFM + catatan periode tetap | statis periode-penuh |
| Rekomendasi Switch | Snapshot switch + catatan periode tetap | statis periode-penuh |

Kolom uang berupa integer rupiah. Window kosong tetap menghasilkan `.xlsx` valid
(sebagian besar kosong, hanya header).

---

## Ringkasan Kode Error

| Kode | HTTP | Kapan |
|---|---|---|
| `unauthenticated` | 401 | Tanpa/invalid token |
| `tidak_berwenang` | 403 | Bukan role manager |
| `validasi_gagal` | 422 | `grain` tak dikenal, format tanggal salah, `end` < `start`, dll. |
