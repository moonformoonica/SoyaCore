    document.addEventListener('DOMContentLoaded', function () {
        // ---- Isi nama & role dari localStorage (bukan Auth::user() Blade,
        // karena auth di app ini pakai token API + localStorage) ----
        const userMenuToggle = document.getElementById('userMenuToggle');
        const userDropdown = document.getElementById('userDropdown');
        const userNameEl = document.getElementById('userName');
        const userRoleEl = document.getElementById('userRole');
        try {
            const rawUser = localStorage.getItem('auth_user');
            if (rawUser) {
                const user = JSON.parse(rawUser);
                userNameEl.textContent = user.nama || 'Pengguna';
                userRoleEl.textContent = user.role || '';
            } else {
                userNameEl.textContent = 'Belum login';
            }
        } catch (e) {
            userNameEl.textContent = 'Pengguna';
        }
        // Inisial nama dipakai selama akun belum punya foto. Tidak ada lagi
        // foto bawaan: satu foto yang sama di semua akun lebih menyesatkan
        // daripada tidak ada foto sama sekali.
        function inisialDari(nama) {
            return String(nama || '')
                .trim()
                .split(/\s+/)
                .slice(0, 2)
                .map((k) => k[0] || '')
                .join('')
                .toUpperCase();
        }

        function tampilkanAvatar(nama, foto) {
            const img = document.getElementById('userAvatar');
            const inisial = document.getElementById('userAvatarInisial');
            if (!img || !inisial) return;

            if (foto) {
                img.src = foto;
                img.hidden = false;
                inisial.hidden = true;
            } else {
                img.removeAttribute('src');
                img.hidden = true;
                inisial.hidden = false;
                inisial.textContent = inisialDari(nama) || '?';
            }
        }

        (function muatFotoHeader() {
            try {
                const raw = localStorage.getItem('auth_user');
                const user = raw ? JSON.parse(raw) : null;
                const foto = user?.id ? localStorage.getItem('profil_foto_' + user.id) : null;
                tampilkanAvatar(user?.nama, foto);
            } catch (e) {
                tampilkanAvatar(null, null);
            }
        })();

        // Data di localStorage cuma potret saat login, kalau nama/role di
        // database berubah setelahnya, header ikut basi. Segarkan dari
        // GET /api/me supaya nggak perlu logout dulu.
        (async function segarkanProfil() {
            const token = localStorage.getItem('auth_token');
            if (!token) return;

            try {
                const res = await fetch('/api/me', {
                    headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + token },
                });
                if (!res.ok) return;

                const user = (await res.json()).user;
                if (!user) return;

                localStorage.setItem('auth_user', JSON.stringify(user));
                userNameEl.textContent = user.nama || 'Pengguna';
                userRoleEl.textContent = user.role || '';

                // Inisialnya ikut disegarkan; kalau tidak, akun yang baru
                // ganti nama tetap memperlihatkan inisial lama.
                let foto = null;
                try { foto = localStorage.getItem('profil_foto_' + user.id); } catch (e) {}
                tampilkanAvatar(user.nama, foto);
            } catch (e) {
            }
        })();

        // ---- Quick navigate search ----
        // Daftar halaman dirender server (butuh route() dan pengecekan role),
        // lalu dititipkan lewat <script type="application/json"> di blade.
        // Itu ELEMEN DATA, bukan kode, jadi berkas ini tetap JavaScript murni
        // dan tidak perlu diproses Blade.
        const dataHalaman = document.getElementById('quickNavData');
        const searchablePages = dataHalaman ? JSON.parse(dataHalaman.textContent) : [];

        const quickNavInput = document.getElementById('quickNavInput');
        const quickNavSuggestions = document.getElementById('quickNavSuggestions');
        let highlightedIndex = -1;
        let currentResults = [];

        function currentRole() {
            try {
                const rawUser = localStorage.getItem('auth_user');
                return rawUser ? JSON.parse(rawUser).role : null;
            } catch (e) {
                return null;
            }
        }

        function renderSuggestions(results) {
            currentResults = results;
            highlightedIndex = -1;

            if (results.length === 0) {
                quickNavSuggestions.innerHTML = '<div class="quicknav-empty">Halaman tidak ditemukan</div>';
                quickNavSuggestions.classList.add('open');
                return;
            }

            quickNavSuggestions.innerHTML = results.map(function (page, i) {
                return '<a href="' + page.url + '" class="quicknav-item" data-index="' + i + '">' +
                    '<i class="fa-solid ' + page.icon + '"></i>' +
                    '<span>' + page.label + '</span>' +
                    '</a>';
            }).join('');

            quickNavSuggestions.classList.add('open');
        }

        function search(keyword) {
            const role = currentRole();
            const kw = keyword.trim().toLowerCase();

            const visible = searchablePages.filter(function (page) {
                return page.role === null || page.role === role;
            });

            if (kw === '') {
                renderSuggestions(visible);
                return;
            }

            renderSuggestions(visible.filter(function (page) {
                return page.label.toLowerCase().includes(kw);
            }));
        }

        quickNavInput.addEventListener('focus', function () {
            search(quickNavInput.value);
        });

        quickNavInput.addEventListener('input', function () {
            search(quickNavInput.value);
        });

        quickNavInput.addEventListener('keydown', function (e) {
            const items = quickNavSuggestions.querySelectorAll('.quicknav-item');
            if (items.length === 0) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                highlightedIndex = Math.min(highlightedIndex + 1, items.length - 1);
                items.forEach((el, i) => el.classList.toggle('highlighted', i === highlightedIndex));
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                highlightedIndex = Math.max(highlightedIndex - 1, 0);
                items.forEach((el, i) => el.classList.toggle('highlighted', i === highlightedIndex));
            } else if (e.key === 'Enter') {
                e.preventDefault();
                const target = highlightedIndex >= 0 ? items[highlightedIndex] : items[0];
                if (target) window.location.href = target.getAttribute('href');
            } else if (e.key === 'Escape') {
                quickNavSuggestions.classList.remove('open');
                quickNavInput.blur();
            }
        });

        document.addEventListener('click', function (e) {
            if (!document.getElementById('quickNav').contains(e.target)) {
                quickNavSuggestions.classList.remove('open');
            }
        });

        const notifToggle = document.getElementById('notifToggle');
        const notifDropdown = document.getElementById('notifDropdown');
        const notifMarkRead = document.getElementById('notifMarkRead');

        notifToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            userDropdown.classList.remove('open');
            userMenuToggle.setAttribute('aria-expanded', 'false');

            const isOpen = notifDropdown.classList.toggle('open');
            notifToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
        const notifList = document.getElementById('notifList');
        const notifBadge = document.getElementById('notifBadge');
        const KUNCI_DIBACA = 'notif_pesanan_dibaca';
        let sudahDibaca = new Set();
        try {
            sudahDibaca = new Set(JSON.parse(localStorage.getItem(KUNCI_DIBACA) || '[]'));
        } catch (e) {  }

        function simpanDibaca() {
            try {
                localStorage.setItem(KUNCI_DIBACA, JSON.stringify([...sudahDibaca]));
            } catch (e) { /* kuota penuh, notifikasi tetap jalan, cuma tidak persist */ }
        }

        let pesananNotif = [];
        let idPernahTampil = null; 

        function rupiahNotif(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID'); }

        function waktuRelatif(iso) {
            if (!iso) return '';
            const selisih = Math.floor((Date.now() - new Date(iso).getTime()) / 60000);
            if (Number.isNaN(selisih)) return '';
            if (selisih < 1) return 'Baru saja';
            if (selisih < 60) return selisih + ' menit lalu';
            const jam = Math.floor(selisih / 60);
            return jam < 24 ? jam + ' jam lalu' : Math.floor(jam / 24) + ' hari lalu';
        }

        function renderNotif() {
            const belumDibaca = pesananNotif.filter(t => !sudahDibaca.has(t.id)).length;

            notifBadge.textContent = belumDibaca;
            notifBadge.style.display = belumDibaca ? 'flex' : 'none';

            if (pesananNotif.length === 0) {
                notifList.innerHTML = '<div class="notif-empty">Belum ada pesanan baru.</div>';
                return;
            }

            notifList.innerHTML = pesananNotif.map(function (t) {
                const baru = !sudahDibaca.has(t.id);
                const nama = (t.customer && t.customer.nama) ? t.customer.nama : 'Tanpa nama';
                return `<div class="notif-item ${baru ? 'unread' : ''}">
                    <span class="notif-icon notif-icon-info">
                        <i class="fa-solid fa-cart-shopping"></i>
                    </span>
                    <span class="notif-text">
                        <span class="notif-title">Pesanan SoyaScan ${t.kode_pesanan || ''}</span>
                        <span class="notif-desc">${nama}, ${rupiahNotif(t.total)}</span>
                        <span class="notif-time">${waktuRelatif(t.created_at)}</span>
                    </span>
                    ${baru ? '<span class="notif-dot"></span>' : ''}
                </div>`;
            }).join('');
        }

        function beriTahuPesananBaru(daftarBaru) {
            if (!('Notification' in window) || Notification.permission !== 'granted') return;

            daftarBaru.forEach(function (t) {
                const nama = (t.customer && t.customer.nama) ? t.customer.nama : 'Tanpa nama';
                new Notification('Pesanan SoyaScan baru', {
                    body: `${t.kode_pesanan || ''} · ${nama} · ${rupiahNotif(t.total)}`,
                    tag: 'pesanan-' + t.id, 
                });
            });
        }

        async function muatNotif() {
            const token = localStorage.getItem('auth_token');
            if (!token) return;

            try {
                const res = await fetch('/api/transaksi?status=pending&per_page=50', {
                    headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + token },
                });
                if (!res.ok) return;

                const rows = ((await res.json()).data || []).filter(t => t.sumber === 'self_order');
                const idSekarang = new Set(rows.map(t => t.id));

                if (idPernahTampil !== null) {
                    beriTahuPesananBaru(rows.filter(t => !idPernahTampil.has(t.id)));
                }
                idPernahTampil = idSekarang;

                sudahDibaca = new Set([...sudahDibaca].filter(id => idSekarang.has(id)));
                simpanDibaca();

                pesananNotif = rows;
                renderNotif();
            } catch (e) {
            }
        }
        
        notifToggle.addEventListener('click', function () {
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission();
            }
        });

        notifMarkRead.addEventListener('click', function () {
            pesananNotif.forEach(t => sudahDibaca.add(t.id));
            simpanDibaca();
            renderNotif();
        });

        muatNotif();
        setInterval(muatNotif, 10000);

        document.addEventListener('click', function (e) {
            if (!document.getElementById('notifMenu').contains(e.target)) {
                notifDropdown.classList.remove('open');
                notifToggle.setAttribute('aria-expanded', 'false');
            }
        });

        userMenuToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            notifDropdown.classList.remove('open');
            notifToggle.setAttribute('aria-expanded', 'false');

            const isOpen = userDropdown.classList.toggle('open');
            userMenuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        document.addEventListener('click', function (e) {
            if (!document.getElementById('userMenu').contains(e.target)) {
                userDropdown.classList.remove('open');
                userMenuToggle.setAttribute('aria-expanded', 'false');
            }
        });

        const logoutBtn = document.getElementById('logoutBtn');
        const logoutOverlay = document.getElementById('logoutConfirmOverlay');
        const logoutCancelBtn = document.getElementById('logoutCancelBtn');
        const logoutConfirmBtn = document.getElementById('logoutConfirmBtn');

        logoutBtn.addEventListener('click', function () {
            userDropdown.classList.remove('open');
            userMenuToggle.setAttribute('aria-expanded', 'false');
            logoutOverlay.classList.add('open');
        });

        logoutCancelBtn.addEventListener('click', function () {
            logoutOverlay.classList.remove('open');
        });

        logoutOverlay.addEventListener('click', function (e) {
            if (e.target === logoutOverlay) {
                logoutOverlay.classList.remove('open');
            }
        });

        logoutConfirmBtn.addEventListener('click', function () {
            const token = localStorage.getItem('auth_token');

            localStorage.removeItem('auth_token');
            localStorage.removeItem('auth_user');
            window.location.href = '/login';

            if (token) {
                fetch('/api/logout', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Authorization': 'Bearer ' + token,
                    },
                }).catch(() => {});
            }
        });
    });
