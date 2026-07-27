(function () {
    const API_BASE = '/api';

    function getToken() { return localStorage.getItem('auth_token'); }
    function fetchOptions(extra = {}) {
        const token = getToken();
        return {
            headers: {
                'Accept': 'application/json',
                ...(token ? { 'Authorization': 'Bearer ' + token } : {}),
            },
            ...extra,
        };
    }

    function rupiah(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID'); }

    const errorEl = document.getElementById('lapError');
    function showError(msg) { errorEl.textContent = msg; errorEl.style.display = 'block'; }

    // Segmen mengikuti data RFM sumber (Data_RFM_Pelanggan.csv):
    // Pelanggan Baru (hijau) / Butuh Perhatian (biru) / Potensial (kuning) / Loyal (merah).
    const SEGMEN_CLASS = {
        'Pelanggan Baru': 'segmen-loyal',
        'Butuh Perhatian': 'segmen-potensial',
        'Potensial': 'segmen-perhatian',
        'Loyal': 'segmen-hilang',
    };
    const SEGMEN_COLOR = {
        'Pelanggan Baru': '#63c088',
        'Butuh Perhatian': '#5aa9e6',
        'Potensial': '#f4c542',
        'Loyal': '#e05a5a',
    };

    let rfmData = [];
    let switchData = [];
    let rfmChart = null;
    let revenueUkuranChart = null;

    // ---- Revenue per Ukuran (sumber sama dengan dashboard) ----
    async function loadRevenueUkuran() {
        const res = await fetch(`${API_BASE}/dashboard/revenue-ukuran`, fetchOptions());
        if (!res.ok) throw new Error('Gagal memuat data revenue ukuran.');
        return res.json();
    }

    function renderRevenueUkuran(rows) {
        if (revenueUkuranChart) revenueUkuranChart.destroy();
        const ctx = document.getElementById('revenueUkuranChart');
        revenueUkuranChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: rows.map(r => r.ukuran),
                datasets: [{ label: 'Total Revenue Ukuran', data: rows.map(r => r.total_revenue), backgroundColor: '#4285f4' }],
            },
            options: { plugins: { legend: { display: true, position: 'top', align: 'start' } } },
        });
    }

    async function initRevenueUkuran() {
        try {
            const json = await loadRevenueUkuran();
            renderRevenueUkuran(json.data || []);
        } catch (err) {
            showError(err.message);
        }
    }

    // ---- RFM ----
    async function loadRfm(segmen) {
        const qs = segmen ? `?segmen=${encodeURIComponent(segmen)}` : '';
        const res = await fetch(`${API_BASE}/dashboard/rfm${qs}`, fetchOptions());

        if (res.status === 401) throw new Error('Sesi habis, silakan login ulang.');
        if (res.status === 403) throw new Error('Akun ini tidak punya akses ke halaman Laporan (khusus manager).');
        if (!res.ok) throw new Error('Gagal memuat data RFM.');

        return res.json();
    }

    function renderCards(ringkasanSegmen) {
        const map = {
            'Pelanggan Baru': 'cardBaru',
            'Butuh Perhatian': 'cardPerhatian',
            'Potensial': 'cardPotensial',
            'Loyal': 'cardLoyal',
        };
        Object.entries(map).forEach(function ([segmen, id]) {
            const el = document.getElementById(id);
            if (el) el.textContent = ringkasanSegmen[segmen] ?? 0;
        });
    }

    function renderRfmChart(ringkasanSegmen) {
        const labels = Object.keys(ringkasanSegmen);
        const values = Object.values(ringkasanSegmen);

        if (rfmChart) rfmChart.destroy();
        const ctx = document.getElementById('rfmDonut');
        rfmChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{ data: values, backgroundColor: labels.map(l => SEGMEN_COLOR[l] || '#999') }],
            },
            options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } } },
        });
    }

    function renderRfmTable() {
        const keyword = document.getElementById('rfmSearch').value.trim().toLowerCase();
        const rows = keyword ? rfmData.filter(r => r.nama_pelanggan.toLowerCase().includes(keyword)) : rfmData;
        const tbody = document.getElementById('rfmTableBody');

        if (rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="lap-empty">Tidak ada data.</td></tr>';
            return;
        }

        tbody.innerHTML = rows.slice(0, 200).map(function (r) {
            const cls = SEGMEN_CLASS[r.segmen] || 'segmen-potensial';
            return `<tr>
                <td>${r.nama_pelanggan}</td>
                <td>${r.recency}</td>
                <td>${r.frequency}</td>
                <td>${rupiah(r.monetary)}</td>
                <td>${r.rfm_total}</td>
                <td><span class="segmen-badge ${cls}">${r.segmen}</span></td>
            </tr>`;
        }).join('');
    }

    // ---- Switch / rekomendasi upsell ----
    async function loadSwitch(keyword) {
        const qs = keyword ? `?rekomendasi=${encodeURIComponent(keyword)}` : '';
        const res = await fetch(`${API_BASE}/dashboard/switch${qs}`, fetchOptions());
        if (!res.ok) throw new Error('Gagal memuat data rekomendasi.');
        return res.json();
    }

    function renderSwitchTable() {
        const tbody = document.getElementById('switchTableBody');

        if (switchData.length === 0) {
            tbody.innerHTML = '<tr><td colspan="10" class="lap-empty">Tidak ada data.</td></tr>';
            return;
        }

        tbody.innerHTML = switchData.slice(0, 200).map(function (r) {
            return `<tr>
                <td>${r.nama_pelanggan}</td>
                <td>${r.rasa_favorit ?? '-'}</td>
                <td>${r.ukuran_saat_ini ?? '-'}</td>
                <td>${r.beli_reguler ?? 0}</td>
                <td>${r.beli_large ?? 0}</td>
                <td>${r.beli_botol ?? 0}</td>
                <td>${r.total_transaksi}</td>
                <td>${Number(r.qty_per_kunjungan).toFixed(1)}</td>
                <td>${rupiah(r.total_belanja)}</td>
                <td>${r.rekomendasi ?? '-'}</td>
            </tr>`;
        }).join('');
    }

    // ---- Init & event wiring ----
    async function initRfm() {
        try {
            const json = await loadRfm(document.getElementById('rfmSegmenFilter').value);
            document.getElementById('periodeLabel').textContent = json.periode_label ?? '-';
            rfmData = json.data || [];
            renderCards(json.ringkasan_segmen || {});
            renderRfmChart(json.ringkasan_segmen || {});
            renderRfmTable();
        } catch (err) {
            showError(err.message);
        }
    }

    async function initSwitch() {
        try {
            const keyword = document.getElementById('switchSearch').value.trim();
            const json = await loadSwitch(keyword);
            switchData = json.data || [];
            renderSwitchTable();
        } catch (err) {
            showError(err.message);
        }
    }

    document.getElementById('rfmSegmenFilter').addEventListener('change', initRfm);
    document.getElementById('rfmSearch').addEventListener('input', debounce(renderRfmTable, 250));
    document.getElementById('switchSearch').addEventListener('input', debounce(initSwitch, 400));

    function debounce(fn, delay) {
        let timer;
        return function (...args) {
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, args), delay);
        };
    }

    // ---- Unduh laporan (butuh header Authorization, jadi lewat fetch + blob,
    // bukan link <a href> biasa yang tidak bawa token) ----
    document.getElementById('unduhBtn').addEventListener('click', async function () {
        const btn = this;
        btn.disabled = true;
        btn.textContent = 'Menyiapkan...';

        try {
            const res = await fetch(`${API_BASE}/laporan/export`, fetchOptions());
            if (!res.ok) throw new Error('Gagal mengunduh laporan.');

            const blob = await res.blob();
            const disposition = res.headers.get('Content-Disposition') || '';
            const match = disposition.match(/filename="?([^"]+)"?/);
            const filename = match ? match[1] : 'Laporan_SoyaCore.xlsx';

            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            a.remove();
            window.URL.revokeObjectURL(url);
        } catch (err) {
            showError(err.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-download"></i> Unduh';
        }
    });

    (async function init() {
        await Promise.all([initRfm(), initSwitch(), initRevenueUkuran()]);
    })();
})();