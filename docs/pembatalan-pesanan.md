# Pembatalan / Koreksi Pesanan

> **Ini pembatalan pesanan yang salah, BUKAN pengembalian uang.**
>
> Tidak ada alur kas keluar, tidak ada metode pengembalian dana, tidak ada
> integrasi payment gateway. Nilainya dicatat sebagai **`nilai_dibatalkan`**,
> yang artinya _"penjualan sebesar ini tidak jadi"_, bukan _"uang sebesar ini
> dikembalikan"_.

Nilainya tetap **wajib** dicatat karena omzet dashboard dan laporan kasir harus
ikut terkoreksi: penjualan yang dibatalkan tidak boleh tetap terhitung.

Istilah `refund` sengaja tidak dipakai di nama tabel, kolom, endpoint, maupun
pesan error.

---

## 1. Prinsip

- **Transaksi asli tidak pernah dihapus atau diubah isinya.** Ia hanya berubah
  status, dan pembatalannya dicatat sebagai dokumen tersendiri, supaya selalu
  bisa ditelusuri siapa membatalkan apa, kapan, dan kenapa.
- **Alasan wajib diisi** (minimal 3 karakter). Ini satu-satunya pagar terhadap
  penyalahgunaan; tanpa alasan, pembatalan jadi cara menghapus penjualan tanpa
  jejak.
- Pembatalan tercatat atas **akun yang memprosesnya**, bukan atas akun pembuat
  penjualan aslinya.
- Yang sudah dibatalkan penuh tidak bisa dibatalkan lagi (`409`).

## 2. Status transaksi

| Status           | Kapan                                                    |
| ---------------- | -------------------------------------------------------- |
| `batal`          | Pembatalan **penuh**, dari `pending` maupun dari `lunas` |
| `batal_sebagian` | Pembatalan **sebagian** transaksi yang sudah `lunas`      |

Pembatalan sebagian yang ternyata menghabiskan seluruh sisa item berakhir sebagai
`batal`, bukan `batal_sebagian`, transaksi tanpa satu pun item tersisa bukan
"sebagian".

Kolom `transaksi.status` bertipe `string` tanpa enum/check constraint, jadi nilai
baru ini tidak butuh perubahan skema.

### Pembatalan sebagian hanya untuk transaksi `lunas`

Pesanan yang **belum dibayar** dikoreksi lewat endpoint item yang sudah ada:

```
PATCH  /api/transaksi/{id}/items/{item}    ubah qty
DELETE /api/transaksi/{id}/items/{item}    hapus item
```

Keduanya sudah menghitung ulang total dengan benar. Kalau pembatalan sebagian
diizinkan pada transaksi `pending`, `transaksi.total` akan tidak lagi sama dengan
yang harus dibayar pelanggan, dan kasir menagih angka yang salah. Karena itu
kasus tersebut ditolak `422` `pembatalan_sebagian_butuh_lunas` dengan pesan yang
mengarahkan ke endpoint item.

Pembatalan **penuh** tetap berlaku untuk `pending` maupun `lunas`. Itu yang
dipakai saat pelanggan membatalkan pesanannya sebelum bayar.

---

## 3. Aturan perhitungan

### Qty kumulatif dijaga

Total qty yang dibatalkan untuk satu `detail_transaksi` tidak boleh melebihi qty
aslinya, **dihitung lintas semua pembatalan sebelumnya**, bukan hanya request
ini. Tiga kali membatalkan 1 dari qty 2 tetap ditolak pada percobaan ketiga.

Ditolak `422` `qty_pembatalan_melebihi`.

### Nilai per item dihitung SETELAH diskon

```
nilai_dibatalkan = (subtotal - diskon_nilai) × (qty_dibatalkan ÷ qty_asli)
```

**Kenapa bukan dari `harga_satuan` mentah:** memakai harga mentah membuat omzet
terkoreksi lebih besar daripada yang pernah tercatat, dan dashboard jadi **minus**.

Contoh: 2 gelas Rp 20.000 dengan diskon 20% → nilai bersih Rp 32.000. Membatalkan
1 gelas menggugurkan **Rp 16.000**, bukan Rp 20.000.

**Pengecualian yang disengaja:** kalau sebuah pembatalan menghabiskan sisa qty
item itu, yang dipakai adalah **nilai sisanya secara persis**, bukan hasil rumus
proporsional. Tanpa ini, residu pembulatan tertinggal sebagai omzet beberapa
rupiah yang tidak akan pernah bisa dihilangkan. Contoh: 3 item bernilai bersih
Rp 54.000 tidak habis dibagi 3 secara bulat, dengan aturan ini penjumlahan
seluruh pembatalannya tetap pas Rp 54.000.

---

## 4. Perilaku poin

Dua jenis poin yang gampang dianggap sama padahal berubah di waktu yang berbeda:

| Jenis poin                       | Kapan berubah                                       | Saat pembatalan                                                          |
| -------------------------------- | --------------------------------------------------- | ------------------------------------------------------------------------ |
| Poin **earn** (dari belanja)     | Saat **lunas** (`loyalty_applied_at` terisi)         | Pending → belum ada, tidak ada yang ditarik. Lunas → tarik proporsional. |
| Poin **redeem** (ditukar reward) | Saat **redeem**, sudah dipotong walau masih pending  | **Selalu dikembalikan utuh**                                            |

Aturan finalnya sederhana dan otomatis benar untuk kedua kasus:

> **Tarik poin earn hanya jika `loyalty_applied_at !== null`.
> Kembalikan poin redeem kapan pun `kode_redeem` terisi.**

### Bug yang diperbaiki di sini

`LoyaltyService::redeemPoin()` **langsung memotong saldo poin pelanggan saat
redeem**, dan redeem hanya boleh pada transaksi `pending`. Sementara itu `batal()`
versi lama hanya mengubah status, **poin yang sudah terpotong tidak pernah
dikembalikan.**

Artinya sebelum perbaikan ini: pelanggan menukar 350 poin untuk gratis Original,
pesanannya dibatalkan sebelum bayar, **poinnya hilang dan minumannya tidak
dapat.** Asumsi "kalau dibatalkan, poinnya belum kehitung" hanya separuh benar,
dan bagian yang tidak benar itu justru merugikan pelanggan.

### Poin earn: proporsional, dan tidak pernah negatif

Ditarik proporsional terhadap nilai yang dibatalkan, **dibulatkan ke bawah**
(memihak pelanggan).

Saldo boleh menjadi **0** tapi **tidak boleh negatif**: kalau pelanggan sudah
membelanjakan poinnya, kekurangannya ditanggung toko. Menagih poin negatif memicu
komplain yang lebih mahal daripada selisihnya.

Yang **dicatat** di `poin_ditarik` adalah poin yang benar-benar ditarik dari
saldo, bukan yang seharusnya, supaya laporan tidak mengklaim menarik poin yang
tidak pernah kembali.

### Poin redeem: dikembalikan utuh, tapi hanya kalau redemption-nya gugur

Yang menggugurkan redemption:

- pembatalan **penuh**, selalu; atau
- pembatalan **sebagian yang menyertakan item reward** (`is_reward`).

Pembatalan sebagian yang **tidak** menyentuh item reward **tidak** mengembalikan
poin, rewardnya memang tetap diterima pelanggan.

Saat poin redeem dikembalikan, `kode_redeem`, `poin_ditukar`, dan `maks_potongan`
pada transaksi ikut **dikosongkan**. Kalau tidak, diskon dari reward yang sudah
digugurkan akan tetap menempel, dan `recalculateTotals()` berikutnya masih memakai
plafon reward yang sudah tidak berlaku.

Masa berlaku poin ikut diperpanjang saat poin kembali: poin yang kembali karena
kesalahan pesanan tidak boleh langsung hangus gara-gara jam kedaluwarsa lama masih
menempel.

---

## 5. Dampak ke laporan

- **Proyeksi laporan disinkronkan** di transaksi database yang sama: pembatalan
  penuh menghapus baris proyeksinya, pembatalan sebagian menulis ulang qty dan
  nilainya. Omzet dashboard ikut turun di detik itu juga.
- Poin di laporan memakai `point_earned` dikurangi poin yang sudah ditarik, supaya
  total poin di dashboard tidak terus menghitung poin yang sudah dicabut.
- Di laporan kasir, pembatalan muncul di **dua tempat dengan makna berbeda**,
  lihat [`laporan-kasir.md`](laporan-kasir.md) §5.

Seluruh operasi berjalan dalam satu `DB::transaction` dengan `lockForUpdate()` pada
saldo loyalty: tanpa itu, dua pembatalan yang tiba bersamaan bisa sama-sama membaca
saldo lama dan mengembalikan poin redeem dua kali.

---

## 6. Endpoint

| Method & path                                | Role           | Keterangan                                      |
| -------------------------------------------- | -------------- | ----------------------------------------------- |
| `POST /api/transaksi/{transaksi}/pembatalan` | kasir, manager | Penuh atau sebagian                             |
| `GET /api/transaksi/{transaksi}/pembatalan`  | kasir, manager | Riwayat pembatalan transaksi itu                |
| `GET /api/pembatalan`                        | manager        | Semua pembatalan; filter tanggal & akun kasir   |

Kasir ikut boleh memproses pembatalan karena dialah yang berhadapan dengan
pelanggan saat kesalahan pesanan ketahuan. Jejaknya dijaga oleh alasan yang wajib
diisi dan pencatatan akun pemroses.

### Body `POST`

```json
{
    "alasan": "Pelanggan salah pesan ukuran",
    "items": [{ "detail_transaksi_id": 12, "qty": 1 }]
}
```

`items` kosong atau tidak dikirim = pembatalan **penuh**.

Satu `detail_transaksi_id` hanya boleh disebut sekali per request (`distinct`),
kalau dibiarkan ganda, qty-nya harus dijumlahkan dulu dan pesan error "melebihi
sisa" jadi membingungkan.

### Response `201`

```json
{
    "data": {
        "id": 3,
        "transaksi_id": 12,
        "alasan": "Pelanggan salah pesan ukuran",
        "nilai_dibatalkan": 16000,
        "poin_ditarik": 16,
        "poin_dikembalikan": 0,
        "diproses_oleh": { "id": 2, "nama": "Kasir Dua" },
        "items": [
            {
                "detail_transaksi_id": 12,
                "nama": "Original",
                "ukuran": "Reguler",
                "is_reward": false,
                "qty": 1,
                "nilai_dibatalkan": 16000
            }
        ],
        "created_at": "2026-08-05T14:05:00+00:00"
    },
    "status_transaksi": "batal_sebagian",
    "saldo_poin_pelanggan": 84
}
```

`saldo_poin_pelanggan` ikut dikirim karena kasir perlu menyebutkannya ke pelanggan
saat itu juga, kalau tidak ada di response, dia harus membuka halaman lain
sementara pelanggannya masih berdiri di depan konter. Isinya `null` untuk
transaksi walk-in tanpa customer.

### Endpoint lama tetap dipertahankan

`POST /api/transaksi/{transaksi}/batal` tetap ada sebagai **alias pembatalan
penuh** supaya frontend yang sudah jalan tidak rusak, tapi sekarang ikut melewati
alur baru: mengembalikan poin redeem, mencatat dokumen pembatalan, dan
menyinkronkan proyeksi laporan.

Alasan tidak perlu dikirim pada alias ini, diisi teks tetap
`"Dibatalkan lewat endpoint lama"` supaya kolomnya tetap jujur soal dari mana
pembatalannya datang.

---

## 7. Kode error

| Kode                                | HTTP | Kapan                                                        |
| ----------------------------------- | ---- | ------------------------------------------------------------ |
| `validasi_gagal`                    | 422  | Alasan kosong/terlalu pendek, qty < 1, item id ganda          |
| `qty_pembatalan_melebihi`           | 422  | Qty melebihi sisa yang belum dibatalkan (lintas pembatalan)   |
| `item_bukan_milik_transaksi`        | 422  | `detail_transaksi_id` bukan bagian dari transaksi itu         |
| `pembatalan_sebagian_butuh_lunas`   | 422  | Pembatalan sebagian pada transaksi `pending`                  |
| `transaksi_sudah_batal`             | 409  | Transaksi sudah dibatalkan seluruhnya                         |
