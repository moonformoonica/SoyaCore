(function () {
    const API_BASE = '/api';

    function getToken() { return localStorage.getItem('auth_token'); }
    function fetchOptions(extra = {}) {
        const token = getToken();
        return {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                ...(token ? { 'Authorization': 'Bearer ' + token } : {}),
            },
            ...extra,
        };
    }

    function rupiah(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID'); }

    const errorEl = document.getElementById('pesError');
    const successEl = document.getElementById('pesSuccess');
    function showError(msg) { errorEl.textContent = msg; errorEl.style.display = 'block'; setTimeout(() => errorEl.style.display = 'none', 5000); }
    function showSuccess(msg) { successEl.textContent = msg; successEl.style.display = 'block'; setTimeout(() => successEl.style.display = 'none', 4000); }

    // ---- State ----
    let allMenu = [];        // flat rows dari GET /api/menu
    let kategoriList = [];   // dari GET /api/kategori
    let activeKategori = ''; // '' = semua
    let currentTransaksi = null; // objek TransaksiResource, null = belum ada transaksi berjalan
    let tipePesanan = 'dine_in'; // 'dine_in' | 'takeaway' — dipakai isi nomor_meja per item
    let metodeBayar = null; // 'cash' | 'qris'
    let selectedCustomer = null; // ver1: { nama, no_wa, poin } hasil GET /api/loyalty/{no_wa}, null = belum/ver2
    let selectedDiskonPersen = null; // preset diskon yang lagi dipilih, diterapkan lewat tombol "Terapkan Diskon"

    // ==================================================================
    // LOAD DATA AWAL
    // ==================================================================
    // Urutan kategori sesuai menu resmi (bukan alfabet) — disamakan dengan
    // halaman SoyaScan supaya kasir dan pelanggan lihat susunan yang sama.
    const KATEGORI_RANK = {
        'Soya Signature': 1,
        'Soya Chocolate': 2,
        'Soya Coffee': 3,
        'Soya Tropical': 4,
        'Soya Tea': 5,
        'Dessert & Cookies': 6,
    };
    const katRank = (nama) => KATEGORI_RANK[nama] || 99;

    async function loadKategori() {
        const res = await fetch(`${API_BASE}/kategori`, fetchOptions());
        const json = await res.json();
        kategoriList = (json.data || []).slice().sort((a, b) => katRank(a.nama) - katRank(b.nama));
        renderKategoriTabs();
    }

    async function loadMenu() {
        const res = await fetch(`${API_BASE}/menu-internal?is_active=1`, fetchOptions());
        const json = await res.json();
        allMenu = json.data || [];
        renderKategoriTabs(); // refresh jumlah per kategori
        renderMenuGrid();
    }

    // ==================================================================
    // KATEGORI TABS
    // ==================================================================
    function renderKategoriTabs() {
        const wrap = document.getElementById('kategoriRow');
        const jumlahSemua = allMenu.length;

        let html = `<div class="pes-kategori-pill ${activeKategori === '' ? 'active' : ''}" data-id="">
            <div class="k-nama">Semua Kategori</div>
            <div class="k-jumlah">${jumlahSemua} Menu</div>
        </div>`;

        kategoriList.forEach(function (k) {
            const jumlah = allMenu.filter(m => m.kategori_id === k.id).length;
            html += `<div class="pes-kategori-pill ${String(activeKategori) === String(k.id) ? 'active' : ''}" data-id="${k.id}">
                <div class="k-nama">${k.nama}</div>
                <div class="k-jumlah">${jumlah} Menu</div>
            </div>`;
        });

        wrap.innerHTML = html;
        wrap.querySelectorAll('.pes-kategori-pill').forEach(function (pill) {
            pill.addEventListener('click', function () {
                activeKategori = pill.dataset.id;
                renderKategoriTabs();
                renderMenuGrid();
            });
        });
    }

    // ==================================================================
    // MENU GRID
    // ==================================================================
    function currentQtyInCart(menuId) {
        if (!currentTransaksi) return 0;
        const item = currentTransaksi.items.find(i => i.menu_id === menuId && !i.is_reward);
        return item ? item.qty : 0;
    }

    function renderMenuGrid() {
        const keyword = document.getElementById('pesSearch').value.trim().toLowerCase();
        let rows = allMenu;

        if (activeKategori) rows = rows.filter(m => String(m.kategori_id) === String(activeKategori));
        if (keyword) rows = rows.filter(m => m.nama.toLowerCase().includes(keyword));

        const grid = document.getElementById('menuGrid');

        if (rows.length === 0) {
            grid.innerHTML = '<div class="pes-empty">Tidak ada menu yang cocok.</div>';
            return;
        }

        grid.innerHTML = rows.map(function (m) {
            const qty = currentQtyInCart(m.id);
            const label = m.ukuran ? `${m.nama} (${m.ukuran})` : m.nama;

            return `<div class="pes-menu-card ${m.is_active ? '' : 'inactive'}" data-menu-id="${m.id}">
                <div class="m-kategori">${m.kategori ?? ''}</div>
                <div class="m-nama">${label}</div>
                <div class="m-ukuran">${m.rasa ?? ''}</div>
                <div class="m-harga">${rupiah(m.harga)}</div>
                <div class="pes-qty-stepper">
                    ${qty > 0
                        ? `<button class="qty-minus" data-menu-id="${m.id}">-</button>
                           <span class="qty-value">${qty}</span>
                           <button class="qty-plus" data-menu-id="${m.id}">+</button>`
                        : `<button class="pes-add-btn" data-menu-id="${m.id}">+ Tambah</button>`
                    }
                </div>
            </div>`;
        }).join('');

        grid.querySelectorAll('.pes-add-btn, .qty-plus').forEach(function (btn) {
            btn.addEventListener('click', () => tambahItem(parseInt(btn.dataset.menuId, 10)));
        });
        grid.querySelectorAll('.qty-minus').forEach(function (btn) {
            btn.addEventListener('click', () => kurangiItem(parseInt(btn.dataset.menuId, 10)));
        });
    }

    // ==================================================================
    // TRANSAKSI: buat / tambah item / kurangi item / hapus
    // ==================================================================

    // Transaksi baru dibuat SEKALI, saat item pertama ditambahkan — memakai
    // data pelanggan yang sudah diisi saat itu (kalau ada). Karena backend
    // tidak punya endpoint untuk mengubah customer pada transaksi yang
    // sudah dibuat, field pelanggan dikunci setelah ini.
    async function pastikanTransaksi() {
        if (currentTransaksi) return currentTransaksi;

        const payload = {};
        if (selectedCustomer) {
            // ver1: pelanggan ketemu dari pencarian — pakai data yang sudah ada.
            payload.customer = { nama: selectedCustomer.nama, no_wa: selectedCustomer.no_wa };
        } else {
            // ver2: pelanggan baru — daftar pakai nama + no WA yang diisi manual.
            const nama = document.getElementById('custNama').value.trim();
            const noWa = document.getElementById('custNoWa').value.trim();
            if (nama && noWa) payload.customer = { nama, no_wa: noWa };
        }

        const res = await fetch(`${API_BASE}/transaksi`, fetchOptions({ method: 'POST', body: JSON.stringify(payload) }));
        if (!res.ok) {
            const err = await res.json().catch(() => ({}));
            throw new Error(err.message || 'Gagal membuat transaksi baru.');
        }

        const json = await res.json();
        currentTransaksi = json.data;
        lockCustomerFields();
        return currentTransaksi;
    }

    function lockCustomerFields() {
        document.getElementById('custSearch').disabled = true;
        document.getElementById('custAddBtn').disabled = true;
        document.getElementById('custGantiBtn').disabled = true;
        document.getElementById('custNama').disabled = true;
        document.getElementById('custNoWa').disabled = true;
        document.getElementById('custLockedNote').style.display = 'block';
    }

    function unlockCustomerFields() {
        document.getElementById('custSearch').disabled = false;
        document.getElementById('custAddBtn').disabled = false;
        document.getElementById('custGantiBtn').disabled = false;
        document.getElementById('custNama').disabled = false;
        document.getElementById('custNoWa').disabled = false;
        document.getElementById('custSearch').value = '';
        document.getElementById('custNama').value = '';
        document.getElementById('custNoWa').value = '';
        document.getElementById('custLockedNote').style.display = 'none';
        selectedCustomer = null;
        bersihkanHasil();
        tampilkanFormKosong();
    }

    // ==================================================================
    // PELANGGAN: cari by no WA (ver1 ketemu / ver2 daftar baru)
    // ==================================================================
    function tampilkanPelangganKetemu(c) {
        document.getElementById('custNewForm').style.display = 'none';
        document.getElementById('custFoundCard').style.display = 'flex';
        document.getElementById('custFoundNama').textContent = c.nama;
        document.getElementById('custFoundMeta').textContent = `${c.no_wa} · ${c.poin} poin`;
    }

    function tampilkanFormBaru(noWa) {
        selectedCustomer = null;
        document.getElementById('custFoundCard').style.display = 'none';
        document.getElementById('custNewForm').style.display = 'block';
        document.getElementById('custNoWa').value = noWa || '';
        document.getElementById('custNama').value = '';
    }

    function tampilkanFormKosong() {
        document.getElementById('custFoundCard').style.display = 'none';
        document.getElementById('custNewForm').style.display = 'none';
    }

    function bersihkanHasil() {
        document.getElementById('custResults').innerHTML = '';
    }

    function tampilkanHasil(daftar) {
        const wrap = document.getElementById('custResults');
        wrap.innerHTML = daftar.map((c, i) =>
            `<div class="pes-cust-result" data-i="${i}">
                <div>
                    <div class="cr-nama">${c.nama}</div>
                    <div class="cr-meta">${c.no_wa}</div>
                </div>
                <span class="cr-poin">${c.poin} poin</span>
            </div>`
        ).join('');

        wrap.querySelectorAll('.pes-cust-result').forEach(function (el) {
            el.addEventListener('click', function () {
                selectedCustomer = daftar[parseInt(el.dataset.i, 10)];
                bersihkanHasil();
                document.getElementById('custSearch').value = selectedCustomer.no_wa;
                tampilkanPelangganKetemu(selectedCustomer);
            });
        });
    }

    /**
     * Cari lewat GET /api/customers/cari — mendukung nama maupun no WA.
     * Kata kunci yang didominasi angka diperlakukan sebagai nomor WA,
     * selain itu dicari sebagai nama.
     */
    async function cariPelanggan() {
        const kata = document.getElementById('custSearch').value.trim();

        if (!kata) {
            selectedCustomer = null;
            bersihkanHasil();
            tampilkanFormKosong();
            return;
        }

        const angka = kata.replace(/\D/g, '');
        const sebagaiNomor = angka.length >= 3 && angka.length >= kata.length - 3;

        // Pencarian nama butuh minimal 2 huruf (aturan CariCustomerRequest).
        if (!sebagaiNomor && kata.length < 2) {
            bersihkanHasil();
            return;
        }

        const params = sebagaiNomor ? { no_wa: kata } : { nama: kata };

        try {
            const res = await fetch(`${API_BASE}/customers/cari?` + new URLSearchParams(params), fetchOptions());
            if (!res.ok) throw new Error('Gagal mencari pelanggan.');

            const daftar = (await res.json()).data || [];

            if (daftar.length === 0) {
                // Belum terdaftar — tawarkan form pendaftaran pelanggan baru.
                bersihkanHasil();
                tampilkanFormBaru(sebagaiNomor ? kata : '');
                return;
            }

            if (daftar.length === 1) {
                selectedCustomer = daftar[0];
                bersihkanHasil();
                tampilkanPelangganKetemu(selectedCustomer);
                return;
            }

            // Lebih dari satu kandidat — biar kasir yang memilih.
            selectedCustomer = null;
            tampilkanFormKosong();
            tampilkanHasil(daftar);
        } catch (err) {
            showError(err.message);
        }
    }

    document.getElementById('custSearch').addEventListener('input', debounce(cariPelanggan, 500));
    document.getElementById('custAddBtn').addEventListener('click', function () {
        tampilkanFormBaru(document.getElementById('custSearch').value.trim());
    });
    // No. WhatsApp pelanggan baru: hanya angka, maksimal 12 digit.
    document.getElementById('custNoWa').addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 12);
    });
    document.getElementById('custGantiBtn').addEventListener('click', function () {
        selectedCustomer = null;
        document.getElementById('custSearch').value = '';
        bersihkanHasil();
        tampilkanFormKosong();
        document.getElementById('custSearch').focus();
    });

    function itemPayloadTambahan() {
        const payload = {};
        if (tipePesanan === 'dine_in') {
            const nomorMeja = document.getElementById('nomorMeja').value.trim();
            if (nomorMeja) payload.nomor_meja = nomorMeja;
        }
        return payload;
    }

    async function tambahItem(menuId) {
        try {
            const transaksi = await pastikanTransaksi();

            const res = await fetch(`${API_BASE}/transaksi/${transaksi.id}/items`, fetchOptions({
                method: 'POST',
                body: JSON.stringify({ menu_id: menuId, qty: 1, ...itemPayloadTambahan() }),
            }));
            if (!res.ok) {
                const err = await res.json().catch(() => ({}));
                throw new Error(err.message || 'Gagal menambah item.');
            }

            const json = await res.json();
            currentTransaksi = json.data;
            renderCart();
            renderMenuGrid();
        } catch (err) {
            showError(err.message);
        }
    }

    async function kurangiItem(menuId) {
        if (!currentTransaksi) return;
        const item = currentTransaksi.items.find(i => i.menu_id === menuId && !i.is_reward);
        if (!item) return;

        try {
            let res;
            if (item.qty <= 1) {
                res = await fetch(`${API_BASE}/transaksi/${currentTransaksi.id}/items/${item.id}`, fetchOptions({ method: 'DELETE' }));
            } else {
                res = await fetch(`${API_BASE}/transaksi/${currentTransaksi.id}/items/${item.id}`, fetchOptions({
                    method: 'PATCH',
                    body: JSON.stringify({ qty: item.qty - 1, ...itemPayloadTambahan() }),
                }));
            }
            if (!res.ok) {
                const err = await res.json().catch(() => ({}));
                throw new Error(err.message || 'Gagal mengubah item.');
            }

            const json = await res.json();
            currentTransaksi = json.data;
            renderCart();
            renderMenuGrid();
        } catch (err) {
            showError(err.message);
        }
    }

    async function hapusItem(itemId) {
        if (!currentTransaksi) return;
        try {
            const res = await fetch(`${API_BASE}/transaksi/${currentTransaksi.id}/items/${itemId}`, fetchOptions({ method: 'DELETE' }));
            if (!res.ok) throw new Error('Gagal menghapus item.');
            const json = await res.json();
            currentTransaksi = json.data;
            renderCart();
            renderMenuGrid();
        } catch (err) {
            showError(err.message);
        }
    }

    // ==================================================================
    // RENDER CART & RINGKASAN
    // ==================================================================
    function renderCart() {
        const list = document.getElementById('cartList');
        const items = currentTransaksi ? currentTransaksi.items : [];

        if (items.length === 0) {
            list.innerHTML = '<div class="pes-cart-empty">Belum ada item dipesan.</div>';
        } else {
            list.innerHTML = items.map(function (i) {
                const label = i.ukuran ? `${i.nama} (${i.ukuran})` : i.nama;
                return `<div class="pes-cart-item">
                    <div class="ci-info">
                        <div class="ci-nama">${label} ${i.is_reward ? '🎁' : ''}</div>
                        <div class="ci-harga">${i.qty} x ${rupiah(i.harga_satuan)} = ${rupiah(i.subtotal)}</div>
                    </div>
                    <div class="ci-actions">
                        <button class="ci-remove" data-item-id="${i.id}" title="Hapus"><i class="fa-regular fa-trash-can"></i></button>
                    </div>
                </div>`;
            }).join('');

            list.querySelectorAll('.ci-remove').forEach(function (btn) {
                btn.addEventListener('click', () => hapusItem(parseInt(btn.dataset.itemId, 10)));
            });
        }

        renderRingkasan();
        updateBayarButtonState();
    }

    function renderRingkasan() {
        const subtotal = currentTransaksi ? currentTransaksi.subtotal : 0;
        const diskonNilai = currentTransaksi ? currentTransaksi.diskon_nilai : 0;
        const total = currentTransaksi ? currentTransaksi.total : 0;

        document.getElementById('ringkasanSubtotal').textContent = rupiah(subtotal);
        document.getElementById('ringkasanDiskon').textContent = '- ' + rupiah(diskonNilai);
        document.getElementById('ringkasanTotal').textContent = rupiah(total);
    }

    function updateBayarButtonState() {
        const btn = document.getElementById('bayarBtn');
        const adaItem = currentTransaksi && currentTransaksi.items.length > 0;
        btn.disabled = !(adaItem && metodeBayar);
    }

    // ==================================================================
    // TIPE PESANAN (Dine In / Bawa Pulang)
    // ==================================================================
    document.querySelectorAll('.pes-tipe-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            tipePesanan = btn.dataset.tipe;
            document.querySelectorAll('.pes-tipe-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('nomorMejaWrap').style.display = tipePesanan === 'dine_in' ? 'block' : 'none';
        });
    });

    // ==================================================================
    // DISKON
    // ==================================================================
    async function terapkanDiskon(tipe, nilai) {
        if (!currentTransaksi) return showError('Tambahkan item dulu sebelum menerapkan diskon.');

        try {
            const res = await fetch(`${API_BASE}/transaksi/${currentTransaksi.id}/diskon`, fetchOptions({
                method: 'POST',
                body: JSON.stringify({ tipe, nilai }),
            }));
            if (!res.ok) {
                const err = await res.json().catch(() => ({}));
                throw new Error(err.message || 'Gagal menerapkan diskon.');
            }
            const json = await res.json();
            currentTransaksi = json.data;
            renderCart();
            showSuccess('Diskon diterapkan.');
        } catch (err) {
            showError(err.message);
        }
    }

    // Pilih preset dulu (cuma menandai aktif) — baru benar-benar diterapkan
    // saat tombol "Terapkan Diskon" ditekan.
    document.querySelectorAll('.pes-diskon-preset').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.pes-diskon-preset').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            selectedDiskonPersen = parseInt(btn.dataset.persen, 10);
        });
    });

    document.getElementById('diskonTerapkanBtn').addEventListener('click', function () {
        if (selectedDiskonPersen === null) return showError('Pilih diskon dulu sebelum menerapkan.');
        // custom_persen dipakai (bukan preset) karena backend cuma terima
        // preset 10/20/50 — 30% (dan preset lain di masa depan) tetap valid
        // lewat jalur custom_persen (terima 0-100), hasilnya identik.
        terapkanDiskon('custom_persen', selectedDiskonPersen);
    });

    // ==================================================================
    // METODE PEMBAYARAN
    // ==================================================================
    document.querySelectorAll('.pes-metode-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            metodeBayar = btn.dataset.metode;
            document.querySelectorAll('.pes-metode-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            updateBayarButtonState();
        });
    });

    // ==================================================================
    // BAYAR
    // ==================================================================
    document.getElementById('bayarBtn').addEventListener('click', async function () {
        if (!currentTransaksi || !metodeBayar) return;

        const btn = this;
        btn.disabled = true;
        btn.textContent = 'Memproses...';

        try {
            const res = await fetch(`${API_BASE}/transaksi/${currentTransaksi.id}/bayar`, fetchOptions({
                method: 'POST',
                body: JSON.stringify({ metode_bayar: metodeBayar }),
            }));
            if (!res.ok) {
                const err = await res.json().catch(() => ({}));
                throw new Error(err.message || 'Gagal memproses pembayaran.');
            }

            const json = await res.json();
            showSuccess(`Pembayaran berhasil — ${json.data.kode_pesanan} (${rupiah(json.data.total)}).`);
            resetPesananBaru();
        } catch (err) {
            showError(err.message);
        } finally {
            btn.disabled = false;
            btn.textContent = 'Bayar Sekarang';
        }
    });

    // ==================================================================
    // BATALKAN PESANAN
    // ==================================================================
    document.getElementById('batalBtn').addEventListener('click', async function () {
        if (!currentTransaksi) return;
        if (!confirm('Batalkan pesanan ini? Item yang sudah ditambahkan akan hilang.')) return;

        try {
            await fetch(`${API_BASE}/transaksi/${currentTransaksi.id}/batal`, fetchOptions({ method: 'POST' }));
        } catch (err) {
            // diabaikan — tetap reset UI di sisi client
        }
        resetPesananBaru();
    });

    function resetPesananBaru() {
        currentTransaksi = null;
        metodeBayar = null;
        selectedDiskonPersen = null;
        document.querySelectorAll('.pes-metode-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.pes-diskon-preset').forEach(b => b.classList.remove('active'));
        unlockCustomerFields();
        renderCart();
        renderMenuGrid();
        // Transaksi tadi sudah lunas/batal — segarkan antrean self-order.
        loadPesananMasuk();
    }

    // ==================================================================
    // REDEEM POIN (LoyalSeed) — POST /api/transaksi/{id}/redeem-poin
    // Aturan backend: butuh transaksi pending + customer, dan hanya boleh
    // SATU redemption per transaksi.
    // ==================================================================
    const redeemBackdrop = document.getElementById('redeemBackdrop');

    function catatanRedeem(pesan, isError) {
        const el = document.getElementById('redeemNote');
        el.textContent = pesan || '';
        el.className = 'pes-redeem-note' + (pesan ? ' show' : '') + (isError ? ' error' : '');
    }

    function bukaRedeem() {
        if (!selectedCustomer) return;

        document.getElementById('redeemNama').textContent = selectedCustomer.nama;
        document.getElementById('redeemPoin').textContent = (selectedCustomer.poin ?? 0) + ' poin tersedia';
        catatanRedeem('');

        segarkanOpsiRedeem();
        redeemBackdrop.classList.add('open');
    }

    function tutupRedeem() {
        redeemBackdrop.classList.remove('open');
    }

    /**
     * Nonaktifkan opsi yang jelas-jelas tidak memenuhi syarat, supaya kasir
     * tidak menabrak error dari server: poin kurang atau minimal belanja.
     */
    function segarkanOpsiRedeem() {
        const poin = selectedCustomer?.poin ?? 0;
        const subtotal = currentTransaksi ? currentTransaksi.subtotal : 0;
        const sudahRedeem = currentTransaksi && currentTransaksi.kode_redeem;

        if (!currentTransaksi) {
            catatanRedeem('Tambahkan minimal satu item dulu sebelum redeem poin.', false);
        } else if (sudahRedeem) {
            catatanRedeem('Transaksi ini sudah memakai redeem "' + currentTransaksi.kode_redeem + '". Hanya satu redemption per transaksi.', true);
        }

        document.querySelectorAll('.pes-redeem-item').forEach(function (btn) {
            const butuhPoin = parseInt(btn.dataset.poin, 10);
            const minBelanja = parseInt(btn.dataset.min, 10) || 0;
            btn.disabled = !currentTransaksi
                || !!sudahRedeem
                || poin < butuhPoin
                || subtotal < minBelanja;
        });
    }

    async function kirimRedeem(kode) {
        if (!currentTransaksi) return catatanRedeem('Tambahkan item dulu sebelum redeem poin.', true);

        try {
            const res = await fetch(`${API_BASE}/transaksi/${currentTransaksi.id}/redeem-poin`, fetchOptions({
                method: 'POST',
                body: JSON.stringify({ kode_redeem: kode }),
            }));

            const json = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(json.message || 'Redeem poin gagal.');

            currentTransaksi = json.data;

            // Poin pelanggan berkurang sesuai biaya katalog.
            const biaya = parseInt(document.querySelector(`.pes-redeem-item[data-kode="${kode}"]`)?.dataset.poin, 10) || 0;
            if (selectedCustomer) {
                selectedCustomer.poin = Math.max(0, (selectedCustomer.poin ?? 0) - biaya);
                tampilkanPelangganKetemu(selectedCustomer);
            }

            tutupRedeem();
            renderCart();
            renderMenuGrid();
            showSuccess('Redeem berhasil diterapkan ke pesanan.');
        } catch (err) {
            catatanRedeem(err.message, true);
            segarkanOpsiRedeem();
        }
    }

    document.getElementById('custLoyaltyBtn')?.addEventListener('click', bukaRedeem);
    document.getElementById('redeemClose')?.addEventListener('click', tutupRedeem);
    redeemBackdrop?.addEventListener('click', function (e) {
        if (e.target === redeemBackdrop) tutupRedeem();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') tutupRedeem();
    });

    document.querySelectorAll('.pes-redeem-item').forEach(function (btn) {
        btn.addEventListener('click', () => kirimRedeem(btn.dataset.kode));
    });

    // ==================================================================
    // PESANAN MASUK dari SoyaScan (self-order, status pending)
    // ==================================================================
    let pesananMasuk = []; // cache TransaksiResource yang sedang ditampilkan

    // Self-order ditandai di level item (detail_transaksi.sumber). Kode
    // pesanan '#A...' dipakai sebagai cadangan kalau item belum termuat.
    function isSelfOrder(trx) {
        if (Array.isArray(trx.items) && trx.items.length) {
            return trx.items.some(i => i.sumber === 'self_order');
        }
        return typeof trx.kode_pesanan === 'string' && trx.kode_pesanan.startsWith('#A');
    }

    function jamDari(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    }

    async function loadPesananMasuk() {
        try {
            const res = await fetch(`${API_BASE}/transaksi?` + new URLSearchParams({
                status: 'pending', per_page: '50',
            }), fetchOptions());
            if (!res.ok) return; // diam-diam saja, jangan ganggu kerja kasir
            const json = await res.json();
            pesananMasuk = (json.data || []).filter(isSelfOrder);
            renderPesananMasuk();
        } catch (err) {
            // offline sesaat / token kedaluwarsa — biarkan daftar lama tampil
        }
    }

    function renderPesananMasuk() {
        const wrap = document.getElementById('pesMasukWrap');
        const row = document.getElementById('pesMasukRow');
        if (!wrap || !row) return;

        // Sembunyikan blok kalau tidak ada antrean, biar tidak makan tempat.
        if (pesananMasuk.length === 0) {
            wrap.style.display = 'none';
            return;
        }
        wrap.style.display = 'block';
        document.getElementById('pesMasukCount').textContent = pesananMasuk.length;

        row.innerHTML = pesananMasuk.map(function (trx) {
            const aktif = currentTransaksi && currentTransaksi.id === trx.id;
            const meja = (trx.items || []).map(i => i.nomor_meja).find(Boolean);
            const ringkas = (trx.items || [])
                .map(i => `${i.qty}× ${i.nama}${i.ukuran ? ' (' + i.ukuran + ')' : ''}`)
                .join(', ');
            const bayar = trx.metode_bayar
                ? (trx.metode_bayar === 'qris' ? 'QRIS' : 'Tunai')
                : 'Belum pilih';

            return `<div class="pm-card ${aktif ? 'active' : ''}">
                <div class="pm-top">
                    <span class="pm-kode">${trx.kode_pesanan || '—'}</span>
                    ${meja ? `<span class="pm-meja">Meja ${meja}</span>` : ''}
                </div>
                <div class="pm-nama">${trx.customer ? trx.customer.nama : 'Tanpa nama'}</div>
                <div class="pm-items">${ringkas || '-'}</div>
                <div class="pm-bot">
                    <div>
                        <div class="pm-total">${rupiah(trx.total)}</div>
                        <div class="pm-bayar">${bayar} &middot; <span class="pm-waktu">${jamDari(trx.created_at)}</span></div>
                    </div>
                    <button type="button" class="pm-proses" data-trx-id="${trx.id}" ${aktif ? 'disabled' : ''}>
                        ${aktif ? 'Diproses' : 'Proses'}
                    </button>
                </div>
            </div>`;
        }).join('');

        row.querySelectorAll('.pm-proses').forEach(function (btn) {
            btn.addEventListener('click', () => prosesPesananMasuk(parseInt(btn.dataset.trxId, 10)));
        });
    }

    /**
     * Muat pesanan self-order ke panel kanan supaya kasir bisa lanjut pakai
     * alur yang sudah ada (diskon, redeem poin, Tandai Lunas).
     */
    async function prosesPesananMasuk(id) {
        const trx = pesananMasuk.find(t => t.id === id);
        if (!trx) return;

        // Jangan sampai keranjang yang sedang disusun kasir hilang diam-diam.
        if (currentTransaksi && currentTransaksi.id !== id && currentTransaksi.items.length > 0) {
            if (!confirm('Ada pesanan lain yang sedang disusun. Tinggalkan dan proses pesanan ini?')) return;
        }

        currentTransaksi = trx;
        metodeBayar = trx.metode_bayar || null;
        document.querySelectorAll('.pes-metode-btn').forEach(function (b) {
            b.classList.toggle('active', metodeBayar !== null && b.dataset.metode === metodeBayar);
        });

        // Nomor meja diwarisi supaya item tambahan dari kasir ikut nomor sama.
        const meja = (trx.items || []).map(i => i.nomor_meja).find(Boolean);
        if (meja) document.getElementById('nomorMeja').value = meja;

        // Tampilkan pelanggan self-order (beserta poin, dipakai untuk redeem).
        if (trx.customer) {
            selectedCustomer = { nama: trx.customer.nama, no_wa: trx.customer.no_wa, poin: 0 };
            try {
                const res = await fetch(`${API_BASE}/loyalty/${encodeURIComponent(trx.customer.no_wa)}`, {
                    headers: { 'Accept': 'application/json' },
                });
                if (res.ok) {
                    const l = await res.json();
                    selectedCustomer.poin = l.poin ?? 0;
                }
            } catch (err) {
                // poin gagal diambil — tetap tampilkan nama & no WA
            }
            tampilkanPelangganKetemu(selectedCustomer);
        }
        lockCustomerFields();

        renderCart();
        renderMenuGrid();
        renderPesananMasuk();
        showSuccess(`Pesanan ${trx.kode_pesanan} dimuat. Lanjutkan pembayaran di panel kanan.`);
    }

    // ==================================================================
    // INIT
    // ==================================================================
    document.getElementById('pesSearch').addEventListener('input', debounce(renderMenuGrid, 250));

    function debounce(fn, delay) {
        let timer;
        return function (...args) {
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, args), delay);
        };
    }

    (async function init() {
        try {
            await Promise.all([loadKategori(), loadMenu()]);
            renderCart();
        } catch (err) {
            showError('Gagal memuat data awal. Coba refresh halaman.');
        }

        // Antrean self-order: muat sekali, lalu polling ringan tiap 10 detik
        // supaya pesanan baru dari SoyaScan muncul tanpa reload halaman.
        loadPesananMasuk();
        setInterval(loadPesananMasuk, 10000);
    })();
})();