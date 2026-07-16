# Catatan Milestone 2 — SoyaCore

> Branch: `feature/m2-transaksi-diskon-auth` · Selesai: 14 Juli 2026
> Scope: Auth ringan (Sanctum), CRUD kategori & menu, alur transaksi kasir, engine diskon.

## Ringkasan Modul yang Dibangun

| Modul | File utama |
|---|---|
| Auth (Sanctum) | `AuthController`, `EnsureUserHasRole` (alias `role:`), `routes/api.php`, `config/sanctum.php` |
| Format error terpusat | `app/Exceptions/ApiException.php` + renderer di `bootstrap/app.php` |
| CRUD Kategori | `KategoriController`, `Store/UpdateKategoriRequest`, `KategoriResource` |
| CRUD Menu | `MenuController`, `Store/UpdateMenuRequest`, `MenuResource` |
| Alur transaksi | `TransaksiController`, `TransaksiItemController`, `TransaksiService`, `TransaksiResource`, `DetailTransaksiResource` |
| Engine diskon | `app/Services/DiskonEngine.php` (unit-tested terpisah) |
| Normalisasi WA | `app/Support/NomorWa.php` |
| Seeder | `DatabaseSeeder` — bug `name`→`nama` diperbaiki + data contoh M2 |
| Dokumentasi | `docs/kontrak-api-kasir-v1-draft.md` (DRAFT, bukan bagian kontrak v1 locked) |

Detail endpoint + contoh request/response: lihat `docs/kontrak-api-kasir-v1-draft.md`.

## Asumsi & Keputusan Desain (final, dipakai di implementasi)

1. **"CRUD semua fitur" M2 = CRUD Kategori + Menu saja.** Roadmap M2 Monica
   eksplisit menyebut "CRUD menu & kategori". CRUD Customer terbentuk otomatis
   lewat find-or-create di alur transaksi; manajemen akun User dilakukan manual
   via seeder/tinker (bukan endpoint) untuk kebutuhan KP.
2. **Endpoint kasir dirancang baru** di luar kontrak v1 self-order (yang locked).
   Tiga endpoint publik self-order (`GET /api/menu` publik, `POST /api/order`,
   `GET /api/loyalty/{no_wa}`) TIDAK disentuh — itu M3. Catatan: `GET /api/menu`
   di M2 ini versi internal ber-auth; versi publik per kategori (kontrak v1) akan
   dibangun M3 dan bisa memakai ulang `MenuResource`.
3. **Tambah item bertahap** (satu endpoint per aksi item), bukan satu payload
   sekali kirim — sesuai 3 langkah roadmap dan perilaku kasir nyata.
4. **`point_earned = 1` saat lunas; tabel `loyalty` tidak disentuh** — increment
   stempel/redeem adalah scope LoyalSeed (M3).
5. **Kode pesanan kasir: `#K` + urutan harian 3 digit** (`#K001`, `#K002`, reset
   tiap hari) — tidak bentrok dengan format `#A23` self-order.
6. **Diskon nominal melebihi subtotal saat DITERAPKAN → ditolak 422**
   (`diskon_melebihi_subtotal`), sehingga `total` tidak pernah negatif.
   (Perilaku setelah item dihapus berubah per revisi skema 15 Juli —
   lihat bagian "Revisi Skema" di bawah.)
7. **Diskon 0% / 0 rupiah dianggap "tidak ada diskon"** dan valid (menghapus
   diskon sebelumnya). Diskon baru selalu MENGGANTIKAN yang lama, tidak menumpuk.
8. **`diskon_persen` integer** (kolom `unsignedInteger`), jadi `custom_persen`
   menerima integer 0–100; pembulatan `diskon_nilai` pakai `round()` ke rupiah
   terdekat.
9. **Item menu sama (non-reward) digabung** saat ditambahkan lagi: qty
   dijumlahkan, snapshot `harga_satuan` lama dipertahankan selama pending.
10. **Menu yang pernah dipakai transaksi tidak dihapus permanen** oleh
    `DELETE /api/menu/{id}` — dinonaktifkan (`is_active = false`) demi menjaga
    histori & menghindari error FK. Menu yang belum pernah dipakai dihapus betulan.
11. **Transaksi `batal` tidak dihapus permanen** — tetap tersimpan untuk audit.
12. **Format error validasi**: `{"error": "validasi_gagal", "message": <error
    pertama>, "details": {per-field}}` — field `details` adalah tambahan di atas
    format standar v1 untuk memudahkan debugging frontend.

## Revisi Skema Transaksi (15 Juli 2026 — keputusan Monica)

Mengikuti **ERD revisi**, kolom `nomor_meja`, `sumber`, `platform`,
`subtotal`, `diskon_persen`, `diskon_nilai`, `catatan` **dipindah dari
`transaksi` ke `detail_transaksi`** (migration
`2026_07_15_000001_move_transaksi_fields_to_detail_transaksi`). Tabel
`transaksi` kini hanya menyimpan `total` sebagai agregat uang. Keputusan ini
menggantikan analisis brief M2 §2 (yang semula menganggap posisi kolom di
gambar ERD sebagai artefak layout drawio) — dikonfirmasi langsung oleh
Monica bahwa ERD revisi adalah acuan final.

Konsekuensi yang menyertai (semua sudah diimplementasikan + dites):

- `POST /api/transaksi` kini hanya menerima `customer`;
  `nomor_meja`/`platform`/`catatan` pindah ke payload tambah/ubah item.
  `sumber` di-set server per item (`kasir`).
- Endpoint diskon tetap level transaksi (spec M2 §5.4), tapi hasilnya
  **disimpan per item**: persen direplikasi ke tiap item; nominal
  didistribusi proporsional terhadap subtotal item (sisa pembulatan ke item
  terakhir, `DiskonEngine::distribusi()`).
- **Menghapus item ikut menghapus porsi diskon nominal item itu** —
  menggantikan perilaku lama "clamp diskon ke subtotal" (keputusan lama #6).
  `total` tetap tidak pernah negatif.
- `subtotal`/`diskon_persen`/`diskon_nilai` di response transaksi adalah
  agregat item (dihitung, tidak tersimpan di tabel `transaksi`).
- Generator kode pesanan tidak bisa lagi filter kolom `sumber` di
  `transaksi` → memakai pola `kode_pesanan LIKE '#K%'`.
- Migration sudah dijalankan ke Supabase dan struktur kolom terverifikasi;
  alur penuh (login → transaksi → item → diskon 20% → bayar) dites
  end-to-end terhadap Supabase, hasil benar.

## Temuan Saat Pengerjaan

- **Supabase sempat tidak bisa diakses** saat M2 dikerjakan (error
  `FATAL: (ENOTFOUND) tenant/user ... not found` dari pooler), sehingga seluruh
  verifikasi M2 dilakukan via test suite (SQLite in-memory) — hijau semua.
  **Sudah diselesaikan (14 Juli 2026):** setelah project aktif kembali,
  ternyata **session pooler (port 5432) tetap menolak tenant**, sedangkan
  **transaction pooler (port 6543) di host yang sama berfungsi normal**.
  `DB_PORT` di `.env` diganti `5432 → 6543` (catatan penting: `.env` tidak
  di-commit — anggota tim lain yang kena error yang sama perlu mengubah ini
  manual, atau cek connection string terbaru di dashboard Supabase → Connect).
  Setelah itu `php artisan migrate` (tabel `personal_access_tokens`) dan
  `php artisan db:seed` sudah dijalankan ke Supabase, dan `POST /api/login`
  dites end-to-end sukses mengembalikan token.
- Bug seeder M1 (`'name'` vs `'nama'`) diperbaiki sesuai §2.2. User seed
  skeleton lama (`test@example.com`) diganti akun manager yang konsisten
  dengan pola akun kasir: `Manager Gressoy` / `manager@gressoy.test`
  ber-role `manager`, plus `Kasir Gressoy` / `kasir@gressoy.test` ber-role
  `kasir` (password keduanya: `password`).

## Checklist Definition of Done

- [x] `php artisan migrate:fresh --seed` berjalan tanpa error (dijalankan ke
      Supabase; seeder menghasilkan 1 manager aktif, 1 kasir aktif, dan data
      menu asli GresSOY: 6 kategori, 93 baris menu — tiap kombinasi
      nama × ukuran/varian jadi satu baris karena harga beda per ukuran;
      komposisi minuman disimpan di kolom `rasa`).
- [x] `POST /api/login` dengan kredensial seeder mengembalikan token (dites di
      `AuthTest`).
- [x] Endpoint tanpa token → 401; endpoint manager-only dengan token kasir → 403.
- [x] CRUD kategori & menu berfungsi penuh sesuai aturan role.
- [x] Alur penuh: buat transaksi → 2 item berbeda → subtotal benar (35000) →
      diskon preset 20% (`diskon_nilai` 7000, total 28000) → ganti custom nominal
      5000 (total 30000) → bayar cash → `lunas`, `waktu_lunas` terisi,
      `point_earned = 1` (dites di `TransaksiDiskonTest`).
- [x] Transaksi `lunas` menolak tambah item / ubah diskon / bayar ulang (409).
- [x] Diskon nominal > subtotal ditolak 422, total tidak pernah negatif.
- [x] Customer baru dibuat saat no WA belum ada; dipakai ulang saat sudah ada
      (dites dengan variasi spasi/strip: `0812 3456 7890`, `081234567890`,
      `0812-3456-7890 ` → 1 customer).
- [x] `php artisan test` hijau semua — 32 passed (128 assertions), termasuk test
      bawaan skeleton.
- [x] Bug seeder §2.2 diperbaiki.
- [x] `docs/kontrak-api-v1.md` & `docs/format-export-excel.md` tidak berubah
      (`git diff master -- docs/kontrak-api-v1.md docs/format-export-excel.md` kosong).
- [x] `docs/m2-notes.md` (file ini) berisi ringkasan, asumsi, dan checklist.
- [x] Commit granular per modul di branch `feature/m2-transaksi-diskon-auth`.

## Perhatian Sebelum M3

- `MenuResource` dan `TransaksiService`/`DiskonEngine` siap dipakai ulang untuk
  `GET /api/menu` publik dan `POST /api/order` (tinggal bungkus per kategori
  sesuai kontrak v1).
- `NomorWa::normalisasi()` siap dipakai `GET /api/loyalty/{nomor_wa}`.
- LoyalSeed (M3) tinggal hook di transisi `pending → lunas`
  (`TransaksiController::bayar`) — satu-satunya tempat status berubah ke lunas.
- Kode pesanan self-order (`#A23`) perlu generator terpisah di M3; generator
  kasir sengaja diberi prefix `#K` agar tidak bentrok.
- Jalankan `php artisan migrate` ke Supabase (tabel `personal_access_tokens`)
  begitu database-nya bisa diakses lagi.
