/* =====================================================================
   Loyalty Manager — logic
   Saat ini semua data masih di-memory (dummy) supaya UI bisa langsung
   dicoba. Titik-titik yang perlu disambungkan ke backend Laravel
   ditandai dengan komentar "TODO: sambungkan ke API".

   Perubahan dari versi sebelumnya:
   - Pengelolaan "Tingkatan Membership" dihapus dari halaman ini.
   - Katalog reward sekarang FIXED (8 jenis), detail (nama, ikon,
     ukuran minuman yang tersedia, apakah butuh minimal pembelian)
     dikunci di REWARD_CATALOG. Manager hanya bisa:
       a) mengaktifkan/menambah salah satu jenis dari katalog
       b) mengatur poin, minimal pembelian & maksimal potongan per jenis
       c) menghapus jenis yang sedang aktif
   ===================================================================== */
(function () {

  /* ================= KATALOG REWARD (FIXED) =================
     key ini yang harus dikirim ke backend saat redeem/CRUD.       */
  const REWARD_CATALOG = {
    diskon_10: {
      name: 'Voucher Diskon 10%', icon: '🏷️',
      desc: 'Berlaku untuk semua menu, dengan minimum pembelian.',
      needsMinPurchase: true, hasMaxDiscount: true,
    },
    diskon_20: {
      name: 'Voucher Diskon 20%', icon: '🏷️',
      desc: 'Berlaku untuk semua menu, dengan minimum pembelian.',
      needsMinPurchase: true, hasMaxDiscount: true,
    },
    diskon_30: {
      name: 'Voucher Diskon 30%', icon: '🏷️',
      desc: 'Berlaku untuk semua menu, dengan minimum pembelian.',
      needsMinPurchase: true, hasMaxDiscount: true,
    },
    diskon_50: {
      name: 'Voucher Diskon 50%', icon: '🏷️',
      desc: 'Diskon besar, berlaku dengan minimum pembelian.',
      needsMinPurchase: true, hasMaxDiscount: true,
    },
    gratis_original: {
      name: 'Gratis Soy Milk Original', icon: '🥛',
      desc: 'Tukar poin dengan Soy Milk Original favorit.',
      sizes: ['Hot', 'Regular'],
    },
    gratis_coffee_kopi: {
      name: 'Gratis Coffee Kopi', icon: '☕',
      desc: 'Tukar poin dengan Coffee Kopi.',
      sizes: ['Hot', 'Regular'],
    },
    gratis_honey_lemon: {
      name: 'Gratis Honey Lemon', icon: '🍋',
      desc: 'Tukar poin dengan Honey Lemon segar.',
      sizes: ['Regular'],
    },
    gratis_mango_monggo: {
      name: 'Gratis Mango Monggo', icon: '🥭',
      desc: 'Tukar poin dengan Mango Monggo.',
      sizes: ['Regular'],
    },
  };
  // Sama dengan batas bawah backend (KatalogRedeem::POIN_MIN). Pagar salah
  // ketik saja — berapa poin yang wajar adalah keputusan manager.
  const MIN_REDEEM_POINTS = 1;

  /* ================= STATE ================= */
  // rewards = jenis dari katalog yang sedang aktif ditawarkan ke member.
  // "points" & "minPurchase" boleh diatur manager, sisanya ikut REWARD_CATALOG[key].
  let rewards = [
    { key: 'diskon_10', points: 100, minPurchase: 25000, maxDiscount: 5000 },
    { key: 'diskon_20', points: 200, minPurchase: 25000, maxDiscount: 10000 },
    { key: 'diskon_30', points: 300, minPurchase: 25000, maxDiscount: 15000 },
    { key: 'diskon_50', points: 500, minPurchase: 25000, maxDiscount: 25000 },
    { key: 'gratis_original', points: 350 },
    { key: 'gratis_coffee_kopi', points: 450 },
    { key: 'gratis_honey_lemon', points: 400 },
    { key: 'gratis_mango_monggo', points: 400 },
  ];

  // Diisi dari data transaksi asli (GET /api/transaksi) di loadHistory().
  let historyAll = [];              // seluruh riwayat, dipaginasi di klien
  let historyPage = 1;
  const HISTORY_PER_PAGE = 10;

  // Diisi dari data asli (GET /api/dashboard/rfm) di loadStats().
  // redeemedThisMonth = null -> belum ada pencatatan penukaran di layer laporan.
  let members = 0;
  let activePoints = 0;
  let redeemedThisMonth = null;
  let newMembers = 0;

  let editingRewardKey = null;
  let addingReward = false;

  /* Header standar untuk request ber-auth (token Sanctum). */
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

  /* Kartu loyalty:
     - Total Member & Member Baru  -> CSV (RFM) saja; gabung live butuh dedup
       pelanggan unik di backend, jadi belum dicampur di sini.
     - Total Poin Aktif            -> poin CSV + poin didapat dari transaksi live.
     - Reward ditukar bulan ini    -> jumlah redeem di transaksi live bulan ini. */
  async function loadStats() {
    const token = localStorage.getItem('auth_token');
    if (token) {
      const headers = { 'Accept': 'application/json', 'Authorization': 'Bearer ' + token };
      try {
        const [rfmRes, liveRes] = await Promise.all([
          fetch('/api/dashboard/rfm', { headers }),
          fetch('/api/transaksi?status=lunas&per_page=200', { headers }),
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
          // Reward ditukar bulan berjalan (dari transaksi live).
          const now = new Date();
          redeemedThisMonth = rows.filter(function (t) {
            if (!t.poin_ditukar || t.poin_ditukar <= 0) return false;
            const d = new Date(t.created_at);
            return d.getFullYear() === now.getFullYear() && d.getMonth() === now.getMonth();
          }).length;
        }
      } catch (e) {
        // biarkan nilai default kalau gagal
      }
    }
    renderStats();
  }

  /* ================= RENDER: REWARDS ================= */
  function rewardTags(catalogItem, minPurchase, maxDiscount) {
    const tags = [];
    if (catalogItem.sizes) {
      tags.push(`<span class="reward-tag">Ukuran: ${catalogItem.sizes.join(' / ')}</span>`);
    }
    if (catalogItem.needsMinPurchase && minPurchase) {
      tags.push(`<span class="reward-tag">Min. belanja ${fmtRp(minPurchase)}</span>`);
    }
    if (catalogItem.hasMaxDiscount && maxDiscount) {
      tags.push(`<span class="reward-tag">Maks. potongan ${fmtRp(maxDiscount)}</span>`);
    }
    return tags.join('');
  }

  function renderRewards() {
    const el = document.getElementById('lm-rewards');
    if (!el) return;

    const kasir = isKasir();

    el.innerHTML = rewards.map(r => {
      const c = REWARD_CATALOG[r.key];
      if (!c) return '';

      if (kasir) {
        return `
        <div class="reward-card">
          <h4>${escapeHtml(c.name)}</h4>
          <p>${escapeHtml(c.desc)}</p>
          <span class="reward-points">${r.points} poin</span>
          <div class="reward-tags">${rewardTags(c, r.minPurchase, r.maxDiscount)}</div>
        </div>`;
      }

      if (editingRewardKey === r.key) {
        return `
        <div class="reward-card">
          <div class="edit-form">
            <label>${escapeHtml(c.name)}</label>
            <label>Poin dibutuhkan</label>
            <input type="number" id="edit-points-${r.key}" value="${r.points}">
            ${c.needsMinPurchase ? `
              <label>Minimal pembelian (Rp)</label>
              <input type="number" id="edit-min-${r.key}" value="${r.minPurchase || 0}">
            ` : ''}
            ${c.hasMaxDiscount ? `
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

      return `
      <div class="reward-card">
        <div class="reward-actions">
          <button data-edit-reward="${r.key}" title="Edit poin"><i class="fa-regular fa-pen-to-square"></i></button>
          <button data-remove-reward="${r.key}" title="Hapus"><i class="fa-regular fa-trash-can"></i></button>
        </div>
        <h4>${escapeHtml(c.name)}</h4>
        <p>${escapeHtml(c.desc)}</p>
        <span class="reward-points">${r.points} poin</span>
        <div class="reward-tags">${rewardTags(c, r.minPurchase, r.maxDiscount)}</div>
      </div>`;
    }).join('');

    if (addingReward) {
      const available = Object.keys(REWARD_CATALOG).filter(k => !rewards.some(r => r.key === k));
      el.insertAdjacentHTML('beforeend', `
      <div class="reward-form-card">
        <label>Jenis reward</label>
        <select id="new-reward-key">
          ${available.length
            ? available.map(k => `<option value="${k}">${escapeHtml(REWARD_CATALOG[k].name)} (${k})</option>`).join('')
            : `<option value="">Semua jenis sudah aktif</option>`}
        </select>
        <label>Poin dibutuhkan</label>
        <input type="number" id="new-reward-points" placeholder="min. ${MIN_REDEEM_POINTS}">
        <label id="new-reward-min-label" style="display:none">Minimal pembelian (Rp)</label>
        <input type="number" id="new-reward-min" style="display:none" placeholder="cth. 50000">
        <p class="hint">Nama, ikon, dan ukuran minuman sudah ditentukan per jenis reward.</p>
        <div class="actions">
          <button class="cancel-btn" id="cancelNewReward">Batal</button>
          <button class="save-btn" id="saveNewReward">Tambah</button>
        </div>
      </div>`);

      const sel = document.getElementById('new-reward-key');
      const minLabel = document.getElementById('new-reward-min-label');
      const minInput = document.getElementById('new-reward-min');
      function syncMinField() {
        const c = REWARD_CATALOG[sel.value];
        const show = !!(c && c.needsMinPurchase);
        minLabel.style.display = show ? 'block' : 'none';
        minInput.style.display = show ? 'block' : 'none';
      }
      if (sel) { sel.addEventListener('change', syncMinField); syncMinField(); }
    }

    // Hapus reward = nonaktifkan (PATCH is_active:false). Struktur reward
    // fixed di backend, jadi bukan hapus permanen — bisa diaktifkan lagi.
    el.querySelectorAll('[data-remove-reward]').forEach(b => b.addEventListener('click', async () => {
      const key = b.dataset.removeReward;
      b.disabled = true;
      try {
        await patchKatalog(key, { is_active: false });
        rewards = rewards.filter(r => r.key !== key);
        toast('Reward dinonaktifkan.');
        renderRewards();
      } catch (err) { toast(err.message); b.disabled = false; }
    }));
    el.querySelectorAll('[data-edit-reward]').forEach(b => b.addEventListener('click', () => {
      editingRewardKey = b.dataset.editReward; renderRewards();
    }));
    el.querySelectorAll('[data-cancel-reward]').forEach(b => b.addEventListener('click', () => {
      editingRewardKey = null; renderRewards();
    }));
    // Simpan edit -> PATCH { poin, min_subtotal, maks_potongan }. Ketiganya
    // sudah dibuka backend; field yang tidak relevan per tipe reward tidak
    // dirender, jadi tidak ikut terkirim.
    el.querySelectorAll('[data-save-reward]').forEach(b => b.addEventListener('click', async () => {
      const key = b.dataset.saveReward;
      const r = rewards.find(x => x.key === key);
      const c = REWARD_CATALOG[key];
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
        toast(`Reward "${c.name}" diperbarui.`);
        renderRewards();
      } catch (err) { toast(err.message); b.disabled = false; }
    }));

    const cancelNew = document.getElementById('cancelNewReward');
    if (cancelNew) cancelNew.addEventListener('click', () => { addingReward = false; renderRewards(); });
    const saveNew = document.getElementById('saveNewReward');
    // Tambah reward = aktifkan tipe (PATCH is_active:true, poin).
    if (saveNew) saveNew.addEventListener('click', async () => {
      const key = document.getElementById('new-reward-key').value;
      const c = REWARD_CATALOG[key];
      if (!key || !c) { toast('Pilih jenis reward dulu.'); return; }
      const points = Number(document.getElementById('new-reward-points').value);
      if (!points || points < MIN_REDEEM_POINTS) {
        toast(`Poin minimal ${MIN_REDEEM_POINTS}.`); return;
      }
      const body = { is_active: true, poin: points };
      const newMin = document.getElementById('new-reward-min');
      if (c.needsMinPurchase && newMin && newMin.value !== '') {
        body.min_subtotal = Number(newMin.value) || 0;
      }

      saveNew.disabled = true;
      try {
        const item = await patchKatalog(key, body);
        if (!rewards.find(r => r.key === key)) {
          rewards.push({
            key,
            points: item.poin ?? points,
            minPurchase: item.min_subtotal || 0,
            maxDiscount: item.maks_potongan || 0,
          });
        }
        addingReward = false;
        toast(`Reward "${c.name}" diaktifkan.`);
        renderRewards();
      } catch (err) { toast(err.message); saveNew.disabled = false; }
    });
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

  /* Muat katalog reward (poin & status aktif) dari backend, ganti data dummy. */
  async function loadKatalog() {
    const token = localStorage.getItem('auth_token');
    if (token) {
      try {
        const res = await fetch('/api/pengaturan/loyalty/katalog', { headers: apiHeaders() });
        if (res.ok) {
          const items = (await res.json()).data || [];
          // rewards = yang aktif (ditawarkan ke pelanggan); nonaktif tetap ada
          // di REWARD_CATALOG untuk bisa diaktifkan lagi.
          rewards = items.filter(i => i.is_active).map(i => ({
            key: i.kode,
            points: i.poin,
            minPurchase: i.min_subtotal || 0,
            maxDiscount: i.maks_potongan || 0,
          }));
        }
      } catch (e) { /* pakai data default kalau gagal */ }
    }
    renderRewards();
  }

  /* ================= RENDER: HISTORY ================= */
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
    const rows = historyAll.slice(mulai, mulai + HISTORY_PER_PAGE);

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
  function ringkasItem(items) {
    const list = (items || []).filter(i => !i.is_reward);
    if (list.length === 0) return '';
    const teks = list.slice(0, 2).map(i => `${i.qty}× ${i.nama}`).join(', ');
    return teks + (list.length > 2 ? ', …' : '');
  }

  // Ukuran unik dari item transaksi, mis. "Reguler" / "Reguler, 250ml". "—" kalau tak ada.
  function ringkasUkuran(items) {
    const sizes = [...new Set((items || [])
      .filter(i => !i.is_reward && i.ukuran)
      .map(i => i.ukuran))];
    return sizes.length ? sizes.join(', ') : '—';
  }

  /* Susun riwayat poin dari transaksi asli:
     - poin_ditukar > 0        -> baris redeem (poin berkurang)
     - status lunas & poin>0   -> baris pembelian (poin bertambah)
     Satu transaksi bisa menghasilkan dua baris (redeem lalu bayar). */
  async function loadHistory() {
    const token = localStorage.getItem('auth_token');
    if (!token) { renderHistory(); return; }

    try {
      const res = await fetch('/api/transaksi?per_page=200', {
        headers: { Accept: 'application/json', Authorization: 'Bearer ' + token },
      });
      if (!res.ok) { renderHistory(); return; }

      const rows = (await res.json()).data || [];
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
        date: fmtTgl(e.ts),
      }));
    } catch (e) {
      // biarkan history kosong -> tampil empty state
    }

    renderHistory();
  }

  /* ================= EVENTS ================= */
  function bindTopEvents() {
    const btnAddReward = document.getElementById('btnAddReward');
    const btnSaveRule = document.getElementById('btnSaveRule');

    if (btnAddReward) btnAddReward.addEventListener('click', () => { addingReward = true; renderRewards(); });
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

  /* Prefill rasio Rp/poin dari GET /api/pengaturan/loyalty */
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

  /* ================= CEK POIN PELANGGAN (kasir) =================
     Reuse endpoint publik yang sama dipakai di halaman Pesanan:
     GET /api/loyalty/{no_wa} -> {nomor_wa, nama, poin} atau 404. */
  let cariPoinTimer = null;
  function bindCekPoin() {
    const input = document.getElementById('lmCariNoWa');
    const hasil = document.getElementById('lmCekPoinResult');
    if (!input || !hasil) return;

    input.addEventListener('input', function () {
      clearTimeout(cariPoinTimer);
      // Hanya angka, maksimal 12 digit.
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
          // Pencarian PARSIAL (LIKE) via customers/cari — nggak harus nomor
          // lengkap. Bisa muncul lebih dari satu kandidat.
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

    // Katalog reward dipakai kasir (read-only) & manager (bisa atur).
    loadKatalog();
  });

})();