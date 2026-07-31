# Breakdown Pekerjaan Frontend — Revisi Pembimbing (kontrak v1.3)

> **Untuk: Ghefira** (SoyaScan + dashboard/halaman manager)
> **Backend: sudah selesai & dites** — 240 feature test lulus, branch
> `revisi-pembimbing-backend`.
>
> Dokumen ini menerjemahkan revisi backend menjadi daftar kerja frontend.
> Acuan payload lengkap tetap di [`kontrak-api-v1.md`](kontrak-api-v1.md) (SoyaScan)
> dan [`kontrak-api-kasir-v1-draft.md`](kontrak-api-kasir-v1-draft.md) (kasir & manager).

**Catatan pembagian tugas.** Dokumen repo menyebut Ghefira memegang SoyaScan
(`local-preview-setup.md` §"Untuk Ghefira") **dan** halaman manager
(`kontrak-dashboard-v1.md`). Bagian **§4 (Kasir)** saya pisahkan karena
kepemilikannya belum tertulis di mana pun — tolong dipastikan dulu siapa yang
mengerjakannya.

---

## Ringkasan prioritas

| Prio   | Apa                                             | Kenapa sekarang                                     |
| ------ | ----------------------------------------------- | --------------------------------------------------- |
| **P0** | SoyaScan: hapus nomor meja                      | **Checkout SoyaScan sudah rusak** sejak backend deploy |
| **P1** | SoyaScan: pilihan sugar & ice                   | Butir revisi pembimbing                             |
| **P1** | SoyaScan: tampilan QRIS saat bayar              | Butir revisi pembimbing                             |
| **P1** | Manager: bar chart tanpa "other"                | Butir revisi pembimbing                             |
| **P1** | Manager: tren penjualan pakai nama hari         | Butir revisi pembimbing                             |
| **P1** | Manager: edit menu cup kiri / botol kanan       | Butir revisi pembimbing                             |
| **P1** | Manager: sidebar arah + logo landing page       | Butir revisi pembimbing (murni frontend)            |
| **P1** | Kasir & manager: pencarian nomor HP pelanggan   | Backend sudah mendukung penuh + 4 digit terakhir    |
| **P2** | Manager: halaman transaksi (filter + ringkasan) | Endpoint baru, UI belum ada                         |
| **P2** | Manager: laporan perbandingan kasir             | Endpoint baru, halaman belum ada                    |
| **P2** | Manager: pengaturan QRIS & QR menu              | Endpoint baru, UI belum ada                         |
| **P2** | Manager: laporan langsung berubah saat diklik   | Endpoint sudah ada sejak lama                       |

---

## 1. ⚠️ P0 — SoyaScan: `nomor_meja` dihapus (BREAKING)

Ini **bukan fitur baru, ini perbaikan wajib**. Backend sudah menghapus
`nomor_meja` sepenuhnya, termasuk kolomnya di database. Checkout SoyaScan yang
sekarang akan **gagal validasi di sisi frontend sendiri** dan menampilkan field
yang sudah tidak berarti.

**File: `resources/js/scan/index.js`**

| Baris | Kondisi sekarang                                                        | Yang harus dilakukan                                       |
| ----- | ----------------------------------------------------------------------- | ---------------------------------------------------------- |
| ~318  | `if (!nama \|\| !nomorWa \|\| !nomorMeja) return showCartError('Nama, nomor WhatsApp, dan nomor meja wajib diisi.')` | Hapus `nomorMeja` dari kondisi **dan** dari pesan errornya |
| ~332  | `nomor_meja: nomorMeja,` di body `POST /api/order`                      | Hapus baris ini                                            |
| ~344  | `$('doneMeja').textContent = json.nomor_meja ?? nomorMeja;`             | Hapus — `json.nomor_meja` sekarang `undefined`              |
| ~383  | Prefill `?meja=5` dari QR                                               | Hapus — QR menu yang digenerate backend tidak lagi berisi `?meja` |

**File: `resources/views/scan/index.blade.php`** — hapus input nomor meja
(`fMeja`) dan elemen `doneMeja` di layar sukses.

**Juga: `routes/web.php`** masih berkomentar `// QR meja: /scan?meja=5` — tolong
diperbarui jadi `/scan` saja.

> Backend tetap menerima request yang masih mengirim `nomor_meja` (nilainya
> diabaikan, tidak ditolak), jadi tidak ada 422 dari sisi server. Yang memblokir
> checkout adalah **validasi di frontend sendiri** di baris ~318.

---

## 2. P1 — SoyaScan

### 2.1 Pilihan sugar & ice setelah pilih menu

**Jangan menyalin daftar opsinya ke frontend.** `GET /api/menu` sudah
mengirimkannya di `meta`:

```json
"meta": {
  "opsi_sugar": [
    { "kode": "normal", "label": "Normal" },
    { "kode": "less",   "label": "Less Sugar" },
    { "kode": "no",     "label": "No Sugar" },
    { "kode": "extra",  "label": "Extra Sugar" }
  ],
  "opsi_ice": [
    { "kode": "normal", "label": "Normal" },
    { "kode": "less",   "label": "Less Ice" },
    { "kode": "no",     "label": "No Ice" },
    { "kode": "extra",  "label": "Extra Ice" }
  ]
}
```

**Jangan pula menebak menu mana yang boleh memilih apa.** Tiap menu sudah
membawa flag-nya sendiri:

```json
{ "id": 1, "nama": "Original", "ukuran": "Hot",
  "golongan_ukuran": "cup", "bisa_pilih_sugar": true, "bisa_pilih_ice": false }
```

Aturan render:

- `bisa_pilih_sugar === false` → **jangan tampilkan** pilihan sugar
- `bisa_pilih_ice === false` → **jangan tampilkan** pilihan ice
- Praktiknya: `Hot` → sugar saja · `Reguler`/`Large` → keduanya ·
  `250ml`/`500ml`/`1000ml` dan dessert → tidak ada sama sekali

Kirim per item di `POST /api/order`:

```json
"items": [
  { "menu_id": 1, "qty": 2, "level_sugar": "less", "level_ice": "no" },
  { "menu_id": 7, "qty": 1 }
]
```

Keduanya **opsional** — item tanpa pilihan tetap sah. Response order memantulkan
`level_sugar_label` / `level_ice_label` yang siap tampil, jadi layar sukses tidak
perlu memetakan `"less"` → `"Less Sugar"` sendiri.

⚠️ **Kalau ini dikirim untuk menu yang tidak boleh**, backend menolak `422`
`opsi_tidak_tersedia` — sengaja tidak diabaikan diam-diam, supaya salah kirim
kelihatan. Jadi kalau UI-nya sudah benar mengikuti flag, error ini tidak akan
pernah muncul.

**Keranjang: item yang sama dengan opsi berbeda adalah dua baris terpisah.**
Backend tidak menggabungkan Original *less sugar* dengan Original *normal* —
itu dua instruksi berbeda buat barista. Keranjang di frontend perlu ikut
memperlakukan `menu_id + level_sugar + level_ice` sebagai kunci baris, bukan
`menu_id` saja.

### 2.2 ❌ Tidak ada fitur notes/catatan

Sesuai keputusan pemilik produk, **SoyaScan tidak punya field catatan per item.**
Backend tidak menerimanya (kalau dikirim, diabaikan dan tidak disimpan), dan ada
test yang menjaga itu tetap begitu. Jangan menambahkan input catatan di SoyaScan.

Permintaan bebas pelanggan disampaikan langsung ke kasir di konter — kasir punya
field `catatan` sendiri.

### 2.3 Tampilan QRIS saat pembayaran

Response `POST /api/order` menyertakan `qris_url` **hanya** ketika pelanggan
memilih `metode_bayar: "qris"`:

```json
{ "kode_pesanan": "#A05", "metode_bayar": "qris",
  "qris_url": "https://.../storage/qris/abc123.png" }
```

Tiga kondisi yang harus ditangani:

| Kondisi                        | Tampilan                                                    |
| ------------------------------ | ----------------------------------------------------------- |
| `metode_bayar = "qris"`, `qris_url` berisi URL | Tampilkan gambarnya di layar pembayaran      |
| `metode_bayar = "qris"`, `qris_url` = `null`   | Manager belum unggah QRIS → tampilkan pesan "bayar di kasir", **jangan** `<img>` kosong |
| `metode_bayar = "cash"` atau tidak dipilih     | Key `qris_url` **tidak ada** di response — jangan diakses buta |

Tombol QRIS-nya sudah ada di `resources/views/scan/index.blade.php` (~baris 110);
yang belum ada layar penampil gambarnya.

> QRIS ini **gambar statis merchant**. Tidak ada polling status pembayaran, tidak
> ada callback — jangan bangun UI yang menunggu konfirmasi otomatis.

### 2.4 Sidebar: tambah logo landing page

Murni frontend, tidak ada endpoint terkait.

---

## 2b. P1 — Pencarian nomor HP pelanggan (kasir & manager)

Backend sekarang **menjamin** satu kontrak yang sama di semua pencarian nomor:
pelanggan terdaftar ketemu baik dari **nomor lengkap** maupun dari **4 digit
terakhir**. Ini sudah ada test-nya, jadi UI boleh mengandalkannya sepenuhnya.

| Yang diketik user | Contoh | Hasil |
| ----------------- | ------ | ----- |
| Nomor lengkap, ejaan apa pun | `081234567890` · `0812-3456-7890` · `+62 812 3456 7890` · `6281234567890` | ketemu |
| **4 digit terakhir** | `7890` | ketemu |
| Potongan tengah | `3456` | ketemu |

Berlaku di dua endpoint:

### 2b.1 `GET /api/customers/cari?no_wa=` — auto-detect pelanggan (halaman Pesanan)

```json
{ "data": [ { "id": 1, "nama": "Budi Santoso", "no_wa": "6281234567890", "poin": 400 } ] }
```

Yang perlu diperhatikan di UI:

- **Minimal 3 digit** sebelum request dikirim. Di bawah itu backend mengembalikan
  `data: []` (bukan error) — sengaja, supaya mengetik 1 digit tidak menumpahkan
  seluruh daftar pelanggan. Jangan tampilkan "tidak ditemukan" untuk kondisi ini,
  tampilkan saja belum ada saran.
- **Hasilnya array, bukan satu objek.** Empat digit terakhir bisa cocok ke lebih
  dari satu pelanggan (`…7890` dan `…17890`). Render sebagai daftar pilihan, jangan
  auto-pilih `data[0]` — salah pilih pelanggan berarti poin masuk ke akun orang lain.
- **Nomor yang persis sama selalu di urutan pertama.** Jadi kalau kasir mengetik
  nomor lengkap, `data[0]` memang yang dimaksud.
- `data: []` = pelanggan baru. Itu **state normal** saat kasir masih mengetik,
  bukan error — jangan tampilkan pesan gagal.
- `nama` juga bisa dipakai (`?nama=budi`), min 2 karakter, dan sekarang
  **case-insensitive**.
- Butuh auth (kasir/manager). Jangan dipanggil dari SoyaScan.

### 2b.2 `GET /api/transaksi?cari=` — riwayat transaksi pelanggan

Param `cari` yang sama mencocokkan **tiga hal sekaligus**: `kode_pesanan`, nama
customer, dan nomor WA customer. Jadi satu kotak pencarian cukup — user tidak
perlu memilih dulu "cari berdasarkan apa".

```
GET /api/transaksi?cari=7890          → riwayat transaksi pemilik nomor itu
GET /api/transaksi?cari=081234567890  → sama, dari nomor lengkap
GET /api/transaksi?cari=budi          → cocok ke nama
GET /api/transaksi?cari=%23K001       → cocok ke kode pesanan (# di-encode)
```

`meta.jumlah_transaksi` / `total_omzet` / `total_qty` ikut menyempit sesuai hasil
pencarian — pakai itu untuk header "N transaksi · Rp X" di atas daftar.

Gabungkan dengan filter lain kalau perlu, mis. riwayat pelanggan yang lunas saja:
`?cari=7890&status=lunas&urut=terlama`.

### 2b.3 ⚠️ Yang TIDAK berlaku: `GET /api/loyalty/{nomorWa}`

Endpoint cek saldo poin di SoyaScan **tetap butuh nomor lengkap dan persis.**
Ini disengaja: endpoint itu publik tanpa login, jadi kalau 4 digit bisa dipakai,
siapa pun bisa menebak-nebak dan memanen nama + saldo poin pelanggan lain.

Konsekuensi untuk UI SoyaScan: input cek poin harus meminta nomor lengkap, dan
`404 pelanggan_tidak_ditemukan` ditampilkan sebagai "nomor belum terdaftar" —
jangan bikin fitur "cari nomor saya" dengan sebagian digit di sana.

---

## 3. P1/P2 — Halaman Manager

### 3.1 Bar chart tanpa bucket "other" (P1)

Sumbernya dulu: nilai `NULL`/kosong ikut jadi batang tanpa nama. Sekarang backend
memberinya label eksplisit `"Tidak diketahui"`, dan menyediakan param untuk
membuangnya.

Tambahkan `?sembunyikan_tidak_diketahui=true` pada:

- `GET /api/dashboard/revenue-ukuran` → sudah dipanggil di
  `resources/js/manager/laporan/index.js:43`
- `GET /api/dashboard/platform` → **belum dipanggil frontend sama sekali**

Param itu membuang bucket tersebut dari hasil **dan** dari perhitungan
persentasenya, jadi tidak ada lagi selisih yang tidak bisa dijelaskan.

> Datanya tidak dihapus dari database — ini keputusan tampilan. Kalau nanti ada
> yang perlu merekonsiliasi total, panggil tanpa param itu.

### 3.2 Tren penjualan: tanggal → hari (P1)

`GET /api/dashboard/time-series` sekarang mengirim tiga field:

```json
{ "periode": "2026-07-27", "periode_label": "Sen, 27 Jul", "hari": "Senin",
  "revenue": 340000, "transaksi": 12, "qty": 18 }
```

- **`periode_label`** → pakai ini untuk label sumbu X. Sudah berbahasa Indonesia:
  `"Sen, 27 Jul"` (harian), `"28 Jul – 3 Agu"` (mingguan), `"Juli 2026"` (bulanan),
  `"2026"` (tahunan).
- **`hari`** → nama hari penuh (`"Senin"`), **hanya** untuk `grain=harian`;
  `null` untuk grain lain. Cocok untuk tooltip.
- **`periode`** → jangan dihapus, ini key stabil untuk sorting/`key` komponen.

Hari tanpa transaksi sekarang **ikut keluar sebagai bucket bernilai 0**, jadi
grafik tidak lagi menyambung dua titik berjauhan dan membaca naik-turun yang tidak
terjadi. Chart-nya tidak perlu mengisi celah sendiri lagi.

> Rentang yang **sama sekali** tidak punya data tetap mengembalikan `data: []`
> dengan `data_tersedia: false` — pakai itu untuk empty state.

⚠️ Endpoint ini **belum dipanggil frontend sama sekali** — jadi ini pekerjaan
"bangun chart-nya", bukan sekadar ganti label.

### 3.3 Edit menu: cup kiri, botol kanan (P1)

Backend sudah memberi tahu golongan tiap ukuran, supaya frontend tidak menebak
dari string.

Dua cara, pilih yang paling pas dengan struktur halaman:

1. **Satu request, pisah di frontend** — `GET /api/menu-internal` sekarang
   mengembalikan `golongan_ukuran` (`"cup"` | `"botol"` | `"lainnya"`) per menu.
2. **Dua request** — `GET /api/menu-internal?golongan=cup` dan `?golongan=botol`.

**Urutan ukuran sudah diperbaiki di backend** menjadi
`Hot → Reguler → Large → 250ml → 500ml → 1000ml`. Sebelumnya alfabetis
(`1000ml, 250ml, 500ml, Hot, Large, Reguler`). **Jangan di-sort ulang di
frontend** — urutan dari API sudah benar, me-sort lagi akan mengembalikan
kekacauan itu.

Perhatikan `golongan_ukuran: "lainnya"` (Dessert & Cookies, `ukuran` string
kosong) — tentukan mau ditaruh di kolom mana, jangan sampai hilang dari layar.

File terkait: `resources/js/manager/menu/index.js`, `edit.js`, `create.js`.
`create.js` punya `UKURAN_PRESET` sendiri (~baris 117) — pastikan urutannya
disamakan.

### 3.4 Sidebar lawan arah (P1)

Murni frontend/CSS.

### 3.5 Halaman transaksi: filter + ringkasan (P2)

Saat ini `resources/js/manager/transaksi.js` (36 baris) **hanya berisi widget
dropdown** — belum ada satu pun panggilan API. Jadi halaman transaksi manager
praktis harus dibangun.

`GET /api/transaksi` sekarang menerima:

| Param                          | Isi                                                                 |
| ------------------------------ | ------------------------------------------------------------------- |
| `tanggal_mulai`, `tanggal_selesai` | `YYYY-MM-DD`, inklusif kedua ujung                              |
| `preset`                       | `hari_ini` \| `kemarin` \| `7_hari` \| `30_hari` \| `bulan_ini`      |
| `urut`                         | `terbaru` (default) \| `terlama`                                     |
| `status`                       | `pending` \| `lunas` \| `batal` \| `batal_sebagian`                  |
| `sumber`                       | `kasir` \| `self_order`                                              |
| `metode_bayar`                 | `cash` \| `qris`                                                     |
| `ada_redeem`                   | `true` \| `false`                                                    |
| `cari`                         | kode pesanan / nama customer / no WA — **nomor lengkap maupun 4 digit terakhir**, lihat §2b.2 |
| `total_min`, `total_max`       | rentang nilai transaksi                                              |
| `dibuat_oleh`, `dibayar_oleh`  | per akun kasir                                                       |
| `per_page`                     | maks 200                                                             |

Semua opsional dan bisa digabung. `tanggal` yang lama tetap didukung.

**Ringkasan ikut berubah saat difilter** — ini yang membuat filternya berguna:

```json
"meta": {
  "current_page": 1, "per_page": 15, "total": 42,
  "jumlah_transaksi": 42, "total_omzet": 1250000, "total_qty": 87
}
```

Perhatikan `meta` berisi **dua hal sekaligus**: paginasi bawaan Laravel + tiga
field ringkasan. `total` = jumlah baris (paginasi), `jumlah_transaksi` = hasil
terfilter. Keduanya sama isinya di sini, tapi jangan tertukar maknanya.

**Kolom baru yang layak ditampilkan:**

```json
{ "sumber": "self_order", "sumber_label": "SoyaScan",
  "kasir_pembuat":    { "id": 1, "nama": "Kasir Satu" },
  "kasir_penyelesai": { "id": 2, "nama": "Kasir Dua" },
  "kasir":            { "id": 2, "nama": "Kasir Dua" } }
```

- `sumber_label` sudah siap tampil (`"Kasir"` / `"SoyaScan"`) — **jangan** memetakan
  `self_order` sendiri, nanti ejaannya beda-beda antar halaman.
- `kasir` (key lama) **tetap ada dan tidak berubah artinya** untuk kode yang sudah
  jalan: penyelesai bila ada, jatuh ke pembuat bila belum dibayar.
- Tampilkan `kasir_pembuat` vs `kasir_penyelesai` berdampingan hanya kalau
  keduanya berbeda — itu sinyal pesanan berpindah tangan saat pergantian akun.

> ⚠️ **JANGAN memfilter antrean pesanan `pending` ke akun sendiri.** Kalau
> `?user_id=` / `?dibuat_oleh=` dipasang sebagai default di antrean, Kasir 2 tidak
> akan menemukan pesanan Kasir 1 dan pelanggan terlantar di konter dengan minuman
> yang sudah dibuat. Filter per-akun hanya boleh default di **kartu statistik**.
> Detail: [`laporan-kasir.md`](laporan-kasir.md) §3.

### 3.6 Laporan perbandingan kasir — halaman baru (P2)

`GET /api/laporan/kasir` (manager only). Param tanggal sama persis dengan §3.5.

Satu baris per akun kasir, sudah **terurut omzet menurun** dari backend:

```
user_id, nama
jumlah_transaksi, total_omzet, total_qty, rata_rata_transaksi
rincian_metode_bayar: { cash: {jumlah, total}, qris: {jumlah, total} }
total_diskon, total_poin_diberikan, total_poin_ditukar
jumlah_pembatalan, nilai_dibatalkan
jumlah_transaksi_dibuat_kasir_lain
```

Plus `meta` berisi total seluruh kasir.

Dua kolom yang paling gampang dianggap tidak penting tapi justru inti permintaan
pembimbing — tolong jangan dipotong dari tabel:

- **`jumlah_transaksi_dibuat_kasir_lain`** — tanpa ini, laporan Kasir 2 terlihat
  seolah semua pesanan dia yang buat.
- **`jumlah_pembatalan`** — pembatalan berlebih dari satu akun adalah pola yang
  perlu terlihat.

> Tidak ada UI shift (buka/tutup shift, modal awal, hitung kas) — memang tidak
> dibangun, sesuai keputusan pemilik produk. Jangan bikin placeholder-nya.

Arti tiap kolom & kenapa `nilai_dibatalkan` bisa beda dari pengurang omzet:
[`laporan-kasir.md`](laporan-kasir.md) §4–§5.

### 3.7 Export Excel per kasir (P2)

`resources/js/manager/laporan/index.js` sudah memanggil `laporan/export`. Cukup
tambahkan param opsional:

```
GET /api/laporan/export?kasir_user_id=3
```

Menyaring **seluruh sheet** ke satu kasir, dan namanya ikut ke nama file. Tanpa
param itu, semua kasir ikut seperti sekarang.

Dua hal yang perlu dijelaskan di UI-nya:

- File berisi **7 sheet**; kalau difilter satu kasir jadi **5 sheet** (RFM &
  Rekomendasi Switch dikeluarkan karena keduanya analisis segmen pelanggan tanpa
  dimensi kasir — kalau ikut, isinya data seluruh toko di file yang berjudul satu
  nama kasir).
- Sheet baru **`Rekap Kasir`** ada di posisi kedua, tepat setelah `Ringkasan`.

### 3.8 Pengaturan: QRIS & QR menu (P2)

| Endpoint                                                     | Untuk                                        |
| ------------------------------------------------------------ | -------------------------------------------- |
| `POST /api/pengaturan/toko/qris` (multipart, field `qris`)    | Unggah/ganti gambar QRIS                     |
| `DELETE /api/pengaturan/toko/qris`                            | Hapus                                        |
| `GET /api/pengaturan/toko`                                    | Sekarang menyertakan `qris_url` (bisa `null`) |
| `GET /api/pengaturan/toko/qr-menu?format=svg\|png&ukuran=512` | QR untuk dicetak & ditempel di meja           |

- Validasi unggah: `jpg`/`jpeg`/`png`, maks **2 MB**. Non-gambar → `422`.
- `qr-menu` mengembalikan **berkas gambar**, bukan JSON base64 — jadi cukup
  `<img src="...">` atau link download/print. Default `svg` (tetap tajam saat
  dicetak); `ukuran` 64–2048 px.
- Keduanya **manager-only** → kasir dapat `403 tidak_berwenang`. Sembunyikan menunya
  untuk role kasir.

### 3.9 Laporan langsung berubah saat diklik (P2)

Endpointnya sudah ada sejak lama — ini murni menyambungkan filter/klik ke
`fetch` ulang. Tidak ada perubahan backend untuk butir ini.

---

## 4. Halaman Kasir — kepemilikan perlu dipastikan

Backend-nya siap, tapi belum jelas siapa yang mengerjakan UI-nya
(`resources/js/kasir/pesanan.js` sudah ada).

### 4.1 Pembatalan / koreksi pesanan

> **Ini pembatalan pesanan yang salah, BUKAN pengembalian uang.** Jangan pakai
> kata "refund" di UI, dan jangan bikin field metode pengembalian dana.

```
POST /api/transaksi/{id}/pembatalan
{ "alasan": "Pelanggan salah pesan ukuran",
  "items": [{ "detail_transaksi_id": 12, "qty": 1 }] }
```

- `items` kosong / tidak dikirim = pembatalan **penuh**
- **`alasan` wajib**, minimal 3 karakter — ini satu-satunya pagar terhadap
  penyalahgunaan, jadi jangan diberi nilai default otomatis
- Pembatalan **sebagian hanya untuk transaksi `lunas`**. Untuk pesanan yang belum
  dibayar, arahkan ke ubah/hapus item yang sudah ada — backend menolak `422`
  `pembatalan_sebagian_butuh_lunas` kalau dipaksa

Response `201` memuat yang perlu langsung diucapkan ke pelanggan:

```json
{ "data": { "nilai_dibatalkan": 16000, "poin_ditarik": 16, "poin_dikembalikan": 0 },
  "status_transaksi": "batal_sebagian",
  "saldo_poin_pelanggan": 84 }
```

Tampilkan `saldo_poin_pelanggan` di layar konfirmasi — itu sebabnya field ini ada
di response, supaya kasir tidak perlu membuka halaman lain sementara pelanggannya
masih berdiri di depan konter. Isinya `null` untuk transaksi walk-in.

Riwayat: `GET /api/transaksi/{id}/pembatalan`.
Aturan lengkap & daftar kode error: [`pembatalan-pesanan.md`](pembatalan-pesanan.md).

### 4.2 Input sugar & ice di panel kasir

Aturan dan flag-nya **sama persis** dengan SoyaScan (§2.1) — kasir harus bisa
mencatat hal yang sama seperti pelanggan. Kirim `level_sugar` / `level_ice` di
`POST /api/transaksi/{id}/items` dan `PATCH .../items/{item}`.

`GET /api/transaksi/{id}` memantulkannya beserta label siap tampil, dan keduanya
perlu ikut tercetak di nota supaya barista membacanya.

⚠️ `nomor_meja` **sudah tidak diterima** di payload item kasir juga.

---

## 5. Yang TIDAK perlu dikerjakan

Supaya tidak ada yang membangun sesuatu yang sudah diputuskan di luar lingkup:

- ❌ Field catatan/notes di SoyaScan (§2.2)
- ❌ Pencarian nomor sebagian di SoyaScan (`/api/loyalty/{nomorWa}`) — §2b.3
- ❌ Normalisasi/pembersihan nomor di frontend sebelum dikirim. Backend sudah
  menormalkan `0812…`/`+62…`/`812…` ke `62…`; kalau frontend ikut "membantu",
  potongan 4 digit terakhir justru rusak (`8122` jadi `628122` yang tidak cocok)
- ❌ UI shift kasir: buka/tutup shift, modal awal, hitung kas fisik, selisih laci
- ❌ Polling status pembayaran QRIS / callback payment gateway
- ❌ Live-push dashboard (WebSocket). Angka benar saat halaman dimuat ulang;
  real-time push adalah kebutuhan terpisah dan belum diminta
- ❌ Input nomor meja di mana pun

---

## 6. Sebelum mulai

1. Backend ada di branch `revisi-pembimbing-backend` — pastikan sudah di-merge/deploy
   sebelum menguji, terutama karena §1 bergantung padanya.
2. **`php artisan storage:link` wajib dijalankan** di environment yang dipakai,
   kalau tidak `qris_url` mengembalikan 404 tanpa error apa pun di backend.
3. Set `SOYASCAN_URL` di `.env` sebelum QR menu dicetak — QR yang sudah ditempel
   di meja tidak bisa ditarik lagi.
4. Setup dev/CORS: [`local-preview-setup.md`](local-preview-setup.md).
