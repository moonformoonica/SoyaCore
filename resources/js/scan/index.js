/* ============================================================
   SoyaScan — self-order pelanggan (mobile).
   Alur: GET /api/menu (publik) -> browse -> pilih ukuran ->
   keranjang -> POST /api/order (publik, tanpa auth).
   ============================================================ */
(function () {
    'use strict';

    const API_BASE = '/api';

    // ---- Peta nama menu (DB) -> file gambar di /images/menu.
    // Dibuat eksplisit karena penamaan file tidak konsisten
    // (Capucinno, Choco Coffiee, Royal belgian, dst).
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

    // Nama tampil khusus (mockup menulis "Soya Original").
    const DISPLAY = { 'Original': 'Soya Original' };

    // Urutan ukuran biar rapi di sheet varian.
    const UKURAN_RANK = { 'Hot': 1, 'Reguler': 2, 'Large': 3, '250ml': 4, '500ml': 5, '1000ml': 6 };

    // Urutan kategori sesuai menu resmi (bukan alfabet).
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
    let cards = [];            // hasil grouping (satu kartu = gelas/botol)
    let kategoriList = [];     // { id, nama }
    let activeKategori = '';   // '' = semua
    let cart = [];             // { menuId, label, ukuran, harga, qty }
    let metodeBayar = null;    // 'cash' | 'qris' — pilihan pelanggan di checkout

    // ---- Util
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

        kategoriList = kategori.map((k) => ({ id: k.id, nama: k.nama }));

        cards = [];
        kategori.forEach((k) => {
            // Kelompokkan menu per nama, lalu pisah varian gelas vs botol.
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
            variants: varian.map((m) => ({ id: m.id, ukuran: m.ukuran, harga: m.harga })),
        };
    }

    // ==================================================================
    // RENDER: kategori chips
    // ==================================================================
    function shortLabel(nama) {
        // Tetap pakai "Soya ..." (mis. Soya Signature); hanya "Dessert & Cookies" dipendekkan jadi "Dessert".
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
            addToCart(card, card.variants[0]);
        } else {
            openVariantSheet(card);
        }
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
                addToCart(card, card.variants[parseInt(btn.dataset.i, 10)]);
                closeSheet('variantSheet');
            });
        });
        openSheet('variantSheet');
    }

    function addToCart(card, variant) {
        const existing = cart.find((c) => c.menuId === variant.id);
        if (existing) {
            existing.qty += 1;
        } else {
            const label = variant.ukuran ? `${card.label} (${variant.ukuran})` : card.label;
            cart.push({ menuId: variant.id, label, ukuran: variant.ukuran, harga: variant.harga, qty: 1 });
        }
        refreshCartUI();
        renderList();
    }

    function changeQty(menuId, delta) {
        const item = cart.find((c) => c.menuId === menuId);
        if (!item) return;
        item.qty += delta;
        if (item.qty <= 0) cart = cart.filter((c) => c.menuId !== menuId);
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
                    <div class="ci-sub">${rupiah(c.harga)} × ${c.qty} = ${rupiah(c.harga * c.qty)}</div>
                </div>
                <div class="scan-stepper">
                    <button type="button" data-minus="${c.menuId}">−</button>
                    <span class="qty">${c.qty}</span>
                    <button type="button" data-plus="${c.menuId}">+</button>
                </div>
            </div>`
        ).join('');
        wrap.querySelectorAll('[data-minus]').forEach((b) =>
            b.addEventListener('click', () => changeQty(parseInt(b.dataset.minus, 10), -1)));
        wrap.querySelectorAll('[data-plus]').forEach((b) =>
            b.addEventListener('click', () => changeQty(parseInt(b.dataset.plus, 10), +1)));
    }

    // ==================================================================
    // SHEET helpers
    // ==================================================================
    function openSheet(id) { $(id).hidden = false; document.body.style.overflow = 'hidden'; }
    function closeSheet(id) { $(id).hidden = true; document.body.style.overflow = ''; }

    document.querySelectorAll('[data-close]').forEach((el) => {
        el.addEventListener('click', () => closeSheet(el.dataset.close === 'cart' ? 'cartSheet' : 'variantSheet'));
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
        const nomorMeja = $('fMeja').value.trim();
        if (!nama || !nomorWa || !nomorMeja) return showCartError('Nama, nomor WhatsApp, dan nomor meja wajib diisi.');
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
                    nomor_meja: nomorMeja,
                    metode_bayar: metodeBayar,
                    items: cart.map((c) => ({ menu_id: c.menuId, qty: c.qty })),
                }),
            });
            const json = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(json.message || 'Pesanan gagal dikirim. Coba lagi.');

            // Isi layar sukses SEBELUM form di-reset (nama & meja diambil dari form).
            const totalBayar = Number(json.total ?? cartTotal());
            $('doneKode').textContent = json.kode_pesanan || '—';
            $('doneNama').textContent = nama;
            $('doneMeja').textContent = json.nomor_meja ?? nomorMeja;
            $('doneTotal').textContent = rupiah(totalBayar);
            // LoyalSeed: 1 poin per Rp 1.000, dibulatkan ke bawah — poin baru
            // benar-benar masuk saat kasir menandai lunas.
            $('donePoin').textContent = '+' + Math.floor(totalBayar / 1000) + ' poin';

            closeSheet('cartSheet');
            $('doneOverlay').hidden = false;
            document.body.style.overflow = 'hidden';

            // reset
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

    // Prefill nomor meja dari QR (?meja=5).
    const meja = new URLSearchParams(location.search).get('meja');
    if (meja) $('fMeja').value = meja;

    // No. WhatsApp: hanya angka, maksimal 12 digit.
    $('fWa').addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 12);
    });

    (async function init() {
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
