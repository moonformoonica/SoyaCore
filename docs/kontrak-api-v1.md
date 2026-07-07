# Kontrak API SoyaCore — v1

> **Status: v1 — locked, 6 Juli 2026.**
> Kontrak ini sudah disepakati tim dan bersifat mengikat untuk integrasi self-order.
> Perubahan apa pun setelah tanggal ini harus disepakati ulang dan dinaikkan ke v1.1/v2.

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
| `nomor_wa` | string | ya | Nomor WhatsApp, dinormalisasi server |
| `nomor_meja` | string | opsional | Nomor meja, boleh kosong |
| `items` | array | ya, min. 1 item | Daftar item pesanan |
| `items[].menu_id` | integer | ya | ID menu dari GET /api/menu |
| `items[].qty` | integer | ya, ≥ 1 | Jumlah |

**PENTING: client TIDAK PERNAH mengirim harga.** `total` dihitung server dari
`menu.harga` saat pesanan dibuat, dan harga satuan di-snapshot ke `detail_transaksi.harga_satuan`.

### Response `201 Created`

```json
{
  "kode_pesanan": "#A23",
  "status": "pending",
  "total": 35000,
  "items": [
    {
      "menu_id": 1,
      "nama": "Susu Kedelai Botol",
      "rasa": "Original",
      "ukuran": "250ml",
      "qty": 2,
      "harga_satuan": 10000,
      "subtotal": 20000
    },
    {
      "menu_id": 7,
      "nama": "Tahu Bakso",
      "rasa": null,
      "ukuran": null,
      "qty": 1,
      "harga_satuan": 15000,
      "subtotal": 15000
    }
  ],
  "pesan": "Pesanan diterima. Silakan lakukan pembayaran di kasir dengan menyebutkan kode pesanan."
}
```

---

## 3. GET /api/loyalty/{nomor_wa}

Cek status loyalty (stempel) pelanggan berdasarkan nomor WhatsApp.

### Request

Path parameter: `nomor_wa` — nomor WhatsApp pelanggan.

Contoh: `GET /api/loyalty/081234567890`

### Response `200 OK`

```json
{
  "nomor_wa": "081234567890",
  "nama": "Budi",
  "stempel": 7,
  "gratis_tersedia": 0,
  "menuju_gratis": 3
}
```

| Field | Keterangan |
|---|---|
| `stempel` | Jumlah stempel terkumpul saat ini (0–9, reset tiap 10) |
| `gratis_tersedia` | Jumlah item gratis yang bisa di-redeem sekarang |
| `menuju_gratis` | Sisa stempel menuju item gratis berikutnya (10 − stempel) |

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

| Transisi | Efek loyalty |
|---|---|
| `pending → lunas` | Poin **+1 stempel** untuk customer terkait (dicatat di `transaksi.point_earned`, `waktu_lunas` terisi) |
| `pending → batal` | Poin **tidak berubah** |

- Status hanya bergerak maju; tidak ada transisi dari `lunas`/`batal` kembali ke `pending`.
- Perhitungan stempel & redeem gratis adalah logic sisi server (milestone M3), bukan
  tanggung jawab client.
