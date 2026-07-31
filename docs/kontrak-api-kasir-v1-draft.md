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
| `no_wa` | Dicocokkan **parsial**, min 3 digit. Lihat kontrak pencocokan nomor di bawah |
| `nama` | Pencarian **parsial** (contains), min 2 karakter, **case-insensitive**; wildcard `%` dan `_` di-escape jadi teks literal |
| `limit` | 1–25, default 10 |

#### Kontrak pencocokan nomor WA (berlaku di semua pencarian nomor)

Nomor tersimpan **selalu** dalam bentuk ternormalisasi `62…`. Pencarian mencoba
**dua bentuk sekaligus** (di-OR), sehingga keduanya bekerja:

| Yang diketik | Contoh | Cocok karena |
|---|---|---|
| **Nomor lengkap**, ejaan apa pun | `081234567890`, `0812-3456-7890`, `+62 812 3456 7890`, `6281234567890` | dinormalisasi dulu ke `6281234567890` |
| **Potongan nomor**, termasuk **4 digit terakhir** | `7890`, `3456` | digitnya dicocokkan apa adanya sebagai substring |

Kenapa dua bentuk, bukan satu: normalisasi dirancang untuk nomor **lengkap** —
input berawalan `0`/`8` selalu ditempeli `62`. Asumsi itu runtuh untuk potongan
nomor, karena `8122` (4 digit terakhir) bukan awal nomor tapi ekornya, dan
`628122` tidak ada di dalam `6281245688122`. Karena itu keduanya dicoba.

Berlaku sama di:

- `GET /api/customers/cari?no_wa=` — auto-detect pelanggan di halaman Pesanan
- `GET /api/transaksi?cari=` — riwayat transaksi (§ daftar transaksi)

> `GET /api/loyalty/{nomorWa}` **sengaja TIDAK** ikut aturan ini — endpoint itu
> publik tanpa auth, jadi pencocokan parsial akan membuat siapa pun bisa
> menebak 4 digit lalu memanen nama + saldo poin pelanggan. Di sana nomornya
> harus **lengkap dan persis**.

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

**Saran nomor terdaftar** (kolom "Cek Poin Pelanggan"). Pencocokan `no_wa` parsial
dan **bisa dari posisi mana pun** — depan, tengah, atau belakang. Contoh untuk
nomor tersimpan `6281245688122`:

| Ketikan | Hasil |
|---|---|
| `0812` / `812` / `6281` | cocok dari **depan** (ejaan lokal maupun format simpan) |
| `8122` (4 digit terakhir) | cocok dari **belakang** |
| `4568`, `5688122` | cocok di **tengah/ekor** |
| `6281245688122` | nomor lengkap; yang **cocok persis diurutkan paling atas**, sisanya menyusul alfabetis |
| `8`, `62`, `+` | `200` dengan `data: []` — di bawah 3 digit |

- Tiap ketikan dicocokkan ke **dua bentuk sekaligus** (OR): digit apa adanya, dan
  hasil normalisasi. `NomorWa::normalisasi()` dirancang untuk nomor **lengkap** —
  input berawalan `0`/`8` selalu ditempeli `62` karena diasumsikan itu awal nomor.
  Untuk potongan, asumsi itu salah: `8122` jadi `628122`, yang tidak ada di dalam
  `6281245688122`. Digit mentah menangkap potongan tengah/ekor, hasil normalisasi
  menangkap ejaan lokal dari depan. Lihat `NomorWa::kandidatCari()`.
- Batas minimalnya diukur dari **digit yang diketik**, bukan hasil normalisasi —
  normalisasi menambahkan awalan `62`, jadi satu ketikan `8` terbaca 3 karakter
  dan lolos batas kalau yang diukur hasilnya.
- Di bawah 3 digit sengaja `200 data: []`, **bukan `422`** — kolom ini bereaksi tiap
  ketikan, jadi error di karakter pertama akan mengganggu. Batasnya ada supaya
  mengetik `8` tidak mencocokkan hampir semua nomor Indonesia (semua tersimpan
  sebagai `628…`) dan mengubah endpoint ini jadi dump daftar pelanggan.
- Frontend tetap perlu debounce; tiap ketikan = satu query `LIKE`.

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
- **Per revisi ERD 15 Juli 2026**: `platform`, `catatan`, dan `sumber` adalah
  atribut level **item** (tabel `detail_transaksi`) — dikirim saat tambah item,
  bukan saat membuat transaksi. (`nomor_meja` **dihapus** pada revisi
  1 Agustus 2026, termasuk kolomnya.)
- **Revisi 1 Agustus 2026**: `transaksi.sumber` (`kasir` | `self_order`) kini ada
  di level transaksi juga — satu transaksi berasal dari satu channel, dan
  menurunkannya dari item memaksa query anak di setiap baris daftar.

Response `201`: objek transaksi lengkap (lihat bentuk di bawah).

### GET /api/transaksi — list

Pagination standar Laravel (15/halaman, maks `per_page=200`): `data`, `links`,
`meta`.

#### Filter tanggal & urutan (revisi 1 Agustus 2026)

| Query param | Isi | Keterangan |
|---|---|---|
| `tanggal` | `YYYY-MM-DD` | Parameter lama, **tetap didukung**: tanggal persis |
| `tanggal_mulai` | `YYYY-MM-DD` | Batas bawah, inklusif |
| `tanggal_selesai` | `YYYY-MM-DD` | Batas atas, inklusif |
| `preset` | `hari_ini` \| `kemarin` \| `7_hari` \| `30_hari` \| `bulan_ini` | Jalan pintas. Kalah dari batas eksplisit bila salah satunya dikirim. `7_hari`/`30_hari` menghitung hari ini sebagai salah satu harinya. |
| `urut` | `terbaru` \| `terlama` | Default `terbaru` (perilaku sebelumnya) |

`tanggal_selesai < tanggal_mulai` ditolak `422`. Batas hari dihitung menurut
**WIB**, bukan zona server — lihat [`laporan-kasir.md`](laporan-kasir.md) §7.

#### Filter data (revisi 1 Agustus 2026)

Semua opsional dan bisa digabung (AND).

| Query param | Isi |
|---|---|
| `status` | `pending` \| `lunas` \| `batal` \| `batal_sebagian` |
| `sumber` | `kasir` \| `self_order` |
| `metode_bayar` | `cash` \| `qris` |
| `ada_redeem` | `true` \| `false` — transaksi yang memakai poin |
| `cari` | Cocokkan ke `kode_pesanan`, nama customer, atau no WA customer (case-insensitive; nomor WA dicoba dalam ejaan lokal `0812…` maupun tersimpan `62812…`) |
| `total_min` / `total_max` | Rentang nilai transaksi |
| `dibuat_oleh` | Transaksi yang **disusun** akun ini |
| `dibayar_oleh` | Transaksi yang **diselesaikan** akun ini |
| `user_id` | Alias lama — diperlakukan sebagai `dibayar_oleh`, jatuh ke `user_id` untuk transaksi `pending`. Dipertahankan supaya kartu statistik kasir yang sudah ada tidak rusak. |

Nilai di luar daftar ditolak `422`, bukan diabaikan — daftar kosong yang muncul
gara-gara salah tulis param terlihat seperti "tidak ada transaksi hari ini".

#### Blok `meta` ringkasan (revisi 1 Agustus 2026)

`meta` pagination bawaan Laravel tetap ada, ditambah ringkasan **hasil
terfilter** — bukan seluruh tabel:

```json
"meta": {
  "current_page": 1, "per_page": 15, "total": 42,
  "jumlah_transaksi": 42,
  "total_omzet": 1250000,
  "total_qty": 87
}
```

Inilah yang membuat filter berguna buat manager: angkanya ikut berubah, bukan
cuma daftarnya.

> ⚠️ **Antrean pesanan `pending` tidak boleh difilter ke akun sendiri.** Saat
> pergantian akun, pesanan yang belum dibayar harus tetap terlihat oleh kasir
> yang baru login. Lihat [`laporan-kasir.md`](laporan-kasir.md) §3.

### GET /api/transaksi/{id} — detail

Response `200`:

```json
{
  "data": {
    "id": 1,
    "kode_pesanan": "#K001",
    "status": "pending",
    "sumber": "kasir",
    "sumber_label": "Kasir",
    "customer": { "id": 1, "nama": "Budi", "no_wa": "081234567890" },
    "kasir_pembuat": { "id": 2, "nama": "Kasir Satu" },
    "kasir_penyelesai": null,
    "kasir": { "id": 2, "nama": "Kasir Satu" },
    "items": [
      {
        "id": 1, "menu_id": 1, "nama": "Original",
        "rasa": "Soya Original Premium + Brown Sugar", "ukuran": "Reguler",
        "qty": 2, "harga_satuan": 17000, "subtotal": 34000, "is_reward": false,
        "level_sugar": "less", "level_sugar_label": "Less Sugar",
        "level_ice": "no", "level_ice_label": "No Ice",
        "sumber": "kasir", "platform": null,
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

**Revisi 1 Agustus 2026 — dua peran kasir:**

| Field              | Arti                                                                      |
| ------------------ | ------------------------------------------------------------------------- |
| `kasir_pembuat`    | Akun yang **menyusun** pesanan. `null` untuk pesanan SoyaScan.             |
| `kasir_penyelesai` | Akun yang **menyelesaikan pembayaran**. `null` selama masih `pending`.     |
| `kasir`            | Key lama, **tetap ada**: penyelesai bila ada, jatuh ke pembuat bila belum. |

Sebelum revisi ini, `bayar()` menimpa `user_id` dengan akun yang menandai lunas,
sehingga jejak kasir pembuat hilang saat pesanan menyeberangi pergantian akun.
Penjelasan lengkap: [`laporan-kasir.md`](laporan-kasir.md).

### POST /api/transaksi/{id}/items — tambah item

Body:

```json
{
  "menu_id": 1,
  "qty": 2,
  "platform": null,
  "catatan": "gelas terpisah",
  "level_sugar": "less",
  "level_ice": "no"
}
```

- `platform` / `catatan` / `level_sugar` / `level_ice` opsional (atribut level
  item). `sumber` di-set server = `kasir`.
- **Tanpa harga** — server snapshot `menu.harga` ke `harga_satuan`.
- Menu yang sama (non-reward) digabung **hanya kalau opsi peracikannya juga
  sama**. Dua gelas Original dengan level sugar berbeda adalah dua instruksi
  berbeda buat barista, jadi tetap dua baris.
- `level_sugar` / `level_ice` mengikuti aturan ketersediaan per ukuran yang sama
  dengan SoyaScan (lihat `kontrak-api-v1.md` §2) — kasir harus bisa mencatat hal
  yang sama seperti pelanggan. Opsi yang tidak relevan ditolak `422`
  `opsi_tidak_tersedia`.
- Error: `menu_tidak_tersedia` (422) untuk menu tak ada / nonaktif.
- ⚠️ `nomor_meja` **sudah tidak diterima lagi** (dihapus 1 Agustus 2026).

### PATCH /api/transaksi/{id}/items/{item} — ubah qty

Body: `{ "qty": 3 }` (≥ 1), boleh sekalian
`platform`/`catatan`/`level_sugar`/`level_ice`. Subtotal item & total transaksi
dihitung ulang.

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

**Revisi 1 Agustus 2026**, dua hal berubah di sini:

1. `dibayar_oleh` diisi akun pemanggil, dan **`user_id` TIDAK lagi ditimpa** —
   jejak kasir pembuat pesanan tetap utuh.
2. Transaksinya langsung **diproyeksikan ke `laporan_transaksi`** di dalam
   transaksi database yang sama, jadi dashboard dan export Excel ikut hidup di
   detik itu juga. Sengaja sinkron, bukan queued job: laporan harus bisa
   di-export real-time.

### POST /api/transaksi/{id}/batal — alias pembatalan penuh

Tanpa body. Set `status = batal`. `409` bila sudah `batal`.

**Revisi 1 Agustus 2026:** endpoint ini **tetap dipertahankan** supaya frontend
yang sudah jalan tidak rusak, tapi sekarang ikut melewati alur pembatalan baru —
mengembalikan poin redeem, mencatat dokumen pembatalan, dan menyinkronkan
proyeksi laporan. Ia juga menerima transaksi `lunas`, bukan hanya `pending`.

---

## Pembatalan / Koreksi Pesanan (revisi 1 Agustus 2026)

> **Ini pembatalan pesanan yang salah, BUKAN pengembalian uang.** Dokumentasi
> lengkap beserta rumus dan aturan poin: [`pembatalan-pesanan.md`](pembatalan-pesanan.md).

| Method & path                       | Role           | Keterangan                                    |
| ----------------------------------- | -------------- | --------------------------------------------- |
| `POST /api/transaksi/{id}/pembatalan` | kasir, manager | Penuh atau sebagian                           |
| `GET /api/transaksi/{id}/pembatalan`  | kasir, manager | Riwayat pembatalan transaksi itu              |
| `GET /api/pembatalan`                 | manager        | Semua pembatalan; filter tanggal & akun kasir |

Body `POST` — `items` kosong/tidak dikirim = pembatalan **penuh**:

```json
{
  "alasan": "Pelanggan salah pesan ukuran",
  "items": [{ "detail_transaksi_id": 12, "qty": 1 }]
}
```

Response `201` memuat rincian per item, `nilai_dibatalkan`, `poin_ditarik`,
`poin_dikembalikan`, `status_transaksi` setelahnya, dan `saldo_poin_pelanggan`
terkini — kasir perlu menyebutkannya ke pelanggan saat itu juga.

`GET /api/pembatalan` menerima `tanggal_mulai`/`tanggal_selesai`/`preset` (aturan
sama dengan daftar transaksi) plus `user_id` (akun yang **memproses**
pembatalan), dan mengembalikan `meta` berisi `jumlah_pembatalan`,
`nilai_dibatalkan`, `poin_ditarik`, `poin_dikembalikan`.

---

## Laporan Kasir & Pengaturan (revisi 1 Agustus 2026)

| Method & path                            | Role    | Keterangan                                          |
| ---------------------------------------- | ------- | --------------------------------------------------- |
| `GET /api/laporan/kasir`                 | manager | Perbandingan antar akun kasir                       |
| `GET /api/laporan/export?kasir_user_id=` | manager | Export Excel, opsional disaring ke satu kasir       |
| `POST /api/pengaturan/toko/qris`         | manager | Unggah/ganti gambar QRIS (`image`, jpg/png, maks 2 MB) |
| `DELETE /api/pengaturan/toko/qris`       | manager | Hapus gambar QRIS                                   |
| `GET /api/pengaturan/toko/qr-menu`       | manager | QR untuk ditempel di meja (`format=svg\|png`, `ukuran`) |

- `GET /api/pengaturan/toko` kini menyertakan `qris_url` (URL penuh, `null` kalau
  belum diunggah).
- `qr-menu` mengembalikan **berkas gambar** dengan `Content-Type` yang sesuai,
  bukan JSON base64 — supaya manager bisa langsung menyimpan/mencetak dari
  browser. Default `svg` karena akan dicetak dan harus tetap tajam; `ukuran`
  dibatasi 64–2048 px.
- Isi QR diambil dari `config('soyascan.url')` (env `SOYASCAN_URL`, fallback
  `APP_URL`) — **tidak** di-hardcode, karena QR yang sudah dicetak dan ditempel
  di meja tidak bisa ditarik lagi.

Isi respons laporan kasir dan sheet `Rekap Kasir`:
[`laporan-kasir.md`](laporan-kasir.md).

Dashboard juga menambah `?sembunyikan_tidak_diketahui=true` pada
`GET /api/dashboard/platform` dan `GET /api/dashboard/revenue-ukuran`, serta
`periode_label` + `hari` (berbahasa Indonesia) pada
`GET /api/dashboard/time-series`.

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

### Tambahan revisi 1 Agustus 2026

| Kode | HTTP | Sumber |
|---|---|---|
| `opsi_tidak_tersedia` | 422 | `level_sugar`/`level_ice` dikirim untuk ukuran yang tidak boleh memilihnya |
| `qty_pembatalan_melebihi` | 422 | Qty pembatalan melebihi sisa yang belum dibatalkan (dihitung lintas semua pembatalan) |
| `item_bukan_milik_transaksi` | 422 | `detail_transaksi_id` bukan bagian dari transaksi itu |
| `pembatalan_sebagian_butuh_lunas` | 422 | Pembatalan sebagian pada transaksi `pending` — pakai ubah/hapus item |
| `format_png_tidak_didukung` | 503 | QR PNG diminta tapi server tidak punya ekstensi GD |
