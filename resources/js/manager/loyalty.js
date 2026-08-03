(function () {

  /* ================= KATALOG REWARD (FIXED) ================= */
  const REWARD_CATALOG = {
    diskon_10: {
      name: 'Diskon 10%', icon: '🏷️',
      desc: 'Berlaku untuk semua menu, dengan minimum pembelian.',
      needsMinPurchase: true, hasMaxDiscount: true,
    },
    diskon_20: {
      name: 'Diskon 20%', icon: '🏷️',
      desc: 'Berlaku untuk semua menu, dengan minimum pembelian.',
      needsMinPurchase: true, hasMaxDiscount: true,
    },
    diskon_30: {
      name: 'Diskon 30%', icon: '🏷️',
      desc: 'Berlaku untuk semua menu, dengan minimum pembelian.',
      needsMinPurchase: true, hasMaxDiscount: true,
    },
    diskon_50: {
      name: 'Diskon 50% (Khusus)', icon: '🏷️',
      desc: 'Diskon besar, berlaku dengan minimum pembelian.',
      needsMinPurchase: true, hasMaxDiscount: true,
    },
    gratis_original: {
      name: 'Gratis Original', icon: '🥛',
      desc: 'Tukar poin dengan Soy Milk Original favorit.',
      sizes: ['Hot', 'Reguler'],
    },
    gratis_coffee_kopi: {
      name: 'Gratis Coffee Kopi', icon: '☕',
      desc: 'Tukar poin dengan Coffee Kopi.',
      sizes: ['Hot', 'Reguler'],
    },
    gratis_honey_lemon: {
      name: 'Gratis Honey Lemon', icon: '🍋',
      desc: 'Tukar poin dengan Honey Lemon segar.',
      sizes: ['Reguler'],
    },
    gratis_mango_monggo: {
      name: 'Gratis Mango Monggo', icon: '🥭',
      desc: 'Tukar poin dengan Mango Monggo.',
      sizes: ['Reguler'],
    },
  };

  const MIN_REDEEM_POINTS = 1;

  /* ================= STATE ================= */
  // Isi awal sebelum katalog asli datang dari backend, dan penampung kalau
  // request-nya gagal. `tipe` dan `bawaan` ikut ditulis karena kartu di layar
  // membacanya, bukan lagi REWARD_CATALOG di berkas ini.
  let rewards = [
    { key: 'diskon_10', label: 'Diskon 10%', tipe: 'diskon', bawaan: true, points: 100, minPurchase: 25000, maxDiscount: 5000 },
    { key: 'diskon_20', label: 'Diskon 20%', tipe: 'diskon', bawaan: true, points: 200, minPurchase: 25000, maxDiscount: 10000 },
    { key: 'diskon_30', label: 'Diskon 30%', tipe: 'diskon', bawaan: true, points: 300, minPurchase: 25000, maxDiscount: 15000 },
    { key: 'diskon_50', label: 'Diskon 50% (Khusus)', tipe: 'diskon', bawaan: true, points: 500, minPurchase: 25000, maxDiscount: 25000 },
    { key: 'gratis_original', label: 'Gratis Original', tipe: 'gratis_menu', bawaan: true, sizes: ['Hot', 'Reguler'], points: 350 },
    { key: 'gratis_coffee_kopi', label: 'Gratis Coffee Kopi', tipe: 'gratis_menu', bawaan: true, sizes: ['Hot', 'Reguler'], points: 450 },
    { key: 'gratis_honey_lemon', label: 'Gratis Honey Lemon', tipe: 'gratis_menu', bawaan: true, sizes: ['Reguler'], points: 400 },
    { key: 'gratis_mango_monggo', label: 'Gratis Mango Monggo', tipe: 'gratis_menu', bawaan: true, sizes: ['Reguler'], points: 400 },
  ];

  let historyAll = [];              
  let historyPage = 1;
  let historySort = 'terbaru';      
  const HISTORY_PER_PAGE = 10;
  let members = 0;
  let activePoints = 0;
  let redeemedThisMonth = null;
  let newMembers = 0;
  let editingRewardKey = null;
  // Terbuka atau tidaknya form tambah reward ditandai kelas `.open` pada
  // backdrop modal, bukan variabel di sini. Satu penanda saja supaya keduanya
  // tidak bisa berbeda pendapat.

  function apiHeaders() {
    const token = localStorage.getItem('auth_token');
    return {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      ...(token ? { 'Authorization': 'Bearer ' + token } : {}),
    };
  }

  function isKasir() {
    try {
      const rawUser = localStorage.getItem('auth_user');
      return rawUser ? JSON.parse(rawUser).role === 'kasir' : false;
    } catch (e) {
      return false;
    }
  }

  /* ================= HELPERS ================= */
  function toast(msg) {
    const t = document.getElementById('lm-toast');
    if (!t) return;
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.classList.remove('show'), 2200);
  }
  function fmtK(n) {
    return n >= 1000 ? (n / 1000).toFixed(1).replace(/\.0$/, '') + 'K' : n;
  }
  function fmtRp(n) {
    return 'Rp' + Number(n).toLocaleString('id-ID');
  }
  function escapeHtml(s) {
    return (s || '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }

  /* ================= RENDER: STATS ================= */
  function renderStats() {
    const el = document.getElementById('lm-stats');
    if (!el) return;
    const rataPoin = members > 0 ? Math.round(activePoints / members) : 0;
    const rewardVal = redeemedThisMonth === null ? '—' : redeemedThisMonth.toLocaleString('id-ID');
    const rewardDelta = redeemedThisMonth === null ? 'Belum ada data penukaran' : 'bulan ini';
    el.innerHTML = `
      <div class="stat-card"><h3>Total member loyalty</h3><p class="val">${members.toLocaleString('id-ID')}</p><p class="delta">Snapshot periode data</p></div>
      <div class="stat-card"><h3>Total Poin Aktif</h3><p class="val">${fmtK(activePoints)}</p><p class="delta">Rata-rata ${rataPoin} poin/member</p></div>
      <div class="stat-card"><h3>Reward ditukar bulan ini</h3><p class="val">${rewardVal}</p><p class="delta">${rewardDelta}</p></div>
      <div class="stat-card"><h3>Member Baru</h3><p class="val">${newMembers.toLocaleString('id-ID')}</p><p class="delta">Segmen Pelanggan Baru</p></div>
    `;
  }

  async function loadStats() {
    const token = localStorage.getItem('auth_token');
    if (token) {
      const headers = { 'Accept': 'application/json', 'Authorization': 'Bearer ' + token };

      // Batas bulan menurut WIB, dihitung dari tanggal hari ini di zona toko.
      // Sebelumnya rentangnya ditentukan `new Date()` milik browser, dan
      // "bulan ini" versi jam laptop bisa berbeda dari "bulan ini" yang dipakai
      // seluruh angka lain di aplikasi.
      const kini = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Jakarta' }));
      const dua = (n) => String(n).padStart(2, '0');
      const awalBulan = `${kini.getFullYear()}-${dua(kini.getMonth() + 1)}-01`;
      const akhirBulan = `${kini.getFullYear()}-${dua(kini.getMonth() + 1)}-${dua(new Date(kini.getFullYear(), kini.getMonth() + 1, 0).getDate())}`;

      try {
        const [rfmRes, liveRes, redeemRes] = await Promise.all([
          fetch('/api/dashboard/rfm', { headers }),
          fetch('/api/transaksi?status=lunas&per_page=200', { headers }),
          fetch(`/api/dashboard/loyalty?start=${awalBulan}&end=${akhirBulan}`, { headers }),
        ]);

        if (rfmRes.ok) {
          const rows = (await rfmRes.json()).data || [];
          members = rows.length;
          activePoints = rows.reduce((s, r) => s + Number(r.total_poin_loyalty || 0), 0);
          newMembers = rows.filter(r => r.segmen === 'Pelanggan Baru').length;
        }

        if (liveRes.ok) {
          const rows = (await liveRes.json()).data || [];
          // Poin dari transaksi live ditambahkan ke total CSV.
          activePoints += rows.reduce((s, t) => s + Number(t.point_earned || 0), 0);
        }

        // Reward ditukar bulan berjalan. Dihitung backend, bukan disaring dari
        // daftar transaksi di browser: daftar itu dipagari `per_page` dan
        // menyaring `status=lunas`, jadi penukaran bisa hilang dari hitungan
        // tanpa gejala apa pun. Alasan lengkapnya di LoyaltyService::rewardDitukar().
        if (redeemRes.ok) {
          redeemedThisMonth = Number((await redeemRes.json()).data?.reward_ditukar ?? 0);
        }
      } catch (e) {
      }
    }
    renderStats();
  }

  /* ================= RENDER: REWARDS ================= */

  /**
   * Deskripsi kartu reward.
   *
   * REWARD_CATALOG cuma memuat delapan jenis bawaan dan dipakai sebagai sumber
   * kalimat yang enak dibaca. Reward buatan manager tidak ada di sana, jadi
   * deskripsinya disusun dari datanya sendiri. Sebelumnya kartu yang keys-nya
   * tidak ada di REWARD_CATALOG di-`return ''`, artinya reward baru apa pun
   * hilang tanpa jejak dari halaman ini.
   */
  function rewardDesc(r) {
    const bawaan = REWARD_CATALOG[r.key];
    if (bawaan) return bawaan.desc;

    if (r.tipe === 'diskon') {
      return `Potongan ${r.persen}% dari subtotal, dengan plafon potongan.`;
    }
    return r.menu ? `Tukar poin dengan ${r.menu}.` : 'Tukar poin dengan menu hadiah.';
  }

  /**
   * Ukuran untuk ditampilkan, tanpa ejaan kembar.
   *
   * `ukuran` dari backend berisi semua EJAAN yang diterima saat mencari menu
   * hadiah, dan "Reguler"/"Regular" sengaja dimuat keduanya di sana. Itu benar
   * untuk pencocokan, tapi salah untuk dibaca: kartunya jadi bertuliskan
   * "Ukuran: Reguler / Regular / Hot", seolah Reguler dan Regular dua pilihan
   * berbeda.
   */
  function ukuranTampil(sizes) {
    const terlihat = [];
    const sudah = new Set();

    (sizes || []).forEach((u) => {
      // "Regular" dinormalkan ke "Reguler" hanya untuk keperluan menyaring
      // kembar; ejaan yang ditampilkan tetap yang pertama muncul.
      const kunci = u.toLowerCase() === 'regular' ? 'reguler' : u.toLowerCase();
      if (sudah.has(kunci)) return;
      sudah.add(kunci);
      terlihat.push(u);
    });

    return terlihat;
  }

  function rewardTags(r) {
    const tags = [];
    const ukuran = ukuranTampil(r.sizes);
    if (ukuran.length) {
      tags.push(`<span class="reward-tag">Ukuran: ${escapeHtml(ukuran.join(' / '))}</span>`);
    }
    if (r.tipe === 'diskon' && r.minPurchase) {
      tags.push(`<span class="reward-tag">Min. belanja ${fmtRp(r.minPurchase)}</span>`);
    }
    if (r.tipe === 'diskon' && r.maxDiscount) {
      tags.push(`<span class="reward-tag">Maks. potongan ${fmtRp(r.maxDiscount)}</span>`);
    }
    return tags.join('');
  }

  function renderRewards() {
    const el = document.getElementById('lm-rewards');
    if (!el) return;

    const kasir = isKasir();

    el.innerHTML = rewards.map(r => {
      const nama = r.label || REWARD_CATALOG[r.key]?.name || r.key;
      const diskon = r.tipe === 'diskon';

      if (kasir) {
        return `
        <div class="reward-card">
          <h4>${escapeHtml(nama)}</h4>
          <p>${escapeHtml(rewardDesc(r))}</p>
          <span class="reward-points">${r.points} poin</span>
          <div class="reward-tags">${rewardTags(r)}</div>
        </div>`;
      }

      if (editingRewardKey === r.key) {
        return `
        <div class="reward-card">
          <div class="edit-form">
            <label>${escapeHtml(nama)}</label>
            <label>Poin dibutuhkan</label>
            <input type="number" id="edit-points-${r.key}" value="${r.points}">
            ${diskon ? `
              <label>Minimal pembelian (Rp)</label>
              <input type="number" id="edit-min-${r.key}" value="${r.minPurchase || 0}">
              <label>Maksimal potongan (Rp)</label>
              <input type="number" id="edit-maks-${r.key}" value="${r.maxDiscount || 0}">
              <p class="hint">Persennya berlaku penuh sampai potongan menyentuh angka ini.</p>
            ` : ''}
            <div class="actions">
              <button class="cancel-btn" data-cancel-reward="${r.key}">Batal</button>
              <button class="save-btn" data-save-reward="${r.key}">Simpan</button>
            </div>
          </div>
        </div>`;
      }

      // Judul dan tombol aksi satu baris flex, bukan tombol yang ditempel
      // absolut di pojok. Dengan absolut, judul panjang seperti
      // "Diskon 50% (Khusus)" tumbuh sampai menyentuh tombolnya, dan seberapa
      // rapat jaraknya jadi bergantung panjang teks tiap kartu.
      return `
      <div class="reward-card">
        <div class="reward-head">
          <h4>${escapeHtml(nama)}</h4>
          <div class="reward-actions">
            <button data-edit-reward="${r.key}" title="Edit poin"><i class="fa-regular fa-pen-to-square"></i></button>
            <button data-remove-reward="${r.key}"
              title="${r.bawaan ? 'Nonaktifkan reward bawaan' : 'Hapus reward'}"><i class="fa-regular fa-trash-can"></i></button>
          </div>
        </div>
        <p>${escapeHtml(rewardDesc(r))}</p>
        <span class="reward-points">${r.points} poin</span>
        <div class="reward-tags">${rewardTags(r)}</div>
      </div>`;
    }).join('');

    // Reward BAWAAN cuma dinonaktifkan, tidak dihapus: logika redeem-nya ada di
    // PHP dan tidak ikut hilang, jadi "menghapus" hanya akan memunculkannya
    // lagi dengan setelan bawaan. Reward buatan manager dihapus betulan.
    el.querySelectorAll('[data-remove-reward]').forEach(b => b.addEventListener('click', async () => {
      const key = b.dataset.removeReward;
      const r = rewards.find(x => x.key === key);
      const nama = r?.label || key;

      const konfirmasi = r?.bawaan
        ? `Nonaktifkan reward "${nama}"? Pelanggan tidak bisa menukarkannya lagi, tapi riwayat penukaran lama tetap terbaca.`
        : `Hapus reward "${nama}" permanen?`;
      if (!confirm(konfirmasi)) return;

      b.disabled = true;
      try {
        if (r?.bawaan) {
          await patchKatalog(key, { is_active: false });
          toast(`Reward "${nama}" dinonaktifkan.`);
        } else {
          await hapusKatalog(key);
          toast(`Reward "${nama}" dihapus.`);
        }
        rewards = rewards.filter(x => x.key !== key);
        renderRewards();
      } catch (err) { toast(err.message); b.disabled = false; }
    }));
    el.querySelectorAll('[data-edit-reward]').forEach(b => b.addEventListener('click', () => {
      editingRewardKey = b.dataset.editReward; renderRewards();
    }));
    el.querySelectorAll('[data-cancel-reward]').forEach(b => b.addEventListener('click', () => {
      editingRewardKey = null; renderRewards();
    }));
    el.querySelectorAll('[data-save-reward]').forEach(b => b.addEventListener('click', async () => {
      const key = b.dataset.saveReward;
      const r = rewards.find(x => x.key === key);
      const nama = r?.label || REWARD_CATALOG[key]?.name || key;
      const points = Number(document.getElementById(`edit-points-${key}`).value);
      if (!points || points < MIN_REDEEM_POINTS) {
        toast(`Poin minimal ${MIN_REDEEM_POINTS}.`); return;
      }

      const body = { poin: points };
      const minInput = document.getElementById(`edit-min-${key}`);
      const maksInput = document.getElementById(`edit-maks-${key}`);
      if (minInput) body.min_subtotal = Number(minInput.value) || 0;
      if (maksInput) body.maks_potongan = Number(maksInput.value) || 0;

      b.disabled = true;
      try {
        const item = await patchKatalog(key, body);
        r.points = item.poin ?? points;
        r.minPurchase = item.min_subtotal ?? r.minPurchase;
        r.maxDiscount = item.maks_potongan ?? r.maxDiscount;
        editingRewardKey = null;
        toast(`Reward "${nama}" diperbarui.`);
        renderRewards();
      } catch (err) { toast(err.message); b.disabled = false; }
    }));

  }

  /* ================= MODAL TAMBAH REWARD ================= */

  /**
   * Form reward baru, dirender ke dalam modal.
   *
   * Dulu form ini disisipkan sebagai kartu kesembilan di grid katalog: lebarnya
   * ikut satu kolom, isinya memanjang jauh ke bawah, dan tinggi baris grid-nya
   * jadi ikut melar. Sebagai modal, lebarnya ditentukan sendiri dan katalog di
   * belakangnya tidak berubah bentuk sama sekali.
   *
   * Tiap field dibungkus `.rw-field` (label DAN input di dalamnya). Sebelumnya
   * label ditaruh sebagai saudara sejajar input di dalam wadah flex-column,
   * jadi begitu ada blok bersyarat seperti bagian diskon, seluruh isinya jadi
   * satu item flex dan label-nya mengalir menyamping di sebelah input.
   */
  function bukaModalReward() {
    const backdrop = document.getElementById('rewardModalBackdrop');
    const body = document.getElementById('rewardModalBody');
    if (!backdrop || !body) return;

    body.innerHTML = `
      <div class="rw-field">
        <label for="new-reward-label">Nama reward</label>
        <input type="text" id="new-reward-label" maxlength="60" placeholder="cth. Diskon 15% Ramadan">
      </div>

      <div class="rw-row">
        <div class="rw-field">
          <label for="new-reward-tipe">Jenis</label>
          <select id="new-reward-tipe">
            <option value="diskon">Voucher diskon</option>
            <option value="gratis_menu">Gratis menu</option>
          </select>
        </div>
        <div class="rw-field">
          <label for="new-reward-points">Poin dibutuhkan</label>
          <input type="number" id="new-reward-points" placeholder="min. ${MIN_REDEEM_POINTS}">
        </div>
      </div>

      <div class="rw-group" id="new-reward-diskon">
        <div class="rw-row">
          <div class="rw-field">
            <label for="new-reward-persen">Persen diskon</label>
            <input type="number" id="new-reward-persen" placeholder="cth. 15">
          </div>
          <div class="rw-field">
            <label for="new-reward-maks">Maksimal potongan (Rp)</label>
            <input type="number" id="new-reward-maks" placeholder="cth. 7500">
          </div>
        </div>
        <div class="rw-field">
          <label for="new-reward-min">Minimal pembelian (Rp)</label>
          <input type="number" id="new-reward-min" placeholder="cth. 25000">
        </div>
      </div>

      <div class="rw-group" id="new-reward-menu-wrap" hidden>
        <div class="rw-field">
          <label for="new-reward-kategori">Kategori</label>
          <select id="new-reward-kategori"><option value="">Memuat…</option></select>
        </div>
        <div class="rw-field">
          <label for="new-reward-menu">Menu yang digratiskan</label>
          <input type="search" id="new-reward-cari" placeholder="Cari menu…" disabled>
          <select id="new-reward-menu" size="6" disabled></select>
        </div>
      </div>

      <p class="rw-hint" id="new-reward-hint"></p>

      <div class="rw-actions">
        <button type="button" class="rw-btn-batal" id="cancelNewReward">Batal</button>
        <button type="button" class="rw-btn-simpan" id="saveNewReward">Tambah reward</button>
      </div>`;

    const tipeSel = document.getElementById('new-reward-tipe');
    const blokDiskon = document.getElementById('new-reward-diskon');
    const blokMenu = document.getElementById('new-reward-menu-wrap');
    const hint = document.getElementById('new-reward-hint');

    function syncTipe() {
      const diskon = tipeSel.value === 'diskon';
      blokDiskon.hidden = !diskon;
      blokMenu.hidden = diskon;
      hint.textContent = diskon
        ? 'Plafon potongan wajib diisi. Tanpa itu, satu pesanan besar bisa memotong berapa pun.'
        : 'Menu dipilih dari daftar menu, bukan diketik, supaya hadiahnya pasti ketemu saat ditukarkan.';
    }
    tipeSel.addEventListener('change', syncTipe);
    syncTipe();

    isiPilihanMenu();
    pasangAksiModal();

    backdrop.classList.add('open');
    document.getElementById('new-reward-label').focus();
  }

  function tutupModalReward() {
    document.getElementById('rewardModalBackdrop')?.classList.remove('open');
  }

  function pasangAksiModal() {
    document.getElementById('cancelNewReward').addEventListener('click', tutupModalReward);

    const saveNew = document.getElementById('saveNewReward');
    saveNew.addEventListener('click', async () => {
      const label = document.getElementById('new-reward-label').value.trim();
      const tipe = document.getElementById('new-reward-tipe').value;
      const points = Number(document.getElementById('new-reward-points').value);

      // Dicegat di sini juga, bukan cuma di backend, supaya manager tidak perlu
      // menunggu satu putaran request untuk tahu isiannya kurang.
      if (!label) { toast('Nama reward wajib diisi.'); return; }
      if (!points || points < MIN_REDEEM_POINTS) {
        toast(`Poin minimal ${MIN_REDEEM_POINTS}.`); return;
      }

      const body = { label, tipe, poin: points };

      if (tipe === 'diskon') {
        body.persen = Number(document.getElementById('new-reward-persen').value);
        body.maks_potongan = Number(document.getElementById('new-reward-maks').value);
        body.min_subtotal = Number(document.getElementById('new-reward-min').value) || 0;

        if (!body.persen) { toast('Persen diskon wajib diisi.'); return; }
        if (!body.maks_potongan) { toast('Maksimal potongan wajib diisi.'); return; }
      } else {
        if (!document.getElementById('new-reward-kategori').value) {
          toast('Pilih kategori menunya dulu.'); return;
        }
        body.menu_id = Number(document.getElementById('new-reward-menu').value);
        if (!body.menu_id) { toast('Pilih menu yang digratiskan.'); return; }
      }

      saveNew.disabled = true;
      try {
        const res = await fetch('/api/pengaturan/loyalty/katalog', {
          method: 'POST', headers: apiHeaders(), body: JSON.stringify(body),
        });
        const json = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(json.message || 'Gagal menambah reward.');

        tutupModalReward();
        toast(`Reward "${label}" ditambahkan.`);
        // Muat ulang dari backend, bukan menempelkan tebakan ke state lokal:
        // kode reward dibuat di server dari labelnya, dan hanya server yang
        // tahu apakah kodenya dapat akhiran angka karena bentrok.
        await loadKatalog();
      } catch (err) { toast(err.message); saveNew.disabled = false; }
    });
  }

  /**
   * Pilihan menu hadiah, DUA LANGKAH: kategori dulu, baru menunya.
   *
   * Satu dropdown berisi seluruh menu tidak terpakai di sini. Katalognya 93
   * baris, dan daftar sepanjang itu terbuka menutupi hampir seluruh layar lalu
   * harus digulir untuk mencari satu nama. Dipecah per kategori, yang dibuka
   * sekali cuma 6 baris, lalu paling banyak 26.
   *
   * DUA SARINGAN, keduanya menutup celah yang sama: reward yang tampil rapi di
   * katalog tapi gagal justru saat pelanggan menukarkannya di depan kasir.
   * `LoyaltyService::cariMenuGratis()` mencari hadiah dengan syarat
   * `is_active = true` DAN kategorinya cocok, jadi menu nonaktif dan menu tanpa
   * kategori tidak pernah bisa jadi hadiah.
   *
   * `kategori` di response `/api/menu-internal` berupa STRING nama kategori,
   * bukan objek. Salah baca inilah yang sempat membuang seluruh 93 menu dan
   * menyisakan "Belum ada menu berkategori".
   */
  async function isiPilihanMenu() {
    const selKategori = document.getElementById('new-reward-kategori');
    const selMenu = document.getElementById('new-reward-menu');
    const inputCari = document.getElementById('new-reward-cari');
    if (!selKategori || !selMenu || !inputCari) return;

    const perKategori = new Map();

    try {
      const res = await fetch('/api/menu-internal', { headers: apiHeaders() });
      if (!res.ok) throw new Error();

      ((await res.json()).data || [])
        .filter(m => m.kategori && m.is_active !== false)
        .forEach((m) => {
          if (!perKategori.has(m.kategori)) perKategori.set(m.kategori, []);
          perKategori.get(m.kategori).push(m);
        });
    } catch (e) {
      selKategori.innerHTML = '<option value="">Gagal memuat menu</option>';
      return;
    }

    if (perKategori.size === 0) {
      selKategori.innerHTML = '<option value="">Belum ada menu aktif berkategori</option>';
      return;
    }

    const kategori = [...perKategori.keys()].sort((a, b) => a.localeCompare(b, 'id'));
    selKategori.innerHTML = '<option value="">Pilih kategori…</option>'
      + kategori.map(k => `<option value="${escapeHtml(k)}">${escapeHtml(k)} (${perKategori.get(k).length})</option>`).join('');

    /** Isi daftar menu dari kategori terpilih, disaring kata kunci pencarian. */
    function isiDaftar() {
      const daftar = perKategori.get(selKategori.value);

      // Kosongkan pilihan menu tiap ganti kategori. Kalau nilainya dibiarkan,
      // menu dari kategori sebelumnya masih terpilih diam-diam sementara
      // labelnya sudah berubah.
      if (!daftar) {
        selMenu.innerHTML = '<option value="" disabled>Pilih kategori dulu</option>';
        selMenu.disabled = true;
        inputCari.disabled = true;
        inputCari.value = '';
        return;
      }

      const kunci = inputCari.value.trim().toLowerCase();
      const cocok = daftar
        .filter(m => !kunci || (m.nama + ' ' + (m.ukuran || '')).toLowerCase().includes(kunci))
        .sort((a, b) => a.nama.localeCompare(b.nama, 'id'));

      selMenu.innerHTML = cocok.length
        ? cocok.map((m) => {
          const ukuran = m.ukuran ? ` (${m.ukuran})` : '';
          return `<option value="${m.id}">${escapeHtml(m.nama + ukuran)}</option>`;
        }).join('')
        : '<option value="" disabled>Tidak ada menu yang cocok</option>';

      selMenu.disabled = false;
      inputCari.disabled = false;
      // Tidak ada yang terpilih otomatis. Menu yang tersorot tanpa pernah
      // diklik gampang terkirim tanpa sadar, dan hadiahnya jadi bukan yang
      // dimaksud manager.
      selMenu.selectedIndex = -1;
    }

    selKategori.addEventListener('change', function () {
      inputCari.value = '';
      isiDaftar();
    });
    inputCari.addEventListener('input', isiDaftar);

    isiDaftar();
  }

  /* PATCH satu kode reward (poin dan/atau is_active). */
  async function patchKatalog(kode, body) {
    const res = await fetch(`/api/pengaturan/loyalty/katalog/${encodeURIComponent(kode)}`, {
      method: 'PATCH', headers: apiHeaders(), body: JSON.stringify(body),
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(json.message || 'Gagal memperbarui reward.');
    return json.data || {};
  }

  /* Hapus permanen satu reward buatan manager. */
  async function hapusKatalog(kode) {
    const res = await fetch(`/api/pengaturan/loyalty/katalog/${encodeURIComponent(kode)}`, {
      method: 'DELETE', headers: apiHeaders(),
    });
    if (!res.ok) {
      const json = await res.json().catch(() => ({}));
      throw new Error(json.message || 'Gagal menghapus reward.');
    }
  }

  /* Muat katalog reward (poin & status aktif) dari backend, ganti data dummy. */
  async function loadKatalog() {
    let gagalMuatKatalog = false;
    const token = localStorage.getItem('auth_token');
    if (token) {
      try {
        const res = await fetch('/api/pengaturan/loyalty/katalog', { headers: apiHeaders() });
        if (res.ok) {
          const items = (await res.json()).data || [];
          // Seluruh isi kartu diambil dari backend, termasuk `tipe`, `ukuran`,
          // dan `bawaan`. Dulu sebagian dibaca dari REWARD_CATALOG di berkas
          // ini, yang cuma memuat delapan jenis bawaan, sehingga reward buatan
          // manager tidak akan pernah bisa dirender.
          rewards = items.filter(i => i.is_active).map(i => ({
            key: i.kode,
            label: i.label,
            tipe: i.tipe,
            persen: i.persen,
            menu: i.menu_gratis,
            sizes: i.ukuran || null,
            bawaan: i.bawaan !== false,
            points: i.poin,
            minPurchase: i.min_subtotal || 0,
            maxDiscount: i.maks_potongan || 0,
          }));
        } else {
          gagalMuatKatalog = true;
        }
      } catch (e) {
        gagalMuatKatalog = true;
      }
    }
    if (gagalMuatKatalog) {
      toast('Gagal memuat katalog reward, angka di bawah ini setelan bawaan, belum tentu sama dengan yang tersimpan.');
    }

    renderRewards();
  }

  /* ================= RENDER: HISTORY ================= */
  function historyTerurut() {
    const rows = historyAll.slice();

    if (historySort === 'poin_desc') return rows.sort((a, b) => b.points - a.points);
    if (historySort === 'poin_asc') return rows.sort((a, b) => a.points - b.points);

    return rows.sort((a, b) => new Date(b.ts) - new Date(a.ts));
  }

  function renderHistory() {
    const el = document.getElementById('lm-history');
    if (!el) return;

    const footer = document.getElementById('lm-history-footer');

    if (historyAll.length === 0) {
      el.innerHTML = '<tr><td colspan="5" style="text-align:center; color:#9CA3AF; padding:22px;">Belum ada aktivitas poin.</td></tr>';
      if (footer) footer.innerHTML = '';
      return;
    }

    const totalPage = Math.ceil(historyAll.length / HISTORY_PER_PAGE);
    if (historyPage > totalPage) historyPage = totalPage;
    const mulai = (historyPage - 1) * HISTORY_PER_PAGE;
    const rows = historyTerurut().slice(mulai, mulai + HISTORY_PER_PAGE);

    el.innerHTML = rows.map(h => `
      <tr>
        <td>${escapeHtml(h.member)}</td>
        <td>${escapeHtml(h.activity)}</td>
        <td>${escapeHtml(h.ukuran)}</td>
        <td class="${h.points > 0 ? 'poin-plus' : ''}">${h.points > 0 ? '+' : ''}${h.points}</td>
        <td>${h.date}</td>
      </tr>
    `).join('');

    renderHistoryPagination(totalPage);
  }

  function renderHistoryPagination(totalPage) {
    const footer = document.getElementById('lm-history-footer');
    if (!footer) return;
    if (totalPage <= 1) { footer.innerHTML = ''; return; }

    let html = `<span class="lm-history-info">${historyAll.length} aktivitas · Halaman ${historyPage} dari ${totalPage}</span>`;
    html += '<div class="lm-pagination">';
    html += `<button data-hal="${historyPage - 1}" ${historyPage <= 1 ? 'disabled' : ''}>‹</button>`;

    // Tampilkan maksimal 5 nomor di sekitar halaman aktif.
    let awal = Math.max(1, historyPage - 2);
    const akhir = Math.min(totalPage, awal + 4);
    awal = Math.max(1, akhir - 4);
    for (let i = awal; i <= akhir; i++) {
      html += `<button data-hal="${i}" class="${i === historyPage ? 'active' : ''}">${i}</button>`;
    }

    html += `<button data-hal="${historyPage + 1}" ${historyPage >= totalPage ? 'disabled' : ''}>›</button>`;
    html += '</div>';
    footer.innerHTML = html;

    footer.querySelectorAll('button[data-hal]').forEach(b => {
      b.addEventListener('click', () => {
        const tujuan = parseInt(b.dataset.hal, 10);
        if (!tujuan || tujuan < 1 || tujuan > totalPage || tujuan === historyPage) return;
        historyPage = tujuan;
        renderHistory();
      });
    });
  }

  function fmtTgl(iso) {
    if (!iso) return '-';
    const d = new Date(iso);
    return Number.isNaN(d.getTime()) ? '-'
      : d.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
  }

  // Ringkas item transaksi jadi teks aktivitas, mis. "2× Choco Maniac, 1× Tiramisu".
  // Transaksi POS menamai itemnya `nama`, baris historis hasil impor CSV
  // memakai `nama_menu`. Keduanya dibaca di sini supaya riwayat poin tidak
  // menampilkan "1× undefined" untuk separuh barisnya.
  function namaItem(i) {
    return i.nama || i.nama_menu || 'Item';
  }

  function ringkasItem(items) {
    const list = (items || []).filter(i => !i.is_reward);
    if (list.length === 0) return '';
    const teks = list.slice(0, 2).map(i => `${i.qty}× ${namaItem(i)}`).join(', ');
    return teks + (list.length > 2 ? ', …' : '');
  }

  // Ukuran unik dari item transaksi, mis. "Reguler" / "Reguler, 250ml". "—" kalau tak ada.
  function ringkasUkuran(items) {
    const sizes = [...new Set((items || [])
      .filter(i => !i.is_reward && i.ukuran)
      .map(i => i.ukuran))];
    return sizes.length ? sizes.join(', ') : '—';
  }

  // Backend membatasi 200 baris per halaman. Riwayat poin harus memuat data
  // Juni-Juli sekaligus transaksi terbaru, dan karena daftarnya terurut
  // terbaru dulu, satu halaman saja akan memotong seluruh data lama.
  // Halamannya ditarik berurutan sampai habis, dengan pagar supaya satu
  // kesalahan di backend tidak berubah jadi permintaan tanpa akhir.
  const HISTORY_MAX_HALAMAN = 6;

  async function ambilSemuaTransaksi(headers) {
    const semua = [];

    for (let halaman = 1; halaman <= HISTORY_MAX_HALAMAN; halaman++) {
      const res = await fetch(`/api/transaksi?per_page=200&page=${halaman}`, { headers });
      if (!res.ok) break;

      const json = await res.json();
      const rows = json.data || [];
      semua.push(...rows);

      const terakhir = json.meta?.last_page ?? json.last_page;
      if (rows.length < 200 || (terakhir && halaman >= terakhir)) break;
    }

    return semua;
  }

  async function loadHistory() {
    const token = localStorage.getItem('auth_token');
    if (!token) { renderHistory(); return; }

    try {
      const rows = await ambilSemuaTransaksi({
        Accept: 'application/json',
        Authorization: 'Bearer ' + token,
      });
      const events = [];

      rows.forEach(trx => {
        const member = trx.customer ? trx.customer.nama : 'Umum';

        if (trx.poin_ditukar && trx.poin_ditukar > 0) {
          const c = REWARD_CATALOG[trx.kode_redeem];
          events.push({
            member,
            activity: 'Redeem ' + (c ? c.name : (trx.kode_redeem || 'reward')),
            ukuran: '—',
            points: -trx.poin_ditukar,
            ts: trx.created_at,
          });
        }

        if (trx.status === 'lunas' && trx.point_earned && trx.point_earned > 0) {
          const rincian = ringkasItem(trx.items);
          events.push({
            member,
            activity: 'Pembelian' + (rincian ? ' ' + rincian : ''),
            ukuran: ringkasUkuran(trx.items),
            points: trx.point_earned,
            ts: trx.waktu_lunas || trx.created_at,
          });
        }
      });

      events.sort((a, b) => new Date(b.ts) - new Date(a.ts));
      historyPage = 1;
      historyAll = events.map(e => ({
        member: e.member,
        activity: e.activity,
        ukuran: e.ukuran,
        points: e.points,
        ts: e.ts,
        date: fmtTgl(e.ts),
      }));
    } catch (e) {
    }

    renderHistory();
  }

  /* ================= EVENTS ================= */
  function bindSortDropdown() {
    const select = document.getElementById('lm-history-sort');
    if (!select) return;

    const trigger = select.querySelector('.custom-select-trigger');
    const label = select.querySelector('.selected-label');
    const options = select.querySelectorAll('.custom-options li');

    trigger.addEventListener('click', (e) => {
      e.stopPropagation();
      select.classList.toggle('open');
    });

    options.forEach((option) => {
      option.addEventListener('click', () => {
        options.forEach(o => o.classList.remove('selected'));
        option.classList.add('selected');

        label.textContent = option.textContent;
        historySort = option.dataset.value;
        historyPage = 1;
        select.classList.remove('open');
        renderHistory();
      });
    });

    document.addEventListener('click', () => select.classList.remove('open'));
  }

  function bindTopEvents() {
    const btnAddReward = document.getElementById('btnAddReward');
    const btnSaveRule = document.getElementById('btnSaveRule');
    bindSortDropdown();

    if (btnAddReward) btnAddReward.addEventListener('click', bukaModalReward);

    // Tutup lewat tombol silang, klik latar, dan Escape. Modal yang cuma bisa
    // ditutup satu cara terasa macet begitu isinya salah ketik dan orangnya
    // cuma ingin membatalkan.
    const backdrop = document.getElementById('rewardModalBackdrop');
    document.getElementById('rewardModalClose')?.addEventListener('click', tutupModalReward);
    backdrop?.addEventListener('click', (e) => { if (e.target === backdrop) tutupModalReward(); });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && backdrop?.classList.contains('open')) tutupModalReward();
    });
    if (btnSaveRule) btnSaveRule.addEventListener('click', async () => {
      const input = document.getElementById('rpPerPoint');
      const val = Number(input.value);
      if (!val || val <= 0) { toast('Rasio Rp/poin harus lebih dari 0.'); return; }

      btnSaveRule.disabled = true;
      try {
        const res = await fetch('/api/pengaturan/loyalty', {
          method: 'PATCH',
          headers: apiHeaders(),
          body: JSON.stringify({ rupiah_per_poin: val }),
        });
        const json = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(json.message || 'Gagal menyimpan aturan.');
        const rate = (json.data || {}).rupiah_per_poin || val;
        input.value = rate;
        toast(`Aturan disimpan: tiap Rp${Number(rate).toLocaleString('id-ID')} = 1 poin.`);
      } catch (err) {
        toast(err.message);
      } finally {
        btnSaveRule.disabled = false;
      }
    });
  }

  async function loadPengaturan() {
    const token = localStorage.getItem('auth_token');
    if (!token) return;
    try {
      const res = await fetch('/api/pengaturan/loyalty', { headers: apiHeaders() });
      if (!res.ok) return;
      const d = (await res.json()).data || {};
      const input = document.getElementById('rpPerPoint');
      if (input && d.rupiah_per_poin) input.value = d.rupiah_per_poin;
    } catch (e) { /* biarkan nilai default */ }
  }

  let cariPoinTimer = null;
  function bindCekPoin() {
    const input = document.getElementById('lmCariNoWa');
    const hasil = document.getElementById('lmCekPoinResult');
    if (!input || !hasil) return;

    input.addEventListener('input', function () {
      clearTimeout(cariPoinTimer);
      input.value = input.value.replace(/\D/g, '').slice(0, 12);
      const noWa = input.value.trim();

      if (!noWa) { hasil.innerHTML = ''; return; }

      if (noWa.length < 3) {
        hasil.innerHTML = '<p class="lm-cek-poin-empty">Ketik minimal 3 angka.</p>';
        return;
      }

      cariPoinTimer = setTimeout(async () => {
        hasil.innerHTML = '<p class="lm-cek-poin-loading">Mencari...</p>';

        try {
          const res = await fetch('/api/customers/cari?' + new URLSearchParams({ no_wa: noWa }), {
            headers: apiHeaders(),
          });
          if (!res.ok) throw new Error();

          const daftar = (await res.json()).data || [];
          if (daftar.length === 0) {
            hasil.innerHTML = '<p class="lm-cek-poin-empty">Pelanggan belum terdaftar (belum pernah beli).</p>';
            return;
          }

          hasil.innerHTML = daftar.map(c => `
            <div class="lm-cek-poin-card">
              <div class="cf-nama">${escapeHtml(c.nama)}</div>
              <div class="cf-meta">${escapeHtml(c.no_wa)} · <strong>${c.poin} poin</strong></div>
            </div>`).join('');
        } catch (e) {
          hasil.innerHTML = '<p class="lm-cek-poin-empty">Gagal mencari pelanggan.</p>';
        }
      }, 400);
    });
  }

  /* ================= INIT ================= */
  document.addEventListener('DOMContentLoaded', function () {
    const kasir = isKasir();

    if (kasir) {
      document.querySelectorAll('.loyalty-manager [data-role="manager"]').forEach(el => { el.style.display = 'none'; });
      document.querySelectorAll('.loyalty-manager [data-role="kasir"]').forEach(el => { el.style.display = ''; });
      bindCekPoin();
    } else {
      bindTopEvents();
      loadStats();
      loadHistory();
      loadPengaturan();
    }

    loadKatalog();
  });

})();