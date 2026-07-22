# Kontrak API Kasir SoyaCore — v1 (DRAFT)

> **Status: DRAFT — M2, 14 Juli 2026.**
> Dokumen ini BUKAN bagian dari `kontrak-api-v1.md` (self-order) yang sudah locked.
> Ini dokumentasi endpoint internal kasir yang dibangun di M2, terbuka untuk revisi
> sampai disepakati tim.

Semua endpoint berada di bawah prefix `/api`. Prinsip mengikuti kontrak v1:
client tidak pernah mengirim harga/total, semua uang integer rupiah, format error
seragam `{"error": "kode_snake_case", "message": "teks untuk user"}`.

---

## Autentikasi (Sanctum Bearer Token)

Semua endpoint di dokumen ini (kecuali `POST /api/login`) membutuhkan header:

```
Authorization: Bearer <token>
```

### POST /api/login

Body: `{ "email": "...", "password": "..." }`

Response `200`: `{ "token": "...", "user": { "id", "nama", "email", "role" } }`

Error: `kredensial_salah` (422), `akun_nonaktif` (403), `validasi_gagal` (422).

### POST /api/logout

Mencabut token yang sedang dipakai. Response `200`.

### GET /api/me

Profil user login. Response `200`: `{ "user": { "id", "nama", "email", "role" } }`.

---

## Role

| Role | Boleh |
|---|---|
| `kasir` | Baca kategori/menu, seluruh alur transaksi |
| `manager` | Semua yang kasir boleh + tulis kategori/menu |

Melanggar aturan role → `403 {"error": "tidak_berwenang"}`.
Tanpa/token salah → `401 {"error": "unauthenticated"}`.

---

## CRUD Kategori

| Method & Path | Role | Keterangan |
|---|---|---|
| `GET /api/kategori` | kasir, manager | List semua kategori |
| `GET /api/kategori/{id}` | kasir, manager | Detail + daftar menu-nya |
| `POST /api/kategori` | manager | Body: `{ "nama": "..." }` |
| `PUT/PATCH /api/kategori/{id}` | manager | Body: `{ "nama": "..." }` |
| `DELETE /api/kategori/{id}` | manager | Ditolak `409 kategori_masih_dipakai` jika masih punya menu |

## CRUD Menu

| Method & Path | Role | Keterangan |
|---|---|---|
| `GET /api/menu` | kasir, manager | Filter opsional: `?kategori_id=` dan `?is_active=` |
| `GET /api/menu/{id}` | kasir, manager | Detail menu |
| `POST /api/menu` | manager | Body: `kategori_id`, `nama`, `harga` (int ≥ 0), `rasa?`, `ukuran?`, `is_active?` |
| `PUT/PATCH /api/menu/{id}` | manager | Field sama, semuanya opsional |
| `DELETE /api/menu/{id}` | manager | Jika menu pernah dipakai transaksi → dinonaktifkan (soft), bukan dihapus |

---

## Pencarian Customer (halaman Pesanan)

### GET /api/customers/cari — auto-detect pelanggan lama/baru

Role: kasir, manager. **Read-only** — tidak membuat/mengubah/menghapus apa pun.

Query param (minimal salah satu dari `no_wa` / `nama` wajib):

| Param | Keterangan |
|---|---|
| `no_wa` | Dinormalisasi dulu (`0812…`, `+62 812…`, `812…` → `62812…`), lalu dicocokkan **persis** |
| `nama` | Pencarian **parsial** (contains), min 2 karakter; wildcard `%` dan `_` di-escape jadi teks literal |
| `limit` | 1–25, default 10 |

Response `200`:

```json
{
  "data": [
    { "id": 1, "nama": "Budi Santoso", "no_wa": "6281234567890", "poin": 400 }
  ]
}
```

- **Tidak ketemu → `200` dengan `data: []`, bukan `404`.** Pelanggan baru adalah
  state normal saat kasir masih mengetik, jadi frontend cukup cek `data.length`:
  ada isi → ver2 (pelanggan lama, tampilkan nama + poin), kosong → ver1 (input baru).
- Customer tanpa baris `loyalty` dilaporkan `poin: 0` (bukan error).
- Query kosong (tanpa `no_wa` maupun `nama`) → `422 validasi_gagal` — endpoint ini
  bukan dump seluruh customer.

Beda dengan `GET /api/loyalty/{nomorWa}` (publik, SoyaScan): endpoint tersebut
tanpa auth, hanya exact-match `no_wa`, dan `404` kalau tidak ketemu. Untuk
halaman Pesanan pakai `/api/customers/cari` — butuh auth karena mengembalikan
data pelanggan yang bisa dienumerasi lewat pencarian nama.

---

## Alur Transaksi Kasir

Status transaksi: `pending → lunas` atau `pending → batal` (satu arah).
Semua aksi ubah (item/diskon/bayar/batal) hanya boleh saat `pending`;
selain itu `409 {"error": "transaksi_sudah_lunas" | "transaksi_sudah_batal"}`.

### POST /api/transaksi — mulai transaksi

Body (semua opsional):

```json
{
  "customer": { "nama": "Budi", "no_wa": "0812 3456 7890" }
}
```

- `customer.no_wa` dinormalisasi (trim, buang non-digit kecuali leading `+`),
  lalu find-or-create by `no_wa`.
- `kode_pesanan` digenerate server: `#K` + urutan harian 3 digit (`#K001`, `#K002`, …) —
  dibedakan dari format `#A23` self-order (M3).
- `user_id` = user yang login, status awal `pending`.
- **Per revisi ERD 15 Juli 2026**: `nomor_meja`, `platform`, `catatan`, dan
  `sumber` adalah atribut level **item** (tabel `detail_transaksi`) — dikirim
  saat tambah item, bukan saat membuat transaksi.

Response `201`: objek transaksi lengkap (lihat bentuk di bawah).

### GET /api/transaksi — list

Filter: `?status=pending|lunas|batal`, `?tanggal=YYYY-MM-DD` (by `created_at`).
Pagination standar Laravel (15/halaman): `data`, `links`, `meta`.

### GET /api/transaksi/{id} — detail

Response `200`:

```json
{
  "data": {
    "id": 1,
    "kode_pesanan": "#K001",
    "status": "pending",
    "customer": { "id": 1, "nama": "Budi", "no_wa": "081234567890" },
    "kasir": { "id": 2, "nama": "Kasir Gressoy" },
    "items": [
      {
        "id": 1, "menu_id": 1, "nama": "Original",
        "rasa": "Soya Original Premium + Brown Sugar", "ukuran": "Reguler",
        "qty": 2, "harga_satuan": 17000, "subtotal": 34000, "is_reward": false,
        "nomor_meja": "5", "sumber": "kasir", "platform": null,
        "diskon_persen": 0, "diskon_nilai": 0, "catatan": null
      }
    ],
    "subtotal": 34000,
    "diskon_persen": 0,
    "diskon_nilai": 0,
    "total": 34000,
    "metode_bayar": null,
    "point_earned": 0,
    "waktu_lunas": null,
    "created_at": "2026-07-15T10:00:00+00:00"
  }
}
```

> `subtotal`, `diskon_persen`, dan `diskon_nilai` level transaksi adalah
> **agregat dari item** (per revisi ERD kolom-kolom ini tersimpan di
> `detail_transaksi`); hanya `total` yang tersimpan di tabel `transaksi`.

### POST /api/transaksi/{id}/items — tambah item

Body:

```json
{
  "menu_id": 1,
  "qty": 2,
  "nomor_meja": "5",
  "platform": null,
  "catatan": "less sugar"
}
```

- `nomor_meja` / `platform` / `catatan` opsional (atribut level item, per
  revisi ERD). `sumber` di-set server = `kasir`.
- **Tanpa harga** — server snapshot `menu.harga` ke `harga_satuan`.
- Menu yang sama (non-reward) digabung: qty ditambahkan ke baris yang sudah ada.
- Error: `menu_tidak_tersedia` (422) untuk menu tak ada / nonaktif.

### PATCH /api/transaksi/{id}/items/{item} — ubah qty

Body: `{ "qty": 3 }` (≥ 1), boleh sekalian `nomor_meja`/`platform`/`catatan`.
Subtotal item & total transaksi dihitung ulang.

### DELETE /api/transaksi/{id}/items/{item} — hapus item

Total transaksi dihitung ulang.

### POST /api/transaksi/{id}/diskon — terapkan/ubah diskon

Body: `{ "tipe": "preset" | "custom_persen" | "custom_nilai", "nilai": <int> }`

| Tipe | Aturan | Efek |
|---|---|---|
| `preset` | nilai ∈ {10, 20, 50} | `diskon_persen = nilai`, `diskon_nilai = round(subtotal × nilai / 100)` |
| `custom_persen` | 0 ≤ nilai ≤ 100 (integer) | sama seperti preset |
| `custom_nilai` | 0 ≤ nilai ≤ subtotal | `diskon_persen = 0`, `diskon_nilai = nilai` |

`total = subtotal − diskon_nilai`. Error: `diskon_preset_invalid`,
`diskon_persen_invalid`, `diskon_nilai_invalid`, `diskon_melebihi_subtotal` (semua 422).

Mengirim diskon baru **menggantikan** diskon sebelumnya (bukan menumpuk).

**Penyimpanan per item (revisi ERD 15 Juli 2026):** endpoint ini tetap
level transaksi, tapi hasilnya disimpan di tiap baris `detail_transaksi`:

- persen → tiap item diberi `diskon_persen` yang sama, `diskon_nilai` item =
  `round(subtotal_item × persen / 100)`.
- nominal → didistribusi **proporsional** terhadap subtotal item (item
  terakhir menerima sisa pembulatan supaya jumlahnya tepat).
- Konsekuensi: **menghapus item ikut menghapus porsi diskon nominal item
  itu** (diskon melekat di baris item, bukan di transaksi).

### POST /api/transaksi/{id}/bayar — finalisasi

Body: `{ "metode_bayar": "cash" | "qris" }`.
Set `status = lunas`, `waktu_lunas = now()`, `point_earned = 1`.
Error: `items_kosong` (422) bila belum ada item; `409` bila bukan `pending`.

> Catatan: `point_earned` dicatat di transaksi saja — increment stempel di tabel
> `loyalty` adalah scope LoyalSeed (M3).

### POST /api/transaksi/{id}/batal

Tanpa body. Set `status = batal`. `409` bila bukan `pending`.

---

## Ringkasan Kode Error M2

| Kode | HTTP | Sumber |
|---|---|---|
| `unauthenticated` | 401 | Tanpa/invalid token |
| `tidak_berwenang` | 403 | Role tidak sesuai |
| `akun_nonaktif` | 403 | Login user `is_active = false` |
| `tidak_ditemukan` | 404 | Resource/route tidak ada |
| `kredensial_salah` | 422 | Email/password salah |
| `validasi_gagal` | 422 | Gagal validasi request (plus field `details`) |
| `menu_tidak_tersedia` | 422 | Menu tidak ada / nonaktif saat tambah item |
| `items_kosong` | 422 | Bayar tanpa item |
| `nomor_wa_invalid` | 422 | Nomor WA kosong setelah normalisasi |
| `diskon_preset_invalid` | 422 | Preset selain 10/20/50 |
| `diskon_persen_invalid` | 422 | Persen custom di luar 0–100 |
| `diskon_nilai_invalid` | 422 | Nominal negatif |
| `diskon_melebihi_subtotal` | 422 | Nominal > subtotal |
| `kategori_masih_dipakai` | 409 | Hapus kategori yang masih punya menu |
| `transaksi_sudah_lunas` / `transaksi_sudah_batal` | 409 | Ubah transaksi non-pending |
