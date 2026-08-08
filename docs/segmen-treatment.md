# Pola Treatment per Segmen Pelanggan

> Menutup celah yang muncul di review pembimbing: *"setiap segmentasi pelanggan
> harus dipahami pola treatment-nya seperti apa saja."* Sebelum ini
> `GET /api/dashboard/rfm` hanya mengembalikan nama segmen dan jumlah
> anggotanya, jadi manager tahu siapa masuk segmen mana tapi tidak tahu harus
> berbuat apa. **Segmentasi yang tidak berujung tindakan sama saja dengan tidak
> ada.**

Sumber kebenarannya di kode: [`app/Support/SegmenTreatment.php`](../app/Support/SegmenTreatment.php).
Dokumen ini menjelaskan **kenapa** tiap pola dipilih, karena alasan itulah yang
tidak muat di kode dan paling mudah tergeser tanpa sengaja.

Tampil di dua tempat, isinya sama:

| Tempat | Bentuk |
|---|---|
| `GET /api/dashboard/rfm` | blok `segmen_treatment` |
| Export Excel | sheet **Segmen & Treatment** |

---

## 1. Segmennya dari mana

Empat segmen dihitung [`RfmQuery`](../app/Services/RfmQuery.php) dari
`laporan_transaksi`, bukan dibaca dari snapshot. Aturannya berurutan dan
urutannya mengikat:

```
frequency <= 1            → Pelanggan Baru
r_score   <= 2            → Butuh Perhatian
monetary  >= Rp 150.000   → Loyal
selain itu                → Potensial
```

Pelanggan yang sudah lama tidak datang masuk **Butuh Perhatian lebih dulu,
sebesar apa pun belanjanya**, karena itulah yang perlu ditindaklanjuti. Pembelanja
besar yang menghilang bukan pelanggan Loyal, ia pelanggan Loyal yang sedang
hilang, dan memperlakukannya sebagai Loyal berarti tidak melakukan apa-apa saat
justru paling perlu.

Komposisi pada data Juni–Juli 2026 (345 pelanggan):

| Segmen | Jumlah | Porsi |
|---|---:|---:|
| Pelanggan Baru | 122 | 35% |
| Butuh Perhatian | 108 | 31% |
| Potensial | 94 | 27% |
| Loyal | 21 | 6% |

Angka ini bergerak sendiri seiring transaksi baru masuk, dan ikut menyempit
kalau manager memilih rentang tanggal tertentu.

---

## 2. Prinsip yang mendasari seluruh pola

**Anggaran insentif dipakai untuk menggeser perilaku, bukan untuk membayar
perilaku yang sudah terjadi.**

Satu kalimat itu yang menentukan seluruh isi tabel di bawah, dan yang membuat
segmen dengan pelanggan terbaik justru menerima diskon paling sedikit.

---

## 3. Pola per segmen

Diurutkan **prioritas penanganan**, bukan jumlah anggota.

### Prioritas 1 — Loyal

| | |
|---|---|
| **Karakteristik** | Sering datang, nilai belanja tinggi, baru saja bertransaksi. |
| **Tujuan** | Pertahankan. Jangan diganggu dengan penawaran yang tidak mereka butuhkan. |
| **Reward** | `gratis_coffee_kopi` (450 poin) |

Treatment:

1. Beri akses awal ke menu baru dan minta pendapatnya, mereka pelanggan yang
   paling paham produk.
2. Apresiasi personal saat datang, sebut namanya dan menu favoritnya.
3. **JANGAN beri diskon.**

> **Ini butir yang paling mudah tergeser tanpa sengaja.** Segmen terbaik terasa
> paling pantas diberi diskon terbesar, dan itu keliru: mereka sudah membeli
> tanpa insentif apa pun. Diskon di sini tidak mengubah perilaku siapa pun, ia
> hanya memotong margin dari penjualan yang toh tetap terjadi. Yang mereka
> butuhkan pengakuan, bukan potongan harga.
>
> Karena itu rewardnya berupa **produk**, bukan diskon. Minuman gratis terbaca
> sebagai apresiasi; potongan harga terbaca sebagai transaksi.

### Prioritas 2 — Potensial

| | |
|---|---|
| **Karakteristik** | Frekuensi menengah, recency masih bagus. Kandidat terkuat naik jadi Loyal. |
| **Tujuan** | Naikkan **frekuensi kedatangan**, bukan nilai belanja per kunjungan. |
| **Reward** | `diskon_10` (100 poin) |

Treatment:

1. Dorong dengan reward yang **cepat diraih, bukan yang besar**. Target yang
   terasa jauh justru membuat orang berhenti mengumpulkan.
2. Ingatkan sisa poin menuju reward terdekat saat membayar.

Tier termurah dipilih justru karena murahnya: yang dibeli di sini adalah
**kunjungan berikutnya**, dan itu lebih murah dibeli dengan target yang terasa
dekat daripada dengan hadiah besar yang terasa mustahil.

### Prioritas 3 — Pelanggan Baru

| | |
|---|---|
| **Karakteristik** | Baru satu kali transaksi. Kebiasaan belum terbentuk. |
| **Tujuan** | Amankan **kunjungan kedua**. Di situlah titik putus terbesar. |
| **Reward** | `diskon_10` (100 poin) |

Treatment:

1. Pastikan kasir menyebutkan bonus 50 poin pendaftaran. Saldo awal membuat
   reward pertama terasa dekat.
2. Sebutkan poin berlaku 12 bulan sejak transaksi terakhir, supaya tidak terasa
   mendesak tapi tetap ada alasan kembali.

Segmen terbesar (35%) tapi prioritas ketiga, dan itu disengaja: sebagian besar
memang tidak akan kembali, dan usaha per orang di sini harus murah. Alat
utamanya sudah otomatis berjalan — bonus 50 poin pendaftaran membuat
`diskon_10` tinggal satu kunjungan lagi, jadi yang tersisa hanyalah memastikan
kasir menyebutkannya.

### Prioritas 4 — Butuh Perhatian

| | |
|---|---|
| **Karakteristik** | Pernah sering membeli, tapi sudah lama tidak datang. Masih ingat merek, kebiasaannya yang putus. |
| **Tujuan** | Reaktivasi sebelum benar-benar lupa. |
| **Reward** | `diskon_30` (300 poin) |

Treatment:

1. Butuh dorongan lebih besar daripada segmen lain, karena kebiasaannya sudah
   terputus.
2. Pengingat poin akan kedaluwarsa adalah pemicu paling wajar, isinya informasi
   yang memang berguna, bukan promosi.
3. Hubungi lewat WhatsApp secara personal, bukan blast. Jumlah orangnya masih
   terkelola.

Satu-satunya segmen yang mendapat tier tinggi. Bukan karena mereka paling
berharga, tapi karena **hanya di sini insentif besar benar-benar mengubah
keputusan**: pelanggan yang sudah berhenti datang butuh alasan yang sepadan
dengan perjalanan kembali ke toko.

---

## 4. Ringkasan reward

| Segmen | Reward | Poin | Jenis |
|---|---|---:|---|
| Loyal | `gratis_coffee_kopi` | 450 | Produk |
| Potensial | `diskon_10` | 100 | Diskon |
| Pelanggan Baru | `diskon_10` | 100 | Diskon |
| Butuh Perhatian | `diskon_30` | 300 | Diskon |

Kode-kode ini menunjuk katalog nyata di
[`LoyaltyRedemptionCatalog`](../app/Support/LoyaltyRedemptionCatalog.php), jadi
rekomendasinya langsung bisa dieksekusi kasir tanpa menerjemahkan apa pun.
Ada test yang memastikan setiap kode benar-benar ada di katalog — rekomendasi
yang menunjuk kode tak ada akan membuat kasir mencari tombol yang tidak pernah
muncul di layarnya.

Latar belakang angka poinnya (konstanta V = Rp 50 per poin, target biaya program
5% omzet) ada di [pengaturan-loyalty.md](pengaturan-loyalty.md).

---

## 5. Batas lingkup

**Ini bukan mesin promo otomatis.** Backend hanya menyajikan polanya; tidak ada
pengiriman pesan, tidak ada penjadwalan, tidak ada integrasi WhatsApp.
Eksekusinya manual dan itu keputusan manager.

Yang juga sengaja tidak dilakukan: menerbitkan voucher otomatis ke pelanggan
tertentu. Reward tetap ditebus pelanggan dengan poinnya sendiri lewat kasir,
supaya tidak ada jalur pemberian diskon yang melewati katalog dan luput dari
pencatatan.

---

## 6. Kalau segmennya berubah

`SegmenTreatment::semua()` memakai `RfmQuery::SEGMEN` sebagai daftar induk. Kalau
suatu saat ada segmen baru di sana tapi belum punya entri treatment, segmen itu
akan **hilang** dari `segmen_treatment` — bukan muncul kosong.

Itu disengaja: entri yang hilang lebih cepat ketahuan daripada baris kosong yang
terlihat seperti fitur yang memang belum diisi. Kunci di `SegmenTreatment::PETA`
harus sama persis dengan nilai yang dihasilkan `RfmQuery::segmen()`, beda satu
huruf saja membuat treatment-nya tidak pernah tampil tanpa error apa pun.
