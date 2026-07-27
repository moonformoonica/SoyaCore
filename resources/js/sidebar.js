document.addEventListener('DOMContentLoaded', function () {

    const toggleBtn = document.getElementById('sidebarToggle');
    const sidebar   = document.getElementById('sidebar');

    if (!toggleBtn || !sidebar) return;

    // Cek status collapse terakhir dari localStorage (biar tetap collapsed pas di-refresh)
    if (localStorage.getItem('sidebarCollapsed') === 'true') {
        sidebar.classList.add('collapsed');
    }

    toggleBtn.addEventListener('click', function () {
        sidebar.classList.toggle('collapsed');

        // Simpan status ke localStorage
        localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
    });

});