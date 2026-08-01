document.querySelectorAll('.custom-select').forEach(function(select){
    const trigger = select.querySelector('.custom-select-trigger');
    const label   = select.querySelector('.selected-label');
    const options = select.querySelectorAll('.custom-options li');
    const hidden  = select.querySelector('input[type="hidden"]');

    trigger.addEventListener('click', function(e){
        e.stopPropagation();
        document.querySelectorAll('.custom-select.open').forEach(function(open){
            if(open !== select) open.classList.remove('open');
        });
        select.classList.toggle('open');
    });

    options.forEach(function(option){
        option.addEventListener('click', function(){
            options.forEach(o => o.classList.remove('selected'));
            option.classList.add('selected');

            label.textContent = option.textContent;
            hidden.value = option.dataset.value;

            select.classList.remove('open');
        });
    });
});

document.addEventListener('click', function(){
    document.querySelectorAll('.custom-select.open').forEach(function(open){
        open.classList.remove('open');
    });
});

// =========================
// Pilih kartu & filter sesuai role login (pola sama kayak data-role di sidebar)
// =========================
document.addEventListener('DOMContentLoaded', function () {
    try {
        const rawUser = localStorage.getItem('auth_user');
        const role = rawUser ? JSON.parse(rawUser).role : null;

        if (role === 'kasir') {
            document.querySelectorAll('.transaction-page [data-role="manager"]').forEach(function (el) {
                el.style.display = 'none';
            });
            document.querySelectorAll('.transaction-page [data-role="kasir"]').forEach(function (el) {
                el.style.display = '';
            });

            muatStatistikKasir();
        } else {
            muatStatistikManager();
        }
    } catch (e) {
    }
});

async function muatStatistikManager() {
    const token = localStorage.getItem('auth_token');
    if (!token) return;
    const headers = { 'Accept': 'application/json', 'Authorization': 'Bearer ' + token };
    const mulai = document.getElementById('trxTanggalMulai')?.value || '';
    const selesai = document.getElementById('trxTanggalSelesai')?.value || '';
    if (mulai && selesai && mulai > selesai) return;

    const qs = new URLSearchParams();
    if (mulai) qs.set('start', mulai);
    if (selesai) qs.set('end', selesai);

    try {
        const [ringkasanRes, rfmRes] = await Promise.all([
            fetch('/api/dashboard/ringkasan' + (qs.toString() ? '?' + qs : ''), { headers }),
            fetch('/api/dashboard/rfm', { headers }),
        ]);

        let revenue = 0, jumlah = 0;
        if (ringkasanRes.ok) {
            const d = (await ringkasanRes.json()).data || {};
            revenue = Number(d.total_revenue || 0);
            jumlah = Number(d.total_transaksi || 0);
        }

        const rata = jumlah > 0 ? Math.round(revenue / jumlah) : 0;
        document.getElementById('statTotalRevenue').textContent = 'Rp ' + revenue.toLocaleString('id-ID');
        document.getElementById('statJumlahTransaksi').textContent = jumlah.toLocaleString('id-ID');
        document.getElementById('statRataNilai').textContent = 'Rp ' + rata.toLocaleString('id-ID');

        if (rfmRes.ok) {
            const rows = (await rfmRes.json()).data || [];
            const aktif = rows.filter(r => Number(r.frequency) >= 10).length;
            document.getElementById('statPelangganAktif').textContent = aktif.toLocaleString('id-ID');
        }
    } catch (e) {
        console.error('Gagal memuat statistik manager', e);
    }
}

async function muatStatistikKasir() {
    const token = localStorage.getItem('auth_token');
    const rawUser = localStorage.getItem('auth_user');
    const user = rawUser ? JSON.parse(rawUser) : null;

    if (!token || !user) return;

    const hariIni = new Date().toISOString().slice(0, 10); // YYYY-MM-DD
    const headers = {
        'Accept': 'application/json',
        'Authorization': 'Bearer ' + token,
    };

    const qs = (params) => new URLSearchParams({
        tanggal: hariIni,
        user_id: user.id,
        ...params,
    }).toString();

    try {
        const [totalRes, prosesRes, selesaiRes] = await Promise.all([
            fetch('/api/transaksi?' + qs({ per_page: 1 }), { headers }),
            fetch('/api/transaksi?' + qs({ status: 'pending', per_page: 1 }), { headers }),
            fetch('/api/transaksi?' + qs({ status: 'lunas', per_page: 200 }), { headers }),
        ]);

        if (!totalRes.ok || !prosesRes.ok || !selesaiRes.ok) return;

        const [totalJson, prosesJson, selesaiJson] = await Promise.all([
            totalRes.json(),
            prosesRes.json(),
            selesaiRes.json(),
        ]);

        const totalTransaksi = totalJson.meta?.total ?? 0;
        const totalProses = prosesJson.meta?.total ?? 0;
        const totalSelesai = selesaiJson.meta?.total ?? 0;
        const omzet = (selesaiJson.data ?? []).reduce(function (sum, trx) {
            return sum + (trx.total || 0);
        }, 0);

        document.getElementById('kasirTotalTransaksi').textContent = totalTransaksi.toLocaleString('id-ID');
        document.getElementById('kasirOmzet').textContent = 'Rp ' + omzet.toLocaleString('id-ID');
        document.getElementById('kasirProses').textContent = totalProses.toLocaleString('id-ID');
        document.getElementById('kasirSelesai').textContent = totalSelesai.toLocaleString('id-ID');
    } catch (e) {
        console.error('Gagal memuat statistik kasir', e);
    }
}

(function () {
    const tbody = document.getElementById('trxTableBody');
    if (!tbody) return;

    let halaman = 1;
    let barisHalamanIni = [];   
    let metaHalaman = null;

    const rupiah = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID');

    function jam(iso) {
        if (!iso) return '—';
        const d = new Date(iso);
        return Number.isNaN(d.getTime())
            ? '—'
            : d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    }

    function labelStatus(status) {
        if (status === 'lunas') return '<span class="status success">Selesai</span>';
        if (status === 'batal') return '<span class="status cancel">Batal</span>';
        return '<span class="status process">Proses</span>';
    }

    function nilaiFilter(nama) {
        const el = document.querySelector('.custom-select[data-name="' + nama + '"] input[type="hidden"]');
        return el ? (el.value || '') : '';
    }

    function selSumber(trx) {
        const label = trx.sumber_label
            || (trx.sumber === 'self_order' ? 'SoyaScan' : 'Kasir');
        const kelas = trx.sumber === 'self_order' ? 'trx-tag' : 'trx-tag trx-tag-kasir';

        const pembuat = trx.kasir_pembuat ? trx.kasir_pembuat.nama : null;
        const penyelesai = trx.kasir_penyelesai ? trx.kasir_penyelesai.nama : null;

        let orang;
        if (pembuat && penyelesai && pembuat !== penyelesai) {
            orang = 'Dibuat ' + pembuat + ' · Dibayar ' + penyelesai;
        } else {
            orang = penyelesai || pembuat || (trx.kasir ? trx.kasir.nama : '');
        }

        return '<span class="' + kelas + '">' + label + '</span>'
            + (orang ? '<div class="trx-kasir">' + orang + '</div>' : '');
    }

    function render() {
        const rows = barisHalamanIni;

        if (rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="10" class="trx-state">Tidak ada transaksi yang cocok.</td></tr>';
        } else {
            tbody.innerHTML = rows.map(function (trx) {
                const metode = trx.metode_bayar
                    ? (trx.metode_bayar.toLowerCase() === 'cash' ? 'Tunai' : trx.metode_bayar.toUpperCase())
                    : '—';

                return '<tr data-id="' + trx.id + '">'
                    + '<td>' + trx.id + '</td>'
                    + '<td>' + (trx.customer ? trx.customer.nama : 'Umum') + '</td>'
                    + '<td>' + (trx.kode_pesanan || '—') + '</td>'
                    + '<td>' + selSumber(trx) + '</td>'
                    + '<td>' + rupiah(trx.total) + '</td>'
                    + '<td>' + metode + '</td>'
                    + '<td>' + labelStatus(trx.status) + '</td>'
                    + '<td>' + (trx.point_earned ?? 0) + '</td>'
                    + '<td>' + jam(trx.created_at) + '</td>'
                    + '<td><button type="button" class="detail-btn" data-id="' + trx.id + '">'
                        + '<i class="fa-solid fa-arrow-up-right-from-square"></i></button></td>'
                    + '</tr>';
            }).join('');
        }

        renderInfo();
        renderPagination();
    }

    function renderInfo() {
        const info = document.getElementById('trxInfo');
        if (!info || !metaHalaman) return;

        const jumlah = metaHalaman.jumlah_transaksi ?? metaHalaman.total ?? 0;
        const bagian = [jumlah.toLocaleString('id-ID') + ' transaksi'];

        if (metaHalaman.total_omzet !== undefined) bagian.push('Omzet ' + rupiah(metaHalaman.total_omzet));
        if (metaHalaman.total_qty !== undefined) bagian.push(Number(metaHalaman.total_qty).toLocaleString('id-ID') + ' item');
        bagian.push('Halaman ' + (metaHalaman.current_page ?? 1) + ' dari ' + (metaHalaman.last_page ?? 1));

        info.textContent = bagian.join(' · ');
    }

    function renderPagination() {
        const wrap = document.getElementById('trxPagination');
        if (!wrap || !metaHalaman) return;

        const kini = metaHalaman.current_page ?? 1;
        const akhir = metaHalaman.last_page ?? 1;
        if (akhir <= 1) { wrap.innerHTML = ''; return; }

        // Tampilkan maksimal 5 nomor di sekitar halaman aktif.
        let mulai = Math.max(1, kini - 2);
        const selesai = Math.min(akhir, mulai + 4);
        mulai = Math.max(1, selesai - 4);

        let html = '<button data-hal="' + (kini - 1) + '"' + (kini <= 1 ? ' disabled' : '') + '>'
            + '<i class="fa-solid fa-chevron-left"></i></button>';
        for (let i = mulai; i <= selesai; i++) {
            html += '<button data-hal="' + i + '"' + (i === kini ? ' class="active"' : '') + '>' + i + '</button>';
        }
        html += '<button data-hal="' + (kini + 1) + '"' + (kini >= akhir ? ' disabled' : '') + '>'
            + '<i class="fa-solid fa-chevron-right"></i></button>';

        wrap.innerHTML = html;
        wrap.querySelectorAll('button[data-hal]').forEach(function (b) {
            b.addEventListener('click', function () {
                const tujuan = parseInt(b.dataset.hal, 10);
                if (!tujuan || tujuan < 1 || tujuan > akhir || tujuan === kini) return;
                halaman = tujuan;
                muatTabel();
            });
        });
    }

    async function muatTabel() {
        const token = localStorage.getItem('auth_token');
        if (!token) {
            tbody.innerHTML = '<tr><td colspan="10" class="trx-state">Sesi berakhir, silakan login ulang.</td></tr>';
            return;
        }

        const mulai = document.getElementById('trxTanggalMulai')?.value || '';
        const selesai = document.getElementById('trxTanggalSelesai')?.value || '';
        if (mulai && selesai && mulai > selesai) {
            tbody.innerHTML = '<tr><td colspan="10" class="trx-state">'
                + 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai.</td></tr>';
            return;
        }

        tbody.innerHTML = '<tr><td colspan="10" class="trx-state">Memuat transaksi...</td></tr>';

        const params = { page: String(halaman), per_page: '15' };
        if (mulai) params.tanggal_mulai = mulai;
        if (selesai) params.tanggal_selesai = selesai;

        const cari = (document.getElementById('trxSearch')?.value || '').trim();
        if (cari) params.cari = cari;

        const sumber = nilaiFilter('sumber');
        if (sumber) params.sumber = sumber;

        const metode = nilaiFilter('metode');
        if (metode) params.metode_bayar = metode;

        const status = nilaiFilter('status');
        if (status) params.status = status;

        const urut = nilaiFilter('urut');
        if (urut) params.urut = urut;

        const redeem = nilaiFilter('redeem');
        if (redeem) params.ada_redeem = redeem;

        const kasirId = nilaiFilter('kasir');
        if (kasirId) params.user_id = kasirId;

        try {
            const res = await fetch('/api/transaksi?' + new URLSearchParams(params), {
                headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + token },
            });
            const json = await res.json().catch(() => ({}));
            // Pesan dari backend memang ditulis untuk dibaca orang.
            if (!res.ok) throw new Error(json.message || 'Gagal memuat transaksi (' + res.status + ').');

            barisHalamanIni = json.data || [];
            metaHalaman = json.meta || null;
            render();
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="10" class="trx-state">' + e.message + '</td></tr>';
        }
    }

    function perbaruiCatatanKartu() {
        const catatan = document.getElementById('statsNote');
        if (!catatan) return;

        const adaFilterLain = ['sumber', 'metode', 'status', 'redeem', 'kasir']
            .some(nama => nilaiFilter(nama))
            || (document.getElementById('trxSearch')?.value || '').trim() !== '';

        catatan.style.display = adaFilterLain ? '' : 'none';
    }

    function terapkanFilter() {
        halaman = 1;
        muatTabel();
        muatStatistikManager(); 
        perbaruiCatatanKartu();
    }

    let timerCari;
    document.getElementById('trxSearch')?.addEventListener('input', function () {
        clearTimeout(timerCari);
        timerCari = setTimeout(terapkanFilter, 400);
    });

    document.querySelectorAll('.custom-select .custom-options').forEach(function (ul) {
        ul.addEventListener('click', function () { setTimeout(terapkanFilter, 0); });
    });

    async function muatDaftarKasir() {
        const ul = document.getElementById('trxKasirOptions');
        const token = localStorage.getItem('auth_token');
        if (!ul || !token) return;

        try {
            const res = await fetch('/api/laporan/kasir', {
                headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + token },
            });
            if (!res.ok) return; // kasir dapat 403, dropdown-nya memang manager-only

            ((await res.json()).data || []).forEach(function (r) {
                const li = document.createElement('li');
                li.dataset.value = r.user_id;
                li.textContent = r.nama;
                ul.appendChild(li);
            });

            pasangOpsiKasir();
        } catch (e) {
        }
    }

    function pasangOpsiKasir() {
        const select = document.querySelector('.custom-select[data-name="kasir"]');
        if (!select) return;

        const label = select.querySelector('.selected-label');
        const hidden = select.querySelector('input[type="hidden"]');

        select.querySelectorAll('.custom-options li').forEach(function (opt) {
            if (opt.dataset.terpasang) return;
            opt.dataset.terpasang = '1';

            opt.addEventListener('click', function () {
                select.querySelectorAll('.custom-options li').forEach(o => o.classList.remove('selected'));
                opt.classList.add('selected');
                label.textContent = opt.textContent;
                hidden.value = opt.dataset.value;
                select.classList.remove('open');
                terapkanFilter();
            });
        });
    }

    document.getElementById('trxTanggalMulai')?.addEventListener('change', terapkanFilter);
    document.getElementById('trxTanggalSelesai')?.addEventListener('change', terapkanFilter);

    document.getElementById('trxTanggalReset')?.addEventListener('click', function () {
        document.getElementById('trxTanggalMulai').value = '';
        document.getElementById('trxTanggalSelesai').value = '';
        terapkanFilter();
    });
    window.muatTabelTransaksi = muatTabel;

    muatDaftarKasir();

    muatTabel();
})();

// =========================
// Modal Detail Transaksi, dipakai kasir & manager (GET /api/transaksi/{id})
// =========================
(function () {
    const backdrop = document.getElementById('detailModalBackdrop');
    const closeBtn = document.getElementById('detailModalClose');
    const loadingEl = document.getElementById('detailModalLoading');
    const errorEl = document.getElementById('detailModalError');
    const bodyEl = document.getElementById('detailModalBody');

    if (!backdrop) return;

    function rupiah(n) {
        return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
    }

    function formatWaktu(iso) {
        if (!iso) return '—';
        const d = new Date(iso);
        if (Number.isNaN(d.getTime())) return '—';
        return d.toLocaleString('id-ID', {
            day: '2-digit', month: 'long', year: 'numeric',
            hour: '2-digit', minute: '2-digit',
        });
    }

    function openModal() {
        backdrop.classList.add('open');
    }

    function closeModal() {
        backdrop.classList.remove('open');
    }

    function tampilkanDetail(trx) {
        document.getElementById('detailKodePesanan').textContent = trx.kode_pesanan || '—';

        const statusEl = document.getElementById('detailStatus');
        statusEl.textContent = trx.status === 'lunas' ? 'Selesai' : trx.status === 'batal' ? 'Batal' : 'Proses';
        statusEl.className = 'status ' + (trx.status === 'lunas' ? 'success' : trx.status === 'batal' ? 'cancel' : 'process');

        document.getElementById('detailCreatedAt').textContent = formatWaktu(trx.created_at);
        document.getElementById('detailWaktuLunas').textContent = formatWaktu(trx.waktu_lunas);
        document.getElementById('detailPelanggan').textContent = trx.customer
            ? trx.customer.nama + (trx.customer.no_wa ? ' · ' + trx.customer.no_wa : '')
            : 'Umum (tanpa data pelanggan)';
        document.getElementById('detailKasir').textContent = trx.kasir ? trx.kasir.nama : '—';
        document.getElementById('detailMetodeBayar').textContent = trx.metode_bayar
            ? trx.metode_bayar.toUpperCase()
            : 'Belum dibayar';

        const itemsBody = document.getElementById('detailItemsBody');
        const items = trx.items || [];
        itemsBody.innerHTML = items.length ? items.map(function (item) {
            const namaLengkap = item.rasa ? item.nama + ' (' + item.rasa + ')' : item.nama;
            return '<tr>'
                + '<td>' + (namaLengkap || '—') + '</td>'
                + '<td>' + (item.ukuran || '—') + '</td>'
                + '<td>' + (item.qty ?? 0) + '</td>'
                + '<td>' + rupiah(item.harga_satuan) + '</td>'
                + '<td>' + rupiah(item.subtotal) + '</td>'
                + '<td>' + (item.catatan || '—') + '</td>'
                + '</tr>';
        }).join('') : '<tr><td colspan="6">Belum ada item.</td></tr>';

        document.getElementById('detailSubtotal').textContent = rupiah(trx.subtotal);
        document.getElementById('detailDiskon').textContent = rupiah(trx.diskon_nilai) + (trx.diskon_persen ? ' (' + trx.diskon_persen + '%)' : '');
        document.getElementById('detailTotal').textContent = rupiah(trx.total);
        document.getElementById('detailPoin').textContent = trx.point_earned ?? 0;

        siapkanAksi(trx);
    }

    let trxAktif = null;      
    let metodeDipilih = null; 

    const aksiWrap = document.getElementById('detailAksi');
    const aksiNote = document.getElementById('detailAksiNote');
    const lunasBtn = document.getElementById('aksiLunasBtn');
    const batalBtn = document.getElementById('aksiBatalBtn');

    function siapkanAksi(trx) {
        trxAktif = trx;
        metodeDipilih = trx.metode_bayar || null;

        siapkanKoreksi(trx);

        if (!aksiWrap) return;

        // Sudah lunas/batal = tidak ada yang bisa diubah lagi.
        if (trx.status !== 'pending') {
            aksiWrap.style.display = 'none';
            return;
        }

        aksiWrap.style.display = 'block';
        aksiNote.textContent = '';

        document.querySelectorAll('.aksi-metode-btn').forEach(function (b) {
            b.classList.toggle('active', metodeDipilih !== null && b.dataset.metode === metodeDipilih);
        });
        lunasBtn.disabled = metodeDipilih === null;
        lunasBtn.textContent = 'Tandai Lunas';
        batalBtn.textContent = 'Batalkan';
    }

    document.querySelectorAll('.aksi-metode-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            metodeDipilih = btn.dataset.metode;
            document.querySelectorAll('.aksi-metode-btn').forEach(b => b.classList.toggle('active', b === btn));
            lunasBtn.disabled = false;
            aksiNote.textContent = '';
        });
    });

    async function kirimAksi(url, body, tombol, labelProses) {
        const token = localStorage.getItem('auth_token');
        const labelAwal = tombol.textContent;
        tombol.disabled = true;
        tombol.textContent = labelProses;
        aksiNote.textContent = '';

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    ...(token ? { 'Authorization': 'Bearer ' + token } : {}),
                },
                body: body ? JSON.stringify(body) : null,
            });

            const json = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(json.message || 'Aksi gagal diproses.');

            closeModal();
            if (typeof window.muatTabelTransaksi === 'function') window.muatTabelTransaksi();
        } catch (e) {
            aksiNote.textContent = e.message;
            tombol.disabled = false;
            tombol.textContent = labelAwal;
        }
    }

    lunasBtn?.addEventListener('click', function () {
        if (!trxAktif || !metodeDipilih) {
            aksiNote.textContent = 'Pilih metode pembayaran dulu.';
            return;
        }
        kirimAksi(
            '/api/transaksi/' + trxAktif.id + '/bayar',
            { metode_bayar: metodeDipilih },
            lunasBtn,
            'Memproses...',
        );
    });

    batalBtn?.addEventListener('click', function () {
        if (!trxAktif) return;
        if (!confirm('Batalkan pesanan ' + (trxAktif.kode_pesanan || '') + '? Tindakan ini tidak bisa dibatalkan.')) return;
        kirimAksi('/api/transaksi/' + trxAktif.id + '/batal', null, batalBtn, 'Membatalkan...');
    });

    // ---- Pembatalan / koreksi pesanan yang sudah lunas ----
    const koreksiWrap = document.getElementById('detailKoreksi');
    const koreksiHasil = document.getElementById('koreksiHasil');
    const koreksiNote = document.getElementById('koreksiNote');
    const koreksiKirimBtn = document.getElementById('koreksiKirimBtn');

    function siapkanKoreksi(trx) {
        if (!koreksiWrap) return;

        koreksiHasil.style.display = 'none';
        koreksiNote.textContent = '';
        document.getElementById('koreksiAlasan').value = '';
        if (trx.status !== 'lunas') {
            koreksiWrap.style.display = 'none';
            return;
        }

        koreksiWrap.style.display = 'block';

        const items = (trx.items || []).filter(i => !i.is_reward);
        document.getElementById('koreksiItems').innerHTML = items.map(function (i) {
            const label = i.ukuran ? i.nama + ' (' + i.ukuran + ')' : i.nama;
            const takaran = [i.level_sugar_label, i.level_ice_label].filter(Boolean).join(' · ');

            return '<div class="koreksi-item" data-max="' + i.qty + '">'
                + '<label class="ki-pilih">'
                    + '<input type="checkbox" data-item-id="' + i.id + '">'
                    + '<span class="ki-teks">'
                        + '<span class="ki-nama">' + label + '</span>'
                        + (takaran ? '<span class="ki-takaran">' + takaran + '</span>' : '')
                    + '</span>'
                + '</label>'
                + '<div class="ki-stepper">'
                    + '<button type="button" class="ki-minus" disabled>&minus;</button>'
                    + '<span class="ki-qty" data-qty="1">1</span>'
                    + '<button type="button" class="ki-plus" disabled>+</button>'
                + '</div>'
                + '<span class="ki-dari">dari ' + i.qty + '</span>'
                + '</div>';
        }).join('') || '<p class="koreksi-hint">Tidak ada item yang bisa dibatalkan.</p>';

        document.getElementById('koreksiItems').querySelectorAll('.koreksi-item').forEach(function (row) {
            const cb = row.querySelector('input[type="checkbox"]');
            const qtyEl = row.querySelector('.ki-qty');
            const minus = row.querySelector('.ki-minus');
            const plus = row.querySelector('.ki-plus');
            const maks = parseInt(row.dataset.max, 10);

            function segarkan() {
                const aktif = cb.checked;
                const qty = parseInt(qtyEl.dataset.qty, 10);
                row.classList.toggle('aktif', aktif);
                minus.disabled = !aktif || qty <= 1;
                plus.disabled = !aktif || qty >= maks;
            }

            function ubah(delta) {
                const qty = Math.min(maks, Math.max(1, parseInt(qtyEl.dataset.qty, 10) + delta));
                qtyEl.dataset.qty = qty;
                qtyEl.textContent = qty;
                segarkan();
            }

            cb.addEventListener('change', segarkan);
            minus.addEventListener('click', () => ubah(-1));
            plus.addEventListener('click', () => ubah(+1));
            segarkan();
        });
    }

    koreksiKirimBtn?.addEventListener('click', async function () {
        if (!trxAktif) return;

        const alasan = document.getElementById('koreksiAlasan').value.trim();
        if (alasan.length < 3) {
            koreksiNote.textContent = 'Alasan wajib diisi, minimal 3 karakter.';
            return;
        }

        const items = [];
        document.getElementById('koreksiItems')
            .querySelectorAll('input[type="checkbox"]:checked')
            .forEach(function (cb) {
                const row = cb.closest('.koreksi-item');
                const qty = parseInt(row.querySelector('.ki-qty').dataset.qty, 10);
                if (qty > 0) items.push({ detail_transaksi_id: parseInt(cb.dataset.itemId, 10), qty });
            });

        const penuh = items.length === 0;
        const konfirmasi = penuh
            ? 'Batalkan SELURUH pesanan ' + (trxAktif.kode_pesanan || '') + '?'
            : 'Batalkan ' + items.length + ' item dari pesanan ' + (trxAktif.kode_pesanan || '') + '?';
        if (!confirm(konfirmasi)) return;

        koreksiKirimBtn.disabled = true;
        const labelAwal = koreksiKirimBtn.textContent;
        koreksiKirimBtn.textContent = 'Memproses...';
        koreksiNote.textContent = '';

        try {
            const token = localStorage.getItem('auth_token');
            const res = await fetch('/api/transaksi/' + trxAktif.id + '/pembatalan', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    ...(token ? { 'Authorization': 'Bearer ' + token } : {}),
                },
                body: JSON.stringify(penuh ? { alasan } : { alasan, items }),
            });

            const json = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(json.message || 'Pembatalan gagal diproses.');

            const d = json.data || {};
            const saldo = json.saldo_poin_pelanggan;

            koreksiWrap.style.display = 'none';
            koreksiHasil.style.display = 'block';
            koreksiHasil.innerHTML =
                '<div class="kh-judul">Pembatalan berhasil</div>'
                + '<div class="kh-baris"><span>Nilai dibatalkan</span><strong>' + rupiah(d.nilai_dibatalkan) + '</strong></div>'
                + '<div class="kh-baris"><span>Poin ditarik</span><strong>' + (d.poin_ditarik ?? 0) + '</strong></div>'
                + '<div class="kh-baris"><span>Poin dikembalikan</span><strong>' + (d.poin_dikembalikan ?? 0) + '</strong></div>'
                + '<div class="kh-baris"><span>Status transaksi</span><strong>' + (json.status_transaksi || '—') + '</strong></div>'
                + '<div class="kh-saldo">Sisa poin pelanggan: <strong>'
                    + (saldo === null || saldo === undefined ? 'tanpa akun pelanggan' : saldo + ' poin')
                + '</strong></div>';

            if (typeof window.muatTabelTransaksi === 'function') window.muatTabelTransaksi();
        } catch (e) {
            koreksiNote.textContent = e.message;
        } finally {
            koreksiKirimBtn.disabled = false;
            koreksiKirimBtn.textContent = labelAwal;
        }
    });

    async function muatDetail(id) {
        openModal();
        loadingEl.style.display = 'block';
        errorEl.style.display = 'none';
        bodyEl.style.display = 'none';

        const token = localStorage.getItem('auth_token');

        try {
            const res = await fetch('/api/transaksi/' + id, {
                headers: {
                    'Accept': 'application/json',
                    ...(token ? { 'Authorization': 'Bearer ' + token } : {}),
                },
            });

            if (!res.ok) {
                throw new Error(res.status === 404
                    ? 'Transaksi tidak ditemukan.'
                    : 'Gagal memuat detail transaksi (' + res.status + ').');
            }

            const json = await res.json();
            tampilkanDetail(json.data ?? json);

            loadingEl.style.display = 'none';
            bodyEl.style.display = 'block';
        } catch (e) {
            loadingEl.style.display = 'none';
            errorEl.textContent = e.message || 'Terjadi kesalahan saat memuat detail transaksi.';
            errorEl.style.display = 'block';
        }
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.detail-btn');
        if (!btn) return;
        const id = btn.dataset.id || btn.closest('tr')?.dataset.id;
        if (!id) return;
        muatDetail(id);
    });

    closeBtn.addEventListener('click', closeModal);
    backdrop.addEventListener('click', function (e) {
        if (e.target === backdrop) closeModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeModal();
    });
})();
