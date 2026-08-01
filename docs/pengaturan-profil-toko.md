# Pengaturan, Profil Saya & Info Toko

Backend untuk halaman **Pengaturan**, tab "Profil Saya" dan "Info Toko".

Tab **"Pengaturan Struk" sengaja tidak dibuat**: pilihan printer, jenis kertas,
dan toggle auto-cetak adalah preferensi perangkat kasir (bahkan nama printer
berbeda per komputer), bukan data yang perlu disimpan server. Simpan di
`localStorage` browser kasir saja. Yang memang milik server dari tab itu cuma
header notanya, dan itu sudah ditangani Info Toko di bawah.

Semua endpoint butuh header `Authorization: Bearer <token>`.

| Method & Path | Role | Keterangan |
|---|---|---|
| `GET /api/me` | kasir, manager | Profil akun sendiri |
| `PATCH /api/me` | kasir, manager | Edit Profil |
| `POST /api/me/password` | kasir, manager | Ganti Password |
| `GET /api/pengaturan/toko` | kasir, manager | Info toko |
| `PATCH /api/pengaturan/toko` | manager | Simpan Info Toko |

---

## 1. Profil Saya

### GET /api/me

```json
{
  "user": {
    "id": 1,
    "nama": "Ghefira Meyta",
    "email": "ghefira@gmail.com",
    "no_telepon": "+62 812 3456 789",
    "role": "manager"
  }
}
```

`no_telepon` baru ditambahkan dan ikut terbawa di response `POST /api/login`
juga, penambahan field, tidak mengubah field lama.

Header kartu profil di UI ("Ghefira Meyta / Manager · GresSOY") disusun dari
`user.nama` + `user.role` di sini, digabung `nama_toko` dari
`GET /api/pengaturan/toko`. Inisial avatar ("GM") dihitung di frontend.

### PATCH /api/me, Edit Profil

Body: `nama`, `email`, `no_telepon`, semuanya opsional, kirim yang berubah
saja. Field yang tidak dikirim dipertahankan. Body kosong ditolak `422`.
Response sama dengan `GET /api/me`.

- `nama`, wajib tidak kosong kalau dikirim, maks 255.
- `email`, harus valid dan belum dipakai akun lain. Menyimpan ulang email
  sendiri **tidak** dianggap bentrok.
- `no_telepon`, maks 30 karakter, boleh `null` untuk mengosongkan. Disimpan
  apa adanya, tidak dinormalisasi seperti `customer.no_wa` (nomor ini untuk
  ditampilkan, bukan kunci pencarian).

> **`role` dan `is_active` tidak bisa diubah lewat endpoint ini, dan jangan
> ditambahkan nanti.** Endpoint ini mengedit akun pemanggil sendiri, kalau
> kedua field itu ikut bisa ditulis, kasir mana pun bisa mengangkat dirinya
> jadi manager dari halaman profilnya sendiri. Mengirim `role` di body akan
> diabaikan diam-diam (ada test-nya).

Endpoint ini juga tidak menerima id user di path maupun body, jadi tidak ada
jalan mengedit akun orang lain lewat sini. Manajemen akun karyawan (kalau nanti
dibutuhkan) harus jadi endpoint manager-only yang terpisah.

### POST /api/me/password, Ganti Password

```json
{
  "password_lama": "...",
  "password_baru": "...",
  "password_baru_confirmation": "..."
}
```

Aturan: `password_baru` minimal 8 karakter, harus cocok dengan
`password_baru_confirmation`, dan harus berbeda dari password lama.

`password_lama` wajib walaupun pemanggil sudah login, token yang bocor jangan
sampai cukup untuk mengambil alih akun secara permanen. Kalau tidak cocok:
`422 password_lama_salah` (bukan `validasi_gagal`, supaya UI bisa menyorot
field "Password Lama" secara spesifik).

Berhasil → `200`:

```json
{ "message": "Password berhasil diubah. Perangkat lain yang masih login sudah dikeluarkan." }
```

**Semua token lain dicabut, token yang sedang dipakai dibiarkan hidup.** Jadi
kalau alasan menggantinya memang karena akun diduga bocor, sesi penyusup ikut
mati, tapi manager tidak ter-logout dari halaman yang sedang dia buka, dan
frontend tidak perlu login ulang setelah ganti password.

---

## 2. Info Toko

Dipakai sebagai header nota dan laporan, jadi satu sumber, jangan diketik
ulang di tiap tempat yang butuh.

Sama seperti `pengaturan_loyalty`, tabelnya **singleton dan tidak di-seed**:
0 baris = pakai nilai bawaan (`GresSOY`, `08:00`, `20:00`). Baris pertama
dibuat saat manager benar-benar menekan Simpan, supaya `updated_by` jujur
mencatat siapa yang pertama mengubah.

### GET /api/pengaturan/toko

```json
{
  "data": {
    "nama_toko": "GresSOY",
    "no_telepon": null,
    "alamat": null,
    "jam_buka": "08:00",
    "jam_tutup": "20:00",
    "diperbarui_pada": null,
    "diperbarui_oleh": null
  }
}
```

Kasir ikut boleh baca karena dia yang mencetak nota berheader ini.

### PATCH /api/pengaturan/toko, manager only

Body: `nama_toko`, `no_telepon`, `alamat`, `jam_buka`, `jam_tutup`, semuanya
opsional, kirim yang berubah saja. Body kosong ditolak `422`. Response sama
dengan `GET`.

- `nama_toko`, wajib tidak kosong kalau dikirim, maks 255.
- `no_telepon`, maks 30, boleh `null`.
- `alamat`, maks 500, boleh `null`.
- `jam_buka` / `jam_tutup`, format 24 jam `HH:MM` (`08:00`, bukan `8 pagi`
  atau `08:00:00`), boleh `null`.

**Urutan jam tidak divalidasi.** Toko yang tutup lewat tengah malam (buka
`08:00`, tutup `02:00`) tetap diterima, memaksa `jam_tutup > jam_buka` akan
memblokir kasus yang sah.

> Catatan implementasi: kolomnya bertipe `time`, dan drivernya membaca berbeda,
> Postgres mengembalikan `"07:30:00"`, SQLite `"07:30"`. Response API selalu
> dipangkas ke `HH:MM` lewat `PengaturanToko::jam()` supaya produksi dan test
> tidak berbeda bentuk. Kalau menambah field jam baru, lewatkan juga ke sana.

---

## 3. Kode error

| Kode | HTTP | Sumber |
|---|---|---|
| `unauthenticated` | 401 | Tanpa/invalid token |
| `tidak_berwenang` | 403 | Kasir mencoba `PATCH /api/pengaturan/toko` |
| `password_lama_salah` | 422 | Password lama tidak cocok saat ganti password |
| `validasi_gagal` | 422 | Email bentrok/tidak valid, jam bukan `HH:MM`, password baru terlalu pendek atau konfirmasi tidak cocok, body kosong |
