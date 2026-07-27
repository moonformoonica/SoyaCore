@extends('layouts.app')

@section('title', 'Dashboard')

@push('styles')
<style>
    .dash-header {
        background: #14532d; color: #fff; padding: 16px 24px;
        display: flex; justify-content: space-between; align-items: center;
        border-radius: 8px; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;
    }
    .dash-header h1 { font-size: 18px; margin: 0; color: #fff; }
    .filter-form { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
    .filter-form input, .filter-form select, .filter-form button {
        padding: 6px 10px; border-radius: 6px; border: none; font-size: 13px;
    }
    .filter-form button { background: #1f7a4d; color: #fff; cursor: pointer; }
    .filter-sd { color: #fff; font-size: 13px; padding: 0 2px; }

    .cards { display: flex; gap: 1px; background: #cfe8da; border-radius: 8px; overflow: hidden; margin-bottom: 16px; flex-wrap: wrap; }
    .card { flex: 1; min-width: 160px; background: #cfe8da; padding: 16px 24px; }
    .card .label { font-size: 13px; color: #1f4d33; }
    .card .value { font-size: 24px; font-weight: 700; color: #14532d; margin-top: 4px; }

    /* min-width: 0 wajib di semua level grid/flex ini — tanpa ini,
       canvas Chart.js "menahan" lebar kolom supaya tidak bisa menyusut
       balik waktu sidebar dibuka lagi, sehingga dashboard overflow
       ke kanan (muncul scrollbar horizontal). */
    .dash-content { min-width: 0; width: 100%; overflow-x: hidden; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; min-width: 0; }
    .panel { background: #fff; border-radius: 8px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.08); min-width: 0; overflow: hidden; }
    .panel h3 { margin: 0 0 10px; font-size: 14px; color: #14532d; }

    .bottom-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; min-width: 0; }
    .stat-box { background: #fff; border-radius: 8px; padding: 16px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,.08); min-width: 0; }
    .stat-box .num { font-size: 32px; font-weight: 700; color: #14532d; }

    .dash-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .dash-table th, .dash-table td { text-align: left; padding: 4px 6px; border-bottom: 1px solid #eee; }

    .grid3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 16px; min-width: 0; }
    .grid-split { display: grid; grid-template-columns: 1fr 2fr; gap: 16px; margin-bottom: 16px; min-width: 0; align-items: start; }

    .fp-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
    .fp-title { font-size: 14px; font-weight: 600; color: #14532d; }
    .fp-exclude { font-weight: 400; color: #888; font-size: 12px; }
    .fp-record-count { font-size: 12px; color: #666; }
    .fp-search { margin-bottom: 8px; }
    .fp-search input {
        width: 100%; padding: 6px 10px; border: 1px solid #ddd; border-radius: 6px;
        font-size: 12px; outline: none;
    }
    .filter-platform-list { display: flex; flex-direction: column; gap: 10px; }
    .filter-platform-item {
        display: flex; align-items: center; justify-content: space-between;
        font-size: 13px; color: #333; gap: 8px;
    }
    .filter-platform-item span.fp-name { display: flex; align-items: center; gap: 8px; }
    .filter-platform-item input[type="checkbox"] { accent-color: #2f9e5f; }
    .filter-platform-item .fp-count { color: #666; font-size: 13px; }

    .mini-stat { text-align: center; padding: 14px 0 4px; border-top: 1px solid #eee; margin-top: 12px; }
    .mini-stat .num { font-size: 30px; font-weight: 700; color: #14532d; }
    .mini-stat .label { font-size: 12px; color: #666; margin-top: 2px; }

    .dash-pagination { display: flex; align-items: center; justify-content: space-between; margin-top: 10px; font-size: 12px; color: #666; }
    .dash-pagination button {
        padding: 4px 10px; border-radius: 6px; border: 1px solid #ddd; background: #fff; cursor: pointer; font-size: 12px;
    }
    .dash-pagination button:disabled { opacity: .4; cursor: not-allowed; }
    .dash-pagebtns { display: flex; gap: 6px; }
    .dash-pagebtns button.active { background: #2E7D32; border-color: #2E7D32; color: #fff; font-weight: 700; }
    .dash-pagebtns button:empty { display: none; }

    /* Paksa canvas Chart.js selalu pas dengan lebar panel-nya, jangan
       biarkan ukuran piksel bawaan Chart.js mendikte lebar container. */
    .dash-content canvas { max-height: 260px; max-width: 100% !important; width: 100% !important; }
    .dash-error {
        background: #fdecea; color: #b3261e; padding: 10px 14px; border-radius: 6px;
        margin-bottom: 16px; font-size: 13px; display: none;
    }
    .dash-loading { text-align: center; padding: 40px; color: #888; font-size: 14px; }

    /* Tabel dibungkus div supaya kalau kolomnya sempit banget di HP,
       yang scroll cuma tabelnya sendiri (horizontal) — bukan seluruh
       halaman dashboard ikut geser. */
    .table-scroll { width: 100%; overflow-x: auto; }

    /* ===========================
            RESPONSIVE
    =========================== */

    @media (max-width: 900px) {
        .grid, .bottom-row, .grid3, .grid-split {
            grid-template-columns: 1fr;
        }
        .dash-header h1 { font-size: 16px; }
        .card { min-width: 45%; padding: 14px 16px; }
        .card .value { font-size: 20px; }
    }

    @media (max-width: 600px) {
        .dash-header { padding: 12px 16px; }
        .filter-form { width: 100%; }
        .filter-form input,
        .filter-form select,
        .filter-form button { flex: 1; min-width: 0; }
        .card { min-width: 100%; }
        .panel { padding: 12px; }
        .dash-content canvas { max-height: 200px; }
    }
</style>
@endpush

@section('content')
<div class="dash-content">

    <div id="dashError" class="dash-error"></div>
    <div id="dashLoading" class="dash-loading">Memuat data dashboard...</div>

    <div id="dashBody" style="display:none;">

        <div class="dash-header">
            <h1>Soya Sight</h1>
            <form id="filterForm" class="filter-form">
                <input type="date" name="start_date" id="startDate">
                <span class="filter-sd">s/d</span>
                <input type="date" name="end_date" id="endDate">
                <select name="grain" id="grainSelect">
                    <option value="harian">Harian</option>
                    <option value="mingguan">Mingguan</option>
                    <option value="bulanan">Bulanan</option>
                    <option value="tahunan">Tahunan</option>
                </select>
                <button type="submit">Terapkan</button>
            </form>
        </div>

        <div class="cards">
            <div class="card">
                <div class="label">Total Revenue</div>
                <div class="value" id="cardRevenue">Rp 0</div>
            </div>
            <div class="card">
                <div class="label">Jumlah Transaksi</div>
                <div class="value" id="cardTransaksi">0</div>
            </div>
            <div class="card">
                <div class="label">Rata-rata nilai/Transaksi</div>
                <div class="value" id="cardRata">Rp 0</div>
            </div>
        </div>

        <div class="grid3" id="dashGrid">
            <div class="panel" id="bestSellerPanel">
                <h3>10 Menu Best Seller</h3>
                <canvas id="bestSellerChart"></canvas>
            </div>
            <div class="panel" data-role="manager">
                <h3>10 Menu Kurang Diminati</h3>
                <canvas id="worstSellerChart"></canvas>
            </div>
            <div class="panel" data-role="manager">
                <h3>Tren Penjualan</h3>
                <canvas id="trendChart"></canvas>
            </div>
        </div>

        <div class="grid" data-role="manager">
            <div class="panel">
                <h3>Revenue per Ukuran</h3>
                <canvas id="ukuranChart"></canvas>
            </div>
            <div class="panel">
                <h3>Segmentasi Pelanggan (RFM)</h3>
                <canvas id="rfmChart"></canvas>
            </div>
        </div>

        <div class="panel" data-role="manager" style="margin-bottom:16px;">
            <div class="mini-stat" style="border-top:none; margin-top:0; padding-top:0;">
                <div class="num" id="statPelanggan10x">0</div>
                <div class="label">Pelanggan 10 x beli</div>
            </div>
        </div>

        <div class="grid-split" data-role="manager">
            <div class="panel">
                <div class="fp-head">
                    <span class="fp-title">Filter Platform <span class="fp-exclude">(Exclude)</span></span>
                    <span class="fp-record-count">Record Count</span>
                </div>
                <div class="fp-search">
                    <input type="text" id="filterPlatformSearch" placeholder="Type to search">
                </div>
                <div id="filterPlatformList" class="filter-platform-list"></div>
            </div>
            <div class="panel">
                <h3>Poin Loyalty Pelanggan</h3>
                <div class="table-scroll">
                    <table class="dash-table">
                        <thead><tr><th>#</th><th>Nama Pelanggan</th><th>Total</th><th>Monetary &#9660;</th><th>Frekuensi</th></tr></thead>
                        <tbody id="loyaltyTableBody"></tbody>
                    </table>
                </div>
                <div class="dash-pagination">
                    <span id="loyaltyPageInfo"></span>
                    <div class="dash-pagebtns" id="loyaltyPagination"></div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(function () {
    // ====== KONFIGURASI — sesuaikan di sini kalau caranya beda ======
    const API_BASE = '/api/dashboard';

    // Ganti fungsi ini kalau token disimpan/dikirim dengan cara lain
    // (misal cookie Sanctum SPA -> tidak perlu header Authorization,
    // cukup pakai { credentials: 'include' } di fetchOptions di bawah).
    function getToken() {
        return localStorage.getItem('auth_token');
    }

    function fetchOptions() {
        const token = getToken();
        return {
            headers: {
                'Accept': 'application/json',
                ...(token ? { 'Authorization': 'Bearer ' + token } : {}),
            },
            // credentials: 'include', // aktifkan ini kalau pakai cookie Sanctum SPA
        };
    }
    // ==================================================================

    const els = {
        error: document.getElementById('dashError'),
        loading: document.getElementById('dashLoading'),
        body: document.getElementById('dashBody'),
    };

    let charts = {};

    function isKasir() {
        try {
            const rawUser = localStorage.getItem('auth_user');
            return rawUser ? JSON.parse(rawUser).role === 'kasir' : false;
        } catch (e) {
            return false;
        }
    }

    const kasir = isKasir();

    // Dashboard kasir sengaja dibatasi backend: tidak ada data per-pelanggan
    // (RFM/loyalty/platform) & tidak ada tren/revenue-ukuran. Sembunyikan
    // panel yang butuh endpoint itu, dan lebarin panel produk terlaris yang
    // tersisa supaya nggak keliatan pincang.
    if (kasir) {
        document.querySelectorAll('.dash-content [data-role="manager"]').forEach(function (el) {
            el.style.display = 'none';
        });
        document.getElementById('bestSellerPanel').style.gridColumn = '1 / -1';
    }

    function rupiah(n) {
        return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
    }

    function showError(msg) {
        els.error.textContent = msg;
        els.error.style.display = 'block';
    }

    function buildQuery(params) {
        const usp = new URLSearchParams();
        Object.entries(params).forEach(([k, v]) => {
            if (v !== null && v !== undefined && v !== '') usp.append(k, v);
        });
        return usp.toString();
    }

    async function apiGet(path, params = {}) {
        const qs = buildQuery(params);
        const res = await fetch(`${API_BASE}${path}${qs ? '?' + qs : ''}`, fetchOptions());

        if (res.status === 401) {
            throw new Error('Sesi habis atau belum login. Silakan login ulang.');
        }
        if (res.status === 403) {
            throw new Error('Akun ini tidak punya akses ke dashboard (khusus role manager).');
        }
        if (!res.ok) {
            throw new Error(`Gagal memuat data (${path}): ${res.status}`);
        }
        return res.json();
    }

    function destroyChart(key) {
        if (charts[key]) {
            charts[key].destroy();
            delete charts[key];
        }
    }

    async function loadDashboard() {
        els.loading.style.display = 'block';
        els.body.style.display = 'none';
        els.error.style.display = 'none';

        const start = document.getElementById('startDate').value || null;
        const end = document.getElementById('endDate').value || null;
        const grain = document.getElementById('grainSelect').value || 'harian';

        const params = { start, end, grain };

        try {
            if (kasir) {
                // Kasir cuma boleh akses meta/ringkasan/produk-terlaris (lihat routes/api.php).
                const [ringkasan, produkTerlaris] = await Promise.all([
                    apiGet('/ringkasan', params),
                    apiGet('/produk-terlaris', { ...params, by: 'qty', limit: 10 }),
                ]);

                renderRingkasan(ringkasan.data);
                renderProdukTerlaris(produkTerlaris.data);
            } else {
                const [ringkasan, timeSeries, revenueUkuran, produkSemua, platform, rfm] =
                    await Promise.all([
                        apiGet('/ringkasan', params),
                        apiGet('/time-series', params),
                        apiGet('/revenue-ukuran', params),
                        // Limit maksimal yang diizinkan backend (LaporanRequest: max 100) —
                        // dipakai buat ambil 10 teratas SEKALIGUS 10 terbawah dari satu
                        // request yang sama (produk-terlaris urut qty desc).
                        apiGet('/produk-terlaris', { ...params, by: 'qty', limit: 100 }),
                        apiGet('/platform', params),
                        apiGet('/rfm', {}),
                    ]);

                renderRingkasan(ringkasan.data);
                renderTrend(timeSeries.data);
                renderProdukTerlaris(produkSemua.data);
                renderWorstSeller(produkSemua.data);
                renderUkuran(revenueUkuran.data);
                renderFilterPlatform(platform.data);
                renderRfm(rfm.ringkasan_segmen);
                renderPelanggan10x(rfm.data);
                initLoyaltyTable(rfm.data);
            }

            els.loading.style.display = 'none';
            els.body.style.display = 'block';
        } catch (err) {
            els.loading.style.display = 'none';
            showError(err.message || 'Terjadi kesalahan saat memuat dashboard.');
        }
    }

    function renderRingkasan(d) {
        document.getElementById('cardRevenue').textContent = rupiah(d.total_revenue);
        document.getElementById('cardTransaksi').textContent = Number(d.total_transaksi).toLocaleString('id-ID');
        document.getElementById('cardRata').textContent = rupiah(d.rata_rata_transaksi);
    }

    function renderTrend(rows) {
        destroyChart('trend');
        const ctx = document.getElementById('trendChart');
        charts.trend = new Chart(ctx, {
            type: 'line',
            data: {
                labels: rows.map(r => r.periode),
                datasets: [{
                    label: 'Revenue',
                    data: rows.map(r => r.revenue),
                    borderColor: '#2f9e5f',
                    backgroundColor: 'rgba(47,158,95,0.1)',
                    fill: true,
                    tension: 0.3,
                }],
            },
            options: { plugins: { legend: { display: false } } },
        });
    }

    function labelProduk(r) {
        return r.rasa ? `${r.nama_produk} (${r.rasa})` : r.nama_produk;
    }

    // labels[] & data[] sudah termasuk bucket "Others". Warna bar "Others"
    // dibedakan (lebih muda) seperti di dashboard sumber.
    function renderProdukChart(chartKey, canvasId, labels, data, color, othersColor) {
        destroyChart(chartKey);
        const ctx = document.getElementById(canvasId);
        const colors = labels.map(l => (l === 'Others' ? othersColor : color));
        charts[chartKey] = new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [{ label: 'Qty Terjual', data, backgroundColor: colors }],
            },
            options: { plugins: { legend: { display: false } } },
        });
    }

    // rows = SEMUA produk (urut qty desc dari backend). Tampilkan 10 teratas
    // + bucket "Others" (akumulasi sisanya), meniru dashboard sumber.
    function renderProdukTerlaris(rows) {
        const top = rows.slice(0, 10);
        const othersQty = rows.slice(10).reduce((s, r) => s + Number(r.qty || 0), 0);
        const labels = top.map(labelProduk);
        const data = top.map(r => Number(r.qty || 0));
        if (othersQty > 0) { labels.push('Others'); data.push(othersQty); }
        renderProdukChart('bestSeller', 'bestSellerChart', labels, data, '#2f9e5f', '#8fd6ac');
    }

    // 10 menu paling sedikit terjual + "Others" (akumulasi menu populer),
    // makanya bar "Others" jadi paling tinggi seperti di dashboard sumber.
    function renderWorstSeller(rows) {
        const worst = rows.slice(-10); // 10 qty terkecil, urut desc
        const othersQty = rows.slice(0, Math.max(0, rows.length - 10))
            .reduce((s, r) => s + Number(r.qty || 0), 0);
        const labels = [];
        const data = [];
        if (othersQty > 0) { labels.push('Others'); data.push(othersQty); }
        worst.forEach(r => { labels.push(labelProduk(r)); data.push(Number(r.qty || 0)); });
        renderProdukChart('worstSeller', 'worstSellerChart', labels, data, '#3fb98f', '#8fd6ac');
    }

    function renderUkuran(rows) {
        destroyChart('ukuran');
        const ctx = document.getElementById('ukuranChart');
        charts.ukuran = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: rows.map(r => r.ukuran),
                datasets: [{ label: 'Revenue', data: rows.map(r => r.total_revenue), backgroundColor: '#8fd6ac' }],
            },
            options: { plugins: { legend: { display: false } } },
        });
    }

    // Statis dulu (frontend-only) — checklist nunjukin jumlah record per
    // platform (urut terbanyak), search hanya memfilter daftar yang tampil,
    // belum benar-benar nge-filter panel lain. Filter beneran butuh parameter
    // platform di tiap endpoint backend, belum ada.
    let platformRows = [];

    function renderFilterPlatform(rows) {
        platformRows = [...rows].sort((a, b) => Number(b.transaksi) - Number(a.transaksi));
        drawFilterPlatform('');
    }

    function drawFilterPlatform(keyword) {
        const kw = (keyword || '').trim().toLowerCase();
        const el = document.getElementById('filterPlatformList');
        el.innerHTML = platformRows
            .filter(r => r.platform.toLowerCase().includes(kw))
            .map(r => `
                <label class="filter-platform-item">
                    <span class="fp-name"><input type="checkbox" checked> ${r.platform}</span>
                    <span class="fp-count">${Number(r.transaksi).toLocaleString('id-ID')}</span>
                </label>
            `).join('');
    }

    document.getElementById('filterPlatformSearch').addEventListener('input', function () {
        drawFilterPlatform(this.value);
    });

    function renderPelanggan10x(rows) {
        const jumlah = rows.filter(r => Number(r.frequency) >= 10).length;
        document.getElementById('statPelanggan10x').textContent = jumlah.toLocaleString('id-ID');
    }

    // Tabel penuh (bukan cuma top-10) dari snapshot RFM, diurut Monetary desc
    // (seperti dashboard sumber), dipaginasi 100/halaman di client karena
    // datanya sudah didapat sekaligus dalam satu response.
    let loyaltyRows = [];
    let loyaltyPage = 1;
    const LOYALTY_PAGE_SIZE = 10;

    function initLoyaltyTable(rows) {
        loyaltyRows = [...rows].sort((a, b) => Number(b.monetary) - Number(a.monetary));
        loyaltyPage = 1;
        renderLoyaltyPage();
    }

    function renderLoyaltyPage() {
        const totalPage = Math.max(1, Math.ceil(loyaltyRows.length / LOYALTY_PAGE_SIZE));
        loyaltyPage = Math.min(Math.max(1, loyaltyPage), totalPage);

        const start = (loyaltyPage - 1) * LOYALTY_PAGE_SIZE;
        const pageRows = loyaltyRows.slice(start, start + LOYALTY_PAGE_SIZE);

        const tbody = document.getElementById('loyaltyTableBody');
        tbody.innerHTML = pageRows.map((r, i) => `
            <tr>
                <td>${start + i + 1}</td>
                <td>${r.nama_pelanggan}</td>
                <td>${Number(r.total_poin_loyalty).toLocaleString('id-ID')}</td>
                <td>${Number(r.monetary).toLocaleString('id-ID')}</td>
                <td>${Number(r.frequency).toLocaleString('id-ID')}</td>
            </tr>
        `).join('');

        document.getElementById('loyaltyPageInfo').textContent =
            loyaltyRows.length === 0 ? '' : `${loyaltyRows.length} pelanggan · Halaman ${loyaltyPage} dari ${totalPage}`;

        renderLoyaltyPagination(totalPage);
    }

    function renderLoyaltyPagination(totalPage) {
        const wrap = document.getElementById('loyaltyPagination');
        if (!wrap) return;
        if (totalPage <= 1) { wrap.innerHTML = ''; return; }

        let html = `<button type="button" data-hal="${loyaltyPage - 1}" ${loyaltyPage <= 1 ? 'disabled' : ''}>&lsaquo;</button>`;

        // Maksimal 5 nomor di sekitar halaman aktif.
        let awal = Math.max(1, loyaltyPage - 2);
        const akhir = Math.min(totalPage, awal + 4);
        awal = Math.max(1, akhir - 4);
        for (let i = awal; i <= akhir; i++) {
            html += `<button type="button" data-hal="${i}" class="${i === loyaltyPage ? 'active' : ''}">${i}</button>`;
        }

        html += `<button type="button" data-hal="${loyaltyPage + 1}" ${loyaltyPage >= totalPage ? 'disabled' : ''}>&rsaquo;</button>`;
        wrap.innerHTML = html;

        wrap.querySelectorAll('button[data-hal]').forEach(function (b) {
            b.addEventListener('click', function () {
                const t = parseInt(b.dataset.hal, 10);
                if (!t || t < 1 || t > totalPage || t === loyaltyPage) return;
                loyaltyPage = t;
                renderLoyaltyPage();
            });
        });
    }

    function renderRfm(segmenObj) {
        destroyChart('rfm');
        const labels = Object.keys(segmenObj);
        const values = Object.values(segmenObj);
        const total = values.reduce((a, b) => a + b, 0);
        const percents = values.map(v => total > 0 ? ((v / total) * 100).toFixed(1) : 0);

        // Segmen mengikuti data RFM sumber (Data_RFM_Pelanggan.csv): Pelanggan
        // Baru (hijau) / Butuh Perhatian (biru) / Potensial (kuning) / Loyal (merah).
        const colorMap = {
            'Pelanggan Baru': '#63c088',
            'Butuh Perhatian': '#5aa9e6',
            'Potensial': '#f4c542',
            'Loyal': '#e05a5a',
        };

        const ctx = document.getElementById('rfmChart');
        charts.rfm = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{ data: values, backgroundColor: labels.map(l => colorMap[l] || '#999') }],
            },
            options: {
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: (ctx) => `${ctx.label}: ${percents[ctx.dataIndex]}%`,
                        },
                    },
                },
            },
        });
    }

    document.getElementById('filterForm').addEventListener('submit', function (e) {
        e.preventDefault();
        loadDashboard();
    });

    // ====== Auto-resize chart tiap kali lebar container berubah ======
    // Menutupi kasus sidebar collapse/expand, resize window, dsb — tidak
    // bergantung pada JS sidebar itu sendiri (yang mungkin ada di file lain).
    const resizeObserver = new ResizeObserver(function () {
        Object.values(charts).forEach(chart => chart.resize());
    });
    resizeObserver.observe(document.querySelector('.dash-content'));

    loadDashboard();
})();
</script>
@endpush