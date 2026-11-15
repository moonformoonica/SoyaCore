/**
 * Perilaku sidebar: tombol ciut/lebar + penyaringan menu per role.
 *
 * Sebelumnya kode ini ada DUA versi, satu di berkas ini (tidak pernah dimuat)
 * dan satu lagi ditulis inline di layouts/app.blade.php. Keduanya memakai kunci
 * localStorage yang berbeda (`sidebarCollapsed` vs `sidebar_collapsed`), jadi
 * versi berkas ini tidak akan pernah mengenali preferensi yang sudah tersimpan.
 * Sekarang tinggal satu: versi ini, dimuat lewat @vite di layout.
 */
document.addEventListener('DOMContentLoaded', function () {

    const toggleBtn = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    const closeBtn = document.getElementById('sidebarClose');

    if (!toggleBtn || !sidebar || !backdrop || !closeBtn) return;

    // Kunci yang dipertahankan adalah `sidebar_collapsed`, itu yang dipakai
    // versi inline yang selama ini benar-benar jalan, jadi preferensi pengguna
    // yang sudah tersimpan tidak ikut hilang saat penggabungan ini.
    const KUNCI = 'sidebar_collapsed';
    const mobileViewport = window.matchMedia('(max-width: 768px)');

    let user;
    try {
        const token = localStorage.getItem('auth_token');
        user = JSON.parse(localStorage.getItem('auth_user') || 'null');
        if (!token || !user || !['manager', 'kasir'].includes(user.role)) {
            throw new Error('Sesi tidak valid.');
        }
    } catch (e) {
        try {
            localStorage.removeItem('auth_token');
            localStorage.removeItem('auth_user');
        } catch (storageError) {}
        window.location.replace('/login');
        return;
    }

    const path = window.location.pathname;
    const halamanManager = path === '/laporan'
        || path === '/laporan-kasir'
        || path === '/menu/create'
        || /^\/menu\/\d+\/edit$/.test(path);

    if (user.role !== 'manager' && halamanManager) {
        window.location.replace('/dashboard');
        return;
    }

    function setMenuTerbuka(terbuka) {
        sidebar.classList.toggle('mobile-open', terbuka);
        backdrop.hidden = !terbuka;
        document.body.classList.toggle('sidebar-open', terbuka);
        toggleBtn.setAttribute('aria-expanded', String(terbuka));
        toggleBtn.setAttribute('aria-label', terbuka ? 'Tutup navigasi' : 'Buka navigasi');
        if (terbuka) {
            const firstLink = sidebar.querySelector('.menu a:not([style*="display: none"])');
            firstLink?.focus();
        }
    }

    function sesuaikanViewport() {
        if (mobileViewport.matches) {
            sidebar.classList.remove('collapsed');
            setMenuTerbuka(false);
            return;
        }

        setMenuTerbuka(false);
        sidebar.classList.toggle('collapsed', localStorage.getItem(KUNCI) === '1');
        toggleBtn.setAttribute('aria-label', sidebar.classList.contains('collapsed')
            ? 'Lebarkan navigasi'
            : 'Ciutkan navigasi');
        toggleBtn.setAttribute('aria-expanded', String(!sidebar.classList.contains('collapsed')));
    }

    sesuaikanViewport();
    mobileViewport.addEventListener('change', sesuaikanViewport);

    toggleBtn.addEventListener('click', function () {
        if (mobileViewport.matches) {
            setMenuTerbuka(!sidebar.classList.contains('mobile-open'));
            return;
        }

        sidebar.classList.toggle('collapsed');
        localStorage.setItem(KUNCI, sidebar.classList.contains('collapsed') ? '1' : '0');
        toggleBtn.setAttribute('aria-label', sidebar.classList.contains('collapsed')
            ? 'Lebarkan navigasi'
            : 'Ciutkan navigasi');
        toggleBtn.setAttribute('aria-expanded', String(!sidebar.classList.contains('collapsed')));
    });

    backdrop.addEventListener('click', function () {
        setMenuTerbuka(false);
        toggleBtn.focus();
    });

    closeBtn.addEventListener('click', function () {
        setMenuTerbuka(false);
        toggleBtn.focus();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && sidebar.classList.contains('mobile-open')) {
            setMenuTerbuka(false);
            toggleBtn.focus();
        }
    });

    sidebar.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
            if (mobileViewport.matches) {
                setMenuTerbuka(false);
                toggleBtn.focus();
            }
        });
    });

    // Menu yang bukan untuk role ini disembunyikan. Dilakukan di klien karena
    // autentikasi aplikasi ini memakai token di localStorage, bukan sesi Blade,
    // sehingga role-nya tidak tersedia saat halaman dirender server.
    try {
        sidebar.querySelectorAll('[data-role]').forEach(function (link) {
            if (link.dataset.role !== user.role) {
                link.style.display = 'none';
            }
        });
    } catch (e) {
        window.location.replace('/login');
    }

});
