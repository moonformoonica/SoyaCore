@extends('layouts.app')

@section('title', 'Pengaturan')

@section('content')

<div class="set-page">

  <div class="set-header">
    <h2>Pengaturan</h2>
    <p>Kelola profil kamu dan pengaturan akun toko Gres'Soy</p>
  </div>

  <div class="set-shell">

    <div class="set-tabs" id="setTabs">
      <button class="active" data-panel="profil">
        <i class="fa-solid fa-user"></i>
        <span class="label">Profil Saya</span>
        <span class="tab-underline"></span>
      </button>
      <button data-panel="toko">
        <i class="fa-solid fa-store"></i>
        <span class="label">Info Toko</span>
        <span class="tab-underline"></span>
      </button>
      {{-- Manager-only. Disembunyikan lewat JS berdasarkan role, sama seperti
           kartu QRIS — server tetap menolak kasir dengan 403, ini cuma supaya
           kasir tidak melihat tab yang pasti gagal dibukanya. --}}
      <button data-panel="akun" id="tabAkun" hidden>
        <i class="fa-solid fa-users"></i>
        <span class="label">Akun Kasir</span>
        <span class="tab-underline"></span>
      </button>
    </div>

    {{-- ============ PROFIL ============ --}}
    <div class="set-panel active" data-panel="profil">
      <div class="set-card">
        <div class="card-head">
          <div>
            <h3>Informasi Pribadi</h3>
            <p>Perbarui detail profil kamu</p>
          </div>
          <button class="btn-pill" id="btnEditProfil">
            <i class="fa-solid fa-pen" id="iconEditProfil"></i>
            <span id="labelEditProfil">Edit Profil</span>
          </button>
        </div>
        <div class="card-divider"></div>
        <div class="avatar-row">
          <div class="avatar-wrap">
            <div class="avatar-circle" id="pfAvatar">–</div>
            <img class="avatar-foto" id="pfAvatarFoto" alt="Foto profil" style="display:none;">
          </div>
          <div class="avatar-info">
            <h4 id="pfAvatarNama">–</h4>
            <span id="pfAvatarRole">–</span>
            <div class="avatar-actions">
              <button type="button" class="avatar-btn" id="pfFotoUpload">Ubah Foto</button>
              <button type="button" class="avatar-btn danger" id="pfFotoHapus" style="display:none;">Hapus</button>
            </div>
          </div>
          <input type="file" id="pfFotoInput" accept="image/jpeg,image/png,image/jpg" hidden>
        </div>

        <div id="pfMsg" class="set-msg" style="display:none;"></div>

        <div class="set-grid">
          <div class="form-group">
            <label>Nama Lengkap</label>
            <input type="text" id="pfNama" class="profil-field" disabled>
          </div>
          <div class="form-group">
            <label>Email</label>
            <input type="email" id="pfEmail" class="profil-field" disabled>
          </div>
        </div>
        <div class="set-grid single" style="margin-top:20px;">
          <div class="form-group">
            <label>Nomor Telepon</label>
            <input type="text" id="pfTelepon" class="profil-field" placeholder="+62" disabled>
          </div>
        </div>
      </div>

      <div class="set-card" style="margin-top:24px;">
        <div class="card-head">
          <div>
            <h3>Ganti Password</h3>
            <p>Perbarui password akun kamu secara berkala</p>
          </div>
        </div>
        <div class="card-divider"></div>

        <div id="pfPassMsg" class="set-msg" style="display:none;"></div>

        <div class="set-grid single">
          <div class="form-group">
            <label>Password Lama</label>
            <input type="password" id="pfPassLama" placeholder="Masukkan password lama" autocomplete="current-password">
          </div>
        </div>
        <div class="set-grid single" style="margin-top:20px;">
          <div class="form-group">
            <label>Password Baru</label>
            <input type="password" id="pfPassBaru" placeholder="Minimal 8 karakter" autocomplete="new-password">
          </div>
        </div>
        <div class="set-grid single" style="margin-top:20px;">
          <div class="form-group">
            <label>Konfirmasi Password Baru</label>
            <input type="password" id="pfPassKonf" placeholder="Ulangi password baru" autocomplete="new-password">
          </div>
        </div>
        <button class="btn-save" style="margin-top:22px;" id="pfPassBtn">Ubah Password</button>
      </div>
    </div>

    {{-- ============ INFO TOKO ============ --}}
    <div class="set-panel" data-panel="toko">
      <div class="set-card">
        <div class="card-head">
          <div>
            <h3>Info Toko</h3>
            <p>Dipakai di header nota dan laporan</p>
          </div>
        </div>
        <div class="card-divider"></div>

        <div id="tkMsg" class="set-msg" style="display:none;"></div>

        <div class="set-grid">
          <div class="form-group">
            <label>Nama Toko</label>
            <input type="text" id="tkNama" placeholder="Gres'Soy">
          </div>
          <div class="form-group">
            <label>Nomor Telepon Toko</label>
            <input type="text" id="tkTelepon" placeholder="+62">
          </div>
        </div>
        <div class="set-grid single" style="margin-top:20px;">
          <div class="form-group">
            <label>Alamat</label>
            <input type="text" id="tkAlamat" placeholder="Alamat toko">
          </div>
        </div>
        <div class="set-grid" style="margin-top:20px;">
          <div class="form-group">
            <label>Jam Buka</label>
            <input type="time" id="tkJamBuka">
          </div>
          <div class="form-group">
            <label>Jam Tutup</label>
            <input type="time" id="tkJamTutup">
          </div>
        </div>
        <button class="btn-save" style="margin-top:22px;" id="tkSaveBtn">Simpan Info Toko</button>
      </div>

      {{-- QRIS & QR menu: manager-only. Kartu ini disembunyikan untuk kasir,
           bukan sekadar mengandalkan 403 dari server. --}}
      <div class="set-card" id="qrCard" style="margin-top:24px;">
        <div class="card-head">
          <div>
            <h3>QRIS &amp; QR Menu</h3>
            <p>Gambar QRIS untuk pembayaran dan QR menu untuk ditempel di meja</p>
          </div>
        </div>
        <div class="card-divider"></div>

        <div id="qrMsg" class="set-msg" style="display:none;"></div>

        <div class="qr-grid">

          <div class="qr-box">
            <span class="qr-label">QRIS Pembayaran</span>
            <div class="qr-preview">
              <img id="qrisImg" alt="Gambar QRIS toko" hidden>
              <p class="qr-empty" id="qrisEmpty">Belum ada QRIS diunggah</p>
            </div>
            <input type="file" id="qrisInput" accept="image/jpeg,image/png,image/jpg" hidden>
            <div class="qr-actions">
              <button type="button" class="btn-save" id="qrisUploadBtn">Unggah QRIS</button>
              <button type="button" class="qr-btn-hapus" id="qrisHapusBtn" hidden>Hapus</button>
            </div>
            <p class="qr-hint">JPG atau PNG, maksimal 2 MB. Tampil di layar pembayaran SoyaScan.</p>
          </div>

          <div class="qr-box">
            <span class="qr-label">QR Menu (untuk meja)</span>
            <div class="qr-preview">
              <img id="qrMenuImg" alt="QR menu SoyaScan" hidden>
              <p class="qr-empty" id="qrMenuEmpty">Memuat…</p>
            </div>
            <div class="qr-actions">
              <button type="button" class="btn-save" id="qrMenuUnduhBtn">Unduh</button>
              <button type="button" class="qr-btn-cetak" id="qrMenuCetakBtn">Cetak</button>
            </div>
            <p class="qr-hint">Cetak dan tempel di meja — pelanggan scan untuk memesan sendiri.</p>
          </div>

        </div>
      </div>
    </div>

    {{-- ============ AKUN KASIR (manager only) ============ --}}
    <div class="set-panel" data-panel="akun">
      <div class="set-card">
        <div class="card-head">
          <div>
            <h3>Akun Kasir</h3>
            <p>Tiap kasir wajib punya akun sendiri — laporan per-kasir menghitung penjualan dari akun yang menandai lunas</p>
          </div>
          <button class="btn-pill" id="akBtnTambah">
            <i class="fa-solid fa-plus"></i>
            <span>Tambah Akun</span>
          </button>
        </div>
        <div class="card-divider"></div>

        <div id="akMsg" class="set-msg" style="display:none;"></div>

        {{-- Form tambah akun, tersembunyi sampai tombol di atas ditekan --}}
        <div class="ak-form" id="akForm" hidden>
          <div class="set-grid">
            <div class="form-group">
              <label>Nama Lengkap</label>
              <input type="text" id="akNama" placeholder="mis. Rani">
            </div>
            <div class="form-group">
              <label>Email</label>
              <input type="email" id="akEmail" placeholder="kasir2@gressoy.test" autocomplete="off">
            </div>
          </div>
          <div class="set-grid" style="margin-top:20px;">
            <div class="form-group">
              <label>Nomor Telepon <span class="ak-opsional">(opsional)</span></label>
              <input type="text" id="akTelepon" placeholder="+62">
            </div>
            <div class="form-group">
              <label>Role</label>
              <select id="akRole">
                <option value="kasir">Kasir</option>
                <option value="manager">Manager</option>
              </select>
            </div>
          </div>
          <div class="set-grid single" style="margin-top:20px;">
            <div class="form-group">
              <label>Password Awal</label>
              <input type="password" id="akPassword" placeholder="Minimal 8 karakter" autocomplete="new-password">
            </div>
          </div>
          <div class="ak-form-actions">
            <button class="btn-save" id="akSimpanBtn">Simpan Akun</button>
            <button type="button" class="qr-btn-cetak" id="akBatalBtn">Batal</button>
          </div>
        </div>

        <div class="ak-table-wrap">
          <table class="ak-table">
            <thead>
              <tr>
                <th>Nama</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th class="ak-aksi-head">Aksi</th>
              </tr>
            </thead>
            <tbody id="akBody">
              <tr><td colspan="5" class="ak-state">Memuat akun…</td></tr>
            </tbody>
          </table>
        </div>

        <p class="qr-hint">
          Akun yang sudah pernah bertransaksi tidak bisa dihapus — laporan bulan-bulan
          sebelumnya masih merujuk ke sana. Kasir yang berhenti kerja dinonaktifkan saja:
          tidak bisa login lagi, riwayat penjualannya tetap utuh.
        </p>
      </div>
    </div>

  </div>
</div>

@endsection

@push('styles')
@vite('resources/css/pengaturan/index.css')
@endpush

@push('scripts')
@vite('resources/js/pengaturan/index.js')
@endpush
