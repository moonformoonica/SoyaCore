/**
 * Perilaku sidebar: tombol ciut/lebar + penyaringan menu per role.
 *
 * Sebelumnya kode ini ada DUA versi — satu di berkas ini (tidak pernah dimuat)
 * dan satu lagi ditulis inline di layouts/app.blade.php. Keduanya memakai kunci
 * localStorage yang berbeda (`sidebarCollapsed` vs `sidebar_collapsed`), jadi
 * versi berkas ini tidak akan pernah mengenali preferensi yang sudah tersimpan.
 * Sekarang tinggal satu: versi ini, dimuat lewat @vite di layout.
 */
document.addEventListener('DOMContentLoaded', function () {

    const toggleBtn = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');

    if (!toggleBtn || !sidebar) return;

    // Kunci yang dipertahankan adalah `sidebar_collapsed` — itu yang dipakai
    // versi inline yang selama ini benar-benar jalan, jadi preferensi pengguna
    // yang sudah tersimpan tidak ikut hilang saat penggabungan ini.
    const KUNCI = 'sidebar_collapsed';

    if (localStorage.getItem(KUNCI) === '1') {
        sidebar.classList.add('collapsed');
    }

    toggleBtn.addEventListener('click', function () {
        sidebar.classList.toggle('collapsed');
        localStorage.setItem(KUNCI, sidebar.classList.contains('collapsed') ? '1' : '0');
    });

    // Menu yang bukan untuk role ini disembunyikan. Dilakukan di klien karena
    // autentikasi aplikasi ini memakai token di localStorage, bukan sesi Blade,
    // sehingga role-nya tidak tersedia saat halaman dirender server.
    try {
        const rawUser = localStorage.getItem('auth_user');
        const role = rawUser ? JSON.parse(rawUser).role : null;

        sidebar.querySelectorAll('[data-role]').forEach(function (link) {
            if (link.dataset.role !== role) {
                link.style.display = 'none';
            }
        });
    } catch (e) {
        // localStorage rusak — biarkan seluruh menu tampil apa adanya
    }

});
