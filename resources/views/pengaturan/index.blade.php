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
    </div>

  </div>
</div>

@endsection

@push('styles')
<style>
  /* Semua di-scope ke .set-page supaya tidak bentrok dengan CSS global */
  .set-page { padding: 4px 4px 30px; }
  .set-header { margin-bottom: 24px; }
  .set-header h2 { font-size: 30px; font-weight: 700; color: #212B36; margin-bottom: 6px; }
  .set-header p { color: #6B7280; font-size: 14px; }

  .set-shell { max-width: 1000px; }

  .set-tabs { display: flex; gap: 44px; margin-bottom: 30px; flex-wrap: wrap; }
  .set-tabs button { all: unset; box-sizing: border-box; display: flex; flex-direction: column; align-items: center; gap: 10px; cursor: pointer; color: #9CA3AF; min-width: 96px; }
  .set-tabs button i { font-size: 20px; font-weight: 900; font-style: normal; }
  .set-tabs button span.label { font-size: 13px; font-weight: 500; }
  .set-tabs button .tab-underline { width: 100%; height: 3px; border-radius: 3px; background: #E2E8DC; margin-top: 4px; }
  .set-tabs button:hover { color: #4B7137; }
  .set-tabs button.active { color: #2F6B3F; }
  .set-tabs button.active .tab-underline { background: #2F6B3F; }

  .set-panel { display: none; }
  .set-panel.active { display: block; }

  .set-card { background: #fff; border-radius: 16px; border: 1px solid #ECECEC; box-shadow: 0 5px 18px rgba(0,0,0,.05); padding: 28px; }
  .set-card .card-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 20px; }
  .set-card .card-head h3 { font-size: 20px; color: #212B36; margin-bottom: 4px; }
  .set-card .card-head p { color: #6B7280; font-size: 13px; }
  .set-card .card-divider { border-top: 1px solid #ECECEC; margin: 0 0 22px; }

  .set-page .btn-pill { display: flex; align-items: center; gap: 8px; border: none; background: #2E7D32; color: #fff; padding: 10px 20px; border-radius: 999px; font-size: 14px; font-weight: 600; cursor: pointer; transition: .25s; white-space: nowrap; font-family: inherit; }
  .set-page .btn-pill:hover { background: #256428; }

  .set-page .avatar-row { display: flex; align-items: center; gap: 14px; margin-bottom: 22px; }
  .set-page .avatar-wrap { position: relative; width: 56px; height: 56px; flex-shrink: 0; }
  .set-page .avatar-circle { width: 56px; height: 56px; border-radius: 50%; background: #E6F4DD; color: #2F6B3F; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 18px; text-transform: uppercase; }
  .set-page .avatar-foto { width: 56px; height: 56px; border-radius: 50%; object-fit: cover; }
  .set-page .avatar-info h4 { font-size: 16px; color: #212B36; margin-bottom: 2px; }
  .set-page .avatar-info > span { font-size: 13px; color: #6B7280; }
  .set-page .avatar-actions { display: flex; gap: 8px; margin-top: 8px; }
  .set-page .avatar-btn { all: unset; box-sizing: border-box; cursor: pointer; font-size: 12px; font-weight: 600; color: #fff; padding: 5px 12px; background: #F7C85C; border-radius: 7px; transition: .15s; }
  .set-page .avatar-btn:hover { background: #EFB945; }
  .set-page .avatar-btn.danger { color: #C0392B; background: #fff; border: 1px solid #F3C6C0; padding: 4px 11px; }
  .set-page .avatar-btn.danger:hover { background: #FEECEC; }

  .set-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
  .set-grid.single { grid-template-columns: 1fr; }
  .set-page .form-group label { display: block; margin-bottom: 8px; font-size: 13px; color: #374151; font-weight: 500; }
  .set-page .form-group input, .set-page .form-group select { width: 100%; border: 1px solid #D9D9D9; border-radius: 10px; padding: 0 16px; font-size: 14px; height: 46px; transition: .2s; font-family: inherit; background: #fff; color: #212B36; }
  .set-page .form-group input:focus, .set-page .form-group select:focus { outline: none; border-color: #2F6B3F; box-shadow: 0 0 0 3px rgba(47,107,63,.15); }
  .set-page .form-group input:disabled { background: #F7F8FA; color: #6B7280; }

  .set-page .btn-save { background: #2E7D32; color: #fff; border: none; height: 46px; padding: 0 26px; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; transition: .25s; font-family: inherit; }
  .set-page .btn-save:hover { background: #256428; }
  .set-page .btn-save:disabled { background: #A9C7AC; cursor: not-allowed; }

  .set-page .pref-row { display: flex; align-items: center; gap: 14px; }
  .set-page .pref-row span { flex: 1; font-size: 14px; color: #212B36; }
  .set-page .switch { position: relative; display: inline-block; width: 44px; height: 24px; flex-shrink: 0; }
  .set-page .switch input { opacity: 0; width: 0; height: 0; }
  .set-page .slider { position: absolute; cursor: pointer; inset: 0; background: #D9D9D9; border-radius: 999px; transition: .2s; }
  .set-page .slider::before { content: ""; position: absolute; width: 18px; height: 18px; left: 3px; top: 3px; background: #fff; border-radius: 50%; transition: .2s; }
  .set-page .switch input:checked + .slider { background: #2E7D32; }
  .set-page .switch input:checked + .slider::before { transform: translateX(20px); }

  .set-msg { font-size: 12.5px; line-height: 1.5; padding: 9px 12px; border-radius: 8px; margin-bottom: 16px; }
  .set-msg.success { background: #EAF6EC; color: #2F6B37; }
  .set-msg.error { background: #FEECEC; color: #C0392B; }

  @media (max-width: 820px) {
    .set-tabs { gap: 24px; }
    .set-grid { grid-template-columns: 1fr; }
    .set-card .card-head { flex-direction: column; }
  }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const token = localStorage.getItem('auth_token');
  function headers() {
    return {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      ...(token ? { 'Authorization': 'Bearer ' + token } : {}),
    };
  }
  function pesan(elId, teks, jenis) {
    const el = document.getElementById(elId);
    if (!el) return;
    el.textContent = teks;
    el.className = 'set-msg ' + (jenis || '');
    el.style.display = teks ? 'block' : 'none';
  }

  // ---------------- TABS ----------------
  const tabButtons = document.querySelectorAll('#setTabs button');
  const panels = document.querySelectorAll('.set-panel');
  tabButtons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      tabButtons.forEach(b => b.classList.remove('active'));
      panels.forEach(p => p.classList.remove('active'));
      btn.classList.add('active');
      document.querySelector('.set-panel[data-panel="' + btn.dataset.panel + '"]').classList.add('active');
    });
  });

  // ---------------- PROFIL ----------------
  const fNama = document.getElementById('pfNama');
  const fEmail = document.getElementById('pfEmail');
  const fTelepon = document.getElementById('pfTelepon');
  const btnEdit = document.getElementById('btnEditProfil');
  const labelEdit = document.getElementById('labelEditProfil');
  const iconEdit = document.getElementById('iconEditProfil');
  let editing = false;

  function isiAvatar(u) {
    const nama = u.nama || 'Pengguna';
    const inisial = nama.trim().split(/\s+/).slice(0, 2).map(s => s[0] || '').join('');
    document.getElementById('pfAvatar').textContent = inisial || '–';
    document.getElementById('pfAvatarNama').textContent = nama;
    const role = (u.role || '').charAt(0).toUpperCase() + (u.role || '').slice(1);
    document.getElementById('pfAvatarRole').textContent = (role || 'Akun') + " · Gres'Soy";
  }

  let userId = null;

  (async function muatProfil() {
    if (!token) return;
    try {
      const res = await fetch('/api/me', { headers: headers() });
      if (!res.ok) return;
      const u = (await res.json()).user || {};
      userId = u.id;
      fNama.value = u.nama || '';
      fEmail.value = u.email || '';
      fTelepon.value = u.no_telepon || '';
      isiAvatar(u);
      loadFoto();
    } catch (e) { /* biarkan kosong */ }
  })();

  // ---------------- FOTO PROFIL (lokal, per perangkat) ----------------
  // Backend belum punya kolom/endpoint foto, jadi foto disimpan sebagai
  // data URL di localStorage (di-resize dulu supaya ringan) dan dipakai
  // di avatar halaman ini + avatar header.
  const fotoInput = document.getElementById('pfFotoInput');
  const fotoImg = document.getElementById('pfAvatarFoto');
  const fotoCircle = document.getElementById('pfAvatar');
  const fotoHapus = document.getElementById('pfFotoHapus');
  const MAKS_FOTO = 5 * 1024 * 1024;

  function fotoKey() { return 'profil_foto_' + (userId || 'x'); }

  function tampilFoto(dataUrl) {
    if (dataUrl) {
      fotoImg.src = dataUrl; fotoImg.style.display = 'block';
      fotoCircle.style.display = 'none'; fotoHapus.style.display = '';
    } else {
      fotoImg.style.display = 'none'; fotoCircle.style.display = '';
      fotoHapus.style.display = 'none';
    }
    // Sinkron ke avatar di header (kalau ada di halaman ini).
    const head = document.getElementById('userAvatar');
    if (head) head.src = dataUrl || head.dataset.default || head.src;
  }

  function loadFoto() {
    try { tampilFoto(localStorage.getItem(fotoKey())); } catch (e) {}
  }

  document.getElementById('pfFotoUpload').addEventListener('click', () => fotoInput.click());

  fotoInput.addEventListener('change', function () {
    const file = fotoInput.files && fotoInput.files[0];
    if (!file) return;
    if (!['image/jpeg', 'image/png', 'image/jpg'].includes(file.type)) {
      return pesan('pfMsg', 'Format tidak didukung. Pakai JPG atau PNG.', 'error');
    }
    if (file.size > MAKS_FOTO) return pesan('pfMsg', 'Ukuran file melebihi 5MB.', 'error');

    const reader = new FileReader();
    reader.onload = function (e) {
      const img = new Image();
      img.onload = function () {
        // Resize ke maksimal 240px (kotak) supaya hemat localStorage.
        const maks = 240;
        const skala = Math.min(1, maks / Math.max(img.width, img.height));
        const w = Math.round(img.width * skala), h = Math.round(img.height * skala);
        const canvas = document.createElement('canvas');
        canvas.width = w; canvas.height = h;
        canvas.getContext('2d').drawImage(img, 0, 0, w, h);
        const dataUrl = canvas.toDataURL('image/jpeg', 0.85);
        try {
          localStorage.setItem(fotoKey(), dataUrl);
          tampilFoto(dataUrl);
          pesan('pfMsg', 'Foto profil diperbarui (tersimpan di perangkat ini).', 'success');
        } catch (err) {
          pesan('pfMsg', 'Gagal menyimpan foto (penyimpanan browser penuh).', 'error');
        }
      };
      img.src = e.target.result;
    };
    reader.readAsDataURL(file);
    fotoInput.value = '';
  });

  fotoHapus.addEventListener('click', function () {
    localStorage.removeItem(fotoKey());
    tampilFoto(null);
    pesan('pfMsg', 'Foto profil dihapus.', 'success');
  });

  async function simpanProfil() {
    const nama = (fNama.value || '').trim();
    const email = (fEmail.value || '').trim();
    if (!nama || !email) { pesan('pfMsg', 'Nama dan email wajib diisi.', 'error'); return false; }
    try {
      const res = await fetch('/api/me', {
        method: 'PATCH', headers: headers(),
        body: JSON.stringify({ nama, email, no_telepon: (fTelepon.value || '').trim() }),
      });
      const json = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(json.message || 'Gagal menyimpan profil.');
      if (json.user) { localStorage.setItem('auth_user', JSON.stringify(json.user)); isiAvatar(json.user); }
      pesan('pfMsg', 'Profil berhasil disimpan.', 'success');
      return true;
    } catch (err) { pesan('pfMsg', err.message, 'error'); return false; }
  }

  btnEdit.addEventListener('click', async function () {
    if (!editing) {
      editing = true;
      document.querySelectorAll('.profil-field').forEach(f => f.disabled = false);
      labelEdit.textContent = 'Simpan';
      iconEdit.className = 'fa-solid fa-check';
      pesan('pfMsg', '', '');
      return;
    }
    // sedang mode edit -> simpan
    btnEdit.disabled = true;
    const ok = await simpanProfil();
    btnEdit.disabled = false;
    if (ok) {
      editing = false;
      document.querySelectorAll('.profil-field').forEach(f => f.disabled = true);
      labelEdit.textContent = 'Edit Profil';
      iconEdit.className = 'fa-solid fa-pen';
    }
  });

  // Ganti password
  const passBtn = document.getElementById('pfPassBtn');
  passBtn.addEventListener('click', async function () {
    const lama = document.getElementById('pfPassLama').value;
    const baru = document.getElementById('pfPassBaru').value;
    const konf = document.getElementById('pfPassKonf').value;
    if (!lama || !baru || !konf) return pesan('pfPassMsg', 'Semua kolom password wajib diisi.', 'error');
    if (baru.length < 8) return pesan('pfPassMsg', 'Password baru minimal 8 karakter.', 'error');
    if (baru !== konf) return pesan('pfPassMsg', 'Konfirmasi password tidak cocok.', 'error');

    passBtn.disabled = true;
    const labelAwal = passBtn.textContent;
    passBtn.textContent = 'Menyimpan...';
    try {
      const res = await fetch('/api/me/password', {
        method: 'POST', headers: headers(),
        body: JSON.stringify({ password_lama: lama, password_baru: baru, password_baru_confirmation: konf }),
      });
      const json = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(json.message || 'Gagal mengubah password.');
      pesan('pfPassMsg', json.message || 'Password berhasil diubah.', 'success');
      document.getElementById('pfPassLama').value = '';
      document.getElementById('pfPassBaru').value = '';
      document.getElementById('pfPassKonf').value = '';
    } catch (err) {
      pesan('pfPassMsg', err.message, 'error');
    } finally {
      passBtn.disabled = false;
      passBtn.textContent = labelAwal;
    }
  });

  // ---------------- INFO TOKO ----------------
  const tkNama = document.getElementById('tkNama');
  const tkTelepon = document.getElementById('tkTelepon');
  const tkAlamat = document.getElementById('tkAlamat');
  const tkBuka = document.getElementById('tkJamBuka');
  const tkTutup = document.getElementById('tkJamTutup');
  const tkBtn = document.getElementById('tkSaveBtn');

  // PATCH /pengaturan/toko manager-only (kasir 403). Kasir tetap boleh LIHAT
  // (GET diizinkan), tapi tidak boleh mengubah — jadi field dikunci & tombol
  // Simpan disembunyikan supaya tidak menawarkan aksi yang pasti ditolak.
  let peranSekarang = '';
  try { peranSekarang = (JSON.parse(localStorage.getItem('auth_user') || '{}').role) || ''; } catch (e) {}
  if (peranSekarang !== 'manager') {
    [tkNama, tkTelepon, tkAlamat, tkBuka, tkTutup].forEach(el => { if (el) el.disabled = true; });
    if (tkBtn) tkBtn.style.display = 'none';
    const el = document.getElementById('tkMsg');
    el.textContent = 'Hanya manager yang bisa mengubah info toko.';
    el.className = 'set-msg';
    el.style.cssText = 'display:block;background:#F3F4F6;color:#6B7280;';
  }

  (async function muatToko() {
    if (!token) return;
    try {
      const res = await fetch('/api/pengaturan/toko', { headers: headers() });
      if (!res.ok) return;
      const d = (await res.json()).data || {};
      tkNama.value = d.nama_toko || '';
      tkTelepon.value = d.no_telepon || '';
      tkAlamat.value = d.alamat || '';
      tkBuka.value = d.jam_buka || '';
      tkTutup.value = d.jam_tutup || '';
    } catch (e) { /* biarkan kosong */ }
  })();

  tkBtn.addEventListener('click', async function () {
    const nama = (tkNama.value || '').trim();
    if (!nama) return pesan('tkMsg', 'Nama toko wajib diisi.', 'error');
    tkBtn.disabled = true;
    const labelAwal = tkBtn.textContent;
    tkBtn.textContent = 'Menyimpan...';
    try {
      const res = await fetch('/api/pengaturan/toko', {
        method: 'PATCH', headers: headers(),
        body: JSON.stringify({
          nama_toko: nama, no_telepon: (tkTelepon.value || '').trim(),
          alamat: (tkAlamat.value || '').trim(),
          jam_buka: tkBuka.value || null, jam_tutup: tkTutup.value || null,
        }),
      });
      const json = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(json.message || 'Gagal menyimpan info toko.');
      pesan('tkMsg', 'Info toko berhasil disimpan.', 'success');
    } catch (err) {
      pesan('tkMsg', err.message, 'error');
    } finally {
      tkBtn.disabled = false;
      tkBtn.textContent = labelAwal;
    }
  });

});
</script>
@endpush
