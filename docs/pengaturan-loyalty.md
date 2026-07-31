# Pengaturan Loyalty & LoyalSeed — rate poin + katalog redeem

> Menutup celah M3: sebelum ini rate poin dan biaya poin tiap reward hanya ada
> sebagai angka di kode (`LoyaltyService` membagi `total` dengan `1000`,
> `LoyaltyRedemptionCatalog` menyimpan 150–350 poin), jadi revisi sekecil apa
> pun harus lewat deploy. Dokumen ini kontrak endpoint yang membuat keduanya
> bisa diubah manager dari halaman Settings.

Semua endpoint di bawah prefix `/api` dan butuh header
`Authorization: Bearer <token>` seperti endpoint internal lainnya.

| Method & Path | Role | Keterangan |
|---|---|---|
| `GET /api/pengaturan/loyalty` | kasir, manager | Rate poin yang berlaku |
| `PATCH /api/pengaturan/loyalty` | manager | Ubah rate poin |
| `GET /api/pengaturan/loyalty/katalog` | kasir, manager | Katalog redeem efektif |
| `PATCH /api/pengaturan/loyalty/katalog/{kode}` | manager | Ubah poin / aktifkan-nonaktifkan reward |

Baca dibuka untuk kasir karena UI kasir memang butuh: rate untuk menampilkan
estimasi poin, katalog untuk merender tombol redeem (dan menyembunyikan reward
yang sedang dinonaktifkan). Tulis dijaga `role:manager`.

---

## 1. Rate poin

### Arah angkanya

`rupiah_per_poin` adalah **pembagi**, bukan pengali:

```
poin = intdiv(total_yang_dibayar, rupiah_per_poin)
```

Jadi **angka lebih besar = poin lebih sulit didapat.** Ini gampang terbaca
terbalik, jadi tolong dicek sekali lagi sebelum disimpan:

| Rate | Belanja Rp 50.000 | Arti |
|---|---|---|
| `1000` (bawaan) | 50 poin | 1 poin tiap Rp 1.000 |
| `10000` | 5 poin | 1 poin tiap Rp 10.000 — **10x lebih pelit** |

Batas yang diterima: **100 – 1.000.000**. Ini pagar salah ketik, bukan aturan
bisnis: rate `1` berarti tiap Rp 1 dapat 1 poin, dan rate raksasa mematikan
earning diam-diam tanpa ada yang sadar.

### GET /api/pengaturan/loyalty

```json
{
  "data": {
    "rupiah_per_poin": 1000,
    "rupiah_per_poin_default": 1000,
    "batas": { "min": 100, "max": 1000000 },
    "contoh": { "belanja": 50000, "poin_didapat": 50 },
    "diperbarui_pada": null,
    "diperbarui_oleh": null
  }
}
```

- `contoh` dihitung server dari rate yang berlaku — UI Settings sebaiknya
  menampilkan ini apa adanya, jangan menghitung ulang rumusnya di frontend.
- `diperbarui_pada`/`diperbarui_oleh` `null` = belum pernah diubah, masih
  memakai nilai bawaan.

### PATCH /api/pengaturan/loyalty

Body: `{ "rupiah_per_poin": 10000 }`. Response sama persis dengan `GET`.

**Tidak retroaktif.** Saldo poin pelanggan dan `point_earned` transaksi lama
tidak disentuh. Rate baru hanya berlaku untuk transaksi yang ditandai lunas
setelah perubahan disimpan.

### Cara memverifikasi setelah menyimpan

Ini tes cepat yang membuktikan setting benar-benar nyampe ke perhitungan poin,
bukan cuma tersimpan di layar:

1. `PATCH /api/pengaturan/loyalty` dengan `rupiah_per_poin: 10000`.
2. Buat transaksi ber-customer senilai **Rp 50.000**, lalu Tandai Lunas.
3. Lihat `data.point_earned` di response:
   - **5** → setting sudah dipakai. ✅
   - **50** → masih memakai rate lama, berarti ada yang belum nyambung.

Response Tandai Lunas juga membawa `rupiah_per_poin` — rate yang **benar-benar
dipakai** saat poin dihitung, disnapshot di transaksinya (kolom
`transaksi.rupiah_per_poin`). Gunanya: begitu rate bisa berubah, `point_earned`
transaksi lama tidak lagi bisa diverifikasi dari totalnya saja. Bernilai `null`
selama transaksi masih pending, dan untuk transaksi yang sudah lunas sebelum
fitur ini ada (baca sebagai rate lama Rp 1.000).

---

## 2. Katalog redeem

### Yang bisa dan tidak bisa diubah

Katalog terbentuk dari dua lapis:

| Lapis | Isi | Bisa diubah manager? |
|---|---|---|
| `LoyaltyRedemptionCatalog::defaults()` (kode) | `label`, `tipe`, `persen`, mapping menu gratis, preferensi ukuran | ❌ |
| Tabel `katalog_redeem` | `poin`, `is_active`, `maks_potongan`, `min_subtotal` | ✅ lewat endpoint |

Yang menentukan **perilaku** redeem sengaja tetap di kode — mapping menu gratis
dan persen diskon tidak aman diketik bebas lewat API. Yang jadi keputusan
bisnis harian (harga poin dan reward mana yang sedang jalan) dibuka.

Baris `katalog_redeem` **hanya dibuat saat sebuah kode benar-benar diedit**.
Kode tanpa baris memakai nilai bawaan, jadi tabel kosong = perilaku persis sama
seperti sebelum fitur ini ada.

### GET /api/pengaturan/loyalty/katalog

```json
{
  "data": [
    {
      "kode": "diskon_20",
      "label": "Diskon 20%",
      "tipe": "diskon",
      "poin": 200,
      "poin_default": 200,
      "is_active": true,
      "persen": 20,
      "min_subtotal": 25000,
      "maks_potongan": 10000,
      "menu_gratis": null,
      "setara_belanja": 200000,
      "rupiah_per_poin_efektif": 50,
      "diubah_pada": null
    }
  ],
  "meta": { "rupiah_per_poin": 1000 }
}
```

- `poin_default` — nilai bawaan kode, ditampilkan sebagai pembanding supaya
  manager tahu angka aslinya sebelum diubah.
- `setara_belanja` = `poin × rupiah_per_poin` — **rupiah belanja yang harus
  dikumpulkan pelanggan untuk menebus reward ini pada rate yang berlaku.**
  Ini angka yang menentukan reward masuk akal atau tidak (lihat §3).
- `maks_potongan` — **plafon rupiah potongan voucher diskon.** Persennya
  berlaku penuh sampai potongan menyentuh angka ini, di atas itu potongannya
  berhenti nambah. `null` untuk reward `gratis_menu`, yang tidak mengenal
  plafon.
- `min_subtotal` — minimal belanja sebelum reward boleh ditukar.
- `rupiah_per_poin_efektif` = nilai maksimal reward ÷ `poin` — **rupiah yang
  didapat pelanggan per 1 poin yang dia bayarkan.** Nilai reward diambil dari
  `maks_potongan` untuk voucher diskon, dan dari harga menu live untuk reward
  gratis minuman. **Angka di atas 50 berarti reward kemurahan** (lihat §3).
- `diubah_pada` `null` = kode ini masih di nilai bawaan.
- `tipe` `diskon` mengisi `persen` + `maks_potongan`; `tipe` `gratis_menu`
  mengisi `menu_gratis`. Field yang tidak relevan bernilai `null`/`0`.

### PATCH /api/pengaturan/loyalty/katalog/{kode}

Body boleh berisi `poin`, `is_active`, `maks_potongan`, `min_subtotal` — satu,
sebagian, atau semuanya. Minimal salah satu wajib ada; body kosong ditolak
`422` supaya tidak tercatat sebagai "perubahan" yang tidak mengubah apa pun.
Field yang tidak dikirim dipertahankan. Response = satu objek item, bentuknya
sama dengan elemen `data` di atas.

Poin diterima di rentang **1 – 100.000**. `maks_potongan` dan `min_subtotal`
minimal 0, dan keduanya `null` selama manager belum pernah menyetelnya —
artinya "ikut nilai bawaan", bukan "tanpa plafon".

`maks_potongan` ditolak `422` pada reward bertipe `gratis_menu`: plafon
potongan tidak punya arti di sana.

Reward yang `is_active: false` ditolak saat kasir mencoba redeem dengan kode
error tersendiri (`kode_redeem_nonaktif`, bukan `kode_redeem_invalid`) supaya
kasir tahu ini reward yang memang sedang dimatikan, bukan salah ketik.

Kode yang tersedia: `diskon_10`, `diskon_20`, `diskon_30`, `diskon_50`,
`gratis_original`, `gratis_coffee_kopi`, `gratis_honey_lemon`,
`gratis_mango_monggo`.

---

## 3. Rate dan katalog harus diubah bersamaan

Dua setting ini saling terkait dan gampang bikin loyalty mati diam-diam.
Reward dibayar pakai poin, tapi poin dikumpulkan dari rupiah — jadi menaikkan
rate otomatis menaikkan harga rupiah semua reward:

| | Rate `1.000` | Rate `10.000` |
|---|---|---|
| Gratis Original (350 poin) | belanja Rp 350.000 | belanja **Rp 3.500.000** |
| Diskon 50% (500 poin) | belanja Rp 500.000 | belanja **Rp 5.000.000** |

Naik 10x tanpa poin katalog ikut diturunkan = praktis tidak ada pelanggan yang
sampai, dan katalognya jadi pajangan. Karena itu `setara_belanja` ikut
dikirim di response katalog — **setelah mengubah rate, buka halaman katalog dan
cek kolom itu.** Kalau angkanya sudah di luar akal untuk belanja normal
Gressoy, turunkan poin rewardnya di endpoint katalog.

### Acuan angkanya: 1 poin = Rp 50

Seluruh angka katalog bawaan diturunkan dari satu konstanta: **nilai 1 poin
(V) = Rp 50.** Pada rate earn Rp 1.000/poin, itu berarti program loyalty ini
berbiaya **5% omzet** — angka yang dipilih supaya masih muat di margin.

```
biaya_program (%) = V ÷ rupiah_per_poin       = 50 ÷ 1.000 = 5%
poin voucher      = persen × 10               (diskon 30% -> 300 poin)
maks_potongan     = poin × V                  (300 poin  -> Rp 15.000)
poin gratis menu  = harga_reguler ÷ V         (dibulatkan KE ATAS per 50 poin)
```

Satu tes untuk semua reward: **`nilai_reward ÷ poin` tidak boleh lebih dari
50.** Itulah kolom `rupiah_per_poin_efektif` di response katalog — **angka di
atas 50 berarti reward kemurahan** (pelanggan dapat lebih banyak rupiah
daripada yang dia kumpulkan untuk membelinya). Katalog bawaan seluruhnya duduk
di rentang **46,7 – 50,0**; setelah mengubah `poin` atau `maks_potongan`,
cek kolom ini sebelum menutup halaman.

Efek samping rumus voucher yang enak dijelaskan ke pelanggan: semua tier
berhenti naik di subtotal yang sama, **Rp 50.000**. Satu kalimat cukup untuk
kasir dan pelanggan: *"Persennya berlaku penuh sampai belanja Rp 50.000; di
atas itu potongannya berhenti nambah."*

### Dua pagar yang sering tertukar

| Pagar | Melindungi | Dari |
|---|---|---|
| `maks_potongan` | Toko | Pesanan besar yang menguras margin |
| `min_subtotal` | Pelanggan | Membuang 500 poin untuk potongan Rp 5.000 |

`min_subtotal` adalah **lantai**, `maks_potongan` adalah **plafon** — menaikkan
minimal belanja tidak melindungi toko dari pesanan besar, dan sebaliknya.

Berapa angka yang benar adalah keputusan owner, bukan default teknis. Yang
disediakan di sini cuma mekanismenya.

---

## 4. Kode error

| Kode | HTTP | Sumber |
|---|---|---|
| `unauthenticated` | 401 | Tanpa/invalid token |
| `tidak_berwenang` | 403 | Kasir mencoba `PATCH` |
| `kode_redeem_invalid` | 404 | `{kode}` tidak ada di katalog (saat PATCH) |
| `validasi_gagal` | 422 | Rate/poin di luar batas, bukan angka, atau body kosong |
| `kode_redeem_nonaktif` | 422 | Kasir redeem reward yang dinonaktifkan manager |
| `maks_potongan_tidak_berlaku` | 422 | `maks_potongan` dikirim untuk reward `gratis_menu` |
| `diskon_terkunci_redeem` | 409 | Kasir menerapkan diskon manual di transaksi yang sudah redeem |

`diskon_terkunci_redeem` muncul di alur kasir, bukan di halaman Settings.
Diskon sifatnya menimpa, bukan menumpuk: menerapkan diskon manual setelah
pelanggan redeem akan menghapus potongan reward yang poinnya terlanjur
terpotong, atau menggantinya dengan potongan yang lebih besar. Jalan keluarnya
memang batalkan transaksi dan buat baru.

---

## 4b. Poin redeem dikembalikan saat pesanan dibatalkan

> Ditambahkan 1 Agustus 2026, sekaligus memperbaiki bug yang merugikan pelanggan.

`redeemPoin()` **langsung memotong saldo poin** saat redeem, padahal redeem hanya
boleh pada transaksi yang masih `pending`. Sebelum perbaikan ini,
`POST /api/transaksi/{id}/batal` cuma mengubah status — poin yang sudah terpotong
tidak pernah dikembalikan.

Artinya: pelanggan menukar 350 poin untuk gratis Original, pesanannya dibatalkan
sebelum bayar, **poinnya hilang dan minumannya tidak dapat.**

Aturan yang sekarang berlaku:

| Jenis poin                       | Kapan berubah                                       | Saat pembatalan                      |
| -------------------------------- | --------------------------------------------------- | ------------------------------------ |
| Poin **earn** (dari belanja)     | Saat **lunas** (`loyalty_applied_at` terisi)         | Ditarik proporsional, tidak pernah negatif |
| Poin **redeem** (ditukar reward) | Saat **redeem**, walau transaksi masih `pending`      | **Dikembalikan utuh**                |

Satu kalimat yang otomatis benar untuk kedua kasus:

> **Tarik poin earn hanya jika `loyalty_applied_at !== null`.
> Kembalikan poin redeem kapan pun `kode_redeem` terisi.**

Saat poin redeem kembali, `kode_redeem`, `poin_ditukar`, dan `maks_potongan` pada
transaksi ikut dikosongkan — kalau tidak, plafon reward yang sudah digugurkan
masih dipakai `recalculateTotals()` berikutnya. Masa berlaku poin juga
diperpanjang ulang: poin yang kembali karena kesalahan pesanan tidak boleh
langsung hangus gara-gara jam kedaluwarsa lama masih menempel.

Pembatalan **sebagian** yang tidak menyentuh item reward **tidak** mengembalikan
poin — rewardnya memang tetap diterima pelanggan.

Rumus lengkap, aturan qty kumulatif, dan daftar kode error:
[`pembatalan-pesanan.md`](pembatalan-pesanan.md).

---

## 5. Yang belum dibuka

Sesuai lingkup yang disepakati, endpoint ini **tidak** bisa menambah/menghapus
item reward baru, mengubah persen diskon, atau memilih menu gratis dari daftar
menu — semua itu masih lewat `LoyaltyRedemptionCatalog`, karena bagian itulah
yang menentukan perilaku redeem. Juga belum ada endpoint untuk mengembalikan
sebuah kode ke nilai bawaannya (caranya: PATCH balik ke angka `poin_default`
yang memang ikut dikirim di response katalog).
