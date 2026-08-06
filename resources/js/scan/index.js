/* ============================================================
   SoyaScan, self-order pelanggan (mobile).
   ============================================================ */
(function () {
    'use strict';

    const API_BASE = '/api';

    // `encodeURIComponent` WAJIB di sini. Kode pesanan diawali `#`, dan di URL
    // tanda itu memulai fragment yang tidak pernah dikirim ke server: menyisipkan
    // `#A02` mentah membuat request jatuh ke `/api/order/` tanpa parameter, lalu
    // 404-nya ditelan `if (!res.ok) return` dan layar pelanggan diam selamanya.
    function statusEndpoint(kode) {
        return `${API_BASE}/order/${encodeURIComponent(kode)}`;
    }
    function isPaidStatus(json) {
        const status = (json.data ? json.data.status : json.status) || '';
        return ['lunas', 'selesai', 'paid', 'dibayar'].includes(String(status).toLowerCase());
    }
    const STATUS_POLL_INTERVAL_MS = 4000;
    const STATUS_POLL_TIMEOUT_MS = 15 * 60 * 1000;

    const STORAGE_KEY = 'soyascan_active_order';

    function saveActiveOrder(data) {
        try { localStorage.setItem(STORAGE_KEY, JSON.stringify(data)); } catch (e) { /* storage penuh/diblokir, abaikan */ }
    }
    function loadActiveOrder() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            return raw ? JSON.parse(raw) : null;
        } catch (e) { return null; }
    }
    function clearActiveOrder() {
        try { localStorage.removeItem(STORAGE_KEY); } catch (e) { /* abaikan */ }
    }

    const IMG = {
        'Original':             { gelas: 'Soya Original.png',      botol: 'Original Botol.png' },
        'Taro Thanos':          { gelas: 'Taro Thanos.png',        botol: 'Taro Thanos Botol.png' },
        'Redvelvet':            { gelas: 'Redvelvet.png',          botol: 'Redvelvet Botol.png' },
        'Mango Smell Good':     { gelas: 'Mango Smell Good.png',   botol: 'Mango Smell Good Botol.png' },
        'Royal Belgian':        { gelas: 'Royal belgian.png',      botol: 'Royal Belgian Botol.png' },
        'Choco Maniac':         { gelas: 'Choco Maniac.png',       botol: 'Choco Maniac Botol.png' },
        'Choco Oat':            { gelas: 'Choco oat.png',          botol: 'Choco oat Botol.png' },
        'Dark Choco':           { gelas: 'Dark choco.png',         botol: 'Dark choco Botol.png' },
        'Choco Coffee':         { gelas: 'Choco Coffiee.png',      botol: 'Choco Coffiee Botol.png' },
        'Coffee Kopi':          { gelas: 'Coffee Kopi.png',        botol: 'Coffee Kopi Botol.png' },
        'Tiramisu':             { gelas: 'Tiramisu.png',           botol: 'Tiramisu Botol.png' },
        'Cappuccino':           { gelas: 'Capucinno.png',          botol: 'Capucinno Botol.png' },
        'Honey Lemon':          { gelas: 'Honey Lemon.png',        botol: 'Honey Lemon Botol.png' },
        'Mango Monggo':         { gelas: 'Mango Monggo.png',       botol: 'Mango Monggo Botol.png' },
        'Green Tea':            { gelas: 'Green Tea.png',          botol: 'Green Tea Botol.png' },
        'Thai Tea':             { gelas: 'Thai Tea.png',           botol: 'Thai Tea Botol.png' },
        'Kembang Tahu Tahwa':   { gelas: 'Kembang Tahu.png' },
        'Soy Milk Pudding':     { gelas: 'Soy Milk Pudding.png' },
        'Vegan Cookies Peanut': { gelas: 'Peanut.png' },
    };

    const DISPLAY = { 'Original': 'Soya Original' };
    const UKURAN_RANK = { 'Hot': 1, 'Reguler': 2, 'Large': 3, '250ml': 4, '500ml': 5, '1000ml': 6 };
    const KATEGORI_RANK = {
        'Soya Signature': 1,
        'Soya Chocolate': 2,
        'Soya Coffee': 3,
        'Soya Tropical': 4,
        'Soya Tea': 5,
        'Dessert & Cookies': 6,
    };
    const katRank = (nama) => KATEGORI_RANK[nama] || 99;

    // ---- State
    let cards = [];
    let kategoriList = [];
    let activeKategori = '';
    let cart = [];
    let metodeBayar = null;
    let statusPollTimer = null;
    let statusPollDeadline = 0;
    let opsiSugar = [];
    let opsiIce = [];
    let pendingOpsi = null;
    const $ = (id) => document.getElementById(id);
    const rupiah = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID');
    const imgUrl = (file) => '/images/menu/' + encodeURIComponent(file);
    const PLACEHOLDER = imgUrl('Soya Original.png');
    const isBotol = (ukuran) => typeof ukuran === 'string' && /ml$/i.test(ukuran.trim());

    // ==================================================================
    // LOAD KATALOG & GROUPING
    // ==================================================================
    async function loadKatalog() {
        const res = await fetch(`${API_BASE}/menu`, { headers: { 'Accept': 'application/json' } });
        if (!res.ok) throw new Error('gagal');
        const json = await res.json();
        const kategori = (json.kategori || []).slice().sort((a, b) => katRank(a.nama) - katRank(b.nama));
        opsiSugar = (json.meta && json.meta.opsi_sugar) || [];
        opsiIce = (json.meta && json.meta.opsi_ice) || [];

        kategoriList = kategori.map((k) => ({ id: k.id, nama: k.nama }));

        cards = [];
        kategori.forEach((k) => {
            const perNama = {};
            (k.menu || []).forEach((m) => {
                (perNama[m.nama] = perNama[m.nama] || []).push(m);
            });

            Object.keys(perNama).forEach((nama) => {
                const varian = perNama[nama];
                const gelas = varian.filter((m) => !isBotol(m.ukuran));
                const botol = varian.filter((m) => isBotol(m.ukuran));

                if (gelas.length) cards.push(buildCard(k, nama, gelas, false));
                if (botol.length) cards.push(buildCard(k, nama, botol, true));
            });
        });
    }

    function buildCard(kategori, nama, varian, botol) {
        varian.sort((a, b) => (UKURAN_RANK[a.ukuran] || 99) - (UKURAN_RANK[b.ukuran] || 99));
        const baseLabel = DISPLAY[nama] || nama;
        const imgMap = IMG[nama] || {};
        const file = botol ? imgMap.botol : imgMap.gelas;

        return {
            kategoriId: kategori.id,
            kategoriNama: kategori.nama,
            label: botol ? `${baseLabel} Botol` : baseLabel,
            img: file ? imgUrl(file) : PLACEHOLDER,
            hargaMulai: Math.min.apply(null, varian.map((m) => m.harga)),
            variants: varian.map((m) => ({
                id: m.id,
                ukuran: m.ukuran,
                harga: m.harga,
                bisaSugar: m.bisa_pilih_sugar === true,
                bisaIce: m.bisa_pilih_ice === true,
                pemanis: m.pemanis || null,
            })),
        };
    }

    // ==================================================================
    // RENDER: kategori chips
    // ==================================================================
    function shortLabel(nama) {
        return nama.replace(/\s*&\s*Cookies$/i, '');
    }

    function renderChips() {
        const wrap = $('scanCats');
        let html = `<button type="button" class="scan-chip ${activeKategori === '' ? 'active' : ''}" data-id="">Semua</button>`;
        kategoriList.forEach((k) => {
            html += `<button type="button" class="scan-chip ${String(activeKategori) === String(k.id) ? 'active' : ''}" data-id="${k.id}">${shortLabel(k.nama)}</button>`;
        });
        wrap.innerHTML = html;
        wrap.querySelectorAll('.scan-chip').forEach((chip) => {
            chip.addEventListener('click', () => {
                activeKategori = chip.dataset.id;
                renderChips();
                renderList();
            });
        });
    }

    // ==================================================================
    // RENDER: list menu
    // ==================================================================
    function qtyForCard(card) {
        const ids = new Set(card.variants.map((v) => v.id));
        return cart.filter((c) => ids.has(c.menuId)).reduce((s, c) => s + c.qty, 0);
    }

    function renderList() {
        const keyword = $('scanSearch').value.trim().toLowerCase();
        let rows = cards;
        if (activeKategori) rows = rows.filter((c) => String(c.kategoriId) === String(activeKategori));
        if (keyword) rows = rows.filter((c) => c.label.toLowerCase().includes(keyword));

        const list = $('scanList');
        if (!rows.length) {
            list.innerHTML = '<div class="scan-empty">Menu tidak ditemukan.</div>';
            return;
        }

        list.innerHTML = rows.map((card) => {
            const qty = qtyForCard(card);
            return `<div class="scan-card">
                <img class="thumb" src="${card.img}" alt="${card.label}" loading="lazy" onerror="this.src='${PLACEHOLDER}'">
                <div class="info">
                    <div class="cat">${card.kategoriNama}</div>
                    <div class="nama">${card.label}</div>
                    <div class="harga"><span class="pre">Mulai</span> ${rupiah(card.hargaMulai)}</div>
                </div>
                <button type="button" class="scan-add" data-idx="${cards.indexOf(card)}" aria-label="Tambah ${card.label}">
                    +${qty > 0 ? `<span class="badge">${qty}</span>` : ''}
                </button>
            </div>`;
        }).join('');

        list.querySelectorAll('.scan-add').forEach((btn) => {
            btn.addEventListener('click', () => onAdd(cards[parseInt(btn.dataset.idx, 10)]));
        });
    }

    // ==================================================================
    // TAMBAH: 1 varian -> langsung; banyak -> sheet ukuran
    // ==================================================================
    function onAdd(card) {
        if (card.variants.length === 1) {
            mulaiTambah(card, card.variants[0]);
        } else {
            openVariantSheet(card);
        }
    }
    function mulaiTambah(card, variant) {
        if (!variant.bisaSugar && !variant.bisaIce) {
            addToCart(card, variant, null, null);
            return;
        }
        openOpsiSheet(card, variant);
    }

    function renderOpsiRow(rowId, daftar, terpilih) {
        const row = $(rowId);
        row.innerHTML = daftar.map((o) =>
            `<button type="button" class="scan-opsi-btn ${o.kode === terpilih ? 'active' : ''}" data-kode="${o.kode}">${o.label}</button>`
        ).join('');
        row.querySelectorAll('.scan-opsi-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                row.querySelectorAll('.scan-opsi-btn').forEach((b) => b.classList.toggle('active', b === btn));
                if (rowId === 'opsiSugarRow') pendingOpsi.sugar = btn.dataset.kode;
                else pendingOpsi.ice = btn.dataset.kode;
            });
        });
    }

    function openOpsiSheet(card, variant) {
        pendingOpsi = {
            card,
            variant,
            sugar: variant.bisaSugar && opsiSugar.length ? opsiSugar[0].kode : null,
            ice: variant.bisaIce && opsiIce.length ? opsiIce[0].kode : null,
        };

        $('opsiTitle').textContent = variant.ukuran ? `${card.label} (${variant.ukuran})` : card.label;
        $('opsiSub').textContent = 'Atur takaran sesuai seleramu.';
        const pemanis = variant.pemanis || {};
        $('opsiSugarLabel').textContent = pemanis.jenis || 'Gula';
        $('opsiSugarKet').textContent = pemanis.keterangan || '';
        $('opsiSugarKet').hidden = ! pemanis.keterangan;
        $('opsiSugarWrap').hidden = !variant.bisaSugar;
        $('opsiIceWrap').hidden = !variant.bisaIce;
        if (variant.bisaSugar) renderOpsiRow('opsiSugarRow', opsiSugar, pendingOpsi.sugar);
        if (variant.bisaIce) renderOpsiRow('opsiIceRow', opsiIce, pendingOpsi.ice);
        openSheet('opsiSheet');
    }

    function openVariantSheet(card) {
        $('varTitle').textContent = card.label;
        $('varSub').textContent = 'Pilih ukuran yang kamu mau.';
        const list = $('varList');
        list.innerHTML = card.variants.map((v, i) =>
            `<button type="button" class="scan-var" data-i="${i}">
                <span class="v-nama">${v.ukuran || 'Standar'}</span>
                <span class="v-harga">${rupiah(v.harga)}</span>
            </button>`
        ).join('');
        list.querySelectorAll('.scan-var').forEach((btn) => {
            btn.addEventListener('click', () => {
                closeSheet('variantSheet');
                mulaiTambah(card, card.variants[parseInt(btn.dataset.i, 10)]);
            });
        });
        openSheet('variantSheet');
    }

    $('opsiConfirm').addEventListener('click', () => {
        if (!pendingOpsi) return;
        addToCart(pendingOpsi.card, pendingOpsi.variant, pendingOpsi.sugar, pendingOpsi.ice);
        pendingOpsi = null;
        closeSheet('opsiSheet');
    });

    const cartKey = (menuId, sugar, ice) => `${menuId}|${sugar || ''}|${ice || ''}`;

    function labelOpsi(daftar, kode) {
        const found = daftar.find((o) => o.kode === kode);
        return found ? found.label : null;
    }

    function addToCart(card, variant, sugar, ice) {
        const key = cartKey(variant.id, sugar, ice);
        const existing = cart.find((c) => c.key === key);

        if (existing) {
            existing.qty += 1;
        } else {
            const label = variant.ukuran ? `${card.label} (${variant.ukuran})` : card.label;
            const namaPemanis = (variant.pemanis && variant.pemanis.jenis) || 'Gula';
            const bagian = [];
            if (sugar) bagian.push(`${namaPemanis}: ${labelOpsi(opsiSugar, sugar)}`);
            if (ice) bagian.push(`Ice: ${labelOpsi(opsiIce, ice)}`);
            const opsiTeks = bagian.join(' · ');

            cart.push({
                key,
                menuId: variant.id,
                label,
                ukuran: variant.ukuran,
                harga: variant.harga,
                qty: 1,
                sugar: sugar || null,
                ice: ice || null,
                opsiTeks,
            });
        }
        refreshCartUI();
        renderList();
    }

    function changeQty(key, delta) {
        const item = cart.find((c) => c.key === key);
        if (!item) return;
        item.qty += delta;
        if (item.qty <= 0) cart = cart.filter((c) => c.key !== key);
        refreshCartUI();
        renderCartItems();
        renderList();
    }

    // ==================================================================
    // CART BAR + SHEET KERANJANG
    // ==================================================================
    function cartCount() { return cart.reduce((s, c) => s + c.qty, 0); }
    function cartTotal() { return cart.reduce((s, c) => s + c.harga * c.qty, 0); }

    function refreshCartUI() {
        const count = cartCount();
        $('scanCartBar').hidden = count === 0;
        $('cbCount').textContent = count;
        $('cbTotal').textContent = rupiah(cartTotal());
        $('cartTotal').textContent = rupiah(cartTotal());
    }

    function renderCartItems() {
        const wrap = $('cartItems');
        if (!cart.length) {
            wrap.innerHTML = '<div class="scan-cart-empty">Keranjang masih kosong.</div>';
            return;
        }
        wrap.innerHTML = cart.map((c) =>
            `<div class="scan-cart-item">
                <div class="ci-info">
                    <div class="ci-nama">${c.label}</div>
                    ${c.opsiTeks ? `<div class="ci-opsi">${c.opsiTeks}</div>` : ''}
                    <div class="ci-sub">${rupiah(c.harga)} × ${c.qty} = ${rupiah(c.harga * c.qty)}</div>
                </div>
                <div class="scan-stepper">
                    <button type="button" data-minus="${c.key}">−</button>
                    <span class="qty">${c.qty}</span>
                    <button type="button" data-plus="${c.key}">+</button>
                </div>
            </div>`
        ).join('');
        wrap.querySelectorAll('[data-minus]').forEach((b) =>
            b.addEventListener('click', () => changeQty(b.dataset.minus, -1)));
        wrap.querySelectorAll('[data-plus]').forEach((b) =>
            b.addEventListener('click', () => changeQty(b.dataset.plus, +1)));
    }

    // ==================================================================
    // SHEET helpers
    // ==================================================================
    function openSheet(id) { $(id).hidden = false; document.body.style.overflow = 'hidden'; }
    function closeSheet(id) { $(id).hidden = true; document.body.style.overflow = ''; }

    const SHEET_ID = { cart: 'cartSheet', variant: 'variantSheet', opsi: 'opsiSheet' };

    document.querySelectorAll('[data-close]').forEach((el) => {
        el.addEventListener('click', () => {
            if (el.dataset.close === 'opsi') pendingOpsi = null;
            closeSheet(SHEET_ID[el.dataset.close] || 'variantSheet');
        });
    });

    $('scanCartBar').addEventListener('click', () => {
        if (!cart.length) return;
        renderCartItems();
        $('cartError').style.display = 'none';
        openSheet('cartSheet');
    });

    // ==================================================================
    // METODE PEMBAYARAN (Tunai / QRIS)
    // ==================================================================
    const payButtons = document.querySelectorAll('.scan-pay-btn');

    payButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            metodeBayar = btn.dataset.metode;
            payButtons.forEach((b) => b.classList.toggle('active', b === btn));
        });
    });

    function resetMetodeBayar() {
        metodeBayar = null;
        payButtons.forEach((b) => b.classList.remove('active'));
    }

    // ==================================================================
    // OVERLAY: state "Menunggu Pembayaran" <-> "Pesanan Berhasil"
    // ==================================================================
    const SUB_MENUNGGU = {
        qris: 'Segera selesaikan pembayaran QRIS-mu, pesanan langsung diproses setelah itu',
        cash: 'Silakan bayar di kasir sambil menyebutkan kode pesanan di atas',
    };

    function setDoneWaiting(metode) {
    console.log('[SoyaScan] setDoneWaiting dipanggil, metode:', metode);
    const iw = $('doneIconWaiting');
    const is = $('doneIconSuccess');
    iw.hidden = false;
    iw.style.setProperty('display', 'block', 'important');
    is.hidden = true;
    is.style.setProperty('display', 'none', 'important');
    $('doneCheck').classList.remove('is-success');
    $('doneTitle').textContent = 'Menunggu Pembayaran';
    $('doneSub').textContent = SUB_MENUNGGU[metode]
        ?? 'Lakukan pembayaran dan pesananmu akan segera diproses';
    console.log('[SoyaScan] setelah setDoneWaiting -> waiting display:', getComputedStyle(iw).display, '| success display:', getComputedStyle(is).display);
}

function setDoneSuccess() {
    console.log('[SoyaScan] setDoneSuccess dipanggil');
    const iw = $('doneIconWaiting');
    const is = $('doneIconSuccess');
    iw.hidden = true;
    iw.style.setProperty('display', 'none', 'important');
    is.hidden = false;
    is.style.setProperty('display', 'block', 'important');
    $('doneCheck').classList.add('is-success');
    $('doneTitle').textContent = 'Pesanan Berhasil! 🎉';
    $('doneSub').textContent = 'Pesananmu sudah masuk ke kasir';
    $('doneQris').hidden = true;
    console.log('[SoyaScan] setelah setDoneSuccess -> waiting display:', getComputedStyle(iw).display, '| success display:', getComputedStyle(is).display);
}

    function tampilkanRincian(items) {
        const wrap = $('doneItems');
        if (!wrap) return;

        if (!items.length) {
            wrap.hidden = true;
            return;
        }

        wrap.hidden = false;
        wrap.innerHTML = items.map((i) => {
            const nama = i.ukuran ? `${i.nama_menu} (${i.ukuran})` : i.nama_menu;
            const takaran = [i.level_sugar_label, i.level_ice_label].filter(Boolean).join(' · ');

            return `<div class="di-baris">
                <div class="di-kiri">
                    <div class="di-nama">${i.qty}× ${nama}</div>
                    ${takaran ? `<div class="di-takaran">${takaran}</div>` : ''}
                </div>
                <div class="di-harga">${rupiah(i.subtotal)}</div>
            </div>`;
        }).join('');
    }

    // QRIS toko yang berlaku SEKARANG, bukan salinan yang ikut tersimpan saat
    // pesanan dibuat. `qris_url` di response POST /api/order cuma potret pada
    // detik itu, jadi pesanan yang dibuat sebelum manager mengunggah QRIS akan
    // menempel di "Kode QRIS belum tersedia" selamanya, termasuk setelah layar
    // ini dibuka ulang. Gagal ambil dibiarkan diam: pesan cadangan "bayar di
    // kasir" sudah menjadi jalan keluar yang benar.
    async function ambilQrisToko() {
        try {
            const res = await fetch(`${API_BASE}/toko`, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return null;

            return (await res.json()).data?.qris_url ?? null;
        } catch (e) {
            return null;
        }
    }

    async function tampilkanQris(metode, qrisUrl) {
        const wrap = $('doneQris');
        const img = $('doneQrisImg');
        const note = $('doneQrisNote');
        const unduh = $('doneQrisUnduh');
        const hint = $('doneQrisHint');

        if (metode !== 'qris') {
            wrap.hidden = true;
            return;
        }

        wrap.hidden = false;

        if (!qrisUrl) {
            // Teks sementara supaya kartunya tidak sempat menampilkan "belum
            // tersedia" padahal QRIS-nya masih dalam perjalanan.
            note.textContent = 'Memuat kode QRIS…';
            note.hidden = false;
            img.hidden = true;
            unduh.hidden = true;
            hint.hidden = true;

            qrisUrl = await ambilQrisToko();
        }

        if (qrisUrl) {
            img.src = qrisUrl;
            img.hidden = false;
            note.hidden = true;
            const cocok = qrisUrl.split('?')[0].match(/\.(png|jpe?g)$/i);
            unduh.href = qrisUrl;
            unduh.download = 'qris-gressoy.' + (cocok ? cocok[1].toLowerCase() : 'png');
            unduh.hidden = false;
            hint.hidden = false;
        } else {
            img.removeAttribute('src');
            img.hidden = true;
            unduh.removeAttribute('href');
            unduh.hidden = true;
            hint.hidden = true;
            note.textContent = 'Kode QRIS belum tersedia. Silakan bayar di kasir sambil menyebutkan kode pesanan di atas.';
            note.hidden = false;
        }
    }

    function stopStatusPolling() {
        if (statusPollTimer) {
            clearInterval(statusPollTimer);
            statusPollTimer = null;
        }
    }

    function startStatusPolling(kode) {
        stopStatusPolling();
        if (!kode || kode === '—') return;

        statusPollDeadline = Date.now() + STATUS_POLL_TIMEOUT_MS;

        const check = async () => {
            if (Date.now() > statusPollDeadline) {
                console.log('[SoyaScan] polling timeout, berhenti');
                stopStatusPolling();
                return;
            }
            try {
                const res = await fetch(statusEndpoint(kode), { headers: { 'Accept': 'application/json' } });
                if (!res.ok) { console.log('[SoyaScan] poll gagal, HTTP', res.status); return; }
                const json = await res.json();
                
                if (isPaidStatus(json)) {
                    setDoneSuccess();
                    stopStatusPolling();
                }
            } catch (e) {
                console.error('[SoyaScan] Error occurred while polling status:', e);
            }
        };

        check();
        statusPollTimer = setInterval(check, STATUS_POLL_INTERVAL_MS);
    }
    function restoreActiveOrderOverlay() {
        const order = loadActiveOrder();
        if (!order || !order.kode) return false;

        $('doneKode').textContent = order.kode;
        $('doneNama').textContent = order.nama ?? '—';
        $('doneTotal').textContent = rupiah(order.total ?? 0);
        $('donePoin').textContent = '+' + (order.poin ?? 0) + ' poin';
        tampilkanQris(order.metode, order.qrisUrl ?? null);
        tampilkanRincian(order.items || []);

        setDoneWaiting(order.metode); 
        $('doneOverlay').hidden = false;
        document.body.style.overflow = 'hidden';
        startStatusPolling(order.kode);
        return true;
    }

    // ==================================================================
    // CHECKOUT -> POST /api/order
    // ==================================================================
    function showCartError(msg) {
        const el = $('cartError');
        el.textContent = msg;
        el.style.display = 'block';
    }

    $('orderForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!cart.length) return showCartError('Keranjang masih kosong.');

        const nama = $('fNama').value.trim();
        const nomorWa = $('fWa').value.trim();
        if (!nama || !nomorWa) return showCartError('Nama dan nomor WhatsApp wajib diisi.');
        if (!metodeBayar) return showCartError('Pilih metode pembayaran dulu (Tunai atau QRIS).');

        const btn = $('submitOrder');
        btn.disabled = true;
        btn.textContent = 'Mengirim…';

        try {
            const res = await fetch(`${API_BASE}/order`, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    nama,
                    nomor_wa: nomorWa,
                    metode_bayar: metodeBayar,
                    items: cart.map((c) => {
                        const item = { menu_id: c.menuId, qty: c.qty };
                        if (c.sugar) item.level_sugar = c.sugar;
                        if (c.ice) item.level_ice = c.ice;
                        return item;
                    }),
                }),
            });
            const json = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(json.message || 'Pesanan gagal dikirim. Coba lagi.');

            const kode = json.kode_pesanan || '—';
            const totalBayar = Number(json.total ?? cartTotal());
            const poin = Math.floor(totalBayar / 1000);
            // Key ini cuma ada di response saat pembayarannya QRIS.
            const qrisUrl = metodeBayar === 'qris' ? (json.qris_url ?? null) : null;

            $('doneKode').textContent = kode;
            $('doneNama').textContent = nama;
            $('doneTotal').textContent = rupiah(totalBayar);
            $('donePoin').textContent = '+' + poin + ' poin';
            tampilkanQris(metodeBayar, qrisUrl);
            tampilkanRincian(json.items || []);
            saveActiveOrder({
                kode, nama, total: totalBayar, poin,
                metode: metodeBayar, qrisUrl,
                items: json.items || [],
            });

            setDoneWaiting(metodeBayar);
            startStatusPolling(kode);

            closeSheet('cartSheet');
            $('doneOverlay').hidden = false;
            document.body.style.overflow = 'hidden';

            cart = [];
            resetMetodeBayar();
            $('orderForm').reset();
            refreshCartUI();
            renderList();
        } catch (err) {
            showCartError(err.message);
        } finally {
            btn.disabled = false;
            btn.textContent = 'Pesan Sekarang';
        }
    });

    $('doneClose').addEventListener('click', () => {
        stopStatusPolling();
        clearActiveOrder(); 
        $('doneOverlay').hidden = true;
        document.body.style.overflow = '';
    });

    // ==================================================================
    // INIT
    // ==================================================================
    let searchTimer;
    $('scanSearch').addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(renderList, 200);
    });

    $('fWa').addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 12);
    });

    (async function init() {
        restoreActiveOrderOverlay();
        try {
            await loadKatalog();
            renderChips();
            renderList();
            refreshCartUI();
        } catch (e) {
            $('scanList').innerHTML = '<div class="scan-empty">Gagal memuat menu. Coba refresh halaman.</div>';
        }
    })();
})();