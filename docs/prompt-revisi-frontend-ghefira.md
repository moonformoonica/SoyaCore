# Prompt Revisi Frontend SoyaCore — Ghefira

> **Cara pakai.** Buka Claude Code di root repo SoyaCore, lalu:
>
> ```
> Kerjakan brief di @docs/prompt-revisi-frontend-ghefira.md
> ```
>
> **Sebelum mulai:**
>
> 1. `git pull` — backend revisi ini sudah selesai & dites (245 test lulus).
> 2. Buat branch baru: `git checkout -b revisi-pembimbing-frontend`.
> 3. `php artisan storage:link` — wajib, kalau tidak gambar QRIS 404 tanpa error.
>
> Rincian payload tiap endpoint ada di
> [`revisi-frontend-v13.md`](revisi-frontend-v13.md). Dokumen ini urutan kerjanya.

---

## 1. Batas tanggung jawab

Brief ini **hanya frontend**: `resources/views/`, `resources/js/`,
`resources/css/`. **Jangan menyentuh** `app/`, `database/`, `routes/api.php`,
`tests/` — semua itu sudah selesai dan sudah ada test-nya. Kalau ada endpoint
yang kelihatan kurang, **laporkan**, jangan tambal dari frontend.

`routes/web.php` boleh disentuh untuk satu hal saja: memperbaiki komentar
`// QR meja: /scan?meja=5` (Blok A).

Peta butir revisi pembimbing dan penanggung jawabnya:

| Butir revisi                                          | Status     | Penanggung jawab           |
| ----------------------------------------------------- | ---------- | -------------------------- |
| **SoyaScan**                                          |            |                            |
| Nomor meja dihilangkan                                | ⬜         | **Frontend — Blok A** ⚠️ rusak |
| Keterangan pilih sugar **dan ice** setelah pilih menu | ⬜         | **Frontend — Blok B**      |
| Tampilan QRIS saat pembayaran                         | ⬜         | **Frontend — Blok C**      |
| Sidebar tambah logo landing page                      | ⬜         | **Frontend — Blok D**      |
| QR untuk scan menu                                    | ⬜         | Backend selesai; UI di Blok H |
| **Manager**                                           |            |                            |
| Bar chart "other" dihilangkan                         | ⬜         | **Frontend — Blok E1**     |
| Tren penjualan: tanggal → hari                        | ⬜         | **Frontend — Blok E2**     |
| Edit menu: kiri cup, kanan botol                      | ⬜         | **Frontend — Blok F**      |
| Sidebar lawan arah                                    | ⬜         | **Frontend — Blok D**      |
| Laporan langsung berubah saat diklik                  | ⬜         | **Frontend — Blok E3**     |
| Bagian transaksi diatur tanggalnya                    | ⬜         | **Frontend — Blok G**      |
| Transaksi bisa memunculkan data yang ingin dilihat    | ⬜         | **Frontend — Blok G**      |
| Perbedaan data kasir 1 vs kasir 2 (per akun)          | ⬜         | **Frontend — Blok G + H**  |
| Keterangan pemesanan lewat kasir vs SoyaScan          | ⬜         | **Frontend — Blok G**      |
| Urutan poin loyalty termahal/termurah                 | ✅ selesai | —                          |
| Numerasi diskon & redeem poin                         | ✅ selesai | —                          |
| **Kasir**                                             |            |                            |
| Pembatalan/koreksi pesanan salah                      | ⬜         | **Blok I** — pemilik belum pasti |
| Pencarian nomor HP pelanggan                          | ⬜         | **Frontend — Blok J**      |

Urutan pengerjaan: **A → B → C → J → E → F → G → H → D → I**.
Blok A paling dulu karena **checkout SoyaScan sekarang rusak**.

---

## 2. Temuan kondisi frontend saat ini

Empat hal ini sudah diverifikasi langsung di kode. Salah paham di sini membuat
estimasinya jauh melenceng.

### T1. ⚠️ Checkout SoyaScan sudah rusak sekarang

`resources/js/scan/index.js:318` masih mewajibkan nomor meja:

```js
if (!nama || !nomorWa || !nomorMeja) return showCartError('Nama, nomor WhatsApp, dan nomor meja wajib diisi.');
```

Backend sudah menghapus `nomor_meja` sepenuhnya (termasuk kolomnya). Backend
masih **menerima** field itu kalau dikirim — nilainya diabaikan, bukan ditolak —
jadi tidak ada 422 dari server. **Yang memblokir checkout adalah validasi di
frontend sendiri.** Ini bukan fitur baru, ini perbaikan wajib.

### T2. Halaman transaksi manager praktis belum ada

`resources/js/manager/transaksi.js` cuma **36 baris** dan isinya **hanya widget
dropdown custom** — tidak ada satu pun `fetch`. Jadi Blok G bukan "tambah
filter", tapi "bangun halamannya".

### T3. Beberapa endpoint dashboard belum pernah dipanggil

Yang sudah dipanggil frontend: `dashboard/revenue-ukuran`, `dashboard/rfm`,
`dashboard/switch`, `laporan/export`.

Yang **belum pernah** dipanggil: `dashboard/ringkasan`, `dashboard/time-series`,
`dashboard/platform`, `dashboard/produk-terlaris`, `dashboard/meta`.

Artinya Blok E2 (tren penjualan pakai nama hari) adalah pekerjaan **membangun
chart-nya**, bukan sekadar mengganti label.

### T4. Urutan ukuran sudah benar dari API — jangan di-sort ulang

Backend sekarang mengembalikan `Hot → Reguler → Large → 250ml → 500ml → 1000ml`.
Sebelumnya alfabetis (`1000ml, 250ml, 500ml, Hot, Large, Reguler`), dan **itulah**
yang diminta pembimbing untuk diperbaiki. Kalau frontend me-`sort()` lagi,
kekacauan itu kembali.

`resources/js/manager/menu/create.js` punya `UKURAN_PRESET` sendiri (~baris 117) —
samakan urutannya.

---

## 3. Aturan kerja

- **Bahasa Indonesia** untuk seluruh komentar dan teks yang dilihat user.
  Komentar menjelaskan **kenapa**, bukan **apa**.
- **Jangan menyalin aturan bisnis ke frontend.** Backend sudah mengirim label &
  flag siap pakai. Yang disalin akan lepas sinkron, dan gejalanya adalah pilihan
  yang tampil di layar tapi ditolak `422`. Konkretnya:
    - daftar opsi sugar/ice → `meta.opsi_sugar` / `meta.opsi_ice`
    - menu mana boleh pilih apa → `bisa_pilih_sugar` / `bisa_pilih_ice`
    - label channel → `sumber_label` (bukan memetakan `self_order` sendiri)
    - label periode chart → `periode_label` / `hari`
    - golongan ukuran → `golongan_ukuran`
- **Jangan menormalkan nomor HP di frontend.** Backend sudah menanganinya. Kalau
  frontend ikut "membantu", pencarian 4 digit terakhir justru rusak.
- Format error backend seragam: `{"error": "kode_snake_case", "message": "teks"}`.
  **Tampilkan `message`-nya ke user** — pesannya sudah ditulis untuk dibaca orang,
  bukan developer. Jangan ganti dengan "Terjadi kesalahan".
- Uang selalu integer rupiah. Format tampilan pakai `toLocaleString('id-ID')`.
- Ikuti pola file yang sudah ada di folder yang sama; jangan menambah library
  atau build step baru tanpa alasan yang ditulis.

---

## BLOK A — ⚠️ SoyaScan: hapus nomor meja (KERJAKAN PALING DULU)

Lihat temuan **T1**.

**`resources/js/scan/index.js`** — empat titik:

| Baris | Sekarang                                                    | Lakukan                                              |
| ----- | ----------------------------------------------------------- | ---------------------------------------------------- |
| ~318  | `!nomorMeja` di kondisi validasi + pesan errornya            | Hapus dari kondisi **dan** dari teks pesannya         |
| ~332  | `nomor_meja: nomorMeja,` di body `POST /api/order`           | Hapus baris ini                                      |
| ~344  | `$('doneMeja').textContent = json.nomor_meja ?? nomorMeja;`  | Hapus — `json.nomor_meja` sekarang `undefined`        |
| ~383  | Prefill `?meja=5` dari query string QR                       | Hapus — QR dari backend tidak lagi berisi `?meja`     |

**`resources/views/scan/index.blade.php`** — hapus input nomor meja (`fMeja`)
beserta label/wrapper-nya, dan elemen `doneMeja` di layar sukses.

**`routes/web.php`** — perbaiki komentar `// QR meja: /scan?meja=5` jadi `/scan`.

**Selesai kalau:** pesan bisa di-checkout dari SoyaScan tanpa ada field meja di
layar, dan layar sukses tidak menampilkan slot kosong.

---

## BLOK B — SoyaScan: pilihan sugar & ice

### B1. Ambil daftar opsinya dari API

`GET /api/menu` sekarang mengirim `meta`:

```json
"meta": {
  "opsi_sugar": [
    { "kode": "normal", "label": "Normal" },
    { "kode": "less",   "label": "Less Sugar" },
    { "kode": "no",     "label": "No Sugar" },
    { "kode": "extra",  "label": "Extra Sugar" }
  ],
  "opsi_ice": [ { "kode": "normal", "label": "Normal" }, … ]
}
```

Render tombol/pilihan dari sini. **Jangan hardcode daftarnya.**

### B2. Tampilkan hanya yang relevan

Tiap menu membawa flag-nya sendiri — **jangan menebak dari string ukuran**:

```json
{ "id": 1, "nama": "Original", "ukuran": "Hot",
  "golongan_ukuran": "cup", "bisa_pilih_sugar": true, "bisa_pilih_ice": false }
```

- `bisa_pilih_sugar === false` → jangan tampilkan pilihan sugar
- `bisa_pilih_ice === false` → jangan tampilkan pilihan ice

Praktiknya: `Hot` sugar saja · `Reguler`/`Large` keduanya · `250ml`/`500ml`/`1000ml`
dan dessert tidak ada sama sekali.

Kalau opsi dikirim untuk menu yang tidak boleh, backend menolak `422`
`opsi_tidak_tersedia`. Kalau UI-nya benar mengikuti flag, error ini tidak akan
pernah muncul — jadi kalau ia muncul saat testing, berarti ada bug di UI.

### B3. Kirim per item

```json
"items": [
  { "menu_id": 1, "qty": 2, "level_sugar": "less", "level_ice": "no" },
  { "menu_id": 7, "qty": 1 }
]
```

Keduanya **opsional** — item tanpa pilihan tetap sah.

### B4. ⚠️ Keranjang: opsi berbeda = baris berbeda

Backend **tidak** menggabungkan Original *less sugar* dengan Original *normal* —
itu dua instruksi berbeda buat barista, dan menggabungkannya akan menghapus salah
satu permintaan pelanggan.

Keranjang di frontend harus ikut memakai **`menu_id` + `level_sugar` + `level_ice`**
sebagai kunci baris, bukan `menu_id` saja. Tombol `+`/`−` juga harus menyasar
kombinasi itu.

### B5. Layar sukses

Response order sudah memantulkan `level_sugar_label` / `level_ice_label` yang siap
tampil — pakai itu, jangan memetakan `"less"` → `"Less Sugar"` sendiri.

### B6. ❌ TIDAK ada field catatan/notes

Keputusan pemilik produk: **SoyaScan tidak punya input catatan per item.** Backend
tidak menyimpannya (ada test yang menjaga itu). Jangan menambahkannya. Permintaan
bebas pelanggan disampaikan ke kasir di konter.

---

## BLOK C — SoyaScan: tampilan QRIS saat pembayaran

Tombol QRIS sudah ada di `resources/views/scan/index.blade.php` (~baris 110);
yang belum ada layar penampil gambarnya.

Response `POST /api/order` menyertakan `qris_url` **hanya** saat
`metode_bayar: "qris"`. Tiga kondisi, semuanya harus ditangani:

| Kondisi                                       | Tampilan                                                            |
| --------------------------------------------- | ------------------------------------------------------------------- |
| `metode_bayar = "qris"`, `qris_url` = URL      | Tampilkan gambarnya besar & jelas di layar pembayaran                |
| `metode_bayar = "qris"`, `qris_url` = `null`   | Manager belum unggah → pesan "silakan bayar di kasir". **Jangan** `<img>` kosong |
| `metode_bayar = "cash"` / tidak dipilih        | Key `qris_url` **tidak ada** di response — jangan diakses buta        |

> QRIS ini **gambar statis merchant**. Tidak ada polling status, tidak ada
> callback. **Jangan** bangun UI yang menunggu konfirmasi pembayaran otomatis —
> pelanggan tetap menunjukkan bukti bayar ke kasir.

---

## BLOK D — Sidebar (murni tampilan)

1. **Arah sidebar dibalik** sesuai catatan pembimbing.
2. **Logo landing page** ditambahkan di sidebar SoyaScan.

Tidak ada endpoint terkait. Pastikan tetap rapi di layar mobile — SoyaScan
dipakai pelanggan dari HP.

---

## BLOK E — Dashboard & laporan manager

### E1. Bar chart tanpa bucket "other"

Nilai kosong dulu muncul sebagai batang tanpa nama. Backend sekarang memberinya
label eksplisit `"Tidak diketahui"` **dan** menyediakan param untuk membuangnya.

Tambahkan `?sembunyikan_tidak_diketahui=true` pada:

- `GET /api/dashboard/revenue-ukuran` → sudah dipanggil di
  `resources/js/manager/laporan/index.js:43`
- `GET /api/dashboard/platform` → belum dipanggil sama sekali (T3)

Param itu membuangnya dari hasil **dan** dari perhitungan persentase, jadi tidak
ada lagi selisih yang tidak bisa dijelaskan.

### E2. Tren penjualan: tanggal → nama hari

`GET /api/dashboard/time-series` (belum dipanggil frontend — lihat T3):

```json
{ "periode": "2026-07-27", "periode_label": "Sen, 27 Jul", "hari": "Senin",
  "revenue": 340000, "transaksi": 12, "qty": 18 }
```

- **`periode_label`** → label sumbu X. Sudah bahasa Indonesia dan menyesuaikan
  grain: `"Sen, 27 Jul"` (harian), `"28 Jul – 3 Agu"` (mingguan),
  `"Juli 2026"` (bulanan), `"2026"` (tahunan).
- **`hari`** → nama hari penuh (`"Senin"`), hanya untuk `grain=harian`, `null`
  untuk grain lain. Cocok untuk tooltip.
- **`periode`** → jangan dibuang; ini key stabil untuk sorting & `key` elemen.

Hari tanpa transaksi sekarang ikut keluar sebagai bucket bernilai **0**, jadi
chart tidak perlu mengisi celah sendiri lagi. Rentang yang sama sekali tidak punya
data tetap `data: []` dengan `data_tersedia: false` → pakai itu untuk empty state.

### E3. Laporan langsung berubah saat diklik

Endpointnya sudah ada sejak lama; ini murni menyambungkan filter/klik ke `fetch`
ulang lalu re-render. Tidak ada perubahan backend.

Semua endpoint dashboard menerima `?start=` & `?end=` (`Y-m-d`) dan `?grain=`
(`harian|mingguan|bulanan|tahunan`), plus `?by=qty|revenue` dan `?limit=` untuk
produk terlaris.

### E4. Export Excel per kasir

`laporan/index.js` sudah memanggil `laporan/export`. Tambahkan param opsional:

```
GET /api/laporan/export?kasir_user_id=3
```

Menyaring **seluruh sheet** ke satu kasir; namanya ikut ke nama file. Dua hal yang
perlu dijelaskan di UI:

- File berisi **7 sheet**; difilter satu kasir jadi **5 sheet** (RFM &
  Rekomendasi Switch dikeluarkan — keduanya analisis segmen pelanggan tanpa
  dimensi kasir, jadi kalau ikut, isinya data seluruh toko di file berjudul satu
  nama kasir).
- Sheet baru **`Rekap Kasir`** ada di posisi kedua, tepat setelah `Ringkasan`.

---

## BLOK F — Edit menu: cup kiri, botol kanan

Backend sudah memberi tahu golongan tiap ukuran supaya frontend tidak menebak dari
string. Dua cara, pilih yang paling pas:

1. **Satu request** — `GET /api/menu-internal` mengembalikan `golongan_ukuran`
   (`"cup"` | `"botol"` | `"lainnya"`) per menu, pisahkan di frontend.
2. **Dua request** — `?golongan=cup` dan `?golongan=botol`.

Perhatikan:

- `golongan_ukuran: "lainnya"` = Dessert & Cookies (`ukuran` string kosong).
  Tentukan mau ditaruh di kolom mana — **jangan sampai hilang dari layar.**
- **Jangan sort ulang** (T4).
- File: `resources/js/manager/menu/index.js`, `edit.js`, `create.js`.

---

## BLOK G — Halaman transaksi manager

Lihat temuan **T2** — halaman ini praktis dibangun dari awal.

### G1. Filter tanggal & urutan

| Param                              | Isi                                                             |
| ---------------------------------- | --------------------------------------------------------------- |
| `tanggal_mulai`, `tanggal_selesai` | `YYYY-MM-DD`, inklusif kedua ujung                              |
| `preset`                           | `hari_ini` \| `kemarin` \| `7_hari` \| `30_hari` \| `bulan_ini`  |
| `urut`                             | `terbaru` (default) \| `terlama`                                |

`tanggal_selesai < tanggal_mulai` ditolak `422` — validasi juga di UI supaya user
tidak perlu menunggu round-trip. Batas hari dihitung **WIB** oleh backend, jadi
frontend tidak perlu mengoreksi zona apa pun.

Sediakan tombol preset (Hari ini / Kemarin / 7 hari / 30 hari / Bulan ini) — itu
yang dipakai sehari-hari, date-picker manual untuk kasus khusus. Kalau keduanya
dikirim, batas eksplisit yang menang.

### G2. Filter "data yang ingin dilihat"

| Param                          | Isi                                                              |
| ------------------------------ | ---------------------------------------------------------------- |
| `status`                       | `pending` \| `lunas` \| `batal` \| `batal_sebagian`               |
| `sumber`                       | `kasir` \| `self_order`                                          |
| `metode_bayar`                 | `cash` \| `qris`                                                 |
| `ada_redeem`                   | `true` \| `false`                                                |
| `cari`                         | kode pesanan / nama / no WA — lihat Blok J                        |
| `total_min`, `total_max`       | rentang nilai transaksi                                          |
| `dibuat_oleh`, `dibayar_oleh`  | per akun kasir                                                   |
| `per_page`                     | maks 200                                                         |

Semua opsional & bisa digabung (AND). Nilai di luar daftar ditolak `422` — jadi
pakai `<select>` dengan nilai persis di atas, jangan input bebas.

### G3. Ringkasan yang ikut berubah

```json
"meta": {
  "current_page": 1, "per_page": 15, "total": 42,
  "jumlah_transaksi": 42, "total_omzet": 1250000, "total_qty": 87
}
```

`meta` berisi **dua hal**: paginasi Laravel + tiga field ringkasan hasil
terfilter. Tampilkan ringkasannya sebagai header di atas tabel — inilah yang
membuat filternya berguna buat manager, karena angkanya ikut berubah.

### G4. Kolom baru: channel & dua peran kasir

```json
{ "sumber": "self_order", "sumber_label": "SoyaScan",
  "kasir_pembuat":    { "id": 1, "nama": "Kasir Satu" },
  "kasir_penyelesai": { "id": 2, "nama": "Kasir Dua" },
  "kasir":            { "id": 2, "nama": "Kasir Dua" } }
```

- **`sumber_label`** → badge "Kasir" / "SoyaScan". Ini butir revisi "keterangan
  pemesanan lewat kasir". Jangan memetakan `self_order` sendiri.
- **`kasir_pembuat`** = yang menyusun pesanan (null untuk SoyaScan).
  **`kasir_penyelesai`** = yang menerima pembayaran (null selama pending).
- Tampilkan keduanya berdampingan **hanya kalau berbeda** — itu sinyal pesanan
  berpindah tangan saat pergantian akun. Kalau sama, satu kolom cukup.
- `kasir` (key lama) tetap ada dan artinya tidak berubah: penyelesai bila ada,
  jatuh ke pembuat bila belum dibayar.

### G5. ⚠️ Jebakan yang paling mahal

**JANGAN memfilter antrean pesanan `pending` ke akun yang sedang login.**

Kalau `?dibuat_oleh=` / `?user_id=` dipasang sebagai default di antrean pesanan,
Kasir 2 tidak akan menemukan pesanan Kasir 1 saat pergantian shift — dan pelanggan
terlantar di depan konter dengan minuman yang sudah dibuat, tanpa error apa pun
yang muncul.

Filter per-akun hanya boleh default di **kartu statistik**, bukan di antrean.

Kasir yang baru login **tidak perlu memasukkan ulang data apa pun**: cukup buka
pesanannya lalu Tandai Lunas. Pastikan UI-nya memang begitu, jangan meminta ulang
customer/item.

---

## BLOK H — Halaman baru manager

### H1. Laporan perbandingan kasir

`GET /api/laporan/kasir` (manager only). Param tanggal sama persis dengan G1 —
pakai komponen filter yang sama, jangan bikin parser tanggal kedua.

Satu baris per akun kasir, **sudah terurut omzet menurun dari backend**:

```
user_id, nama
jumlah_transaksi, total_omzet, total_qty, rata_rata_transaksi
rincian_metode_bayar : { cash: {jumlah, total}, qris: {jumlah, total} }
total_diskon, total_poin_diberikan, total_poin_ditukar
jumlah_pembatalan, nilai_dibatalkan
jumlah_transaksi_dibuat_kasir_lain
```

Plus `meta` berisi total seluruh kasir — tampilkan sebagai baris TOTAL.

Dua kolom yang gampang dianggap tidak penting tapi justru **inti permintaan
pembimbing** — jangan dipotong dari tabel:

- **`jumlah_transaksi_dibuat_kasir_lain`** — tanpa ini, laporan Kasir 2 terlihat
  seolah semua pesanan dia yang buat.
- **`jumlah_pembatalan`** — pembatalan berlebih dari satu akun adalah pola yang
  perlu terlihat.

> **Tidak ada UI shift** (buka/tutup shift, modal awal, hitung kas fisik) — memang
> tidak dibangun, sesuai keputusan pemilik produk. Jangan bikin placeholder-nya.

Arti tiap kolom: [`laporan-kasir.md`](laporan-kasir.md) §4–§5.

### H2. Pengaturan QRIS & QR menu

| Endpoint                                                      | Untuk                                       |
| ------------------------------------------------------------- | ------------------------------------------- |
| `POST /api/pengaturan/toko/qris` (multipart, field `qris`)      | Unggah/ganti gambar QRIS                    |
| `DELETE /api/pengaturan/toko/qris`                             | Hapus                                       |
| `GET /api/pengaturan/toko`                                     | Menyertakan `qris_url` (bisa `null`)         |
| `GET /api/pengaturan/toko/qr-menu?format=svg\|png&ukuran=512`  | QR untuk dicetak & ditempel di meja          |

- Validasi unggah: `jpg`/`jpeg`/`png`, maks **2 MB**. Non-gambar → `422`.
  Validasi juga di UI supaya user tidak menunggu upload gagal.
- Tampilkan preview QRIS yang tersimpan, plus tombol ganti & hapus.
- `qr-menu` mengembalikan **berkas gambar**, bukan JSON base64 → cukup
  `<img src="…">` plus tombol download/print. Default `svg` (tetap tajam saat
  dicetak), `ukuran` 64–2048 px.
- Keduanya **manager-only** → kasir dapat `403 tidak_berwenang`. Sembunyikan
  menunya untuk role kasir, jangan cuma mengandalkan error.

---

## BLOK I — Panel kasir: pembatalan pesanan

> ⚠️ **Pastikan dulu ini tugas siapa.** Dokumen repo tidak menyebut pemilik panel
> kasir (`resources/js/kasir/pesanan.js`). Tanyakan sebelum mengerjakan.

> **Ini pembatalan pesanan yang salah, BUKAN pengembalian uang.** Jangan pakai
> kata "refund" di UI, dan jangan bikin field metode pengembalian dana.

```
POST /api/transaksi/{id}/pembatalan
{ "alasan": "Pelanggan salah pesan ukuran",
  "items": [{ "detail_transaksi_id": 12, "qty": 1 }] }
```

- `items` kosong / tidak dikirim = pembatalan **penuh**
- **`alasan` wajib**, min 3 karakter. Ini satu-satunya pagar terhadap
  penyalahgunaan — **jangan diberi nilai default otomatis** dan jangan
  diprefill. Biarkan kasir menuliskannya.
- Pembatalan **sebagian hanya untuk transaksi `lunas`**. Untuk pesanan yang belum
  dibayar, arahkan ke ubah/hapus item yang sudah ada. Kalau dipaksa, backend
  menolak `422 pembatalan_sebagian_butuh_lunas`.

Response `201`:

```json
{ "data": { "nilai_dibatalkan": 16000, "poin_ditarik": 16, "poin_dikembalikan": 0 },
  "status_transaksi": "batal_sebagian",
  "saldo_poin_pelanggan": 84 }
```

**Tampilkan `saldo_poin_pelanggan` di layar konfirmasi.** Itu sebabnya field ini
ada di response — supaya kasir bisa menyebutkannya langsung ke pelanggan yang
masih berdiri di depan konter, tanpa membuka halaman lain. `null` = walk-in tanpa
customer.

Riwayat per transaksi: `GET /api/transaksi/{id}/pembatalan`.
Rekap seluruh toko (manager): `GET /api/pembatalan`.

Kode error yang perlu ditampilkan apa adanya: `qty_pembatalan_melebihi`,
`item_bukan_milik_transaksi`, `transaksi_sudah_batal` (409).
Aturan lengkap: [`pembatalan-pesanan.md`](pembatalan-pesanan.md).

**Juga di panel kasir:** input `level_sugar`/`level_ice` (aturan sama dengan
Blok B) di `POST /api/transaksi/{id}/items` dan `PATCH .../items/{item}`, dan
keduanya ikut tercetak di nota supaya barista membacanya. `nomor_meja` sudah
tidak diterima di payload item kasir.

---

## BLOK J — Pencarian nomor HP pelanggan

Backend **menjamin** satu kontrak yang sama, dan sudah ada test-nya: pelanggan
terdaftar ketemu baik dari **nomor lengkap** maupun dari **4 digit terakhir**.

| Yang diketik user            | Contoh                                                | Hasil  |
| ---------------------------- | ----------------------------------------------------- | ------ |
| Nomor lengkap, ejaan apa pun | `081234567890` · `0812-3456-7890` · `+62 812 3456 7890` | ketemu |
| **4 digit terakhir**         | `7890`                                                | ketemu |
| Potongan tengah              | `3456`                                                | ketemu |

⚠️ **Jangan menormalkan/membersihkan nomor di frontend sebelum dikirim.** Backend
sudah menanganinya. Kalau frontend menambahkan `62` di depan, potongan 4 digit
terakhir justru rusak.

### J1. `GET /api/customers/cari?no_wa=` — auto-detect di halaman Pesanan

```json
{ "data": [ { "id": 1, "nama": "Budi Santoso", "no_wa": "6281234567890", "poin": 400 } ] }
```

- **Kirim mulai 3 digit.** Di bawah itu backend mengembalikan `data: []` (bukan
  error) supaya 1 digit tidak menumpahkan seluruh daftar pelanggan. Untuk kondisi
  ini **jangan** tampilkan "tidak ditemukan" — tampilkan saja belum ada saran.
- **Hasilnya array, bukan satu objek.** Empat digit terakhir bisa cocok ke lebih
  dari satu pelanggan. **Render sebagai daftar pilihan, jangan auto-pilih
  `data[0]`** — salah pilih pelanggan berarti poin masuk ke akun orang lain.
- Nomor yang **persis sama selalu di urutan pertama**, jadi kalau kasir mengetik
  nomor lengkap, `data[0]` memang yang dimaksud.
- `data: []` = pelanggan baru. Itu **state normal** saat kasir masih mengetik —
  bukan error.
- Pencarian nama juga tersedia (`?nama=budi`, min 2 karakter, case-insensitive).
- Butuh auth. **Jangan dipanggil dari SoyaScan.**

### J2. `GET /api/transaksi?cari=` — riwayat transaksi pelanggan

Satu param `cari` mencocokkan **tiga hal sekaligus**: `kode_pesanan`, nama
customer, dan nomor WA. Jadi satu kotak pencarian cukup — user tidak perlu memilih
"cari berdasarkan apa" dulu.

```
?cari=7890            → riwayat pemilik nomor berakhiran 7890
?cari=081234567890    → sama, dari nomor lengkap
?cari=budi            → cocok ke nama
?cari=%23K001         → cocok ke kode pesanan (# di-encode)
```

`meta.jumlah_transaksi` / `total_omzet` / `total_qty` ikut menyempit — pakai untuk
header "N transaksi · Rp X". Bisa digabung filter lain:
`?cari=7890&status=lunas&urut=terlama`.

### J3. ⚠️ Yang TIDAK ikut aturan ini

`GET /api/loyalty/{nomorWa}` (cek saldo poin di SoyaScan) **tetap butuh nomor
lengkap dan persis.** Ini disengaja: endpoint itu publik tanpa login, jadi kalau 4
digit bisa dipakai, siapa pun bisa menebak-nebak lalu memanen nama + saldo poin
pelanggan lain.

Konsekuensinya di UI SoyaScan: minta nomor lengkap, dan tampilkan `404
pelanggan_tidak_ditemukan` sebagai "nomor belum terdaftar". **Jangan** bikin fitur
"cari nomor saya" dengan sebagian digit di SoyaScan.

---

## 4. Yang TIDAK perlu dikerjakan

Supaya tidak ada waktu terbuang membangun sesuatu yang sudah diputuskan di luar
lingkup:

- ❌ Input nomor meja di mana pun
- ❌ Field catatan/notes di SoyaScan (Blok B6)
- ❌ Pencarian nomor sebagian di SoyaScan (Blok J3)
- ❌ Normalisasi nomor HP di frontend (Blok J)
- ❌ UI shift kasir: buka/tutup shift, modal awal, hitung kas fisik, selisih laci
- ❌ Polling status pembayaran QRIS / integrasi payment gateway
- ❌ Live-push dashboard (WebSocket). Angka sudah benar saat halaman dimuat ulang;
  real-time push kebutuhan terpisah dan belum diminta
- ❌ Menyalin aturan bisnis (daftar opsi, aturan ukuran, label) ke frontend
- ❌ Menyentuh `app/`, `database/`, `routes/api.php`, `tests/`

---

## 5. Definition of done

- Blok A selesai dan checkout SoyaScan bisa dipakai dari HP tanpa field meja.
- Semua butir revisi pembimbing di tabel §1 yang bertanda **Frontend** sudah ⬜ → ✅.
- Tidak ada perubahan di `app/`, `database/`, `routes/api.php`, `tests/`
  (`routes/web.php` hanya untuk komentar QR di Blok A).
- `php artisan test` tetap **245 lulus** (1 kegagalan `ExampleTest` memang sudah
  ada sebelumnya: route `/` di-comment di `routes/web.php`). Kalau angka lulusnya
  turun, ada yang tersenggol — perbaiki, jangan diabaikan.
- Diuji di **layar HP** untuk SoyaScan, bukan cuma desktop.
- Tiap error dari backend menampilkan `message`-nya, bukan teks generik.
- Tulis ringkasan akhir: apa yang diubah per file, keputusan UI yang diambil, dan
  hal yang perlu dikonfirmasi ke Monica/pemilik produk.

---

## 6. Referensi

| Dokumen                                                        | Isi                                             |
| -------------------------------------------------------------- | ----------------------------------------------- |
| [`revisi-frontend-v13.md`](revisi-frontend-v13.md)             | Rincian payload & nomor baris tiap perubahan     |
| [`kontrak-api-v1.md`](kontrak-api-v1.md)                       | Kontrak SoyaScan (v1.3)                          |
| [`kontrak-api-kasir-v1-draft.md`](kontrak-api-kasir-v1-draft.md) | Endpoint kasir & manager, kontrak pencarian nomor |
| [`kontrak-dashboard-v1.md`](kontrak-dashboard-v1.md)           | Endpoint dashboard & laporan                     |
| [`laporan-kasir.md`](laporan-kasir.md)                         | Arti kolom laporan kasir & export                |
| [`pembatalan-pesanan.md`](pembatalan-pesanan.md)               | Aturan pembatalan & kode error                   |
| [`local-preview-setup.md`](local-preview-setup.md)             | Setup dev server & CORS                          |
