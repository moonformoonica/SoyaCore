# Format Export Excel — Laporan SoyaCore

> **Status: v1 — locked, 6 Juli 2026.**
> Format ini sudah disepakati dengan Kamila dan menjadi acuan pembuatan
> export class (Laravel Excel) di milestone berikutnya.

Export terdiri dari **2 sheet** dalam satu file Excel, sesuai Diagram Laporan Excel.

Aturan umum:
- Hanya transaksi dengan **`transaksi.status = 'lunas'`** yang masuk laporan.
- Kolom tanggal/waktu memakai **`transaksi.waktu_lunas`** (bukan `created_at`),
  karena laporan penjualan dihitung dari saat pembayaran, bukan saat pesanan dibuat.
- Semua nilai uang adalah rupiah bulat (integer).

---

## Sheet 1 — "Penjualan"

**Grain: satu baris = satu item transaksi** (baris `detail_transaksi`), bukan per transaksi.
Transaksi dengan 3 item menghasilkan 3 baris; kolom level-transaksi (ID, tanggal, pelanggan,
platform, catatan) berulang di tiap barisnya.

| # | Kolom Excel | Sumber | Keterangan |
|---|---|---|---|
| 1 | ID Transaksi | `transaksi.id` | |
| 2 | Tanggal | `transaksi.waktu_lunas` (bagian tanggal) | Format `DD/MM/YYYY` |
| 3 | Waktu | `transaksi.waktu_lunas` (bagian jam) | Format `HH:MM` |
| 4 | Platform | `transaksi.platform` | Boleh kosong; catatan manual (mis. Shopee/GoJek/Grab) |
| 5 | Nama Pelanggan | `customer.nama` (via `transaksi.customer_id`) | Kosong jika walk-in tanpa data pelanggan |
| 6 | No. WhatsApp | `customer.no_wa` (via `transaksi.customer_id`) | Kosong jika walk-in |
| 7 | Nama Produk | `menu.nama` (via `detail_transaksi.menu_id`) | |
| 8 | Rasa | `menu.rasa` | Boleh kosong |
| 9 | Ukuran | `menu.ukuran` | Boleh kosong |
| 10 | Jumlah | `detail_transaksi.qty` | |
| 11 | Harga Satuan | `detail_transaksi.harga_satuan` | Snapshot harga saat transaksi — **jangan** ambil `menu.harga` live |
| 12 | Total | `detail_transaksi.subtotal` | `qty × harga_satuan`; 0 jika `is_reward = true` |
| 13 | Point Loyalty | `transaksi.point_earned` | Level transaksi, berulang per baris item |
| 14 | Catatan | `transaksi.catatan` | Free text, boleh kosong |

Filter: `transaksi.status = 'lunas'`. Urutkan `transaksi.waktu_lunas` ascending.

---

## Sheet 2 — "Pelanggan"

**Grain: satu baris = satu pelanggan** (agregat dari seluruh transaksi lunas miliknya).
Pelanggan tanpa transaksi lunas tidak perlu dimunculkan.

| # | Kolom Excel | Sumber / Agregasi | Keterangan |
|---|---|---|---|
| 1 | No. WhatsApp | `customer.no_wa` | Kunci baris |
| 2 | Nama Pelanggan | `customer.nama` | |
| 3 | Tgl Pertama Beli | `MIN(transaksi.waktu_lunas)` | Status lunas saja |
| 4 | Tgl Terakhir Beli | `MAX(transaksi.waktu_lunas)` | Status lunas saja |
| 5 | Total Transaksi | `COUNT(transaksi.id)` | Jumlah transaksi lunas |
| 6 | Total Belanja | `SUM(transaksi.total)` | Status lunas saja |
| 7 | Rasa Favorit | `MODE(menu.rasa)` via `detail_transaksi` | Rasa yang paling sering dibeli (weighted by qty); abaikan baris `rasa` kosong |
| 8 | Ukuran Favorit | `MODE(menu.ukuran)` via `detail_transaksi` | Ukuran yang paling sering dibeli (weighted by qty); abaikan baris `ukuran` kosong |
| 9 | Platform Utama | `MODE(transaksi.platform)` | Platform yang paling sering muncul; abaikan baris `platform` kosong |

Catatan implementasi untuk export class (Laravel Excel):
- Semua agregasi difilter `transaksi.status = 'lunas'`.
- MODE = nilai yang paling sering muncul; jika seri, ambil salah satu (yang terbaru
  dipakai) — tidak perlu tie-breaking rumit.
- Join Rasa/Ukuran Favorit: `customer → transaksi (lunas) → detail_transaksi → menu`.
