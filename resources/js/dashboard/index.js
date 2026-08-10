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
           
        };
    }

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

    async function loadDashboard(silent = false) {
        if (!silent) {
            els.loading.style.display = 'block';
            els.body.style.display = 'none';
        }
        els.error.style.display = 'none';

        const start = document.getElementById('startDate').value || null;
        const end = document.getElementById('endDate').value || null;
        const grain = 'harian';

        const params = { start, end, grain };

        try {
            if (kasir) {
                const [ringkasan, produkTerlaris] = await Promise.all([
                    apiGet('/ringkasan', params),
                    apiGet('/produk-terlaris', { ...params, by: 'qty', limit: 10 }),
                ]);

                renderRingkasan(ringkasan.data);
                renderProdukTerlaris(produkTerlaris.data);
            } else {
                const [ringkasan, timeSeries, revenueUkuran, bestSeller, worstSeller, platform, rfm] =
                    await Promise.all([
                        apiGet('/ringkasan', params),
                        apiGet('/time-series', params),
                        apiGet('/revenue-ukuran', { ...params, sembunyikan_tidak_diketahui: true }),
                        apiGet('/produk-terlaris', { ...params, by: 'qty', arah: 'tertinggi', limit: 10 }),
                        apiGet('/produk-terlaris', { ...params, by: 'qty', arah: 'terendah', limit: 10 }),
                        apiGet('/platform', { ...params, sembunyikan_tidak_diketahui: true }),
                        apiGet('/rfm', {}),
                    ]);

                renderRingkasan(ringkasan.data);
                renderTrend(timeSeries.data, timeSeries.data_tersedia);
                renderProdukTerlaris(bestSeller.data);
                renderWorstSeller(worstSeller.data);
                renderUkuran(revenueUkuran.data);
                renderFilterPlatform(platform.data);
                renderRfm(rfm.ringkasan_segmen);
                renderPelanggan10x(rfm.data);
                initLoyaltyTable(rfm.data);
            }

            els.loading.style.display = 'none';
            els.body.style.display = 'block';
        } catch (err) {
            if (!silent) {
                els.loading.style.display = 'none';
                showError(err.message || 'Terjadi kesalahan saat memuat dashboard.');
            }
        }
    }

    function renderRingkasan(d) {
        const revenue = Number(d.total_revenue || 0);
        const jumlah = Number(d.total_transaksi || 0);
        const rata = jumlah > 0 ? Math.round(revenue / jumlah) : 0;

        document.getElementById('cardRevenue').textContent = rupiah(revenue);
        document.getElementById('cardTransaksi').textContent = jumlah.toLocaleString('id-ID');
        document.getElementById('cardRata').textContent = rupiah(rata);
    }

    const HARI_URUT = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    function agregatPerHari(rows) {
        const ember = {};
        HARI_URUT.forEach(h => { ember[h] = { hari: h, revenue: 0, transaksi: 0, qty: 0, tanggal: [] }; });

        rows.forEach(r => {
            const b = ember[r.hari];
            if (!b) return; 
            b.revenue += Number(r.revenue || 0);
            b.transaksi += Number(r.transaksi || 0);
            b.qty += Number(r.qty || 0);
            b.tanggal.push({ label: r.periode_label || r.periode, revenue: Number(r.revenue || 0) });
        });

        return HARI_URUT.map(h => ember[h]);
    }

    function renderTrend(rows, dataTersedia) {
        destroyChart('trend');

        const ctx = document.getElementById('trendChart');
        const kosong = document.getElementById('trendKosong');

        if (dataTersedia === false || !rows.length) {
            ctx.hidden = true;
            if (kosong) kosong.hidden = false;
            return;
        }
        ctx.hidden = false;
        if (kosong) kosong.hidden = true;

        const perHari = agregatPerHari(rows);

        charts.trend = new Chart(ctx, {
            type: 'line',
            data: {
                labels: HARI_URUT,
                datasets: [{
                    label: 'Total Penjualan',
                    data: perHari.map(b => b.revenue),
                    borderColor: '#2f9e5f',
                    backgroundColor: 'rgba(47,158,95,0.12)',
                    borderWidth: 3,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#2f9e5f',
                    tension: 0.35,
                    fill: true,
                }],
            },
            options: {
                interaction: { mode: 'index', intersect: false },
                scales: { y: { beginAtZero: true } },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: (items) => items[0].label, // nama hari
                            label: (item) => {
                                const b = perHari[item.dataIndex];
                                return [
                                    `Total: ${rupiah(b.revenue)}`,
                                    `${Number(b.transaksi).toLocaleString('id-ID')} transaksi · ${Number(b.qty).toLocaleString('id-ID')} qty`,
                                ];
                            },
                            afterBody: (items) => {
                                const b = perHari[items[0].dataIndex];
                                if (!b.tanggal.length) return [];
                                return [''].concat(
                                    b.tanggal.map(t => `${t.label}: ${rupiah(t.revenue)}`),
                                );
                            },
                        },
                    },
                },
            },
        });
    }

    function labelProduk(r) {
        const nama = r.nama_produk || '';
        const rasa = r.rasa || '';
        return rasa && !nama.toLowerCase().includes(rasa.toLowerCase())
            ? `${nama} (${rasa})`
            : nama;
    }

    function potongLabel(teks, maks = 16) {
        return teks.length > maks ? teks.slice(0, maks - 1) + '…' : teks;
    }

    function renderProdukChart(chartKey, canvasId, labelsPenuh, data, color) {
        destroyChart(chartKey);
        const ctx = document.getElementById(canvasId);
        charts[chartKey] = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labelsPenuh.map(l => potongLabel(l)),
                datasets: [{ label: 'Qty Terjual', data, backgroundColor: color }],
            },
            options: {
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: { title: (items) => labelsPenuh[items[0].dataIndex] },
                    },
                },
                scales: {
                    x: { ticks: { maxRotation: 45, minRotation: 45, autoSkip: false, font: { size: 10 } } },
                },
            },
        });
    }

    function renderProdukTerlaris(rows) {
        renderProdukChart(
            'bestSeller', 'bestSellerChart',
            rows.map(labelProduk), rows.map(r => Number(r.qty || 0)), '#2f9e5f',
        );
    }

    function renderWorstSeller(rows) {
        renderProdukChart(
            'worstSeller', 'worstSellerChart',
            rows.map(labelProduk), rows.map(r => Number(r.qty || 0)), '#3fb98f',
        );
    }

    function renderUkuran(rows) {
        destroyChart('ukuran');
        const ctx = document.getElementById('ukuranChart');
        rows = [...rows].sort((a, b) => Number(b.jumlah_terjual || 0) - Number(a.jumlah_terjual || 0));
        charts.ukuran = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: rows.map(r => r.ukuran),
                datasets: [{ label: 'Jumlah Terjual', data: rows.map(r => Number(r.jumlah_terjual || 0)), backgroundColor: '#8fd6ac' }],
            },
            options: {
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (item) => {
                                const r = rows[item.dataIndex];
                                return [
                                    `Terjual: ${Number(r.jumlah_terjual || 0).toLocaleString('id-ID')} pcs`,
                                    `Revenue: ${rupiah(r.total_revenue)}`,
                                ];
                            },
                        },
                    },
                },
            },
        });
    }

    let platformRows = [];

    const PLATFORM_ALIAS = { 'cash': 'Tunai', 'qris': 'QRIS', 'tunai': 'Tunai' };

    function normalizePlatform(nama) {
        const key = String(nama || '').trim().toLowerCase();
        return PLATFORM_ALIAS[key] || nama;
    }

    function mergePlatform(rows) {
        const map = {};
        rows.forEach((r) => {
            const nama = normalizePlatform(r.platform);
            if (!map[nama]) map[nama] = { platform: nama, transaksi: 0, revenue: 0, qty: 0 };
            map[nama].transaksi += Number(r.transaksi || 0);
            map[nama].revenue += Number(r.revenue || 0);
            map[nama].qty += Number(r.qty || 0);
        });
        return Object.values(map);
    }

    function renderFilterPlatform(rows) {
        platformRows = mergePlatform(rows).sort((a, b) => Number(b.transaksi) - Number(a.transaksi));
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

    const resizeObserver = new ResizeObserver(function () {
        Object.values(charts).forEach(chart => chart.resize());
    });
    resizeObserver.observe(document.querySelector('.dash-content'));

    loadDashboard();

    const AUTO_REFRESH_MS = 30000;
    setInterval(function () {
        if (!document.hidden) loadDashboard(true);
    }, AUTO_REFRESH_MS);
})();