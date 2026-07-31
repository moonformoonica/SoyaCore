# Prompt Revisi Backend SoyaCore — Monica (Backend)

> **Cara pakai.** File ini sengaja disimpan **di luar folder project** supaya tidak
> ikut ter-commit. Buka Claude Code di VS Code pada root repo SoyaCore, lalu:
>
> ```
> Kerjakan brief di @../prompt-revisi-backend-monica.md
> ```
>
> Kalau path relatif tidak terbaca, salin-tempel isi file ini ke chat.
>
> **Sebelum mulai:** buat branch baru (`git checkout -b revisi-pembimbing-backend`).
> Saat ini `master` masih menyimpan pekerjaan loyalty v2 yang belum di-commit —
> jangan menimpanya.

---

## 1. Batas tanggung jawab

Brief ini **hanya backend** (Laravel 12 — `app/`, `database/`, `routes/`,
`tests/`, `docs/`). Yang menyentuh `resources/views`, `resources/js`, dan
`resources/css` dikerjakan orang lain — **jangan disentuh**, kecuali disebut
eksplisit.

Peta seluruh butir revisi pembimbing dan siapa yang mengerjakan:

| Butir revisi                                          | Status     | Penanggung jawab                      |
| ----------------------------------------------------- | ---------- | ------------------------------------- |
| **Manager**                                           |            |                                       |
| Keterangan pemesanan lewat kasir (SoyaScan sudah ada) | ⬜         | **Backend — Blok A1**                 |
| Bagian transaksi diatur tanggalnya                    | ⬜         | **Backend — Blok A2**                 |
| Transaksi bisa memunculkan data yang ingin dilihat    | ⬜         | **Backend — Blok A3**                 |
| Penambahan pesanan nambah ke dashboard                | ⬜         | **Backend — Blok B1** ⚠️ arsitektur   |
| Bar chart "other" dihilangkan                         | ⬜         | **Backend — Blok B2** + frontend      |
| Tren penjualan: tanggal → hari                        | ⬜         | **Backend — Blok B3** + frontend      |
| Urutan poin loyalty termahal/termurah                 | ✅ selesai | —                                     |
| Edit menu: kiri cup, kanan botol                      | ⬜         | **Backend — Blok F1** + frontend      |
| Numerasi diskon (10/20/30/50%)                        | ✅ selesai | —                                     |
| Poin gratis minuman dinaikkan                         | ✅ selesai | —                                     |
| Numerasi redeem poin diatur ulang                     | ✅ selesai | —                                     |
| Laporan langsung berubah saat diklik                  | ⬜         | Frontend (endpoint sudah ada)         |
| Perbedaan data kasir 1 vs kasir 2 (per akun)          | ⬜         | **Backend — Blok C**                  |
| Sidebar lawan arah                                    | ⬜         | Frontend saja                         |
| **SoyaScan**                                          |            |                                       |
| Keterangan pilih sugar **dan ice** setelah pilih menu | ⬜         | **Backend — Blok E1** + frontend      |
| Nomor meja dihilangkan                                | ⬜         | **Backend — Blok E2** ⚠️ ubah kontrak |
| Tampilan QRIS saat pembayaran                         | ⬜         | **Backend — Blok E3** + frontend      |
| Sidebar tambah logo landing page                      | ⬜         | Frontend saja                         |
| QR untuk scan menu                                    | ⬜         | **Backend — Blok E4**                 |
| **Kasir**                                             |            |                                       |
| Pembatalan/koreksi pesanan salah                      | ⬜         | **Backend — Blok D**                  |

Urutan pengerjaan yang disarankan: **A → B → C → D → E → F**. Blok D
(pembatalan) bergantung pada B1 dan C — jangan dikerjakan lebih dulu.

---

## 2. Temuan arsitektur yang harus dipahami dulu

Empat hal ini sudah diverifikasi langsung di kode. Salah paham di sini membuat
implementasinya salah arah.

### T1. Dashboard tidak membaca transaksi live — ini akar masalah butir "pesanan nambah ke dashboard"

`DashboardController` → `LaporanQuery` → model **`LaporanTransaksi`**, yaitu
tabel `laporan_transaksi` yang diisi dari CSV historis
(`database/seeders/data/Data_Transaksi_Bersih.csv`). Tabel POS live
(`transaksi` + `detail_transaksi`) **tidak pernah dibaca dashboard sama sekali**.

Jadi pesanan baru memang tidak akan pernah muncul — bukan bug polling atau
cache, tapi memang dua sumber data yang terpisah. Perbaikannya butuh keputusan
arsitektur, bukan tambal kecil. Lihat Blok B1.

### T2. `sumber` ada di level item, bukan transaksi

Kolom `sumber` (`'kasir'` | `'self_order'`) ada di **`detail_transaksi`**, bukan
`transaksi`. `TransaksiResource` tidak mengeksposnya sama sekali, sehingga
halaman transaksi manager tidak punya cara membedakan pesanan kasir vs SoyaScan.

### T3. `nomor_meja` saat ini wajib — menghapusnya adalah perubahan kontrak

`StoreOrderRequest` memvalidasi `'nomor_meja' => ['required', 'string', 'max:20']`,
`OrderService` menulisnya ke tiap baris `detail_transaksi`, dan `OrderController`
mengembalikannya di response. Ketiganya harus diubah bersamaan, plus
`docs/kontrak-api-v1.md`.

### T4. Pembatalan baru setengah ada

Yang **sudah ada** hanyalah
`TransaksiController::batal()`, dan itu cuma bisa membatalkan transaksi
berstatus `pending` (`pastikanPending()`), seluruhnya, tanpa alasan, tanpa
jejak siapa yang membatalkan. Transaksi yang sudah lunas belum bisa dikoreksi
sama sekali. Lihat Blok D.

### T6. ⚠️ `user_id` transaksi ditimpa saat pembayaran — jejak kasir pembuat hilang

`TransaksiController::store()` mengisi `user_id` dengan kasir pembuat, lalu
`bayar()` **menimpanya** dengan kasir yang menandai lunas:

```php
$transaksi->update([
    'metode_bayar' => ..., 'status' => 'lunas', 'waktu_lunas' => now(),
    'user_id' => $request->user()->id,   // menimpa pembuat
]);
```

Akibatnya, tepat pada skenario yang ingin dibedakan pembimbing — Kasir 1
membuat pesanan, shift berganti, Kasir 2 yang menutup pembayaran — seluruh
transaksi tercatat atas nama Kasir 2 dan kontribusi Kasir 1 lenyap tanpa jejak.

Satu kolom tidak cukup menampung dua peran yang berbeda. Perbaikannya ada di
Blok C2.

### T5 (perlu diverifikasi). Kemungkinan inkonsistensi timezone

`TransaksiController::index()` memfilter dengan `whereDate('created_at', ...)`
yang memakai `config('app.timezone')`, sementara
`OrderService::generateKodePesananSelfOrder()` eksplisit menghitung awal hari
dengan `Carbon::now('Asia/Jakarta')`. Kalau `app.timezone` bukan
`Asia/Jakarta`, filter tanggal manager akan bergeser sampai 7 jam — transaksi
malam masuk ke tanggal berikutnya.

**Cek `config/app.php` dan `.env` lebih dulu.** Kalau memang berbeda, perbaiki
sebagai bagian dari Blok A2 dan catat di ringkasan akhir.

Aturan yang ditegaskan pemilik produk, berlaku di **seluruh** brief ini:

> **Transaksi yang terjadi pada suatu hari harus masuk ke tanggal hari itu
> menurut WIB.** Transaksi pukul 23:30 tanggal 5 masuk ke tanggal 5, bukan
> tanggal 6.

Konsekuensinya bukan cuma di filter daftar transaksi. Setiap tempat yang
mengubah waktu menjadi tanggal harus memakai `Asia/Jakarta` secara eksplisit:
filter `TransaksiController::index()`, kolom `tanggal` pada proyeksi laporan
(Blok B1), pengelompokan `timeSeries` (Blok B3), rentang laporan kasir
(Blok C3), dan penomoran `kode_pesanan` harian. Kalau ada satu saja yang memakai zona
server, angka dashboard tidak akan pernah cocok dengan daftar transaksi.

Sarankan **satu helper terpusat** (mis. `app/Support/WaktuToko.php`) yang
dipakai semua tempat itu, supaya aturannya tidak ditulis ulang berkali-kali dan
tidak bisa lepas sinkron.

---

## 3. Aturan kerja

- **Bahasa Indonesia** untuk seluruh komentar, docblock, dan pesan error.
  Ikuti gaya repo: komentar menjelaskan **kenapa**, bukan **apa** — lihat
  `LoyaltyRedemptionCatalog.php` dan `docs/pengaturan-loyalty.md` sebagai acuan.
- Idiom repo yang sudah mapan, pertahankan:
    - error lewat `ApiException(kode_snake_case, pesan_manusiawi, http_status)`
    - `DB::transaction()` untuk mutasi berganda, `lockForUpdate()` untuk saldo poin
    - `forceFill()->save()` untuk kolom non-fillable
    - response list pakai API Resource (`TransaksiResource`), bukan array mentah
    - setiap migrasi punya `down()` yang benar-benar bisa di-rollback
- **Pola singleton pengaturan**: tabel `pengaturan_*` berisi 0 atau 1 baris;
  0 baris = pakai konstanta `DEFAULT_*` di model. Ikuti pola ini kalau menambah
  pengaturan baru.
- **Pola override katalog**: tabel menyimpan **selisih** dari default, bukan
  seluruh datanya. Baris hanya dibuat saat benar-benar diedit.
- Jangan mengubah perilaku loyalty v2 yang baru selesai (plafon potongan,
  kedaluwarsa poin, bonus daftar, kunci diskon setelah redeem) kecuali diminta.
- Setiap endpoint baru **wajib** punya Feature test dan masuk ke
  `docs/kontrak-api-v1.md`.

---

## BLOK A — Halaman transaksi manager

### A1. Bedakan pesanan kasir vs SoyaScan

Lihat temuan **T2**.

1. Migrasi: kolom `sumber` (`string`, default `'kasir'`) pada tabel `transaksi`.
   Alasan menaruhnya di level transaksi, bukan mengandalkan item: satu transaksi
   berasal dari satu channel, dan menurunkannya dari item memaksa query anak
   di setiap baris daftar.
2. Isi nilainya di dua titik pembuatan:
    - `TransaksiController::store()` → `'kasir'`
    - `OrderService::buatOrder()` → `'self_order'`
3. Backfill di migrasi yang sama untuk baris lama: turunkan dari
   `detail_transaksi.sumber` baris pertama milik tiap transaksi; kalau tidak ada
   item, jatuhkan ke `'kasir'`.
4. Ekspos `sumber` di `TransaksiResource`, beserta label siap tampil
   (`'Kasir'` / `'SoyaScan'`) supaya frontend tidak perlu memetakan sendiri.
5. Kolom `detail_transaksi.sumber` **tetap dipertahankan** — dipakai
   `LoyaltyService` saat membuat item reward. Jangan dihapus.

### A2. Filter dan urutan tanggal

`TransaksiController::index()` sekarang hanya punya `?tanggal=` (tanggal persis).
Tambahkan:

| Query param       | Isi                                                             | Keterangan                                                                       |
| ----------------- | --------------------------------------------------------------- | -------------------------------------------------------------------------------- |
| `tanggal_mulai`   | `YYYY-MM-DD`                                                    | Batas bawah, inklusif                                                            |
| `tanggal_selesai` | `YYYY-MM-DD`                                                    | Batas atas, inklusif                                                             |
| `preset`          | `hari_ini` \| `kemarin` \| `7_hari` \| `30_hari` \| `bulan_ini` | Jalan pintas; kalah dari `tanggal_mulai`/`tanggal_selesai` bila keduanya dikirim |
| `urut`            | `terbaru` \| `terlama`                                          | Default `terbaru` (perilaku sekarang)                                            |

- Buat `IndexTransaksiRequest` (FormRequest) untuk memvalidasi semuanya —
  jangan menumpuk `$request->query()` mentah di controller.
- Tolak `tanggal_mulai > tanggal_selesai` dengan `422` dan pesan yang jelas.
- `?tanggal=` yang lama **tetap didukung** supaya frontend yang belum diperbarui
  tidak rusak.
- **Selesaikan T5 di sini**: pastikan seluruh perbandingan tanggal memakai
  zona `Asia/Jakarta`, apa pun isi `config('app.timezone')`.

### A3. Filter data yang ingin dilihat

Tambahkan pada endpoint yang sama:

| Query param                    | Isi                                                                                        |
| ------------------------------ | ------------------------------------------------------------------------------------------ |
| `status`                       | `pending` \| `lunas` \| `batal` \| `batal_sebagian` _(yang terakhir menyusul dari Blok D)_ |
| `sumber`                       | `kasir` \| `self_order`                                                                    |
| `user_id`                      | sudah ada, pertahankan                                                                     |
| `metode_bayar`                 | `cash` \| `qris`                                                                           |
| `ada_redeem`                   | `true` \| `false` — transaksi yang memakai poin                                            |
| `cari`                         | Cocokkan ke `kode_pesanan`, nama customer, atau no WA customer                             |
| `total_min` / `total_max`      | Rentang nilai transaksi                                                                    |
| `dibuat_oleh` / `dibayar_oleh` | Per akun kasir _(menyusul dari Blok C5)_                                                   |

Ketentuan:

- Semua filter **opsional dan bisa digabung** (AND).
- `cari` memakai `LIKE` case-insensitive; gunakan `whereHas` untuk kolom milik
  customer, jangan `join` manual.
- Sertakan blok `meta` di response berisi ringkasan **hasil terfilter**:
  `jumlah_transaksi`, `total_omzet`, `total_qty`. Ini yang membuat filter
  berguna buat manager — angkanya ikut berubah, bukan cuma daftarnya.
- Pertahankan paginasi yang sudah ada (`per_page`, maksimum 200).

---

## BLOK B — Dashboard

### B1. Pesanan baru masuk ke dashboard ⚠️ butuh keputusan arsitektur

Lihat temuan **T1**. Tiga opsi, dan **pakai opsi B**:

| Opsi  | Cara                                                        | Kenapa tidak/ya                                                         |
| ----- | ----------------------------------------------------------- | ----------------------------------------------------------------------- |
| A     | Dashboard membaca UNION `laporan_transaksi` + `transaksi`   | Setiap query agregasi harus ditulis dua kali; export & RFM ikut pecah   |
| **B** | **Proyeksikan tiap transaksi lunas ke `laporan_transaksi`** | **Satu layer query tetap, semua endpoint & export langsung ikut hidup** |
| C     | Bagian "hari ini" terpisah di dashboard                     | Manager tetap melihat dua angka berbeda untuk hal yang sama             |

**Implementasi opsi B:**

1. Service baru `app/Services/LaporanProjector.php`.
2. Dipanggil dari `TransaksiController::bayar()` **di dalam** `DB::transaction`
   yang sudah ada, setelah `earnPoinFor()`.

    **Harus sinkron — jangan dijadikan queued job.** Kebutuhan yang diminta
    adalah laporan yang bisa di-export _real-time_; kalau proyeksinya antre di
    queue, transaksi yang baru dibayar tidak muncul di file Excel yang di-download
    satu menit kemudian, dan tidak ada yang sadar datanya tertinggal. Beban satu
    `updateOrCreate` per item tidak sepadan dengan risiko itu.

3. Satu baris `laporan_transaksi` per baris `detail_transaksi`, mengikuti bentuk
   tabel yang sudah ada:

    | Kolom laporan                   | Sumber                                                                                 |
    | ------------------------------- | -------------------------------------------------------------------------------------- |
    | `kode`                          | `"TRX-{transaksi_id}-{detail_id}"` — deterministik, jadi idempoten                     |
    | `tanggal`                       | `waktu_lunas` dalam zona `Asia/Jakarta`                                                |
    | `platform`                      | `metode_bayar` transaksi (`cash`/`qris`)                                               |
    | `nama_pelanggan`, `no_wa`       | dari customer; `null` untuk walk-in                                                    |
    | `nama_produk`, `rasa`, `ukuran` | dari `menu`                                                                            |
    | `qty`                           | `detail.qty`                                                                           |
    | `harga_satuan`                  | `detail.harga_satuan`                                                                  |
    | `total`                         | `detail.subtotal - detail.diskon_nilai`                                                |
    | `poin_loyalty`                  | `point_earned` transaksi, dibagi rata ke item; sisa pembagian ditaruh di item terakhir |
    | `catatan`                       | `detail.catatan`                                                                       |
    | `kasir_user_id`                 | `dibayar_oleh`, jatuh ke `user_id` bila kosong                                         |
    | `kasir_nama`                    | Snapshot nama kasir saat itu                                                           |

    **Dua kolom kasir terakhir adalah kolom baru** pada `laporan_transaksi` —
    tabel itu sekarang sama sekali tidak menyimpan kasir, sehingga export
    per-kasir (C4) mustahil tanpa keduanya. Tambahkan lewat migrasi.

    **Batas datanya sudah ditetapkan pemilik produk, dan ini bukan cacat:**

    | Sumber baris                                     | Kasir            | Status                                          |
    | ------------------------------------------------ | ---------------- | ----------------------------------------------- |
    | Impor CSV Juni–Juli 2026 (`kode` bukan `TRX-`)   | `null`           | Diterima — data lama memang tidak merekam kasir |
    | Transaksi baru lewat SoyaCore (`kode` = `TRX-…`) | **Wajib terisi** | Mulai berlaku sejak fitur ini jalan             |

    Karena itu kolomnya `nullable`, tapi ada invarian yang harus dijaga dan
    diuji: **setiap baris berawalan `TRX-` wajib punya `kasir_user_id`.**
    Proyeksi hanya terjadi di `bayar()`, dan di sana selalu ada user terautentikasi
    — jadi baris baru ber-kasir kosong berarti ada yang bocor, bukan kondisi
    normal. Jangan diamkan.

    `kasir_user_id` untuk pengelompokan yang benar (dua kasir bisa bernama sama),
    `kasir_nama` sebagai snapshot supaya laporan lama tidak berubah isinya kalau
    kasir mengganti nama atau akunnya dihapus. Pola denormalisasi ini sudah
    dipakai kolom `nama_pelanggan` di tabel yang sama.

4. **Wajib idempoten** — pakai `updateOrCreate` berkunci `kode`. `bayar()` yang
   terpanggil dua kali tidak boleh menggandakan omzet.
5. Sediakan method `sinkronkan(Transaksi $t)` yang menulis ulang seluruh baris
   proyeksi milik satu transaksi (hapus lalu tulis ulang dari kondisi terkini),
   dan `hapus(Transaksi $t)` untuk transaksi yang dibatalkan penuh. Blok D
   memakai keduanya supaya omzet dashboard ikut terkoreksi saat ada pembatalan.
6. **Item reward (`is_reward = true`, `subtotal = 0`) tetap diproyeksikan**
   dengan `total = 0`. Alasannya: qty terjual harus jujur, dan minuman gratis
   tetap mengonsumsi stok.
7. Command artisan `laporan:proyeksi-ulang` yang membangun ulang proyeksi
   seluruh transaksi berstatus `lunas`. Ini jaring pengaman kalau proyeksi
   pernah gagal atau rumusnya berubah. Idempoten, aman dijalankan berkali-kali.
8. **Jangan menyentuh baris CSV historis.** Baris impor tidak berawalan `TRX-`,
   jadi keduanya bisa hidup berdampingan. Pastikan `laporan:proyeksi-ulang`
   hanya menghapus/menulis ulang baris berawalan `TRX-`.

### B2. Bar chart tanpa bucket "other"

Sumber bucket tak berlabel ada di `LaporanQuery`:

- `platform()` mengelompokkan `platform` termasuk baris yang `NULL` → muncul
  sebagai batang kosong/"other".
- `revenueUkuran()` sudah membuang `Cup`/`Pack`, tapi `ukuran` `NULL` (dessert)
  masih lolos.

Yang dikerjakan:

1. Beri label eksplisit `'Tidak diketahui'` untuk nilai `NULL`/string kosong —
   jangan biarkan frontend menerima key kosong.
2. Tambahkan query param `sembunyikan_tidak_diketahui=true` pada
   `GET /api/dashboard/platform` dan `GET /api/dashboard/revenue-ukuran` yang
   membuang bucket itu dari hasil **dan** dari perhitungan persentase.
3. Jangan menghapus datanya dari database — ini keputusan tampilan, dan angka
   ringkasan harus tetap bisa direkonsiliasi dengan total keseluruhan.

### B3. Tren penjualan: tanggal → hari

`LaporanQuery::timeSeries()` mengembalikan `periode` mentah (`'2026-07-28'`
untuk grain harian). Yang dibutuhkan label hari.

1. Tambahkan dua field pada tiap bucket, **tanpa menghapus `periode`** (dipakai
   sorting dan sebagai key stabil):
    - `periode_label` — siap tampil, Indonesia: `'Sen, 28 Jul'` (harian),
      `'28 Jul – 3 Agu'` (mingguan), `'Juli 2026'` (bulanan), `'2026'` (tahunan)
    - `hari` — nama hari penuh (`'Senin'`), diisi **hanya** untuk grain harian,
      `null` untuk grain lain
2. Nama hari dan bulan **di-hardcode sebagai array Indonesia** di satu Support
   class. Jangan bergantung pada locale server — di container produksi locale
   Indonesia sering tidak terpasang dan hasilnya diam-diam jadi bahasa Inggris.
3. Isi tanggal kosong: kalau suatu hari dalam rentang tidak punya transaksi,
   keluarkan bucket bernilai 0, bukan dilewati. Grafik tren yang melompati hari
   kosong membaca naik-turun yang tidak terjadi.

---

## BLOK C — Pembedaan data per akun kasir

### C0. Keputusan lingkup — JANGAN membangun mekanisme shift

Butir pembimbing berbunyi _"bikin perbedaan data kasir misalnya kasir 1 dan
kasir 2 pas pergantian shift"_. Pemilik produk sudah menegaskan maksudnya:

> **Pembedaannya cukup lewat akun kasir.** Kasir 1 dan Kasir 2 punya akun
> masing-masing; siapa pun yang sedang login, dialah yang tercatat melayani
> pemesanan dan transaksi itu.

Jadi **tidak ada** tabel `shift`, tidak ada tombol buka/tutup shift, tidak ada
modal awal, hitung kas fisik, maupun selisih kas. "Pergantian shift" di sini
artinya sekadar berganti akun yang login.

Yang tetap dibutuhkan hanya dua hal: **atribusi yang benar** ke akun kasir, dan
**laporan yang menyandingkan** antar kasir.

> Konsekuensi yang perlu diketahui: tanpa hitung kas fisik, sistem tidak bisa
> mendeteksi selisih laci. Itu memang di luar permintaan — cukup catat saja
> kalau nanti ditanya.

### C1. Dua peran kasir, dua kolom — jaring pengaman T6

Karena satu terminal hanya dipakai satu akun pada satu waktu, pembuat dan
penyelesai transaksi **hampir selalu akun yang sama**, dan penimpaan `user_id`
di `bayar()` tidak berakibat apa-apa.

Yang tersisa satu celah sempit tapi nyata: **pesanan berstatus `pending` yang
menyeberangi pergantian akun.** Kasir 1 membuat pesanan pukul 13:55, logout;
Kasir 2 login; pelanggan baru membayar pukul 14:05. Tanpa pemisahan kolom,
transaksi itu tercatat seolah Kasir 1 tidak pernah menyentuhnya, dan tidak ada
error apa pun yang muncul — datanya diam-diam salah.

Biayanya satu kolom nullable dan nol perubahan UI, jadi kerjakan sebagai jaring
pengaman. **Jangan diperlakukan sebagai fitur besar.**

Migrasi: kolom `dibayar_oleh` (FK `users`, **nullable**) pada tabel `transaksi`.
Nullable karena transaksi yang masih `pending` belum dibayar siapa pun.

| Kolom          | Arti                                         | Diisi saat                                    |
| -------------- | -------------------------------------------- | --------------------------------------------- |
| `user_id`      | Akun kasir **pembuat** pesanan               | `store()` — dan **tidak pernah ditimpa lagi** |
| `dibayar_oleh` | Akun kasir yang **menyelesaikan pembayaran** | `bayar()`                                     |

1. **Hapus `'user_id' => $request->user()->id` dari `bayar()`** dan ganti dengan
   `'dibayar_oleh' => $request->user()->id`. Ini inti perbaikannya — tanpa ini,
   membedakan Kasir 1 dan Kasir 2 tidak akan pernah akurat.
2. Pesanan SoyaScan punya `user_id = null` (memang tidak ada kasir pembuat),
   tapi `dibayar_oleh` tetap terisi saat kasir menerimanya di konter.
3. `TransaksiResource` mengekspos **keduanya** sebagai `kasir_pembuat` dan
   `kasir_penyelesai`. Pertahankan key `kasir` yang sudah ada supaya frontend
   lama tidak rusak; isi dengan penyelesai bila ada, jatuhkan ke pembuat bila
   belum dibayar.
4. **Pembatalan** (Blok D) tercatat atas akun yang memprosesnya, lewat kolom
   `user_id` di tabel `pembatalan` — bukan atas akun pembuat penjualan aslinya.

### C2. Penjualan dihitung ke akun siapa

> **Penjualan dihitung ke akun kasir yang menyelesaikan pembayarannya**
> (`dibayar_oleh`), karena di titik itulah transaksi benar-benar terjadi.

- Skenario pergantian: pesanan dibuat Kasir 1 pukul 13:55, dibayar Kasir 2 pukul
  14:05 → omzet masuk ke **Kasir 2**, sementara `user_id` tetap merekam bahwa
  Kasir 1 yang menyusunnya. Tidak ada informasi yang hilang.
- Laporan memotong berdasarkan **`waktu_lunas`**, bukan `created_at` —
  transaksi yang dibuat kemarin malam dan dibayar pagi ini masuk ke hari ini.
  Konsisten dengan aturan WIB di **T5**.
- Transaksi berstatus `pending` belum masuk laporan kasir mana pun. Ia belum
  jadi penjualan.

### C3. Laporan perbandingan kasir — inti permintaan pembimbing

Ini deliverable utamanya: satu endpoint yang menyandingkan kasir berdampingan,
supaya manager tidak perlu membuka satu per satu lalu membandingkan sendiri.

`GET /api/laporan/kasir` — **manager saja**.

Query param: `tanggal_mulai`, `tanggal_selesai`, `preset` — **pakai ulang aturan
dan validasi dari Blok A2**, jangan menulis parser tanggal kedua.

Response: satu baris per akun kasir dalam rentang itu, diurutkan omzet menurun:

```
user_id, nama
jumlah_transaksi, total_omzet, total_qty, rata_rata_transaksi
rincian_metode_bayar : { cash: {jumlah, total}, qris: {jumlah, total} }
total_diskon, total_poin_diberikan, total_poin_ditukar
jumlah_pembatalan, nilai_dibatalkan          (setelah Blok D)
jumlah_transaksi_dibuat_kasir_lain
```

Plus blok `meta` berisi total seluruh kasir, supaya angkanya bisa direkonsiliasi
dengan dashboard.

Dua kolom yang gampang terlewat tapi justru paling berguna:

- **`jumlah_transaksi_dibuat_kasir_lain`** — transaksi yang omzetnya masuk ke
  kasir ini tapi `user_id`-nya kasir lain. Inilah angka yang menunjukkan serah
  terima pesanan saat pergantian; tanpa itu, laporan Kasir 2 terlihat seolah
  semua pesanan dia yang buat.
- **`jumlah_pembatalan`** — pembatalan berlebih dari satu akun adalah pola yang
  perlu terlihat, dan inilah gunanya alasan pembatalan diwajibkan di Blok D.

### C4. Export Excel per kasir

> Bergantung pada kolom `kasir_user_id` + `kasir_nama` yang ditambahkan di
> **Blok B1**. Tanpa keduanya bagian ini tidak bisa dikerjakan sama sekali.

Kebutuhan yang diminta: **file Excel harus memuat rincian tiap kasir, pada
tanggal yang akurat, dan mencerminkan kondisi terkini saat di-download.**

Ketiganya sudah terjawab oleh pekerjaan sebelumnya, asal disambungkan:

| Kebutuhan      | Dijamin oleh                                           |
| -------------- | ------------------------------------------------------ |
| Per kasir      | Kolom kasir baru di `laporan_transaksi` (B1)           |
| Tanggal akurat | Proyeksi memakai `waktu_lunas` dalam WIB (T5)          |
| Real-time      | Proyeksi sinkron di `bayar()`, bukan queue (B1 poin 2) |

Yang dikerjakan:

1. **`DetailTransaksiSheet`** — tambah kolom **"Kasir"** setelah "Platform".
   Baris impor CSV historis yang tidak punya kasir diisi
   `'—'`, jangan dibiarkan kosong: sel kosong terbaca sebagai data hilang,
   sedangkan ini memang transaksi dari sebelum SoyaCore dipakai.
2. **Sheet baru `RekapKasirSheet`** — inilah inti permintaannya. Satu baris per
   kombinasi **tanggal × kasir**, diurutkan tanggal lalu nama.

    Baris impor Juni–Juli yang tidak punya kasir **tetap dimasukkan** di bawah
    label `'— (data historis)'`, bukan dibuang. Alasannya: kalau dibuang, total
    di sheet ini tidak akan pernah cocok dengan `Ringkasan`, dan manager yang
    menjumlahkan sendiri akan mengira ada data hilang. Angka yang tidak bisa
    direkonsiliasi lebih berbahaya daripada satu baris berlabel jujur.

    Kolomnya:

    | Kolom                                                                                                                                                                                                                    |
    | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
    | Tanggal · Kasir · Jumlah Transaksi · Total Qty · Total Omzet (Rp) · Rata-rata per Transaksi (Rp) · Cash (Rp) · QRIS (Rp) · Total Diskon (Rp) · Poin Diberikan · Poin Ditukar · Jumlah Pembatalan · Nilai Dibatalkan (Rp) |

    Tambahkan baris **TOTAL** di akhir tiap tanggal, dan satu baris total
    keseluruhan — manager membaca file ini tanpa membuat pivot sendiri.

3. Daftarkan sheet baru itu di `LaporanExport::sheets()`, letakkan **setelah**
   `RingkasanSheet` supaya terlihat lebih dulu daripada sheet detail.
4. **`LaporanRequest`** — tambah aturan `kasir_user_id`
   (`nullable|integer|exists:users,id`) dan accessor `kasirUserId()`, mengikuti
   pola `startInput()`/`grain()` yang sudah ada.
5. `GET /api/laporan/export?kasir_user_id=` menyaring **seluruh sheet** ke satu
   kasir, dan namanya masuk ke nama file
   (`Laporan_SoyaCore_harian_2026-07-01_2026-07-31_Adrian.xlsx`). Tanpa param
   ini, semua kasir ikut seperti sekarang.
6. Kolom `Tanggal` di semua sheet diformat `Y-m-d` **dalam WIB**, konsisten
   dengan T5. Ini sumber ketidakcocokan yang paling sering terjadi antara angka
   di layar dan angka di Excel.

### C5. Filter per kasir di daftar transaksi

`GET /api/transaksi` sudah punya `?user_id=`. Perluas agar bisa menyaring kedua
peran, karena keduanya pertanyaan yang berbeda:

| Query param    | Arti                                                                                                                                                                 |
| -------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `dibuat_oleh`  | Transaksi yang **disusun** akun ini                                                                                                                                  |
| `dibayar_oleh` | Transaksi yang **diselesaikan** akun ini                                                                                                                             |
| `user_id`      | Alias lama — perlakukan sebagai `dibayar_oleh`, jatuhkan ke `user_id` untuk transaksi `pending`. Pertahankan supaya kartu statistik kasir yang sudah ada tidak rusak |

⚠️ **Daftar pesanan `pending` tidak boleh difilter ke akun sendiri.**

Saat akun berganti, pesanan yang belum dibayar harus tetap terlihat oleh kasir
yang baru login — kalau tidak, Kasir 2 tidak akan menemukan pesanan Kasir 1 dan
pelanggan terlantar di depan konter dengan minuman yang sudah dibuat.

Kasir 2 **tidak perlu memasukkan ulang data apa pun**: transaksi tersimpan di
database, bukan di sesi login. Customer, item, diskon, dan redeem semuanya masih
menempel. Cukup buka pesanan itu lalu Tandai Lunas — yang berubah hanya
`status`, `waktu_lunas`, dan `dibayar_oleh`.

Karena itu: filter `user_id`/`dibuat_oleh` di daftar transaksi harus tetap
**opsional dan default mati**. Kalau frontend memasangnya sebagai default untuk
"transaksi milik sendiri", pastikan itu hanya berlaku di kartu statistik, bukan
di antrean pesanan pending. Catat hal ini di ringkasan akhir supaya tim frontend
ikut tahu.

---

## BLOK D — Pembatalan / Koreksi Pesanan

> Bergantung pada Blok B1 (proyeksi laporan) dan Blok C (atribusi akun kasir).

### D0. Yang dimaksud, dan yang TIDAK dimaksud

Butir pembimbing berbunyi _"bikin yang bisa refund untuk salah pesanan"_ dengan
contoh Loyverse POS. Pemilik produk sudah menegaskan artinya:

> **Ini pembatalan/koreksi pesanan yang salah, BUKAN pengembalian uang.**

Konsekuensinya untuk penamaan dan model data — ikuti ini dengan konsisten:

- Jangan pakai istilah `refund` di nama tabel, kolom, endpoint, maupun pesan
  error. Pakai **`pembatalan`**.
- Nilainya dicatat sebagai **`nilai_dibatalkan`**, bukan `total_refund`.
  Maknanya _"penjualan sebesar ini tidak jadi"_, bukan _"uang sebesar ini
  dikembalikan"_.
- **Tidak ada** alur kas keluar, tidak ada metode pengembalian dana, tidak ada
  integrasi payment gateway.

Nilainya tetap wajib dicatat karena omzet dashboard dan laporan kasir harus ikut
terkoreksi — penjualan yang dibatalkan tidak boleh tetap terhitung.

### D1. ⚠️ Bug yang sudah ada dan harus ikut diperbaiki di sini

`LoyaltyService::redeemPoin()` **langsung memotong saldo poin pelanggan saat
redeem**, dan redeem hanya boleh pada transaksi berstatus `pending`. Sementara
itu `TransaksiController::batal()` yang ada sekarang hanya mengubah status
menjadi `batal` — **poin yang sudah terpotong tidak pernah dikembalikan.**

Artinya hari ini: pelanggan menukar 350 poin untuk gratis Original, pesanannya
dibatalkan sebelum bayar, poinnya hilang dan minumannya tidak dapat.

Jadi asumsi _"kalau dibatalkan, poinnya belum kehitung"_ hanya **separuh
benar**, dan bagian yang tidak benar itu justru merugikan pelanggan:

| Jenis poin                       | Kapan berubah                                       | Saat pembatalan                                                         |
| -------------------------------- | --------------------------------------------------- | ----------------------------------------------------------------------- |
| Poin **earn** (dari belanja)     | Saat **lunas** (`loyalty_applied_at` terisi)        | Pending → belum ada, tidak ada yang ditarik. Lunas → tarik proporsional |
| Poin **redeem** (ditukar reward) | Saat **redeem**, sudah dipotong walau masih pending | **Selalu dikembalikan utuh**                                            |

Aturan finalnya jadi sederhana dan otomatis benar untuk kedua kasus:

> **Tarik poin earn hanya jika `loyalty_applied_at !== null`.
> Kembalikan poin redeem kapan pun `kode_redeem` terisi.**

### D2. Prinsip

- **Transaksi asli tidak pernah dihapus atau diubah isinya.** Ia hanya berubah
  status, dan pembatalannya dicatat sebagai dokumen tersendiri — supaya selalu
  bisa ditelusuri siapa membatalkan apa, kapan, dan kenapa.
- Berlaku untuk transaksi berstatus **`pending` maupun `lunas`**. Yang sudah
  dibatalkan tidak bisa dibatalkan lagi.
- Bisa **penuh** atau **sebagian** (pilih item dan qty) — kasus paling umum:
  pesan 3 item, yang salah cuma 1.
- **Alasan wajib diisi.** Ini satu-satunya pagar terhadap penyalahgunaan;
  tanpa alasan, pembatalan jadi cara menghapus penjualan tanpa jejak.

### D3. Tabel

Migrasi `pembatalan`:

| Kolom               | Tipe            | Keterangan                                                             |
| ------------------- | --------------- | ---------------------------------------------------------------------- |
| `id`                | id              |                                                                        |
| `transaksi_id`      | FK transaksi    |                                                                        |
| `user_id`           | FK users        | Akun kasir yang memproses pembatalan — bukan pembuat penjualan aslinya |
| `alasan`            | string, wajib   |                                                                        |
| `nilai_dibatalkan`  | unsignedInteger | Nilai penjualan yang gugur                                             |
| `poin_ditarik`      | unsignedInteger | Poin earn yang dibatalkan                                              |
| `poin_dikembalikan` | unsignedInteger | Poin redeem yang dikembalikan                                          |
| `timestamps`        |                 |                                                                        |

Migrasi `pembatalan_item`: `pembatalan_id`, `detail_transaksi_id`, `qty`,
`nilai_dibatalkan`.

Migrasi tambahan: status `transaksi` menerima nilai baru **`batal_sebagian`**.
Status `batal` yang sudah ada dipakai untuk pembatalan penuh — jangan membuat
status baru untuk itu.

### D4. Aturan perhitungan — bagian paling rawan

1. **Qty kumulatif dijaga.** Total qty yang dibatalkan untuk satu
   `detail_transaksi` tidak boleh melebihi qty aslinya, **dihitung lintas semua
   pembatalan sebelumnya**, bukan hanya request ini. Tolak `422`
   `qty_pembatalan_melebihi`.
2. **Nilai per item dihitung setelah diskon**, bukan dari `harga_satuan` mentah:
    ```
    nilai_dibatalkan = (subtotal - diskon_nilai) × (qty_dibatalkan ÷ qty_asli)
    ```
    Memakai harga mentah membuat omzet terkoreksi lebih besar daripada yang
    pernah tercatat, dan dashboard jadi minus.
3. **Poin earn** ditarik proporsional terhadap nilai yang dibatalkan, dibulatkan
   ke bawah, dan **hanya jika `loyalty_applied_at !== null`**. Saldo boleh
   menjadi 0 tapi **tidak boleh negatif** — kalau pelanggan sudah membelanjakan
   poinnya, kekurangannya ditanggung toko. Menagih poin negatif memicu komplain
   yang lebih mahal daripada selisihnya.
4. **Poin redeem dikembalikan utuh** setiap kali pembatalan menggugurkan
   redemption-nya (lihat D1). Yang menggugurkan:
    - pembatalan **penuh**, selalu; atau
    - pembatalan **sebagian yang menyertakan item reward** (`is_reward`).

    Pembatalan sebagian yang **tidak** menyentuh item reward tidak mengembalikan
    poin — rewardnya memang tetap diterima pelanggan.

    Saat poin redeem dikembalikan, kosongkan juga `kode_redeem`, `poin_ditukar`,
    dan `maks_potongan` pada transaksi, lalu hitung ulang totalnya — kalau tidak,
    diskon dari reward yang sudah digugurkan akan tetap menempel.

5. **Proyeksi laporan disinkronkan**: panggil `LaporanProjector::sinkronkan()`
   (sebagian) atau `hapus()` (penuh) supaya omzet dashboard ikut turun.
6. Seluruhnya dalam satu `DB::transaction` dengan `lockForUpdate()` pada saldo
   loyalty.
7. Transaksi yang sudah `batal` (penuh) menolak pembatalan berikutnya dengan
   `409`.

### D5. Endpoint

| Method & path                                | Role           | Keterangan                                      |
| -------------------------------------------- | -------------- | ----------------------------------------------- |
| `POST /api/transaksi/{transaksi}/pembatalan` | kasir, manager | Penuh atau sebagian                             |
| `GET /api/transaksi/{transaksi}/pembatalan`  | kasir, manager | Riwayat pembatalan transaksi itu                |
| `GET /api/pembatalan`                        | manager        | Semua pembatalan; filter tanggal dan akun kasir |

Body `POST`:

```json
{
    "alasan": "Pelanggan salah pesan ukuran",
    "items": [{ "detail_transaksi_id": 12, "qty": 1 }]
}
```

`items` kosong atau tidak dikirim = pembatalan **penuh**.

Response memuat rincian per item, `nilai_dibatalkan`, `poin_ditarik`,
`poin_dikembalikan`, status transaksi setelahnya, dan **saldo poin pelanggan
terkini** — kasir perlu menyebutkannya ke pelanggan saat itu juga.

**Endpoint lama `POST /api/transaksi/{transaksi}/batal` tetap dipertahankan**
sebagai alias pembatalan penuh supaya frontend yang ada tidak rusak, tapi
sekarang ikut melewati alur baru: mengembalikan poin redeem, mencatat dokumen
pembatalan, dan menyinkronkan proyeksi. Alasan boleh kosong pada alias ini —
isi dengan `'Dibatalkan lewat endpoint lama'` supaya kolomnya tetap jujur.

---

## BLOK E — SoyaScan

### E1. Pilihan sugar dan ice

> Butuh `GolonganUkuran` dari **Blok F1** — kerjakan F1 lebih dulu, atau buat
> Support class-nya di sini dan pakai bersama. Jangan menulis dua klasifikasi
> ukuran yang berbeda.

**Pilihan yang berlaku** (sudah dikonfirmasi pemilik produk, jangan diubah):

|           | Kode     | Label       |
| --------- | -------- | ----------- |
| **Sugar** | `normal` | Normal      |
|           | `less`   | Less Sugar  |
|           | `no`     | No Sugar    |
|           | `extra`  | Extra Sugar |
| **Ice**   | `normal` | Normal      |
|           | `less`   | Less Ice    |
|           | `no`     | No Ice      |
|           | `extra`  | Extra Ice   |

**Ketersediaan per ukuran** — ini aturannya, turunkan dari `GolonganUkuran`,
jangan dari daftar nama menu:

| Ukuran                            | Sugar | Ice | Alasan                                            |
| --------------------------------- | ----- | --- | ------------------------------------------------- |
| `Hot`                             | ✅    | ❌  | Minuman panas, es tidak relevan                   |
| `Reguler`, `Large`                | ✅    | ✅  | Diracik per pesanan                               |
| `250ml`, `500ml`, `1000ml`        | ❌    | ❌  | Kemasan botol diproduksi batch, bukan per pesanan |
| kosong/`null` (Dessert & Cookies) | ❌    | ❌  | Bukan minuman                                     |

Yang dikerjakan:

1. Support class `app/Support/OpsiMinuman.php` berisi kedua daftar dan aturan
   ketersediaan di atas. **Satu sumber kebenaran** — jangan menyalin daftarnya
   ke FormRequest maupun resource.
2. Migrasi: kolom `level_sugar` dan `level_ice` (`string`, nullable) pada
   `detail_transaksi`. Nullable = item lama, kemasan botol, dan dessert.
3. Validasi di `StoreOrderRequest` (per item) **dan** `TransaksiItemController`
   — kasir harus bisa mencatat hal yang sama seperti pelanggan SoyaScan.
4. **Tolak pilihan yang tidak relevan** dengan `422`, jangan diam-diam
   diabaikan: mengirim `level_ice` untuk menu `Hot` atau kemasan botol adalah
   kesalahan yang harus terlihat, bukan data yang disimpan lalu membingungkan
   barista. Kode error: `opsi_tidak_tersedia`.
5. `GET /api/menu` menyertakan:
    - `meta.opsi_sugar` dan `meta.opsi_ice` berisi daftar `{kode, label}` —
      frontend merender dari sini, tidak menyalin daftarnya sendiri
    - per menu: `bisa_pilih_sugar` dan `bisa_pilih_ice` (boolean), supaya
      frontend cukup membaca flag dan tidak perlu tahu aturan ukurannya
6. Tampilkan `level_sugar` dan `level_ice` di `DetailTransaksiResource` —
   kasir dan barista harus melihatnya saat menyiapkan pesanan, dan keduanya
   ikut tercetak di nota.

### E2. Hapus nomor meja

Lihat temuan **T3**. Sudah dikonfirmasi: **hapus sepenuhnya**, termasuk
kolomnya — SoyaScan masih dalam revisi dan belum berjalan produksi, jadi tidak
ada riwayat yang perlu dijaga.

1. `StoreOrderRequest` — hapus aturan `nomor_meja`.
2. `OrderService::buatOrder()` — berhenti menulis `nomor_meja`; sesuaikan juga
   docblock `@param` di atasnya yang masih mencantumkannya.
3. `OrderController::store()` — hapus `nomor_meja` dari response, termasuk baris
   `$nomorMeja = $transaksi->detailTransaksi->first()?->nomor_meja;`.
4. `DetailTransaksi` — keluarkan `nomor_meja` dari `$fillable`.
5. Migrasi baru: **drop kolom `nomor_meja`** dari `detail_transaksi`.
   `down()` harus mengembalikan kolomnya (`string`, nullable) supaya rollback
   tetap sah — datanya memang tidak kembali, dan itu diterima.
6. Pastikan tidak ada sisa referensi: cari `nomor_meja` di seluruh `app/`,
   `tests/`, dan `docs/` sebelum menyatakan selesai.
7. Perbarui `docs/kontrak-api-v1.md` — tandai sebagai **perubahan kontrak**,
   jangan cuma dihapus diam-diam.
8. Sesuaikan test yang mengirim `nomor_meja` (`tests/Feature/OrderApiTest.php`),
   dan **tambahkan** test bahwa request tanpa `nomor_meja` kini diterima `201`.

### E3. QRIS saat pembayaran

1. Migrasi: kolom `qris_gambar` (`string`, nullable) pada `pengaturan_toko` —
   menyimpan path, bukan berkas.
2. `POST /api/pengaturan/toko/qris` — upload, **manager saja**. Validasi
   `image|mimes:jpg,jpeg,png|max:2048`. Simpan ke disk `public`
   (`storage/app/public/qris`), pastikan `php artisan storage:link` disebut di
   README/dokumen deploy.
3. `GET /api/pengaturan/toko` menyertakan `qris_url` (URL penuh, `null` kalau
   belum diunggah).
4. `DELETE /api/pengaturan/toko/qris` — manager, untuk mengganti/menghapus.
   Hapus juga berkas lama saat diganti supaya storage tidak menumpuk.
5. Response `POST /api/order` menyertakan `qris_url` **hanya** ketika
   `metode_bayar = 'qris'`, supaya halaman pembayaran SoyaScan bisa langsung
   menampilkannya.

> QRIS statis milik merchant hanyalah gambar — backend tidak memvalidasi,
> membaca, atau memproses pembayaran apa pun. Jangan menambahkan integrasi
> payment gateway; itu di luar lingkup.

### E4. QR untuk scan menu

1. Tambah dependensi: `composer require simplesoftwareio/simple-qrcode`.
2. Tambah konfigurasi URL publik SoyaScan (`config/soyascan.php` dengan
   `env('SOYASCAN_URL')`, fallback ke `config('app.url')`). **Jangan hardcode
   domain di kode.**
3. `GET /api/pengaturan/toko/qr-menu` — manager saja. Query param
   `format=svg|png` (default `svg`, karena akan dicetak dan harus tajam) dan
   `ukuran` (px, default 512, batasi maksimum 2048).
4. Response mengembalikan berkas gambar dengan `Content-Type` yang sesuai,
   bukan JSON berisi base64 — supaya manager bisa langsung menyimpan/mencetak
   dari browser.
5. Tambahkan test yang memastikan endpoint mengembalikan `200` dengan
   `Content-Type` benar, dan `403` untuk kasir.

---

## BLOK F — Menu cup vs botol

Butir "edit menu ukuran sebelah kiri buat cup dan sebelah kanan buat botol"
adalah tata letak (frontend), tapi frontend butuh backend memberi tahu **ukuran
mana termasuk golongan apa**. Jangan biarkan frontend menebak dari string.

Nilai `ukuran` yang benar-benar ada di seeder:

| Golongan  | Nilai                             |
| --------- | --------------------------------- |
| `cup`     | `Hot`, `Reguler`, `Large`         |
| `botol`   | `250ml`, `500ml`, `1000ml`        |
| `lainnya` | `''` / `null` (Dessert & Cookies) |

1. Support class `app/Support/GolonganUkuran.php` dengan method
   `dari(?string $ukuran): string` dan `semua(): array`.
   **Blok E1 ikut memakainya** untuk menentukan ketersediaan sugar/ice — kalau
   F1 dikerjakan belakangan, pastikan E1 memanggil class yang sama, bukan
   membuat klasifikasi ukuran kedua yang bisa lepas sinkron.
2. Ekspos `golongan_ukuran` di `MenuResource` dan di `GET /api/menu`.
3. `GET /api/menu-internal` menerima param `golongan=cup|botol|lainnya`.
4. Urutan ukuran di dalam golongan **jangan alfabetis** — `orderBy('ukuran')`
   sekarang menghasilkan `1000ml, 250ml, 500ml, Hot, Large, Reguler`, yang
   membingungkan. Definisikan urutan eksplisit
   (`Hot → Reguler → Large → 250ml → 500ml → 1000ml`) dan pakai di
   `MenuController::katalog()` maupun `index()`.

---

## 4. Dokumentasi

Perbarui bersamaan dengan kodenya, jangan ditumpuk di akhir:

- `docs/kontrak-api-v1.md` — `nomor_meja` dihapus (**tandai sebagai perubahan
  kontrak**), `level_sugar` + `level_ice` ditambahkan beserta aturan
  ketersediaannya per ukuran, `qris_url` di response order.
- `docs/kontrak-api-kasir-v1-draft.md` — filter transaksi baru, laporan kasir,
  endpoint pembatalan.
- **Dokumen baru** `docs/laporan-kasir.md` — perbedaan `user_id` (pembuat) vs
  `dibayar_oleh` (penyelesai) dan kenapa keduanya perlu, aturan "penjualan
  dihitung ke akun penyelesai pembayaran", contoh lengkap skenario pergantian
  akun kasir (termasuk bahwa kasir baru **tidak perlu input ulang apa pun**),
  serta isi sheet `Rekap Kasir` dan arti tiap kolomnya. Sebut juga secara
  eksplisit bahwa **tidak ada mekanisme shift** (buka/tutup, modal awal, hitung
  kas) — supaya tidak ada yang mengira fitur itu terlewat dikerjakan.
- **Dokumen baru** `docs/pembatalan-pesanan.md` — tegaskan di paragraf pembuka
  bahwa ini pembatalan pesanan, **bukan pengembalian uang**. Lalu: aturan qty
  kumulatif, rumus nilai proporsional setelah diskon, dan tabel perilaku poin
  (earn ditarik hanya bila sudah lunas; redeem selalu dikembalikan utuh).
  Cantumkan alasannya, bukan cuma rumusnya.
- `docs/pengaturan-loyalty.md` — tambahkan catatan bahwa poin redeem
  dikembalikan saat pesanan dibatalkan, karena dokumen itu yang menjelaskan
  alur poin.
- `README.md` — sebut `php artisan storage:link` dan command
  `laporan:proyeksi-ulang`.

---

## 5. Test

Feature test di `tests/Feature/`, gaya mengikuti `LoyaltyPengaturanTest.php`.

**Blok A**

- Filter rentang tanggal inklusif di kedua ujung
- `tanggal_mulai > tanggal_selesai` ditolak `422`
- Tiap preset menghasilkan rentang yang benar
- Filter gabungan (`status` + `sumber` + `cari`) mempersempit dengan benar
- `meta` ringkasan cocok dengan hasil terfilter, bukan seluruh tabel
- `sumber` terisi benar dari kasir maupun SoyaScan, dan backfill baris lama jalan

**Blok B**

- Transaksi lunas muncul di `GET /api/dashboard/ringkasan`
- `bayar()` dipanggil dua kali tidak menggandakan omzet (idempotensi)
- Item reward terproyeksi dengan `total = 0` tapi `qty` tetap terhitung
- `laporan:proyeksi-ulang` tidak menyentuh baris CSV historis
- `sembunyikan_tidak_diketahui` membuang bucket `NULL`
- `periode_label` dan `hari` berbahasa Indonesia dan benar isinya
- Hari tanpa transaksi tetap muncul sebagai bucket bernilai 0

**Blok C — pergantian akun kasir (inti permintaan pembimbing)**

- **Kasir 1 membuat pesanan, Kasir 2 menandai lunas** → `user_id` tetap Kasir 1,
  `dibayar_oleh` Kasir 2. Ini regression test untuk **T6**; sebelum perbaikan,
  `user_id` ikut berubah jadi Kasir 2 dan jejak Kasir 1 hilang
- Omzet transaksi itu masuk ke laporan **Kasir 2**, bukan Kasir 1
- Laporan Kasir 2 menghitungnya di `jumlah_transaksi_dibuat_kasir_lain`
- Pesanan SoyaScan yang dibayar Kasir 2 masuk ke Kasir 2, `user_id` tetap `null`
- Pembatalan yang diproses Kasir 2 atas penjualan Kasir 1 tercatat atas Kasir 2
- Laporan dipotong berdasarkan `waktu_lunas`: transaksi dibuat kemarin malam,
  dibayar pagi ini → masuk hari ini
- Transaksi `pending` tidak muncul di laporan kasir mana pun
- `GET /api/laporan/kasir` mengembalikan satu baris per akun, terurut omzet
  menurun, dan `meta` totalnya cocok dengan penjumlahan barisnya; kasir `403`
- Filter `?dibuat_oleh=` dan `?dibayar_oleh=` memberi hasil berbeda untuk
  transaksi yang berpindah tangan
- **Pesanan `pending` milik Kasir 1 tetap muncul di `GET /api/transaksi?status=pending`
  saat dipanggil dengan token Kasir 2** — regression test untuk jebakan di C5
- Kasir 2 bisa `bayar()` pesanan yang dibuat Kasir 1 tanpa mengirim ulang data
  customer maupun item; isi transaksi tidak berubah selain status,
  `waktu_lunas`, dan `dibayar_oleh`

**Blok C — export Excel per kasir**

- Kolom `kasir_user_id` + `kasir_nama` terisi di `laporan_transaksi` setelah
  `bayar()`, dan **langsung terbaca di export detik itu juga** (bukti proyeksi
  sinkron, bukan queue)
- Baris impor CSV historis tetap ber-kasir `null` dan tampil `'—'` di Excel
- **Invarian: tidak ada satu pun baris berawalan `TRX-` yang `kasir_user_id`-nya
  `null`** — jalankan sebagai assertion setelah beberapa transaksi dibayar
- Sheet `Rekap Kasir` memuat baris `'— (data historis)'`, dan total
  keseluruhannya cocok dengan total di sheet `Ringkasan`
- Sheet `Rekap Kasir` ada di file, satu baris per tanggal × kasir
- Baris TOTAL per tanggal dan total keseluruhan jumlahnya cocok dengan
  penjumlahan barisnya
- `?kasir_user_id=` menyaring **semua** sheet, bukan cuma sheet rekap
- Nama kasir masuk ke nama file saat difilter
- Kolom Tanggal di Excel memakai WIB — transaksi 23:30 tanggal 5 tertulis
  tanggal 5

**Blok D**

- Pembatalan penuh transaksi **lunas**: status `batal`, omzet dashboard turun
- Pembatalan sebagian: status `batal_sebagian`, nilainya proporsional
- Qty melebihi asli ditolak `422`, termasuk **akumulasi** beberapa pembatalan
- Item berdiskon menghasilkan nilai setelah diskon, bukan harga mentah
- Poin earn ditarik proporsional dan **tidak pernah negatif**
- **Transaksi `pending` yang dibatalkan tidak menarik poin earn sama sekali**
  (`loyalty_applied_at` masih `null`) — ini regression test untuk D1
- **Transaksi `pending` ber-redeem yang dibatalkan mengembalikan poin redeem
  secara utuh**, dan `kode_redeem` ikut dikosongkan — ini bug yang diperbaiki
- Pembatalan penuh transaksi lunas ber-redeem mengembalikan poin redeem utuh
- Pembatalan sebagian tanpa item reward **tidak** mengembalikan poin redeem
- Alasan kosong ditolak `422`
- Transaksi yang sudah `batal` menolak pembatalan kedua dengan `409`
- Endpoint lama `/batal` tetap jalan dan kini ikut mengembalikan poin redeem
- Pembatalan tercatat di laporan kasir yang memprosesnya, dan menurunkan
  `total_omzet` akun itu

**Blok E & F**

- `POST /api/order` tanpa `nomor_meja` diterima `201`; tidak ada sisa referensi
  `nomor_meja` di seluruh `app/`
- `level_sugar`/`level_ice` di luar daftar ditolak `422`
- Menu `Hot`: sugar diterima, `level_ice` ditolak `422` `opsi_tidak_tersedia`
- Kemasan botol (`500ml`): sugar **dan** ice sama-sama ditolak
- `Reguler`: keduanya diterima
- Dessert: `bisa_pilih_sugar` dan `bisa_pilih_ice` keduanya `false`
- `meta.opsi_sugar` dan `meta.opsi_ice` muncul di `GET /api/menu`
- `level_sugar`/`level_ice` ikut muncul di detail transaksi kasir
- Upload QRIS: manager `200`, kasir `403`; berkas non-gambar `422`
- `qris_url` muncul di response order hanya saat `metode_bayar = qris`
- `qr-menu` mengembalikan `Content-Type` benar; kasir `403`
- `golongan_ukuran` benar untuk cup, botol, dan dessert
- Urutan ukuran mengikuti urutan eksplisit, bukan alfabetis

**Zona waktu (T5) — lintas blok**

- Transaksi pukul 23:30 WIB tanggal 5 muncul di filter `tanggal=2026-xx-05`,
  bukan tanggal 6
- Transaksi yang sama masuk ke bucket tanggal 5 di `timeSeries` dan di laporan
  kasir

---

## 6. Definition of done

```bash
php artisan migrate:fresh --seed && php artisan test
```

- Seluruh test lama tetap lulus, kecuali yang memang harus disesuaikan karena
  perubahan kontrak (`OrderApiTest` untuk `nomor_meja`) — perubahan itu harus
  disengaja dan disebut di ringkasan akhir.
- Setiap migrasi bisa `migrate:rollback` tanpa error.
- Tidak ada perubahan di `resources/views`, `resources/js`, atau `resources/css`.
- `./vendor/bin/pint` bersih.
- Tulis ringkasan akhir berisi: endpoint baru, perubahan kontrak yang
  memengaruhi frontend, dan hasil pengecekan timezone (**T5**).

---

## 7. Di luar lingkup

- Seluruh pekerjaan frontend: sidebar (arah panah maupun logo), tata letak dua
  kolom edit menu, interaktivitas klik di halaman laporan, tampilan chart.
- Integrasi payment gateway. QRIS di sini hanya gambar statis milik merchant.
- **Pengembalian uang.** Blok D adalah pembatalan pesanan, bukan refund dana —
  tidak ada alur kas keluar, tidak ada pencatatan metode pengembalian.
- WebSocket/broadcasting untuk dashboard real-time. Blok B1 membuat data benar
  saat halaman dimuat ulang; live-push adalah kebutuhan terpisah dan belum
  diminta.
- Mengubah angka atau perilaku loyalty v2 yang baru selesai.
- Menghapus tabel/kolom lama demi kerapian. Kolom `nomor_meja` dan
  `detail_transaksi.sumber` tetap tinggal.
