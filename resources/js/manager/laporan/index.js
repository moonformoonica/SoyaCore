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
    function angkaBulat(n) {
        return Number(n || 0).toLocaleString('id-ID', { maximumFractionDigits: 0 });
    }

    const errorEl = document.getElementById('lapError');
    function showError(msg) { errorEl.textContent = msg; errorEl.style.display = 'block'; }
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

    function rentangQS() {
        const s = document.getElementById('exportStart').value;
        const e = document.getElementById('exportEnd').value;
        const p = new URLSearchParams();
        if (s) p.set('start', s);
        if (e) p.set('end', e);
        const qs = p.toString();
        return qs ? '?' + qs : '';
    }

    async function loadRevenueUkuran() {
        const pemisah = rentangQS() ? '&' : '?';
        const res = await fetch(
            `${API_BASE}/dashboard/revenue-ukuran${rentangQS()}${pemisah}sembunyikan_tidak_diketahui=true`,
            fetchOptions(),
        );
        if (!res.ok) throw new Error('Gagal memuat data revenue ukuran.');
        return res.json();
    }

    function fmtTanggal(iso) {
        if (!iso) return null;
        const d = new Date(iso + 'T00:00:00');
        return Number.isNaN(d.getTime())
            ? null
            : d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
    }
    async function updatePeriodeLabel() {
        const el = document.getElementById('periodeLabel');
        if (!el) return;
        try {
            const res = await fetch(`${API_BASE}/dashboard/ringkasan${rentangQS()}`, fetchOptions());
            if (!res.ok) return;
            const p = (await res.json()).periode || {};
            const mulai = fmtTanggal(p.start);
            const selesai = fmtTanggal(p.end);
            el.textContent = mulai && selesai ? `${mulai} – ${selesai}` : '—';
        } catch (e) {
            // biarkan label sebelumnya kalau koneksi bermasalah
        }
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
                <td>${angkaBulat(r.qty_per_kunjungan)}</td>
                <td>${rupiah(r.total_belanja)}</td>
                <td>${r.rekomendasi ?? '-'}</td>
            </tr>`;
        }).join('');
    }

    async function initRfm() {
        try {
            const json = await loadRfm(document.getElementById('rfmSegmenFilter').value);
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

    document.getElementById('rfmSegmenFilter').addEventListener('change', function () {
        sorotKartuSegmen(this.value);
        initRfm();
    });

    function sorotKartuSegmen(segmen) {
        document.querySelectorAll('.lap-card[data-segmen]').forEach((el) => {
            el.classList.toggle('aktif', el.dataset.segmen === segmen && segmen !== '');
        });
    }

    document.querySelectorAll('.lap-card[data-segmen]').forEach((kartu) => {
        function pilih() {
            const select = document.getElementById('rfmSegmenFilter');
            const segmen = select.value === kartu.dataset.segmen ? '' : kartu.dataset.segmen;
            select.value = segmen;
            sorotKartuSegmen(segmen);
            initRfm();
        }
        kartu.addEventListener('click', pilih);
        kartu.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); pilih(); }
        });
    });
    document.getElementById('rfmSearch').addEventListener('input', debounce(renderRfmTable, 250));
    document.getElementById('switchSearch').addEventListener('input', debounce(initSwitch, 400));
    // Daftar akun kasir untuk dropdown export.
    //
    // Sumbernya `/api/users`, BUKAN `/api/laporan/kasir`. Yang kedua hanya
    // memuat akun yang punya transaksi di rentangnya, jadi kasir yang baru
    // dibuat, atau yang sedang libur sepanjang rentang itu, tidak pernah
    // muncul sebagai pilihan, dan manager mengira akunnya gagal dibuat.
    // Memilih kasir yang kebetulan nihil transaksi menghasilkan export kosong,
    // dan itu jawaban yang benar.
    async function muatDaftarKasir() {
        const sel = document.getElementById('exportKasir');
        if (!sel) return;
        try {
            const res = await fetch(`${API_BASE}/users`, fetchOptions());
            if (!res.ok) return;

            ((await res.json()).data || [])
                .filter((u) => u.role === 'kasir')
                .forEach((u) => {
                    const opt = document.createElement('option');
                    opt.value = u.id;
                    // Kasir nonaktif tetap dipilih: laporan bulan lalu masih
                    // berisi transaksinya, dan itu justru yang dicari saat
                    // seseorang sudah berhenti kerja.
                    opt.textContent = u.is_active ? u.nama : `${u.nama} (nonaktif)`;
                    sel.appendChild(opt);
                });
        } catch (e) {
        }
    }

    function terapkanRentang() {
        initRevenueUkuran();
        updatePeriodeLabel();
    }
    document.getElementById('exportStart').addEventListener('change', terapkanRentang);
    document.getElementById('exportEnd').addEventListener('change', terapkanRentang);

    function debounce(fn, delay) {
        let timer;
        return function (...args) {
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, args), delay);
        };
    }

    document.getElementById('unduhBtn').addEventListener('click', async function () {
        const btn = this;
        errorEl.style.display = 'none';

        const s = document.getElementById('exportStart').value;
        const e = document.getElementById('exportEnd').value;

        if (s && e && s > e) {
            showError('Tanggal akhir tidak boleh lebih awal dari tanggal mulai.');
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Menyiapkan...';

        try {
            const cek = await fetch(`${API_BASE}/dashboard/ringkasan${rentangQS()}`, fetchOptions());
            if (cek.ok) {
                const cekJson = await cek.json();
                if (cekJson.data_tersedia === false) {
                    throw new Error('Tidak ada data di rentang tanggal itu, tidak bisa diunduh. (Data tersedia Juni–Juli.)');
                }
            }

            const kasirId = document.getElementById('exportKasir')?.value || '';
            const paramKasir = kasirId ? (rentangQS() ? '&' : '?') + 'kasir_user_id=' + kasirId : '';

            const res = await fetch(`${API_BASE}/laporan/export${rentangQS()}${paramKasir}`, fetchOptions());
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
        await Promise.all([
            initRfm(), initSwitch(), initRevenueUkuran(),
            updatePeriodeLabel(), muatDaftarKasir(),
        ]);
    })();
})();