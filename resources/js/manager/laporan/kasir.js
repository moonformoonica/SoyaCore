(function () {
    const API = '/api/laporan/kasir';
    const KOLOM = 12;

    function fetchOptions() {
        const token = localStorage.getItem('auth_token');
        return {
            headers: {
                'Accept': 'application/json',
                ...(token ? { 'Authorization': 'Bearer ' + token } : {}),
            },
        };
    }

    const rupiah = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID');
    const angka = (n) => Number(n || 0).toLocaleString('id-ID');

    const body = document.getElementById('lkBody');
    const errorEl = document.getElementById('lkError');
    const mulaiEl = document.getElementById('lkMulai');
    const selesaiEl = document.getElementById('lkSelesai');

    function showError(msg) {
        errorEl.textContent = msg;
        errorEl.style.display = 'block';
    }

    function fmtTanggal(iso) {
        if (!iso) return null;
        const d = new Date(iso + 'T00:00:00');
        return Number.isNaN(d.getTime())
            ? null
            : d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
    }

    function selMetode(rincian, kunci) {
        const d = (rincian || {})[kunci] || {};
        return angka(d.jumlah || 0) + '× · ' + rupiah(d.total || 0);
    }
    function totalMetode(rows) {
        const hasil = { cash: { jumlah: 0, total: 0 }, qris: { jumlah: 0, total: 0 } };

        rows.forEach(function (r) {
            const rincian = r.rincian_metode_bayar || {};
            ['cash', 'qris'].forEach(function (kunci) {
                const d = rincian[kunci] || {};
                hasil[kunci].jumlah += Number(d.jumlah || 0);
                hasil[kunci].total += Number(d.total || 0);
            });
        });

        return hasil;
    }

    function baris(r, isTotal) {
        return '<tr' + (isTotal ? ' class="lk-total"' : '') + '>'
            + '<td class="lk-nama">' + (isTotal ? 'TOTAL' : (r.nama || '—')) + '</td>'
            + '<td>' + angka(r.jumlah_transaksi) + '</td>'
            + '<td>' + rupiah(r.total_omzet) + '</td>'
            + '<td>' + angka(r.total_qty) + '</td>'
            + '<td>' + rupiah(r.rata_rata_transaksi) + '</td>'
            + '<td>' + selMetode(r.rincian_metode_bayar, 'cash') + '</td>'
            + '<td>' + selMetode(r.rincian_metode_bayar, 'qris') + '</td>'
            + '<td>' + rupiah(r.total_diskon) + '</td>'
            + '<td>' + angka(r.total_poin_diberikan) + '</td>'
            + '<td>' + angka(r.total_poin_ditukar) + '</td>'
            + '<td>' + angka(r.jumlah_pembatalan) + '</td>'
            + '<td>' + rupiah(r.nilai_dibatalkan) + '</td>'
            + '</tr>';
    }

    function render(rows, meta) {
        if (!rows.length) {
            body.innerHTML = '<tr><td colspan="' + KOLOM + '" class="lk-state">'
                + 'Tidak ada transaksi di rentang ini.</td></tr>';
        } else {
            // Baris TOTAL diambil dari `meta`, bukan dijumlah ulang di klien,
            // supaya angkanya tidak bisa berbeda dari perhitungan backend.
            const totalRow = meta
                ? { ...meta, rincian_metode_bayar: totalMetode(rows) }
                : null;

            body.innerHTML = rows.map((r) => baris(r, false)).join('')
                + (totalRow ? baris(totalRow, true) : '');
        }

        const m = meta || {};

        document.getElementById('lkOmzet').textContent = rupiah(m.total_omzet);
        document.getElementById('lkTransaksi').textContent = angka(m.jumlah_transaksi);
        document.getElementById('lkJumlahKasir').textContent = angka(m.jumlah_kasir ?? rows.length);
        document.getElementById('lkPembatalan').textContent = angka(m.jumlah_pembatalan);

        // Rentang tanggal ada langsung di `meta`, bukan di dalam `meta.periode`.
        const mulai = fmtTanggal(m.tanggal_mulai);
        const selesai = fmtTanggal(m.tanggal_selesai);

        if (mulai && selesai) document.getElementById('lkPeriode').textContent = mulai + ' – ' + selesai;
        else if (mulai) document.getElementById('lkPeriode').textContent = 'sejak ' + mulai;
        else if (selesai) document.getElementById('lkPeriode').textContent = 'sampai ' + selesai;
        else document.getElementById('lkPeriode').textContent = 'semua data';
    }

    async function muat() {
        errorEl.style.display = 'none';

        const mulai = mulaiEl.value;
        const selesai = selesaiEl.value;
        if (mulai && selesai && mulai > selesai) {
            return showError('Tanggal selesai tidak boleh lebih awal dari tanggal mulai.');
        }

        body.innerHTML = '<tr><td colspan="' + KOLOM + '" class="lk-state">Memuat data…</td></tr>';

        const params = new URLSearchParams();
        // Tanpa tanggal = seluruh data; backend yang menentukan rentangnya.
        if (mulai) params.set('tanggal_mulai', mulai);
        if (selesai) params.set('tanggal_selesai', selesai);

        try {
            const qs = params.toString();
            const res = await fetch(API + (qs ? '?' + qs : ''), fetchOptions());
            const json = await res.json().catch(() => ({}));

            if (res.status === 403) throw new Error('Halaman ini khusus manager.');
            if (!res.ok) throw new Error(json.message || 'Gagal memuat laporan kasir.');

            render(json.data || [], json.meta || null);
        } catch (e) {
            body.innerHTML = '<tr><td colspan="' + KOLOM + '" class="lk-state">Gagal memuat data.</td></tr>';
            showError(e.message);
        }
    }

    const unduhBtn = document.getElementById('lkUnduhBtn');

    unduhBtn?.addEventListener('click', async function () {
        const token = localStorage.getItem('auth_token');
        if (!token) return showError('Sesi berakhir, silakan login ulang.');

        const mulai = mulaiEl.value;
        const selesai = selesaiEl.value;
        if (mulai && selesai && mulai > selesai) {
            return showError('Tanggal selesai tidak boleh lebih awal dari tanggal mulai.');
        }

        errorEl.style.display = 'none';
        unduhBtn.disabled = true;
        unduhBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyiapkan...';

        const params = new URLSearchParams();
        if (mulai) params.set('tanggal_mulai', mulai);
        if (selesai) params.set('tanggal_selesai', selesai);

        try {
            const qs = params.toString();
            const res = await fetch(API + '/export' + (qs ? '?' + qs : ''), fetchOptions());

            if (!res.ok) {
                const json = await res.json().catch(() => ({}));
                throw new Error(json.message
                    || (res.status === 403
                        ? 'Unduhan laporan hanya untuk akun manager.'
                        : 'Gagal mengunduh laporan kasir (' + res.status + ').'));
            }

            const blob = await res.blob();
            const cocok = (res.headers.get('Content-Disposition') || '').match(/filename="?([^"]+)"?/);

            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = cocok ? cocok[1] : 'Laporan Kasir_SoyaCore.xlsx';
            document.body.appendChild(a);
            a.click();
            a.remove();
            window.URL.revokeObjectURL(url);
        } catch (e) {
            showError(e.message || 'Gagal mengunduh laporan kasir.');
        } finally {
            unduhBtn.disabled = false;
            unduhBtn.innerHTML = '<i class="fa-solid fa-download"></i> Unduh';
        }
    });

    [mulaiEl, selesaiEl].forEach(function (el) {
        el.addEventListener('change', muat);
    });

    document.getElementById('lkReset').addEventListener('click', function () {
        mulaiEl.value = '';
        selesaiEl.value = '';
        muat();
    });

    muat();
})();
