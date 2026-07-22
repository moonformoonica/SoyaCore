# Cara Update Data Laporan (CSV → Dashboard & Excel)

Dokumen ini menjawab: *"kalau ada data baru, gimana biar dashboard dan export
Excel ikut ke-update?"*

## Jawaban singkat

Dashboard dan export Excel **tidak membaca file CSV**. Keduanya membaca tabel
`laporan_*` di database. Jadi alurnya:

```
CSV baru  ──►  php artisan laporan:import  ──►  tabel laporan_*  ──►  dashboard + Excel
```

Begitu impor selesai, dashboard dan Excel otomatis memakai data baru — tidak ada
langkah tambahan, tidak perlu deploy ulang, tidak perlu ubah kode frontend.

## Langkah update

1. **Timpa CSV** di `database/seeders/data/` dengan hasil olahan terbaru. Nama
   file harus tetap sama:

   | File | Tabel tujuan |
   |---|---|
   | `Data_Transaksi_Bersih.csv` | `laporan_transaksi` |
   | `Data_Revenue_Ukuran.csv` | `laporan_revenue_ukuran` |
   | `Data_RFM_Pelanggan.csv` | `laporan_rfm` |
   | `Data_Switch_Ukuran.csv` | `laporan_switch` |

2. **Jalankan impor:**

   ```bash
   php artisan laporan:import
   ```

   Outputnya jumlah baris per tabel:

   ```
   +------------------------+-------+
   | Tabel                  | Baris |
   +------------------------+-------+
   | laporan_transaksi      | 882   |
   | laporan_revenue_ukuran | 6     |
   | laporan_rfm            | 345   |
   | laporan_switch         | 35    |
   +------------------------+-------+
   ```

3. **Refresh dashboard.** Selesai.

Perintahnya **idempotent** — tiap tabel di-truncate lalu diisi ulang, jadi aman
dijalankan berkali-kali dan tidak akan menghasilkan data dobel.

Untuk file CSV di lokasi lain: `php artisan laporan:import --dir=/path/ke/folder`.

## Kalau header CSV berubah

Kolom dipetakan **berdasarkan nama header**, bukan urutan kolom. Konsekuensinya:

- Menambah kolom baru di CSV → aman, kolom yang tidak dikenal diabaikan.
- Mengubah urutan kolom → aman.
- Beda huruf besar/kecil atau spasi berlebih di header → aman.
- Menghapus/mengganti nama kolom yang dipakai → **impor gagal** dengan pesan
  yang menyebut kolom mana yang hilang, dan **tidak ada data yang berubah**
  untuk tabel itu.

Contoh pesan gagal:

```
Kolom wajib tidak ada di Data_RFM_Pelanggan.csv: Total_Pcs_Dibeli.
Header yang terbaca: Nama Pelanggan, Recency, Frekuensi_Kedatangan, ...
Perbaiki header CSV-nya, atau sesuaikan LaporanImporter::SPEC kalau nama
kolomnya memang sengaja berubah.
```

> Ini disengaja. Versi sebelumnya memetakan kolom secara **posisional**, dan itu
> berbahaya: waktu `Data_RFM_Pelanggan.csv` menyisipkan 3 kolom baru di tengah,
> pemetaan posisional akan memasukkan `Monetary` ke `r_score` dan `F_Score` ke
> `segmen` — dashboard tampil "normal" padahal datanya salah total, tanpa error
> sama sekali. Sekarang kasus itu berhenti di langkah impor.

Kalau nama kolom di CSV memang sengaja diganti, sesuaikan konstanta `SPEC` di
[`app/Services/LaporanImporter.php`](../app/Services/LaporanImporter.php).

## Kalau bentuk datanya berubah (bukan cuma isinya)

Perlu kolom baru di database (seperti waktu `Total_Poin_Loyalty` ditambahkan):

1. Bikin migration untuk kolom barunya.
2. Tambah baris pemetaan di `LaporanImporter::SPEC`.
3. Tambah kolom ke `$fillable` + `$casts` di model `App\Models\Laporan*`.
4. Kalau perlu tampil di Excel, tambah di `app/Exports/Sheets/*Sheet.php`.
5. Update `docs/kontrak-dashboard-v1.md` supaya frontend tahu ada field baru.

Endpoint `/api/dashboard/rfm` dan `/switch` mengirim seluruh kolom model apa
adanya, jadi setelah langkah 1–3 field barunya otomatis muncul di API.

## Catatan cakupan data

- **Revenue per ukuran hanya minuman.** Dessert & cookies (ukuran `Cup`/`Pack`)
  tidak dihitung, jadi totalnya lebih kecil dari `/ringkasan`. Diatur lewat
  konstanta `UKURAN_NON_MINUMAN` di `app/Services/LaporanQuery.php`.
- **Poin loyalty**: 1 poin per Rp 1.000, item non-minuman tidak menghasilkan
  poin. Kolom `Poin Loyalty` di CSV transaksi sudah mengikuti aturan ini.
- **Segmen RFM** sejak data revisi Juni–Juli 2026: `Loyal`, `Potensial`,
  `Butuh Perhatian`, `Pelanggan Baru`.

## Test terkait

`tests/Feature/LaporanImportTest.php` mengunci perilaku impor — termasuk kasus
kolom disisipkan di tengah, urutan kolom diacak, dan header hilang. Jalankan
setelah mengubah importer:

```bash
php artisan test --filter=LaporanImportTest
```
