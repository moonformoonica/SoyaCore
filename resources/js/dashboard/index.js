(function () {

    const API_BASE = '/api/dashboard';
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
        const grain = 'harian';

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