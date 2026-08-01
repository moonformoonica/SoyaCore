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
    } catch (e) {  }
  })();

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

  // ---------------- QRIS & QR MENU (manager only) ----------------
  const qrCard = document.getElementById('qrCard');

  if (peranSekarang !== 'manager') {
    qrCard.hidden = true;
  } else {
    const qrisImg = document.getElementById('qrisImg');
    const qrisEmpty = document.getElementById('qrisEmpty');
    const qrisInput = document.getElementById('qrisInput');
    const qrisUploadBtn = document.getElementById('qrisUploadBtn');
    const qrisHapusBtn = document.getElementById('qrisHapusBtn');
    const qrMenuImg = document.getElementById('qrMenuImg');
    const qrMenuEmpty = document.getElementById('qrMenuEmpty');
    function headersUpload() {
      return {
        'Accept': 'application/json',
        ...(token ? { 'Authorization': 'Bearer ' + token } : {}),
      };
    }

    function tampilkanQris(url) {
      if (url) {
        qrisImg.src = url;
        qrisImg.hidden = false;
        qrisEmpty.hidden = true;
        qrisHapusBtn.hidden = false;
        qrisUploadBtn.textContent = 'Ganti QRIS';
      } else {
        qrisImg.removeAttribute('src');
        qrisImg.hidden = true;
        qrisEmpty.hidden = false;
        qrisHapusBtn.hidden = true;
        qrisUploadBtn.textContent = 'Unggah QRIS';
      }
    }

    (async function muatQris() {
      try {
        const res = await fetch('/api/pengaturan/toko', { headers: headers() });
        if (!res.ok) return;
        tampilkanQris(((await res.json()).data || {}).qris_url || null);
      } catch (e) { /* biarkan state kosong */ }
    })();

    qrisUploadBtn.addEventListener('click', () => qrisInput.click());

    qrisInput.addEventListener('change', async function () {
      const file = qrisInput.files && qrisInput.files[0];
      if (!file) return;
      if (!['image/jpeg', 'image/jpg', 'image/png'].includes(file.type)) {
        qrisInput.value = '';
        return pesan('qrMsg', 'Format tidak didukung. Pakai JPG atau PNG.', 'error');
      }
      if (file.size > 2 * 1024 * 1024) {
        qrisInput.value = '';
        return pesan('qrMsg', 'Ukuran file melebihi 2 MB.', 'error');
      }

      const data = new FormData();
      data.append('qris', file);

      qrisUploadBtn.disabled = true;
      const labelAwal = qrisUploadBtn.textContent;
      qrisUploadBtn.textContent = 'Mengunggah…';
      try {
        const res = await fetch('/api/pengaturan/toko/qris', {
          method: 'POST', headers: headersUpload(), body: data,
        });
        const json = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(json.message || 'Gagal mengunggah QRIS.');

        tampilkanQris((json.data || {}).qris_url || null);
        pesan('qrMsg', 'QRIS berhasil diunggah.', 'success');
      } catch (err) {
        pesan('qrMsg', err.message, 'error');
      } finally {
        qrisInput.value = '';
        qrisUploadBtn.disabled = false;
        qrisUploadBtn.textContent = labelAwal;
      }
    });

    qrisHapusBtn.addEventListener('click', async function () {
      if (!confirm('Hapus gambar QRIS? Pelanggan SoyaScan akan diarahkan bayar di kasir.')) return;
      qrisHapusBtn.disabled = true;
      try {
        const res = await fetch('/api/pengaturan/toko/qris', { method: 'DELETE', headers: headers() });
        const json = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(json.message || 'Gagal menghapus QRIS.');
        tampilkanQris(null);
        pesan('qrMsg', 'QRIS dihapus.', 'success');
      } catch (err) {
        pesan('qrMsg', err.message, 'error');
      } finally {
        qrisHapusBtn.disabled = false;
      }
    });

    let qrMenuBlobUrl = null;

    (async function muatQrMenu() {
      try {
        const res = await fetch('/api/pengaturan/toko/qr-menu?format=svg&ukuran=512', {
          headers: { 'Authorization': 'Bearer ' + token },
        });
        if (!res.ok) throw new Error();

        qrMenuBlobUrl = URL.createObjectURL(await res.blob());
        qrMenuImg.src = qrMenuBlobUrl;
        qrMenuImg.hidden = false;
        qrMenuEmpty.hidden = true;
      } catch (e) {
        qrMenuEmpty.textContent = 'QR menu gagal dimuat.';
      }
    })();

    document.getElementById('qrMenuUnduhBtn').addEventListener('click', function () {
      if (!qrMenuBlobUrl) return pesan('qrMsg', 'QR menu belum siap.', 'error');
      const a = document.createElement('a');
      a.href = qrMenuBlobUrl;
      a.download = 'QR-Menu-GresSoy.svg';
      document.body.appendChild(a);
      a.click();
      a.remove();
    });

    document.getElementById('qrMenuCetakBtn').addEventListener('click', function () {
      if (!qrMenuBlobUrl) return pesan('qrMsg', 'QR menu belum siap.', 'error');
      const w = window.open('', '_blank');
      if (!w) return pesan('qrMsg', 'Popup diblokir browser. Izinkan popup untuk mencetak.', 'error');
      w.document.write(
        '<title>QR Menu GresSoy</title>' +
        '<body style="margin:0;display:grid;place-items:center;height:100vh">' +
        '<img src="' + qrMenuBlobUrl + '" style="width:420px;max-width:90vw" onload="window.print()">' +
        '</body>'
      );
      w.document.close();
    });
  }

});
