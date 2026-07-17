# Kontrak API SoyaCore — v1

> **Status: v1 — locked, 6 Juli 2026. Direvisi ke v1.1 — 17 Juli 2026 (M3).**
> Kontrak ini sudah disepakati tim dan bersifat mengikat untuk integrasi self-order.
>
> **Revisi v1.1 (M3) — WAJIB dibaca Ghefira:**
> 1. ⚠️ **BREAKING CHANGE**: response `GET /api/loyalty/{nomor_wa}` berubah bentuk
>    (model loyalty pindah dari stempel ke poin) — lihat §3.
> 2. Response `POST /api/order` direvisi (field item pakai `nama_menu`,
>    ada `nomor_meja`) dan `nomor_meja` kini **wajib** — lihat §2.
> 3. Endpoint kasir baru: `POST /api/transaksi/{id}/redeem-poin` dan
>    `POST /api/transaksi/{id}/tandai-lunas` — lihat §4.

Dokumen ini adalah acuan integrasi antara frontend self-order (Ghefira & Farah) dan
backend SoyaCore (Laravel). Semua endpoint berada di bawah prefix `/api`.

---

## Prinsip Umum

1. **Client TIDAK PERNAH mengirim harga.** Semua harga dan total dihitung server dari
   `menu.harga` yang tersimpan di database. Payload request yang menyertakan field harga
   akan diabaikan.
2. Semua nilai uang adalah **rupiah bulat (integer)** — tidak ada desimal.
3. Format response: JSON, UTF-8.
4. Identitas pelanggan untuk loyalty memakai **nomor WhatsApp** (`no_wa`), dinormalisasi
   di sisi server.

---

## 1. GET /api/menu

Mengambil daftar menu aktif, dikelompokkan per kategori.

### Request

Tanpa parameter.

### Response `200 OK`

```json
{
  "kategori": [
    {
      "id": 1,
      "nama": "Susu Kedelai",
      "menu": [
        {
          "id": 1,
          "nama": "Susu Kedelai Botol",
          "rasa": "Original",
          "ukuran": "250ml",
          "harga": 10000
        },
        {
          "id": 2,
          "nama": "Susu Kedelai Botol",
          "rasa": "Cokelat",
          "ukuran": "250ml",
          "harga": 12000
        }
      ]
    },
    {
      "id": 2,
      "nama": "Snack",
      "menu": [
        {
          "id": 7,
          "nama": "Tahu Bakso",
          "rasa": null,
          "ukuran": null,
          "harga": 15000
        }
      ]
    }
  ]
}
```

Catatan:
- Hanya menu dengan `is_active = true` yang dikembalikan.
- `rasa` dan `ukuran` bisa `null` — frontend wajib menangani keduanya.

---

## 2. POST /api/order

Membuat pesanan baru dari self-order. Transaksi dibuat dengan status `pending`;
pembayaran & pelunasan terjadi di kasir.

### Request

```json
{
  "nama": "Budi",
  "nomor_wa": "081234567890",
  "nomor_meja": "5",
  "items": [
    { "menu_id": 1, "qty": 2 },
    { "menu_id": 7, "qty": 1 }
  ]
}
```

| Field | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `nama` | string | ya | Nama pelanggan |
| `nomor_wa` | string | ya | Nomor WhatsApp, dinormalisasi server ke format 62 |
| `nomor_meja` | string | **ya** (revisi v1.1) | Nomor meja dari form SoyaScan |
| `items` | array | ya, min. 1 item | Daftar item pesanan |
| `items[].menu_id` | integer | ya | ID menu dari GET /api/menu |
| `items[].qty` | integer | ya, ≥ 1 | Jumlah |

**PENTING: client TIDAK PERNAH mengirim harga.** `total` dihitung server dari
`menu.harga` saat pesanan dibuat, dan harga satuan di-snapshot ke `detail_transaksi.harga_satuan`.

Kode pesanan self-order: `#A` + counter harian (`#A01`–`#A99`, reset tiap hari
Asia/Jakarta; melewati 99 lanjut `#A100` dst).

### Response `201 Created` (revisi v1.1)

```json
{
  "kode_pesanan": "#A05",
  "status": "pending",
  "nomor_meja": "12",
  "total": 45000,
  "items": [
    { "nama_menu": "Original", "qty": 2, "harga_satuan": 15000, "subtotal": 30000 },
    { "nama_menu": "Coffee Kopi", "qty": 1, "harga_satuan": 15000, "subtotal": 15000 }
  ],
  "pesan": "Pesanan diterima! Silakan bayar di kasir (Cash/QRIS) dengan menyebutkan kode pesanan #A05."
}
```

---

## 3. GET /api/loyalty/{nomor_wa}

> ⚠️ **BREAKING CHANGE dari v1 (berlaku sejak v1.1, 17 Juli 2026).**
> Model loyalty berubah dari **stempel/kartu punch** menjadi **poin sebagai
> mata uang** (1 poin per Rp 1.000 dari total yang dibayar, bertambah HANYA
> saat transaksi lunas). Field `stempel`, `gratis_tersedia`, dan
> `menuju_gratis` **sudah tidak ada**. Kalau UI SoyaScan dibangun di atas
> bentuk lama, wajib disesuaikan ke bentuk baru di bawah.

Cek saldo poin pelanggan berdasarkan nomor WhatsApp.

### Request

Path parameter: `nomor_wa` — nomor WhatsApp pelanggan, toleran terhadap
format (spasi/strip/`+62`/`08` dinormalisasi server).

Contoh: `GET /api/loyalty/081234567890`

### Response `200 OK` (bentuk baru v1.1)

```json
{
  "nomor_wa": "6281234567890",
  "nama": "Budi",
  "poin": 123
}
```

| Field | Keterangan |
|---|---|
| `poin` | Saldo poin aktual (1 poin per Rp 1.000 belanja lunas, dipotong saat redeem) |

Error: `404 {"error": "pelanggan_tidak_ditemukan", "message": "..."}` bila
nomor belum terdaftar.

---

## 4. Endpoint Kasir Baru (v1.1 — auth Sanctum, role kasir/manager)

Dua aksi kasir M3 di bawah `Authorization: Bearer <token>` (login via
`POST /api/login`). Katalog redeem: `diskon_10` (150 poin), `diskon_20`
(250), `diskon_50` (350, minimal pembelian Rp 50.000), `gratis_original`
(150), `gratis_coffee_kopi` (250), `gratis_honey_lemon` (250),
`gratis_mango_monggo` (250).

### POST /api/transaksi/{id}/redeem-poin

Body: `{ "kode_redeem": "diskon_10" }` — hanya saat transaksi `pending`,
**satu redemption per transaksi**, dan hanya bila transaksi punya customer.

- Tipe diskon → diskon dihitung server dari subtotal saat ini.
- Tipe gratis_menu → item reward ditambahkan (`is_reward: true`,
  `subtotal: 0`, `harga_satuan` = snapshot harga asli untuk laporan).
- Poin dipotong sesuai katalog; response = objek transaksi ter-update
  (`kode_redeem`, `poin_ditukar` terisi).

Error: `poin_kurang` (422, menyebut kekurangannya),
`minimal_pembelian_kurang` (422, khusus diskon_50), `kode_redeem_invalid`
(422), `transaksi_sudah_redeem` (409), `transaksi_sudah_lunas`/`_batal`
(409), `transaksi_tanpa_customer` (422).

### POST /api/transaksi/{id}/tandai-lunas

(Alias dari `POST /api/transaksi/{id}/bayar` — dua-duanya valid.)

Body: `{ "metode_bayar": "cash" | "qris" }`. Efek: `status = lunas`,
`waktu_lunas` terisi, `user_id` = kasir yang memproses, lalu **earning poin
LoyalSeed**: `point_earned = intdiv(total, 1000)` ditambahkan ke saldo poin
customer. **Idempotent** — pemanggilan kedua ditolak `409` dan poin tidak
bertambah dua kali.

---

## Format Error Standar

Semua error dikembalikan dengan struktur seragam:

```json
{
  "error": "menu_tidak_tersedia",
  "message": "Menu dengan id 99 tidak tersedia atau sudah tidak aktif."
}
```

| Kode `error` | HTTP Status | Kapan terjadi |
|---|---|---|
| `menu_tidak_tersedia` | 422 | `menu_id` tidak ditemukan atau `is_active = false` |
| `items_kosong` | 422 | `items` kosong / tidak dikirim |
| `nomor_wa_invalid` | 422 | Format nomor WhatsApp tidak valid |
| `qty_invalid` | 422 | `qty` bukan integer ≥ 1 |

`message` adalah teks yang boleh langsung ditampilkan ke pengguna. Frontend cukup
switch berdasarkan `error` untuk penanganan khusus.

---

## State Machine Transaksi

```
             ┌─────────┐
             │ pending │  (dibuat oleh POST /api/order atau kasir)
             └────┬────┘
        ┌─────────┴──────────┐
        ▼                    ▼
   ┌─────────┐          ┌─────────┐
   │  lunas  │          │  batal  │
   └─────────┘          └─────────┘
```

| Transisi | Efek loyalty (revisi v1.1) |
|---|---|
| `pending → lunas` | Poin **+`intdiv(total, 1000)`** untuk customer terkait — 1 poin per Rp 1.000 dari total yang benar-benar dibayar (dicatat di `transaksi.point_earned`, `waktu_lunas` terisi, idempotent via `loyalty_applied_at`) |
| `pending → batal` | Poin **tidak berubah** |

- Status hanya bergerak maju; tidak ada transisi dari `lunas`/`batal` kembali ke `pending`.
- Perhitungan stempel & redeem gratis adalah logic sisi server (milestone M3), bukan
  tanggung jawab client.
