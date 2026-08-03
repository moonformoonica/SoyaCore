# Laporan & Atribusi per Akun Kasir

Dokumen ini menjelaskan bagaimana SoyaCore membedakan data antar akun kasir,
termasuk apa yang **tidak** dibangun, supaya tidak ada yang mengira ada fitur
yang terlewat dikerjakan.

---

## 1. Tidak ada mekanisme shift

Permintaannya berbunyi _"bikin perbedaan data kasir misalnya kasir 1 dan kasir 2
pas pergantian shift"_. Maksudnya sudah ditegaskan pemilik produk:

> **Pembedaannya cukup lewat akun kasir.** Kasir 1 dan Kasir 2 punya akun
> masing-masing; siapa pun yang sedang login, dialah yang tercatat melayani
> pemesanan dan transaksi itu.

Karena itu **tidak ada**:

- tabel `shift`
- tombol buka/tutup shift
- input modal awal kas
- hitung kas fisik maupun perhitungan selisih laci

"Pergantian shift" di SoyaCore artinya sekadar berganti akun yang login.

> **Konsekuensi yang perlu diketahui:** tanpa hitung kas fisik, sistem tidak bisa
> mendeteksi selisih laci. Itu memang di luar permintaan, dicatat di sini supaya
> jelas bahwa ini keputusan, bukan kelalaian.

---

## 2. Dua kolom, dua peran

Sebelum revisi ini, `bayar()` **menimpa** `transaksi.user_id` dengan akun yang
menandai lunas. Pada terminal yang dipakai satu akun sepanjang hari, penimpaan
itu tidak berakibat apa-apa. Celahnya muncul tepat pada skenario yang ingin
dibedakan: pesanan yang menyeberangi pergantian akun.

| Kolom          | Arti                                         | Diisi saat                                    |
| -------------- | -------------------------------------------- | --------------------------------------------- |
| `user_id`      | Akun kasir **pembuat** pesanan               | `store()`, dan **tidak pernah ditimpa lagi** |
| `dibayar_oleh` | Akun kasir yang **menyelesaikan pembayaran** | `bayar()`                                     |

Keduanya nullable dengan alasan yang berbeda:

- `dibayar_oleh` null selama transaksi masih `pending`, belum dibayar siapa pun.
- `user_id` null untuk pesanan SoyaScan, memang tidak ada kasir yang menyusunnya.
  `dibayar_oleh` tetap terisi saat kasir menerimanya di konter.

Di API keduanya diekspos sebagai `kasir_pembuat` dan `kasir_penyelesai`. Key
`kasir` yang lama **tetap ada** supaya frontend yang sudah jalan tidak rusak:
isinya penyelesai bila ada, jatuh ke pembuat bila belum dibayar.

---

## 3. Penjualan dihitung ke akun siapa

> **Penjualan dihitung ke akun kasir yang menyelesaikan pembayarannya**
> (`dibayar_oleh`), karena di titik itulah transaksi benar-benar terjadi.

- Laporan memotong berdasarkan **`waktu_lunas`**, bukan `created_at`. Transaksi
  yang dibuat kemarin malam dan dibayar pagi ini masuk ke **hari ini**.
- Batas harinya **WIB**, apa pun isi `config('app.timezone')`. Lihat §7.
- Transaksi berstatus `pending` **belum masuk** laporan kasir mana pun. Ia belum
  jadi penjualan.
- Transaksi yang dibatalkan penuh **keluar** dari laporan; yang dibatalkan
  sebagian tetap masuk dengan nilai yang sudah dikurangi.

### Contoh lengkap: pergantian akun kasir

```
13.55  Kasir 1 login, membuat pesanan #K012 (2x Original = Rp 40.000)
14.00  Kasir 1 logout, pelanggan masih menunggu minumannya
14.02  Kasir 2 login
14.05  Pelanggan membayar. Kasir 2 membuka #K012 lalu Tandai Lunas.
```

Hasilnya:

| Data                       | Nilai                    |
| -------------------------- | ------------------------ |
| `transaksi.user_id`        | Kasir 1                  |
| `transaksi.dibayar_oleh`   | Kasir 2                  |
| Omzet Rp 40.000 masuk ke   | **Kasir 2**              |
| Muncul di kolom            | `jumlah_transaksi_dibuat_kasir_lain` milik Kasir 2 |

Tidak ada informasi yang hilang: uangnya tercatat di akun yang menerimanya, dan
jejak bahwa Kasir 1 yang menyusun pesanannya tetap ada.

### Kasir baru TIDAK perlu input ulang apa pun

Ini yang sering dikhawatirkan, jadi ditegaskan: transaksi tersimpan di
**database**, bukan di sesi login. Customer, item, diskon, dan redeem semuanya
masih menempel pada pesanan itu.

Kasir 2 cukup membuka pesanannya lalu menekan **Tandai Lunas**. Yang berubah
hanya tiga hal: `status`, `waktu_lunas`, dan `dibayar_oleh`.

### ⚠️ Antrean pesanan pending tidak boleh difilter ke akun sendiri

`GET /api/transaksi?status=pending` **harus** menampilkan pesanan milik akun lain
juga. Kalau tidak, Kasir 2 tidak akan menemukan pesanan Kasir 1 dan pelanggan
terlantar di depan konter dengan minuman yang sudah dibuat.

Filter `?user_id=` / `?dibuat_oleh=` di daftar transaksi tetap **opsional dan
default mati**. Kalau frontend memasangnya sebagai default untuk "transaksi milik
sendiri", itu hanya boleh berlaku di **kartu statistik**, bukan di antrean pesanan
pending.

---

## 4. `GET /api/laporan/kasir`, perbandingan antar kasir

Manager saja. Query param: `tanggal_mulai`, `tanggal_selesai`, `preset`
(`hari_ini` | `kemarin` | `7_hari` | `30_hari` | `bulan_ini`), aturannya sama
persis dengan filter daftar transaksi.

Satu baris per akun kasir, diurutkan omzet menurun:

| Field                                | Arti                                                            |
| ------------------------------------ | --------------------------------------------------------------- |
| `user_id`, `nama`                    | Akun kasirnya                                                   |
| `jumlah_transaksi`                   | Transaksi yang ia selesaikan pembayarannya                      |
| `total_omzet`                        | Nilai penjualan bersih, sudah dikurangi pembatalan              |
| `total_qty`                          | Jumlah item terjual, sudah dikurangi qty yang dibatalkan        |
| `rata_rata_transaksi`                | `total_omzet ÷ jumlah_transaksi`                                |
| `rincian_metode_bayar`               | `{ cash: {jumlah, total}, qris: {jumlah, total} }`              |
| `total_diskon`                       | Diskon yang **pernah diberikan** (lihat catatan di bawah)       |
| `total_poin_diberikan`               | Poin earn, dikurangi yang ditarik lewat pembatalan              |
| `total_poin_ditukar`                 | Poin yang ditukar reward di transaksinya                        |
| `jumlah_pembatalan`, `nilai_dibatalkan` | Pembatalan yang **ia proses**, lihat §5                      |
| `jumlah_transaksi_dibuat_kasir_lain` | Serah terima pesanan saat pergantian akun                       |

Plus blok `meta` berisi total seluruh kasir, supaya angkanya bisa direkonsiliasi
dengan dashboard tanpa manager menjumlahkan barisnya sendiri.

**Dua kolom yang paling gampang terlewat tapi justru paling berguna:**

- **`jumlah_transaksi_dibuat_kasir_lain`**: tanpa ini, laporan Kasir 2 terlihat
  seolah semua pesanan dia yang buat.
- **`jumlah_pembatalan`**: pembatalan berlebih dari satu akun adalah pola yang
  perlu terlihat, dan inilah gunanya alasan pembatalan diwajibkan.

**Catatan `total_diskon`:** dicatat sebesar yang benar-benar diberikan saat
transaksi terjadi, **tidak** dikurangi pembatalan. Potongannya memang pernah
diberikan, dan angka ini dipakai mengevaluasi kebijakan diskon, bukan menghitung
uang masuk.

---

## 5. Dua angka pembatalan yang beda maknanya

Ini gampang tertukar, jadi ditulis eksplisit:

| Angka                                     | Menjawab pertanyaan                              | Menempel di akun    |
| ----------------------------------------- | ------------------------------------------------ | ------------------- |
| `jumlah_pembatalan` + `nilai_dibatalkan`  | "Akun ini membatalkan berapa kali periode ini?"  | Yang **memproses**  |
| Pengurang `total_omzet`                   | "Berapa penjualan akun ini yang akhirnya gugur?" | Yang **menjual**    |

Pada kasus normal, kasir yang sama menjual dan membatalkan, kedua angka itu
sama besar, jadi bedanya hanya terasa saat pembatalan menyeberangi akun.

Kenapa pengurang omzet tidak ikut mengejar akun pemroses: kalau Kasir 2
membatalkan penjualan Kasir 1 dan omzet Kasir 2 yang dikurangi, akun yang tidak
pernah menerima uangnya bisa jadi **minus**, dan `meta` tidak lagi bisa
direkonsiliasi dengan dashboard. Angka yang tidak bisa direkonsiliasi lebih
berbahaya daripada pembagian yang sedikit lebih rumit dijelaskan.

Pembatalan dipotong berdasarkan **tanggal dokumen pembatalannya**, bukan tanggal
penjualan aslinya, karena yang ditanya adalah "siapa membatalkan apa periode
ini".

---

## 6. Export Excel per kasir

`GET /api/laporan/export?kasir_user_id=`, manager saja. Tanpa param itu, semua
kasir ikut seperti sebelumnya.

Tiga kebutuhan yang diminta, dan apa yang menjaminnya:

| Kebutuhan      | Dijamin oleh                                                       |
| -------------- | ------------------------------------------------------------------ |
| Per kasir      | Kolom `kasir_user_id` + `kasir_nama` di `laporan_transaksi`         |
| Tanggal akurat | Proyeksi memakai `waktu_lunas` dalam WIB                            |
| Real-time      | Proyeksi **sinkron** di `bayar()`, bukan queued job                 |

### Sheet `Rekap Kasir`

Diletakkan tepat setelah `Ringkasan` supaya terlihat lebih dulu daripada sheet
detail. Satu baris per kombinasi **tanggal × kasir**, diurutkan tanggal lalu nama.

| Kolom                        | Arti                                                              |
| ---------------------------- | ----------------------------------------------------------------- |
| Tanggal                      | Tanggal penjualan menurut **WIB**, format `Y-m-d`                 |
| Kasir                        | Nama kasir saat transaksi terjadi (snapshot, bukan nama terkini)  |
| Jumlah Transaksi             | Transaksi unik, bukan jumlah baris item                           |
| Total Qty                    | Item terjual, termasuk minuman gratis hasil redeem                |
| Total Omzet (Rp)             | Nilai bersih setelah diskon dan setelah pembatalan                |
| Rata-rata per Transaksi (Rp) | Total omzet ÷ jumlah transaksi                                    |
| Cash (Rp) / QRIS (Rp)        | Pecahan omzet per metode bayar                                    |
| Total Diskon (Rp)            | Potongan yang pernah diberikan                                    |
| Poin Diberikan               | Poin earn dari belanja                                            |
| Poin Ditukar                 | Poin yang dipakai menukar reward                                  |
| Jumlah Pembatalan            | Pembatalan yang diproses akun itu pada tanggal itu                |
| Nilai Dibatalkan (Rp)        | Nilai penjualan yang gugur                                        |

Ada baris **TOTAL** di akhir tiap tanggal, dan satu **TOTAL KESELURUHAN**,
manager membaca file ini tanpa membuat pivot sendiri.

> **Cash + QRIS tidak selalu sama dengan Total Omzet.** Baris impor CSV historis
> punya `platform` lain (Shopee/GoJek/Grab) yang tidak masuk kedua kolom itu tapi
> tetap terhitung di Total Omzet. Itu memang apa adanya datanya.

### Baris tanpa kasir

| Sumber baris                                     | Kasir            | Tampil sebagai        |
| ------------------------------------------------ | ---------------- | --------------------- |
| Impor CSV Juni–Juli 2026 (`kode` berawalan `TR-`) | `null`           | `—` / `— (data historis)` |
| Transaksi SoyaCore (`kode` berawalan `TRX-`)      | **wajib terisi** | Nama kasirnya         |

Baris historis **tetap dimasukkan** di sheet `Rekap Kasir`, bukan dibuang. Kalau
dibuang, total sheet ini tidak akan pernah cocok dengan `Ringkasan`, dan manager
yang menjumlahkan sendiri akan mengira ada data hilang.

Di sheet `Detail Transaksi`, kolom Kasir untuk baris historis diisi `'—'`,
sel kosong terbaca sebagai data hilang, sedangkan ini memang transaksi dari
sebelum SoyaCore dipakai.

**Invarian yang diuji:** tidak ada satu pun baris berawalan `TRX-` dengan
`kasir_user_id` kosong. Proyeksi hanya terjadi di `bayar()`, dan di sana selalu
ada user terautentikasi, baris `TRX-` tanpa kasir berarti ada yang bocor.

### Sheet yang dikeluarkan saat difilter kasir

`RFM Pelanggan` dan `Rekomendasi Switch` adalah analisis **segmen pelanggan** dari
data historis dan tidak punya dimensi kasir sama sekali. Kalau tetap disertakan
pada export yang difilter satu kasir, isinya akan menampilkan seluruh pelanggan
toko, angka yang tidak tersaring di dalam file yang judulnya menyebut satu nama
kasir. Karena itu keduanya **dikeluarkan** saat `kasir_user_id` dikirim.

Nama kasir juga masuk ke nama file:
`Laporan_SoyaCore_2026-07-01 Hingga 2026-07-31_adrian.xlsx`.

### Unduhan halaman Laporan Kasir sendiri

Halaman Laporan Kasir punya tombol Unduh terpisah
(`GET /api/laporan/kasir/export`) yang menghasilkan **satu sheet** berisi tabel
halaman itu saja, memakai rentang tanggal yang sedang ditampilkan. Sumber
datanya `LaporanKasirQuery` yang sama dengan endpoint tabelnya, jadi angka di
Excel tidak bisa berbeda dari angka di layar, dan baris TOTAL ikut terunduh.
Nama filenya `Laporan Kasir_SoyaCore_{start} Hingga {end}.xlsx`.

---

## 7. Zona waktu

Seluruh angka di dokumen ini memakai aturan tunggal:

> **Transaksi yang terjadi pada suatu hari masuk ke tanggal hari itu menurut
> WIB.** Transaksi pukul 23.30 tanggal 5 masuk ke tanggal 5, bukan tanggal 6.

`config('app.timezone')` di repo ini masih `'UTC'`, jadi `whereDate()` memotong
hari pada **07.00 WIB**, transaksi pagi akan jatuh ke tanggal sebelumnya. Karena
itu semua konversi waktu→tanggal lewat satu helper, `App\Support\WaktuToko`, dan
tidak ada satu pun query yang memakai `whereDate()` pada kolom datetime lagi.

Yang memakainya: filter daftar transaksi, kolom `tanggal` proyeksi laporan,
pengelompokan `timeSeries`, rentang laporan kasir, rekap Excel, dan penomoran
`kode_pesanan` harian.
