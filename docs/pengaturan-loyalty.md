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
| `LoyaltyRedemptionCatalog::defaults()` (kode) | `label`, `tipe`, `persen`, `min_subtotal`, mapping menu gratis, preferensi ukuran | ❌ |
| Tabel `katalog_redeem` | `poin`, `is_active` | ✅ lewat endpoint |

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
      "kode": "gratis_original",
      "label": "Gratis Original",
      "tipe": "gratis_menu",
      "poin": 150,
      "poin_default": 150,
      "is_active": true,
      "persen": null,
      "min_subtotal": 0,
      "menu_gratis": "Original",
      "setara_belanja": 150000,
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
- `diubah_pada` `null` = kode ini masih di nilai bawaan.
- `tipe` `diskon` mengisi `persen` + `min_subtotal`; `tipe` `gratis_menu`
  mengisi `menu_gratis`. Field yang tidak relevan bernilai `null`/`0`.

### PATCH /api/pengaturan/loyalty/katalog/{kode}

Body: `{ "poin": 200 }`, `{ "is_active": false }`, atau keduanya. Minimal salah
satu wajib ada — body kosong ditolak `422` supaya tidak tercatat sebagai
"perubahan" yang tidak mengubah apa pun. Field yang tidak dikirim
dipertahankan. Response = satu objek item, bentuknya sama dengan elemen `data`
di atas.

Poin diterima di rentang **1 – 100.000**.

Reward yang `is_active: false` ditolak saat kasir mencoba redeem dengan kode
error tersendiri (`kode_redeem_nonaktif`, bukan `kode_redeem_invalid`) supaya
kasir tahu ini reward yang memang sedang dimatikan, bukan salah ketik.

Kode yang tersedia: `diskon_10`, `diskon_20`, `diskon_50`, `gratis_original`,
`gratis_coffee_kopi`, `gratis_honey_lemon`, `gratis_mango_monggo`.

---

## 3. Rate dan katalog harus diubah bersamaan

Dua setting ini saling terkait dan gampang bikin loyalty mati diam-diam.
Reward dibayar pakai poin, tapi poin dikumpulkan dari rupiah — jadi menaikkan
rate otomatis menaikkan harga rupiah semua reward:

| | Rate `1.000` | Rate `10.000` |
|---|---|---|
| Gratis Original (150 poin) | belanja Rp 150.000 | belanja **Rp 1.500.000** |
| Diskon 50% (350 poin) | belanja Rp 350.000 | belanja **Rp 3.500.000** |

Naik 10x tanpa poin katalog ikut diturunkan = praktis tidak ada pelanggan yang
sampai, dan katalognya jadi pajangan. Karena itu `setara_belanja` ikut
dikirim di response katalog — **setelah mengubah rate, buka halaman katalog dan
cek kolom itu.** Kalau angkanya sudah di luar akal untuk belanja normal
Gressoy, turunkan poin rewardnya di endpoint katalog.

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

---

## 5. Yang belum dibuka

Sesuai lingkup yang disepakati, endpoint ini **tidak** bisa menambah/menghapus
item reward baru, mengubah persen diskon, mengatur `min_subtotal`, atau memilih
menu gratis dari daftar menu — semua itu masih lewat
`LoyaltyRedemptionCatalog`. Juga belum ada endpoint untuk mengembalikan sebuah
kode ke nilai bawaannya (caranya: PATCH balik ke angka `poin_default` yang
memang ikut dikirim di response katalog).
