@extends('layouts.app')

@section('title', 'Transaksi')

@section('content')

<div class="transaction-page">

    <div class="page-header">

        <div>
            <h1>Transaksi</h1>
            <p>Riwayat seluruh transaksi penjualan Gres'Soy</p>
        </div>

        <button class="download-btn" data-role="manager">
            <i class="fa-solid fa-download"></i>
            Unduh
        </button>

    </div>

    {{-- =========================
            CARD STATISTIK
    ========================== --}}

    <div class="stats-grid" data-role="manager">

        <div class="stat-card">

            <h5>Total Revenue</h5>

            <h2 id="statTotalRevenue">Rp 0</h2>

            <span>Periode data laporan</span>

        </div>

        <div class="stat-card">

            <h5>Jumlah Transaksi</h5>

            <h2 id="statJumlahTransaksi">0</h2>

            <span>Total keseluruhan</span>

        </div>

        <div class="stat-card">

            <h5>Rata-rata Nilai</h5>

            <h2 id="statRataNilai">Rp 0</h2>

            <span>Per transaksi</span>

        </div>

        <div class="stat-card">

            <h5>Pelanggan Aktif</h5>

            <h2 id="statPelangganAktif">0</h2>

            <span>Telah bertransaksi ≥10 kali</span>

        </div>

    </div>

    {{-- Kartu khusus kasir — hanya tampil kalau role login = kasir --}}
    <div class="stats-grid" data-role="kasir" style="display:none;">

        <div class="stat-card">

            <h5>Total Transaksi Hari ini</h5>

            <h2 id="kasirTotalTransaksi">–</h2>

            <span>Transaksi milik kamu</span>

        </div>

        <div class="stat-card">

            <h5>Omzet Hari ini</h5>

            <h2 id="kasirOmzet">Rp 0</h2>

            <span>Dari transaksi lunas</span>

        </div>

        <div class="stat-card">

            <h5>Proses</h5>

            <h2 id="kasirProses">0</h2>

            <span>Menunggu pembayaran</span>

        </div>

        <div class="stat-card">

            <h5>Selesai</h5>

            <h2 id="kasirSelesai">0</h2>

            <span>Sudah dibayar</span>

        </div>

    </div>

    {{-- =========================
            FILTER
    ========================== --}}

    <div class="filter-bar">

        <div class="search-box">

            <i class="fa-solid fa-magnifying-glass"></i>

            <input
                type="text"
                id="trxSearch"
                placeholder="Cari kode pesanan / nama pelanggan">

        </div>

        <div class="filter-right">

            {{-- Dropdown Sumber/Metode cuma buat manager, disembunyikan buat kasir --}}
            <div class="filter-dropdowns" data-role="manager">

                <div class="custom-select" data-name="sumber">
                    <button type="button" class="custom-select-trigger">
                        <span class="selected-label">Sumber</span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>
                    <ul class="custom-options">
                        <li data-value="self_order">SoyaScan</li>
                        <li data-value="kasir">Kasir</li>
                    </ul>
                    <input type="hidden" name="sumber" value="">
                </div>

                <div class="custom-select" data-name="metode">
                    <button type="button" class="custom-select-trigger">
                        <span class="selected-label">Metode</span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>
                    <ul class="custom-options">
                        <li data-value="tunai">Tunai</li>
                        <li data-value="qris">QRIS</li>
                    </ul>
                    <input type="hidden" name="metode" value="">
                </div>

            </div>

            <div class="date-picker-wrap">
                <button type="button" class="date-btn" id="dateBtn">

                    <i class="fa-regular fa-calendar"></i>

                    <span id="dateBtnLabel">Semua Tanggal</span>

                </button>

                <input type="date" id="dateInput" class="date-input-hidden">
            </div>

        </div>

    </div>

    {{-- =========================
            TABLE
    ========================== --}}

    <div class="table-card">

        <table>

            <thead>

                <tr>

                    <th>Id Transaksi</th>

                    <th>Id Pelanggan</th>

                    <th>Id Pesanan</th>

                    <th>Total</th>

                    <th>Metode</th>

                    <th>Status</th>

                    <th>Poin</th>

                    <th>Waktu</th>

                    <th>Detail</th>

                </tr>

            </thead>

            {{-- Diisi dari API oleh script di bawah (GET /api/transaksi) --}}
            <tbody id="trxTableBody">

                <tr>
                    <td colspan="9" class="trx-state">Memuat transaksi...</td>
                </tr>

            </tbody>

        </table>

        <div class="table-footer">

            <span id="trxInfo">—</span>

            <div class="pagination" id="trxPagination"></div>

        </div>

    </div>

    {{-- =========================
            MODAL DETAIL TRANSAKSI
    ========================== --}}

    <div class="detail-modal-backdrop" id="detailModalBackdrop">
        <div class="detail-modal">

            <div class="detail-modal-header">
                <h3 id="detailKodePesanan">—</h3>
                <button type="button" class="detail-modal-close" id="detailModalClose">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div id="detailModalLoading" class="detail-modal-loading">Memuat detail transaksi...</div>
            <div id="detailModalError" class="detail-modal-error"></div>

            <div id="detailModalBody" style="display:none;">

                <div class="detail-modal-meta">
                    <div>
                        <span class="detail-modal-label">Status</span>
                        <span id="detailStatus" class="status">—</span>
                    </div>
                    <div>
                        <span class="detail-modal-label">Waktu Dibuat</span>
                        <span id="detailCreatedAt">—</span>
                    </div>
                    <div>
                        <span class="detail-modal-label">Waktu Lunas</span>
                        <span id="detailWaktuLunas">—</span>
                    </div>
                </div>

                <div class="detail-modal-meta">
                    <div>
                        <span class="detail-modal-label">Pelanggan</span>
                        <span id="detailPelanggan">—</span>
                    </div>
                    <div>
                        <span class="detail-modal-label">Kasir</span>
                        <span id="detailKasir">—</span>
                    </div>
                    <div>
                        <span class="detail-modal-label">Metode Bayar</span>
                        <span id="detailMetodeBayar">—</span>
                    </div>
                </div>

                <div class="table-scroll">
                    <table class="detail-item-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Ukuran</th>
                                <th>Qty</th>
                                <th>Harga Satuan</th>
                                <th>Subtotal</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody id="detailItemsBody"></tbody>
                    </table>
                </div>

                <div class="detail-modal-summary">
                    <div><span>Subtotal</span><span id="detailSubtotal">Rp 0</span></div>
                    <div><span>Diskon</span><span id="detailDiskon">Rp 0</span></div>
                    <div class="total"><span>Total</span><span id="detailTotal">Rp 0</span></div>
                    <div><span>Poin Didapat</span><span id="detailPoin">0</span></div>
                </div>

                {{-- Aksi penyelesaian — hanya tampil saat status masih 'pending' --}}
                <div class="detail-aksi" id="detailAksi" style="display:none;">

                    <span class="detail-aksi-label">Selesaikan Pesanan</span>

                    <div class="detail-aksi-metode">
                        <button type="button" class="aksi-metode-btn" data-metode="cash">
                            <i class="fa-solid fa-money-bill-1"></i> Tunai
                        </button>
                        <button type="button" class="aksi-metode-btn" data-metode="qris">
                            <i class="fa-solid fa-qrcode"></i> QRIS
                        </button>
                    </div>

                    <div class="detail-aksi-note" id="detailAksiNote"></div>

                    <div class="detail-aksi-row">
                        <button type="button" class="aksi-batal-btn" id="aksiBatalBtn">Batalkan</button>
                        <button type="button" class="aksi-lunas-btn" id="aksiLunasBtn" disabled>Tandai Lunas</button>
                    </div>

                </div>

            </div>

        </div>
    </div>

<script>
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
// Date picker tombol tanggal
// =========================
(function () {
    const dateBtn = document.getElementById('dateBtn');
    const dateInput = document.getElementById('dateInput');
    const dateLabel = document.getElementById('dateBtnLabel');

    if (!dateBtn || !dateInput || !dateLabel) return;

    const namaBulan = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    dateBtn.addEventListener('click', function () {
        if (typeof dateInput.showPicker === 'function') {
            dateInput.showPicker();
        } else {
            dateInput.focus();
            dateInput.click();
        }
    });

    dateInput.addEventListener('change', function () {
        if (!dateInput.value) return;
        const [tahun, bulan, tanggal] = dateInput.value.split('-').map(Number);
        dateLabel.textContent = String(tanggal).padStart(2, '0') + ' ' + namaBulan[bulan - 1] + ' ' + tahun;
    });
})();

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
        // localStorage tidak terbaca / rusak — biarkan tampilan default (manager)
    }
});

// Kartu manager diisi dari data laporan (sama sumbernya dengan Dashboard):
// ringkasan (revenue/jumlah/rata-rata) + rfm (pelanggan aktif >= 10 transaksi).
async function muatStatistikManager() {
    const token = localStorage.getItem('auth_token');
    if (!token) return;

    const headers = { 'Accept': 'application/json', 'Authorization': 'Bearer ' + token };

    try {
        // ringkasan = data CSV (historis); transaksi lunas = data live sekarang.
        // 3 kartu (revenue, jumlah, rata) digabung; Pelanggan Aktif tetap dari
        // RFM (CSV) karena butuh penggabungan pelanggan unik di backend.
        const [ringkasanRes, rfmRes, liveRes] = await Promise.all([
            fetch('/api/dashboard/ringkasan', { headers }),
            fetch('/api/dashboard/rfm', { headers }),
            fetch('/api/transaksi?status=lunas&per_page=200', { headers }),
        ]);

        // ---- angka CSV ----
        let revenue = 0, jumlah = 0;
        if (ringkasanRes.ok) {
            const d = (await ringkasanRes.json()).data || {};
            revenue = Number(d.total_revenue || 0);
            jumlah = Number(d.total_transaksi || 0);
        }

        // ---- tambahkan transaksi live yang sudah lunas ----
        if (liveRes.ok) {
            const live = await liveRes.json();
            const rows = live.data || [];
            revenue += rows.reduce((s, t) => s + Number(t.total || 0), 0);
            // Pakai meta.total kalau ada (jumlah sebenarnya di server), jatuh
            // ke panjang array kalau tidak.
            jumlah += Number(live.meta?.total ?? rows.length);
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

// Ambil Total Transaksi Hari ini / Omzet Hari ini / Proses / Selesai dari data asli
// (API /api/transaksi), di-scope ke transaksi kasir yang login, hari ini saja.
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

// =========================
// Tabel Transaksi — data asli dari GET /api/transaksi
// Filter tanggal & halaman dikerjakan server; pencarian, metode, sumber,
// dan kasir disaring di sisi klien karena API belum menyediakannya.
// =========================
(function () {
    const tbody = document.getElementById('trxTableBody');
    if (!tbody) return;

    let halaman = 1;
    let barisHalamanIni = [];   // TransaksiResource halaman aktif
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

    // Dropdown memakai "tunai", sedangkan database menyimpan "cash".
    function cocokMetode(trx, pilihan) {
        if (!pilihan) return true;
        const metode = (trx.metode_bayar || '').toLowerCase();
        return pilihan === 'tunai' ? metode === 'cash' : metode === pilihan;
    }

    // Sumber transaksi: 'self_order' (SoyaScan) atau 'kasir'. Ditandai di
    // item; kode '#A...' dipakai cadangan kalau item belum termuat.
    function cocokSumber(trx, pilihan) {
        if (!pilihan) return true;
        const selfOrder = (trx.items || []).some(i => i.sumber === 'self_order')
            || (typeof trx.kode_pesanan === 'string' && trx.kode_pesanan.startsWith('#A'));
        return pilihan === 'self_order' ? selfOrder : !selfOrder;
    }

    function cocokCari(trx, kata) {
        if (!kata) return true;
        const gabungan = [trx.kode_pesanan, trx.customer?.nama, trx.customer?.no_wa]
            .filter(Boolean).join(' ').toLowerCase();
        return gabungan.includes(kata);
    }

    function barisTerlihat() {
        const kata = (document.getElementById('trxSearch')?.value || '').trim().toLowerCase();
        const metode = nilaiFilter('metode');
        const sumber = nilaiFilter('sumber');

        return barisHalamanIni.filter(function (trx) {
            return cocokCari(trx, kata)
                && cocokMetode(trx, metode)
                && cocokSumber(trx, sumber);
        });
    }

    function render() {
        const rows = barisTerlihat();

        if (rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="trx-state">Tidak ada transaksi yang cocok.</td></tr>';
        } else {
            tbody.innerHTML = rows.map(function (trx) {
                const metode = trx.metode_bayar
                    ? (trx.metode_bayar.toLowerCase() === 'cash' ? 'Tunai' : trx.metode_bayar.toUpperCase())
                    : '—';
                // Self-order ditandai supaya beda dari transaksi kasir manual.
                const selfOrder = (trx.items || []).some(i => i.sumber === 'self_order');

                return '<tr data-id="' + trx.id + '">'
                    + '<td>' + trx.id + '</td>'
                    + '<td>' + (trx.customer ? trx.customer.nama : 'Umum') + '</td>'
                    + '<td>' + (trx.kode_pesanan || '—')
                        + (selfOrder ? ' <span class="trx-tag">SoyaScan</span>' : '') + '</td>'
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

        renderInfo(rows.length);
        renderPagination();
    }

    function renderInfo(jumlahTampil) {
        const info = document.getElementById('trxInfo');
        if (!info || !metaHalaman) return;
        const total = metaHalaman.total ?? 0;
        const akhir = metaHalaman.last_page ?? 1;
        const disaring = jumlahTampil !== barisHalamanIni.length
            ? ' (' + jumlahTampil + ' cocok dengan filter)'
            : '';
        info.textContent = total + ' transaksi · Halaman ' + (metaHalaman.current_page ?? 1) + ' dari ' + akhir + disaring;
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
            tbody.innerHTML = '<tr><td colspan="9" class="trx-state">Sesi berakhir — silakan login ulang.</td></tr>';
            return;
        }

        tbody.innerHTML = '<tr><td colspan="9" class="trx-state">Memuat transaksi...</td></tr>';

        const params = { page: String(halaman), per_page: '15' };
        const tanggal = document.getElementById('dateInput')?.value;
        if (tanggal) params.tanggal = tanggal;

        try {
            const res = await fetch('/api/transaksi?' + new URLSearchParams(params), {
                headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + token },
            });
            if (!res.ok) throw new Error('Gagal memuat transaksi (' + res.status + ').');

            const json = await res.json();
            barisHalamanIni = json.data || [];
            metaHalaman = json.meta || null;
            render();
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="9" class="trx-state">' + e.message + '</td></tr>';
        }
    }

    // Pencarian & dropdown cuma menyaring data halaman aktif (tanpa request ulang).
    let timerCari;
    document.getElementById('trxSearch')?.addEventListener('input', function () {
        clearTimeout(timerCari);
        timerCari = setTimeout(render, 250);
    });

    document.querySelectorAll('.custom-select .custom-options').forEach(function (ul) {
        ul.addEventListener('click', function () { setTimeout(render, 0); });
    });

    // Ganti tanggal = data berbeda, jadi minta ulang dari server dari halaman 1.
    document.getElementById('dateInput')?.addEventListener('change', function () {
        halaman = 1;
        muatTabel();
    });

    // Dipakai modal detail untuk menyegarkan tabel setelah lunas/batal.
    window.muatTabelTransaksi = muatTabel;

    muatTabel();
})();

// =========================
// Modal Detail Transaksi — dipakai kasir & manager (GET /api/transaksi/{id})
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

    // ---- Aksi selesaikan pesanan (hanya untuk status 'pending') ----
    let trxAktif = null;      // transaksi yang sedang dibuka di modal
    let metodeDipilih = null; // 'cash' | 'qris'

    const aksiWrap = document.getElementById('detailAksi');
    const aksiNote = document.getElementById('detailAksiNote');
    const lunasBtn = document.getElementById('aksiLunasBtn');
    const batalBtn = document.getElementById('aksiBatalBtn');

    function siapkanAksi(trx) {
        trxAktif = trx;
        metodeDipilih = trx.metode_bayar || null;

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

    // Delegasi: baris tabel dibuat ulang tiap kali data dimuat, jadi listener
    // tidak boleh menempel langsung ke tombolnya.
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
</script>

@endsection