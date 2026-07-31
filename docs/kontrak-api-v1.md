# Kontrak API SoyaCore — v1

> **Status: v1 — locked, 6 Juli 2026. Direvisi ke v1.1 — 17 Juli 2026 (M3),
> v1.2 — 31 Juli 2026 (konsep redeem poin v2), lalu v1.3 — 1 Agustus 2026
> (revisi pembimbing).**
> Kontrak ini sudah disepakati tim dan bersifat mengikat untuk integrasi self-order.
>
> **Revisi v1.3 — yang berubah untuk frontend:**
>
> 1. ⚠️ **PERUBAHAN KONTRAK**: `nomor_meja` **dihapus sepenuhnya** dari request
>    maupun response `POST /api/order`, termasuk kolomnya di database. Request
>    yang masih mengirimnya tetap diterima `201` (nilainya diabaikan, bukan
>    ditolak) supaya klien lama tidak rusak di tengah revisi — lihat §2.
> 2. `POST /api/order` menerima `items[].level_sugar` dan `items[].level_ice`
>    per item, dengan aturan ketersediaan per ukuran — lihat §2.
> 3. Response `POST /api/order` menyertakan `qris_url` **hanya** saat
>    `metode_bayar = "qris"` — lihat §2.
> 4. `GET /api/menu` menambah `meta.opsi_sugar`, `meta.opsi_ice`,
>    `meta.golongan_ukuran`, serta per menu: `golongan_ukuran`,
>    `bisa_pilih_sugar`, `bisa_pilih_ice`. Urutan ukuran kini eksplisit
>    (Hot → Reguler → Large → 250ml → 500ml → 1000ml), bukan alfabetis — lihat §1.
>
> **Revisi v1.2 — yang berubah untuk frontend:**
> 1. `GET /api/loyalty/{nomor_wa}` menambah field `poin_kedaluwarsa_pada`
>    (aditif, bentuk lama tetap terbaca) — lihat §3.
> 2. Poin katalog redeem berubah semua, ada kode baru `diskon_30`, dan
>    voucher diskon sekarang punya plafon potongan — lihat §4.
> 3. Pelanggan baru dapat bonus pendaftaran 50 poin — lihat §4.
>
> **Revisi v1.1 (M3) — WAJIB dibaca Ghefira:**
> 1. ⚠️ **BREAKING CHANGE**: response `GET /api/loyalty/{nomor_wa}` berubah bentuk
>    (model loyalty pindah dari stempel ke poin) — lihat §3.
> 2. Response `POST /api/order` direvisi (field item pakai `nama_menu`,
>    ada `nomor_meja`) dan `nomor_meja` kini **wajib** — lihat §2.
>    ~~Dibatalkan di v1.3: `nomor_meja` dihapus sepenuhnya.~~
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
      "nama": "Soya Signature",
      "menu": [
        {
          "id": 1,
          "nama": "Original",
          "rasa": "Soya Original Premium + Brown Sugar",
          "ukuran": "Hot",
          "harga": 17000,
          "golongan_ukuran": "cup",
          "bisa_pilih_sugar": true,
          "bisa_pilih_ice": false
        },
        {
          "id": 2,
          "nama": "Original",
          "rasa": "Soya Original Premium + Brown Sugar",
          "ukuran": "500ml",
          "harga": 39000,
          "golongan_ukuran": "botol",
          "bisa_pilih_sugar": false,
          "bisa_pilih_ice": false
        }
      ]
    },
    {
      "id": 2,
      "nama": "Dessert & Cookies",
      "menu": [
        {
          "id": 7,
          "nama": "Soy Milk Pudding",
          "rasa": "Puding susu kedelai lembut",
          "ukuran": "",
          "harga": 15000,
          "golongan_ukuran": "lainnya",
          "bisa_pilih_sugar": false,
          "bisa_pilih_ice": false
        }
      ]
    }
  ],
  "meta": {
    "opsi_sugar": [
      { "kode": "normal", "label": "Normal" },
      { "kode": "less", "label": "Less Sugar" },
      { "kode": "no", "label": "No Sugar" },
      { "kode": "extra", "label": "Extra Sugar" }
    ],
    "opsi_ice": [
      { "kode": "normal", "label": "Normal" },
      { "kode": "less", "label": "Less Ice" },
      { "kode": "no", "label": "No Ice" },
      { "kode": "extra", "label": "Extra Ice" }
    ],
    "golongan_ukuran": ["cup", "botol", "lainnya"]
  }
}
```

Catatan:

- Hanya menu dengan `is_active = true` yang dikembalikan.
- `rasa` dan `ukuran` bisa `null` atau string kosong — frontend wajib menangani
  keduanya.

**Baru di v1.3:**

- **`meta.opsi_sugar` / `meta.opsi_ice`** — render tombol/dropdown dari sini,
  jangan menyalin daftarnya ke frontend. Daftar yang disalin akan lepas sinkron
  dengan validasi backend, dan gejalanya adalah pilihan yang tampil di layar tapi
  ditolak `422` saat dikirim.
- **`bisa_pilih_sugar` / `bisa_pilih_ice`** per menu — cukup baca flag ini;
  frontend tidak perlu tahu ukuran mana termasuk golongan apa.
- **`golongan_ukuran`** (`cup` | `botol` | `lainnya`) — dipakai halaman Edit Menu
  memisahkan kolom cup (kiri) dan botol (kanan).
- **Urutan ukuran kini eksplisit**: `Hot → Reguler → Large → 250ml → 500ml →
  1000ml`. Sebelumnya alfabetis, yang menghasilkan `1000ml, 250ml, 500ml, Hot,
  Large, Reguler` dan tidak berarti apa pun buat pembaca.

---

## 2. POST /api/order

Membuat pesanan baru dari self-order. Transaksi dibuat dengan status `pending`;
pembayaran & pelunasan terjadi di kasir.

### Request

```json
{
  "nama": "Budi",
  "nomor_wa": "081234567890",
  "metode_bayar": "qris",
  "items": [
    { "menu_id": 1, "qty": 2, "level_sugar": "less", "level_ice": "no" },
    { "menu_id": 7, "qty": 1 }
  ]
}
```

| Field | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `nama` | string | ya | Nama pelanggan |
| `nomor_wa` | string | ya | Nomor WhatsApp, dinormalisasi server ke format 62 |
| ~~`nomor_meja`~~ | — | — | ⚠️ **DIHAPUS di v1.3**, termasuk kolomnya di database. Masih boleh dikirim (diabaikan) supaya klien lama tidak rusak. |
| `metode_bayar` | string | tidak | Pilihan bayar pelanggan. Nilai **`"cash"`** atau **`"qris"`** — perhatikan ini nilai tersimpan, walau UI menampilkan "Tunai/QRIS". Kalau tidak dikirim → `null`. |
| `items` | array | ya, min. 1 item | Daftar item pesanan |
| `items[].menu_id` | integer | ya | ID menu dari GET /api/menu |
| `items[].qty` | integer | ya, ≥ 1 | Jumlah |
| `items[].level_sugar` | string | tidak | `normal` \| `less` \| `no` \| `extra` — lihat aturan ketersediaan di bawah |
| `items[].level_ice` | string | tidak | `normal` \| `less` \| `no` \| `extra` — lihat aturan ketersediaan di bawah |

#### Ketersediaan `level_sugar` / `level_ice` per ukuran (v1.3)

Diturunkan dari **golongan ukuran**, bukan dari daftar nama menu — menu baru
bertambah terus, ukurannya tidak. Frontend tidak perlu menyalin aturan ini:
`GET /api/menu` sudah mengirim flag `bisa_pilih_sugar` / `bisa_pilih_ice` per
menu.

| Ukuran | Golongan | Sugar | Ice | Alasan |
|---|---|---|---|---|
| `Hot` | cup | ✅ | ❌ | Minuman panas, es tidak relevan |
| `Reguler`, `Large` | cup | ✅ | ✅ | Diracik per pesanan |
| `250ml`, `500ml`, `1000ml` | botol | ❌ | ❌ | Kemasan botol diproduksi batch, bukan per pesanan |
| kosong (Dessert & Cookies) | lainnya | ❌ | ❌ | Bukan minuman |

Mengirim opsi yang tidak relevan **ditolak `422` `opsi_tidak_tersedia`**, tidak
diabaikan diam-diam: data yang lolos lalu tersimpan membuat barista membaca
instruksi yang tidak bisa dia kerjakan. Kode di luar daftar (mis. `"setengah"`)
ditolak `422 validasi_gagal`.

> **`metode_bayar` di sini = niat bayar pelanggan, belum final.** Kasir tetap
> mengonfirmasi metode saat Tandai Lunas (§4, `POST .../bayar` yang **wajib**
> `metode_bayar`), dan nilai itulah yang jadi sumber kebenaran akhir. Field ini
> berguna supaya kasir bisa melihat/prefill pilihan pelanggan. Nilai selain
> `cash`/`qris` (mis. `"tunai"`) ditolak `422 validasi_gagal`.

**PENTING: client TIDAK PERNAH mengirim harga.** `total` dihitung server dari
`menu.harga` saat pesanan dibuat, dan harga satuan di-snapshot ke `detail_transaksi.harga_satuan`.

Kode pesanan self-order: `#A` + counter harian (`#A01`–`#A99`, reset tiap hari
Asia/Jakarta; melewati 99 lanjut `#A100` dst).

### Response `201 Created` (revisi v1.3)

```json
{
  "kode_pesanan": "#A05",
  "status": "pending",
  "total": 45000,
  "metode_bayar": "qris",
  "items": [
    {
      "nama_menu": "Original",
      "ukuran": "Reguler",
      "qty": 2,
      "harga_satuan": 15000,
      "subtotal": 30000,
      "level_sugar": "less",
      "level_sugar_label": "Less Sugar",
      "level_ice": "no",
      "level_ice_label": "No Ice"
    },
    {
      "nama_menu": "Coffee Kopi",
      "ukuran": "Hot",
      "qty": 1,
      "harga_satuan": 15000,
      "subtotal": 15000,
      "level_sugar": null,
      "level_sugar_label": null,
      "level_ice": null,
      "level_ice_label": null
    }
  ],
  "pesan": "Pesanan diterima! Silakan bayar di kasir (Cash/QRIS) dengan menyebutkan kode pesanan #A05.",
  "qris_url": "https://.../storage/qris/abc123.png"
}
```

Perubahan v1.3 pada response:

- ⚠️ `nomor_meja` **sudah tidak ada**.
- Tiap item menambah `ukuran`, `level_sugar`, `level_sugar_label`, `level_ice`,
  `level_ice_label`. Label ikut dikirim supaya frontend tidak merender `"less"`
  apa adanya ke pelanggan.
- **`qris_url` hanya ada saat `metode_bayar = "qris"`.** Key-nya tidak muncul
  sama sekali untuk `cash` maupun saat pelanggan tidak memilih metode. Isinya
  `null` kalau manager belum pernah mengunggah gambar QRIS-nya.

> QRIS di sini adalah **gambar statis milik merchant**. Backend tidak
> memvalidasi, membaca, atau memproses pembayaran apa pun dari gambar itu, dan
> tidak ada integrasi payment gateway.

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
  "poin": 123,
  "poin_kedaluwarsa_pada": "2027-07-31T10:00:00+07:00"
}
```

| Field | Keterangan |
|---|---|
| `poin` | Saldo poin aktual (1 poin per Rp 1.000 belanja lunas, dipotong saat redeem) |
| `poin_kedaluwarsa_pada` | Kapan saldo ini hangus kalau pelanggan tidak bertransaksi lagi. `null` = belum berlaku kedaluwarsa (baris lama sebelum aturan ini ada). Ditambahkan v1.2. |

Kedaluwarsa dihitung dari **transaksi terakhir**, bukan tanggal perolehan tiap
poin: setiap transaksi lunas mengundur tanggalnya 12 bulan ke depan, jadi
pelanggan yang masih aktif tidak pernah kehilangan poin. Saldo yang sudah lewat
tanggalnya dikembalikan sebagai `0` (dan memang dinolkan di server).

Error: `404 {"error": "pelanggan_tidak_ditemukan", "message": "..."}` bila
nomor belum terdaftar.

---

## 4. Endpoint Kasir Baru (v1.1 — auth Sanctum, role kasir/manager)

Dua aksi kasir M3 di bawah `Authorization: Bearer <token>` (login via
`POST /api/login`).

Katalog redeem bawaan (v1.2 — angka bisa disetel manager lewat
`PATCH /api/pengaturan/loyalty/katalog/{kode}`, jadi UI sebaiknya membacanya
dari `GET /api/pengaturan/loyalty/katalog`, bukan meng-hardcode):

| Kode | Poin | Potongan | Minimal belanja |
|---|---|---|---|
| `diskon_10` | 100 | 10%, maks Rp 5.000 | Rp 25.000 |
| `diskon_20` | 200 | 20%, maks Rp 10.000 | Rp 25.000 |
| `diskon_30` | 300 | 30%, maks Rp 15.000 | Rp 25.000 |
| `diskon_50` | 500 | 50%, maks Rp 25.000 | Rp 25.000 |
| `gratis_original` | 350 | 1 Original reguler | — |
| `gratis_honey_lemon` | 400 | 1 Honey Lemon reguler | — |
| `gratis_mango_monggo` | 400 | 1 Mango Monggo reguler | — |
| `gratis_coffee_kopi` | 450 | 1 Coffee Kopi reguler | — |

**Voucher diskon punya plafon potongan**: persennya berlaku penuh sampai
belanja Rp 50.000, di atas itu potongannya berhenti nambah. Jadi `diskon_50`
pada pesanan Rp 475.000 memotong Rp 25.000, bukan Rp 237.500.

Pelanggan yang baru pertama kali didaftarkan (lewat kasir maupun SoyaScan)
langsung mendapat **bonus pendaftaran 50 poin**, sekali seumur pelanggan.

### POST /api/transaksi/{id}/redeem-poin

Body: `{ "kode_redeem": "diskon_10" }` — hanya saat transaksi `pending`,
**satu redemption per transaksi**, dan hanya bila transaksi punya customer.

- Tipe diskon → diskon dihitung server dari subtotal saat ini.
- Tipe gratis_menu → item reward ditambahkan (`is_reward: true`,
  `subtotal: 0`, `harga_satuan` = snapshot harga asli untuk laporan).
- Poin dipotong sesuai katalog; response = objek transaksi ter-update
  (`kode_redeem`, `poin_ditukar` terisi).

Error: `poin_kurang` (422, menyebut kekurangannya),
`minimal_pembelian_kurang` (422, semua tier diskon), `kode_redeem_invalid`
(422), `kode_redeem_nonaktif` (422, reward sedang dimatikan manager),
`transaksi_sudah_redeem` (409), `transaksi_sudah_lunas`/`_batal`
(409), `transaksi_tanpa_customer` (422).

Setelah redeem berhasil, `POST /api/transaksi/{id}/diskon` ditolak `409`
`diskon_terkunci_redeem` — diskon manual menimpa (bukan menumpuk) diskon
reward, padahal poinnya sudah terpotong. Kalau memang salah, batalkan
transaksi dan buat baru.

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
