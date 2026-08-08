# Kontrak API Dashboard & Laporan SoyaCore, v1

> **Status: v1, 16 Juli 2026. Direvisi v1.1, 8 Agustus 2026 (revisi
> dashboard dari review pembimbing).** Kontrak integrasi untuk frontend
> dashboard manager (Ghefira). Semua endpoint bersifat **read-only reporting**.
>
> **Revisi v1.1, yang berubah untuk frontend:**
>
> 1. **Grain baru `hari_dalam_minggu`** di `/time-series`, mengagregasi per nama
>    hari untuk menjawab "hari apa yang paling ramai" (lihat §2).
> 2. **Bucket kosong kini DIISI bernilai 0**, tidak lagi di-skip (§2).
> 3. **Field `periode_label` dan `hari`** di tiap bucket time-series, teks siap
>    tampil Bahasa Indonesia (§2).
> 4. ⚠️ **Urutan `data` di `/revenue-ukuran` berubah** jadi golongan dulu lalu
>    jumlah terjual, plus field `golongan`, `persen_dari_golongan`, dan blok
>    `ringkasan_golongan` (§3).
> 5. **Param `arah` dan `sertakan_nol`** di `/produk-terlaris`. Grafik "10 Menu
>    Kurang Diminati" harus memakai `?arah=terendah` dan **merender hasilnya apa
>    adanya**, jangan dibalik lagi di frontend (§4).
> 6. **Nilai `platform` sudah dinormalkan** di server, `qris`/`cash` huruf kecil
>    tidak akan muncul lagi (§5).
> 7. **Blok `segmen_treatment`** di `/rfm`, pola penanganan tiap segmen (§7).
> 8. **Sheet baru "Segmen & Treatment"** di export, total jadi 8 sheet (§9).
>
> Data dihitung dari layer reporting `laporan_transaksi`, yang kini memuat baris
> impor CSV historis **dan** proyeksi transaksi POS yang ditulis begitu kasir
> menandai lunas, sehingga angkanya ikut bergerak real-time.

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
> endpoint manager-only, hasilnya cuma layar kosong dengan error 403.
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
| `grain` | `harian` \| `mingguan` \| `bulanan` \| `tahunan` \| `hari_dalam_minggu` | `harian` | Ukuran bucket time-series |
| `start` | `YYYY-MM-DD` | tanggal_min data | Awal window (inklusif) |
| `end` | `YYYY-MM-DD` | tanggal_max data | Akhir window (inklusif) |
| `sembunyikan_tidak_diketahui` | `true` \| `false` | `false` | Buang bucket tak berlabel dari chart |

**Validasi** (gagal → `422 { "error": "validasi_gagal", "message": "...", "details": {...} }`):

- `grain` harus salah satu nilai di atas.
- `start`/`end` harus format `YYYY-MM-DD`.
- `end` tidak boleh lebih awal dari `start`.
- Minggu memakai standar ISO (Senin–Minggu).

## Kontrak Envelope & Empty-State (endpoint 1–6)

Setiap endpoint yang bisa difilter tanggal **selalu** balas `200`, termasuk
window tanpa data (mis. Agustus 2026), dengan envelope konsisten:

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

Deret waktu di-bucket sesuai `grain`, urut ascending.

**Bucket kosong sekarang DIISI, tidak lagi di-skip.** Hari tanpa transaksi
dikeluarkan sebagai bucket bernilai 0. Grafik yang melompati hari kosong
menyambung dua titik yang berjauhan dan membaca naik-turun yang tidak pernah
terjadi. Pengecualiannya: rentang yang sama sekali tidak punya transaksi tetap
mengembalikan `[]`, karena `data_tersedia: false` sudah menyampaikannya.

**Contoh `?grain=bulanan`:**

```json
{
  "periode": { "grain": "bulanan", "start": "2026-06-01", "end": "2026-07-30" },
  "data_tersedia": true,
  "data": [
    { "periode": "2026-06", "periode_label": "Juni 2026", "hari": null, "revenue": 12831000, "transaksi": 419, "qty": 526 },
    { "periode": "2026-07", "periode_label": "Juli 2026", "hari": null, "revenue": 13426000, "transaksi": 463, "qty": 552 }
  ]
}
```

Format field `periode` per grain: `harian` → `YYYY-MM-DD`, `mingguan` →
tanggal Senin awal minggu (`YYYY-MM-DD`), `bulanan` → `YYYY-MM`, `tahunan` → `YYYY`.

### Label siap tampil

`periode` (key mentah) tetap ada dan dipakai sorting serta key stabil di
frontend. Dua field label ditambahkan di sebelahnya:

| Field | Isi | Contoh |
|---|---|---|
| `periode_label` | Teks siap tampil, Bahasa Indonesia | `Sen, 28 Jul` · `28 Jul – 3 Agu` · `Juli 2026` · `2026` |
| `hari` | Nama hari penuh, **hanya untuk grain `harian`**, `null` untuk grain lain | `Senin` |

Nama hari dan bulan di-hardcode di server, tidak memakai locale sistem: locale
Indonesia sering tidak terpasang di container produksi, dan kalau tidak ada,
hasilnya diam-diam kembali ke bahasa Inggris tanpa error apa pun.

### Grain `hari_dalam_minggu`

Menjawab pertanyaan yang **berbeda** dari grain `harian`, jadi keduanya
disediakan, bukan saling menggantikan:

| Grain | Bucket | Menjawab |
|---|---|---|
| `harian` | per tanggal | "Bagaimana penjualan bergerak dari hari ke hari?" |
| `hari_dalam_minggu` | per nama hari, semua tanggal digabung | "Hari apa yang paling ramai?" |

```json
{
  "periode": { "grain": "hari_dalam_minggu", "start": "2026-06-01", "end": "2026-07-30" },
  "data_tersedia": true,
  "data": [
    { "periode": "1", "periode_label": "Senin", "hari": "Senin", "revenue": 3812000, "transaksi": 128, "qty": 156, "jumlah_hari": 9, "rata_rata_per_hari": 423556 },
    { "periode": "2", "periode_label": "Selasa", "hari": "Selasa", "revenue": 3554000, "transaksi": 119, "qty": 145, "jumlah_hari": 9, "rata_rata_per_hari": 394889 }
  ]
}
```

- `periode` berisi nomor hari ISO `"1"`–`"7"`. **Selalu 7 bucket**, terurut
  Senin → Minggu; hari tanpa transaksi tetap muncul bernilai 0.
- **Tampilkan `rata_rata_per_hari`, bukan `revenue` mentah.** Dalam rentang 9
  minggu, sebuah hari bisa muncul 9 kali sementara hari lain 10 kali, semata
  karena posisi tanggal awal dan akhir. Membandingkan total mentah membuat hari
  yang kebetulan muncul lebih sering terlihat lebih ramai padahal tidak.
- `jumlah_hari` adalah jumlah kemunculan hari itu di **kalender** rentang
  tersebut, bukan jumlah hari yang ada transaksinya, sehingga hari buka yang
  nihil penjualan tetap ikut menekan rata-ratanya.

---

## 3. GET /api/dashboard/revenue-ukuran

Group by `ukuran` dalam window, **dikelompokkan per golongan kemasan**. Saat
window = rentang penuh, angkanya **identik** dengan tabel referensi
`laporan_revenue_ukuran` (urutan tampilnya berbeda, lihat di bawah).

> **KHUSUS MINUMAN.** Dessert & cookies (ukuran `Cup` dan `Pack`) tidak dihitung
> di endpoint ini. Jadi jumlah `total_revenue` di sini **lebih kecil** dari
> `data.total_revenue` di `/ringkasan`, Rp 21.192.000 vs Rp 26.257.000 untuk
> periode penuh; selisih Rp 5.065.000 adalah revenue dessert & cookies. Ini
> disengaja, bukan salah hitung. Endpoint lain (`/ringkasan`,
> `/produk-terlaris`, `/platform`) tetap menghitung semua item.
>
> Field `catatan` dikirim khusus di endpoint ini, tampilkan apa adanya di
> dekat chart supaya user paham cakupannya.

```json
{
  "periode": { "grain": "harian", "start": "2026-06-01", "end": "2026-07-30" },
  "data_tersedia": true,
  "catatan": "Khusus minuman, dessert & cookies (Cup/Pack) tidak termasuk.",
  "ringkasan_golongan": [
    { "golongan": "cup", "jumlah_terjual": 573, "total_revenue": 13593000, "jumlah_transaksi": 512, "ukuran_terlaris": "Reguler" },
    { "golongan": "botol", "jumlah_terjual": 179, "total_revenue": 7599000, "jumlah_transaksi": 145, "ukuran_terlaris": "250ml" }
  ],
  "data": [
    { "ukuran": "Reguler", "golongan": "cup", "jumlah_terjual": 360, "total_revenue": 8047000, "jumlah_transaksi": 323, "rata_rata_transaksi": 24913, "persen_dari_golongan": 62.8 },
    { "ukuran": "Large", "golongan": "cup", "jumlah_terjual": 193, "total_revenue": 5182000, "jumlah_transaksi": 173, "rata_rata_transaksi": 29954, "persen_dari_golongan": 33.7 },
    { "ukuran": "Hot", "golongan": "cup", "jumlah_terjual": 20, "total_revenue": 364000, "jumlah_transaksi": 16, "rata_rata_transaksi": 22750, "persen_dari_golongan": 3.5 },
    { "ukuran": "250ml", "golongan": "botol", "jumlah_terjual": 87, "total_revenue": 2282000, "jumlah_transaksi": 75, "rata_rata_transaksi": 30427, "persen_dari_golongan": 48.6 },
    { "ukuran": "500ml", "golongan": "botol", "jumlah_terjual": 53, "total_revenue": 2187000, "jumlah_transaksi": 31, "rata_rata_transaksi": 70548, "persen_dari_golongan": 29.6 },
    { "ukuran": "1000ml", "golongan": "botol", "jumlah_terjual": 39, "total_revenue": 3130000, "jumlah_transaksi": 39, "rata_rata_transaksi": 80256, "persen_dari_golongan": 21.8 }
  ]
}
```

Jadi jawaban untuk "ukuran berapa ml yang paling sering keluar": **250ml, 48,6%
dari seluruh botol** — meski 1000ml yang menyumbang revenue terbesar. Dua angka
itu memang menjawab pertanyaan berbeda, jadi keduanya dikirim.

### Golongan kemasan dan urutannya

| Golongan | Ukuran |
|---|---|
| `cup` | `Hot`, `Reguler`, `Large` |
| `botol` | `250ml`, `500ml`, `1000ml` |
| `lainnya` | ukuran kosong/tak dikenal |

> **Ejaan ukuran sudah diseragamkan.** Impor CSV menulis `250 ml` (pakai spasi),
> katalog menu menulis `250ml`. Sebelum diseragamkan, 145 dari 148 baris botol
> jatuh ke golongan `lainnya` dan grafik melaporkan botol nyaris tidak pernah
> terjual, padahal yang salah cuma cara mengetiknya. Field `ukuran` sekarang
> selalu memakai ejaan katalog menu, jadi **frontend tidak perlu menormalkan
> apa pun** dan tidak akan menemukan dua batang untuk ukuran yang sama.

**Urutan `data` berubah**: sebelumnya `total_revenue` desc lintas semua ukuran,
sekarang **golongan dulu** (cup → botol → lainnya), lalu **jumlah terjual
menurun** di dalam golongan itu. Jadi baris pertama tiap golongan adalah ukuran
yang paling sering keluar, tanpa perlu dicari.

- `persen_dari_golongan` membandingkan **di dalam golongannya sendiri**, bukan
  terhadap seluruh penjualan. Membandingkan 250ml dengan Reguler tidak berarti
  apa-apa, keduanya kemasan untuk keperluan berbeda. Jumlahnya 100% per golongan.
- `ringkasan_golongan` adalah subtotal siap pakai beserta `ukuran_terlaris`.
  Dihitung server dari `data` yang sama, jadi **jangan menjumlahkan ulang di
  frontend**, dua rumus terpisah pasti lepas sinkron begitu salah satunya
  diubah.
- `ukuran_terlaris` adalah jawaban langsung untuk "ukuran berapa ml yang paling
  sering keluar" di golongan itu.

---

## 4. GET /api/dashboard/produk-terlaris

Produk terlaris **maupun** yang kurang diminati, tergantung `arah`.

| Param | Nilai | Default |
|---|---|---|
| `limit` | 1–100 | `10` |
| `by` | `qty` \| `revenue` | `qty` |
| `arah` | `tertinggi` \| `terendah` | `tertinggi` |
| `sertakan_nol` | `true` \| `false` | `false` |

### Arah, dan kenapa frontend tidak boleh mengurutkan ulang

**Urutan array yang dikembalikan = urutan batang di chart.** Render apa adanya.

| `arah` | Elemen pertama | Bacaan chart |
|---|---|---|
| `tertinggi` | Paling banyak terjual | Tinggi → rendah |
| `terendah` | **Paling sedikit terjual** | **Rendah → tinggi** |

Grafik "10 Menu Kurang Diminati" memakai `?arah=terendah`. Membalik lagi
hasilnya di frontend akan membuatnya terbaca `2, 2, 1, 1, 1…` alih-alih
`1, 1, 1… 2, 2` — persis bug yang sedang diperbaiki.

Produk ber-nilai sama diurutkan sekunder menurut `nama_produk` lalu `rasa`. Ini
bukan kerapian: di daftar terendah belasan produk bisa sama-sama `qty` 1, dan
tanpa pengurutan kedua urutan batang ditentukan database sehingga chart terlihat
bergoyang tiap kali halaman dimuat padahal datanya tidak berubah.

### `sertakan_nol`

Menu aktif yang **belum pernah terjual sama sekali** tidak punya baris di
`laporan_transaksi`, sehingga justru menu yang paling kurang diminati adalah
yang tidak kelihatan. Gejalanya: batang terendah bernilai 1, bukan 0, dan itu
terbaca seolah semua menu laku.

`?sertakan_nol=true` menggabungkan daftar menu aktif sehingga menu tanpa
penjualan ikut tampil dengan `qty: 0`. Dengan `arah=terendah` menu bernilai nol
otomatis berada paling depan — dan itulah jawaban yang dicari grafiknya.

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

Group by `platform` dalam window (campur metode bayar & channel delivery), urut
`revenue` desc.

> **Nilainya sudah dinormalkan.** Kolom `platform` diisi dua sumber yang dulu
> menamai hal yang sama dengan cara berbeda: impor CSV memakai `QRIS`/`Tunai`,
> sementara POS live menulis `metode_bayar` apa adanya (`qris`/`cash` huruf
> kecil). Akibatnya filter platform sempat menampilkan `QRIS` dan `qris` sebagai
> dua entri terpisah, dan angka QRIS yang sebenarnya terpecah dua.
>
> Sekarang proyeksi POS memetakan `cash` → `Tunai` dan `qris` → `QRIS` sebelum
> menulis, dan baris yang terlanjur masuk sudah dinormalkan lewat migrasi. Nilai
> yang mungkin muncul tepat enam: `QRIS`, `Tunai`, `GrabFood`, `ShopeeFood`,
> `Transfer`, `GoFood`. **Frontend tidak perlu menormalkan apa pun.**
>
> Kolom `transaksi.metode_bayar` di sisi POS tetap `cash`/`qris` huruf kecil,
> karena nilai itu terikat validasi `BayarRequest` dan kontrak API kasir.
> Normalisasi hanya terjadi di batas menuju layer laporan.

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

Menerima `start`/`end` seperti endpoint lain; tanpa keduanya = seluruh data.
Filter opsional `?segmen=<nama>`. `ringkasan_segmen` dihitung dari seluruh
pelanggan **di rentang itu**, bukan dari hasil yang sudah tersaring segmen,
supaya donut chart-nya tidak berubah jadi satu potong penuh begitu manager
memilih satu segmen.

`recency` memakai acuan hari setelah transaksi terakhir **di dalam rentang**,
bukan ujung seluruh data. Kalau acuannya dipaku ke ujung data, memilih Juni saja
membuat semua pelanggan Juni terlihat "tidak datang 60 hari" dan seluruhnya
jatuh ke Butuh Perhatian, padahal di rentang itu mereka pelanggan aktif.

```json
{
  "periode_label": "1 Jun 2026 – 30 Jul 2026",
  "ringkasan_segmen": {
    "Pelanggan Baru": 122,
    "Butuh Perhatian": 108,
    "Potensial": 94,
    "Loyal": 21
  },
  "segmen_treatment": [
    {
      "segmen": "Loyal",
      "prioritas": 1,
      "jumlah_pelanggan": 21,
      "persen": 6.1,
      "karakteristik": "Sering datang, nilai belanja tinggi, dan baru saja bertransaksi.",
      "tujuan": "Pertahankan. Jangan diganggu dengan penawaran yang tidak mereka butuhkan.",
      "treatment": ["Beri akses awal ke menu baru…", "Apresiasi personal saat datang…", "JANGAN beri diskon…"],
      "reward_disarankan": "gratis_coffee_kopi",
      "alasan_reward": "Reward berupa produk terasa sebagai apresiasi, sementara diskon terasa sebagai transaksi."
    }
  ],
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

### `segmen_treatment`

Selalu berisi **keempat segmen**, termasuk yang jumlah anggotanya 0 — manager
perlu melihat bahwa "Butuh Perhatian" kosong, dan itu kabar baik yang hilang
kalau barisnya tidak muncul sama sekali.

Diurutkan **prioritas penanganan**, bukan jumlah anggota. `jumlah_pelanggan`
selalu cocok dengan `ringkasan_segmen` di response yang sama; dua angka berbeda
di satu layar untuk hal yang sama adalah cara tercepat kehilangan kepercayaan
pada laporannya.

`reward_disarankan` berisi **kode katalog redeem yang benar-benar ada**
(lihat `GET /api/pengaturan/loyalty/katalog`), jadi kasir bisa langsung
mengeksekusinya tanpa menerjemahkan apa pun. Latar belakang tiap segmen dan
alasan pemilihan reward-nya ada di [segmen-treatment.md](segmen-treatment.md).

> Ini **bukan** mesin promo otomatis. Backend hanya menyajikan polanya;
> eksekusinya manual lewat WhatsApp dan itu keputusan manager.

> **BREAKING (data revisi Juni–Juli 2026).** Penamaan segmen berubah:
> `Pelanggan Loyal` → `Loyal`, `Pelanggan Potensial` → `Potensial`, dan
> `Hampir Hilang` **dihapus**, diganti `Pelanggan Baru`. Kalau frontend
> meng-hardcode nama/warna segmen, sesuaikan. Lebih aman ambil daftarnya
> dari `GET /api/dashboard/meta` (field `segmen`) yang selalu live dari DB.

Tiga field baru di objek data:

| Field | Arti |
|---|---|
| `total_pcs_dibeli` | Jumlah pcs. Beda dari `frequency` yang menghitung **kunjungan**, 1 kunjungan bisa banyak pcs. |
| `total_poin_loyalty` | Akumulasi poin LoyalSeed (1 poin per Rp 1.000; item non-minuman tidak dapat poin). |
| `frequency_skor` | Frekuensi terbobot = `0,6 × frequency + 0,4 × total_pcs_dibeli`. Desimal. Dasar `f_score`, supaya pembeli borongan tidak kalah dari yang sering datang tapi beli sedikit. |

---

## 8. GET /api/dashboard/switch

Menerima `start`/`end` seperti endpoint lain; tanpa keduanya = seluruh data.
Filter opsional substring `?rekomendasi=<teks>` (mis. `?rekomendasi=Large`).

```json
{
  "periode_label": "1 Jun 2026 – 30 Jul 2026",
  "data": [
    {
      "id": 1, "nama_pelanggan": "Sharen", "rasa_favorit": "Choco Maniac",
      "ukuran_saat_ini": "Reguler", "beli_reguler": 7, "beli_large": 10,
      "beli_botol": 0, "total_transaksi": 17, "qty_per_kunjungan": 1.0,
      "total_belanja": 512000, "rekomendasi": "Switch ke Large, frekuensi tinggi, mulai dari Large"
    }
  ]
}
```

---

## 9. GET /api/laporan/export

Download workbook `.xlsx` multi-sheet. Params sama: `grain`/`start`/`end`.

- **Response:** `200` dengan `Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`
  dan `Content-Disposition: attachment`.
- **Nama file:** `Laporan_SoyaCore_{start} Hingga {end}.xlsx`
  (mis. `Laporan_SoyaCore_2026-06-01 Hingga 2026-07-30.xlsx`).

**Sheet (berurutan):**

| Sheet | Isi | Scope |
|---|---|---|
| Ringkasan | Blok KPI + label periode & grain | window |
| Rekap Kasir | Satu baris per tanggal × kasir + subtotal | window |
| Detail Transaksi | Baris `laporan_transaksi` (header Bahasa Indonesia) | window |
| Revenue per Ukuran | Per golongan kemasan + subtotal + catatan cakupan | window, **minuman saja** |
| Time Series | Bucket sesuai grain | window |
| RFM Pelanggan | RFM dihitung + catatan periode | window |
| **Segmen & Treatment** | Pola treatment tiap segmen + jumlah anggotanya | window |
| Rekomendasi Switch | Switch dihitung + catatan periode | window |

Kolom uang berupa integer rupiah. Window kosong tetap menghasilkan `.xlsx` valid
(sebagian besar kosong, hanya header).

**RFM Pelanggan**, **Segmen & Treatment**, dan **Rekomendasi Switch** dikeluarkan
dari file bila `?kasir_user_id=` dipakai: ketiganya analisis segmen pelanggan dan
tidak punya dimensi kasir sama sekali, jadi isinya akan menampilkan seluruh
pelanggan toko di dalam file yang judulnya menyebut satu nama kasir.

### Baris header tiap sheet

Sheet yang diawali catatan cakupan menaruh header tabelnya **bukan di baris 1**.
Penting kalau file ini dibaca ulang otomatis (mis.
`pandas.read_excel(..., skiprows=N)`):

| Sheet | Baris header | Data mulai |
|---|:---:|:---:|
| Revenue per Ukuran | 4 | 5 |
| RFM Pelanggan | 4 | 5 |
| Segmen & Treatment | 4 | 5 |
| Rekomendasi Switch | 3 | 4 |
| Ringkasan, Rekap Kasir, Detail Transaksi, Time Series | 1 | 2 |

Sheet **Revenue per Ukuran** memuat baris **subtotal per golongan** di antara
baris datanya (dibedakan huruf tebal dan latar sendiri). Jangan menjumlahkan
seluruh kolom mentah-mentah, subtotalnya akan terhitung dua kali.

Sheet **RFM Pelanggan** memakai 12 kolom: Nama Pelanggan, Recency (hari),
Kunjungan, Total Pcs, Monetary (Rp), Total Poin, Skor Frekuensi, R, F, M,
RFM Total, Segmen.

---

## 9b. Dua export lain: per halaman, bukan serba-ada

Endpoint di atas adalah unduhan halaman **Laporan** dan isinya memang
keseluruhan. Halaman Laporan Kasir dan Transaksi punya tombol Unduh sendiri
yang menghasilkan **satu sheet** berisi tabel halaman itu saja. Manager menekan
Unduh sambil menatap satu tabel tertentu; file tujuh sheet memaksanya mencari
lagi tabel yang tadi sudah ada di depan matanya.

| Endpoint | Params | Sheet | Nama file |
|---|---|---|---|
| `GET /api/laporan/export` | `grain`, `start`, `end`, `kasir_user_id` | 8 sheet, atau 5 bila `kasir_user_id` dipakai (lihat di atas) | `Laporan_SoyaCore_{start} Hingga {end}.xlsx` |
| `GET /api/laporan/kasir/export` | sama dengan `GET /api/laporan/kasir` | `Laporan Kasir` | `Laporan Kasir_SoyaCore_{start} Hingga {end}.xlsx` |
| `GET /api/laporan/transaksi/export` | sama dengan `GET /api/transaksi` | `Transaksi` | `Laporan Transaksi_SoyaCore_{start} Hingga {end}.xlsx` |

Ketiganya manager-only. Dua yang terakhir memakai FormRequest yang **sama
persis** dengan endpoint yang mengisi tabelnya di layar, jadi angka di Excel
tidak bisa menyimpang dari angka di halaman, dan seluruh filter yang aktif ikut
terbawa, bukan cuma rentang tanggalnya.

Export transaksi mengabaikan `per_page`: yang diunduh adalah **seluruh** baris
hasil filter, bukan halaman yang sedang dibuka. Batas per halaman ada supaya
tabel HTML tetap ringan, sedangkan file Excel justru dipakai untuk yang tidak
muat di layar.

Batas tanggal yang tidak dikirim disimpulkan dari datanya sendiri saat menyusun
nama file, sehingga unduhan tanpa filter tanggal tetap dinamai dengan tanggal
sungguhan alih-alih `Awal Hingga Akhir`.

Kolom Tunai/QRIS di sheet `Laporan Kasir` dipecah jadi jumlah transaksi dan
nilai rupiah, berbeda dengan layar yang meringkasnya jadi `1× · Rp 41.600`.
Gabungan itu akan jadi teks yang tidak bisa dijumlahkan di Excel.

---

## Ringkasan Kode Error

| Kode | HTTP | Kapan |
|---|---|---|
| `unauthenticated` | 401 | Tanpa/invalid token |
| `tidak_berwenang` | 403 | Role tidak mencukupi (lihat tabel Hak Akses per Role) |
| `validasi_gagal` | 422 | `grain` tak dikenal, format tanggal salah, `end` < `start`, dll. |
