<header class="app-header">

  {{-- Sidebar collapse toggle --}}
  <button type="button" class="toggle-btn" id="sidebarToggle" aria-label="Toggle sidebar">
    <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
      <path d="M15 6l-6 6 6 6"></path>
      <path d="M20 6l-6 6 6 6"></path>
    </svg>
  </button>

  {{-- Quick navigate search --}}
  @php
      $searchablePages = [
          ['label' => 'Dashboard', 'url' => route('dashboard'), 'icon' => 'fa-table-cells-large', 'role' => null],
          ['label' => 'Transaksi', 'url' => route('manager.transaksi'), 'icon' => 'fa-money-bill-1', 'role' => null],
          ['label' => 'Menu', 'url' => route('manager.menu'), 'icon' => 'fa-cube', 'role' => null],
          ['label' => 'Loyalty', 'url' => route('manager.loyalty'), 'icon' => 'fa-ticket', 'role' => null],
          ['label' => 'Laporan', 'url' => route('manager.laporan'), 'icon' => 'fa-chart-column', 'role' => 'manager'],
      ];
  @endphp
  <div class="search-box" id="quickNav" style="position: relative;">
    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="11" cy="11" r="7"></circle>
      <path d="m21 21-4.35-4.35"></path>
    </svg>
    <input type="text" id="quickNavInput" placeholder="Cari..." autocomplete="off">
    <div class="quicknav-suggestions" id="quickNavSuggestions"></div>
  </div>

  {{-- Right side actions --}}
  <div class="header-actions">

    {{-- Notification dropdown --}}
    @php
        // DUMMY — ganti jadi hasil fetch() ke API notifikasi begitu backend-nya siap.
        $dummyNotifications = [
            ['icon' => 'fa-triangle-exclamation', 'tone' => 'warning', 'title' => 'Stok Reguler menipis', 'desc' => 'Sisa 8 pcs, segera restock.', 'time' => '5 menit lalu', 'unread' => true],
            ['icon' => 'fa-cart-shopping', 'tone' => 'info', 'title' => 'Transaksi baru masuk', 'desc' => 'GrabFood — Rp 45.000', 'time' => '20 menit lalu', 'unread' => true],
            ['icon' => 'fa-chart-column', 'tone' => 'success', 'title' => 'Laporan bulanan siap', 'desc' => 'Laporan Juni 2026 sudah bisa diunduh.', 'time' => '1 jam lalu', 'unread' => true],
            ['icon' => 'fa-user-plus', 'tone' => 'info', 'title' => 'Pelanggan baru terdaftar', 'desc' => 'Nadia bergabung lewat program Loyalty.', 'time' => 'Kemarin', 'unread' => false],
        ];
        $unreadCount = collect($dummyNotifications)->where('unread', true)->count();
    @endphp
    <div class="notif-menu" id="notifMenu">

      <button type="button" class="notif-btn" id="notifToggle" aria-label="Notifikasi" aria-haspopup="true" aria-expanded="false">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"></path>
          <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
        </svg>
        @if($unreadCount)
          <span class="badge">{{ $unreadCount }}</span>
        @endif
      </button>

      <div class="notif-dropdown" id="notifDropdown">
        <div class="notif-dropdown-header">
          <span>Notifikasi</span>
          <button type="button" class="notif-mark-read" id="notifMarkRead">Tandai semua dibaca</button>
        </div>
        <div class="notif-list">
          @forelse($dummyNotifications as $n)
            <div class="notif-item {{ $n['unread'] ? 'unread' : '' }}">
              <span class="notif-icon notif-icon-{{ $n['tone'] }}">
                <i class="fa-solid {{ $n['icon'] }}"></i>
              </span>
              <span class="notif-text">
                <span class="notif-title">{{ $n['title'] }}</span>
                <span class="notif-desc">{{ $n['desc'] }}</span>
                <span class="notif-time">{{ $n['time'] }}</span>
              </span>
              @if($n['unread'])
                <span class="notif-dot"></span>
              @endif
            </div>
          @empty
            <div class="notif-empty">Belum ada notifikasi.</div>
          @endforelse
        </div>
      </div>

    </div>

    {{-- Profile dropdown --}}
    <div class="user-menu" id="userMenu">

      <button type="button" class="user-profile" id="userMenuToggle" aria-haspopup="true" aria-expanded="false">
        <span class="avatar">
          <img id="userAvatar" src="{{ asset('images/profil.jpg') }}" data-default="{{ asset('images/profil.jpg') }}" alt="Foto profil">
        </span>
        <span class="user-info">
          <span class="name-row">
            <span class="user-name" id="userName">Memuat...</span>
            <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="m6 9 6 6 6-6"></path>
            </svg>
          </span>
          <span class="user-role" id="userRole">&nbsp;</span>
        </span>
      </button>

      <div class="dropdown-menu" id="userDropdown">
        <a href="{{ Route::has('pengaturan') ? route('pengaturan') : '/pengaturan' }}" class="dropdown-item">
          <i class="fa-solid fa-gear"></i>
          <span>Pengaturan</span>
        </a>
        <div class="dropdown-divider"></div>
        <button type="button" class="dropdown-item dropdown-item-danger" id="logoutBtn">
          <i class="fa-solid fa-right-from-bracket"></i>
          <span>Keluar</span>
        </button>
      </div>

    </div>

  </div>

</header>

{{-- Modal konfirmasi logout --}}
<div class="confirm-overlay" id="logoutConfirmOverlay">
  <div class="confirm-box">
    <div class="confirm-icon">
      <i class="fa-solid fa-right-from-bracket"></i>
    </div>
    <h3>Yakin mau keluar?</h3>
    <p>Kamu perlu login ulang untuk masuk lagi ke SoyaCore.</p>
    <div class="confirm-actions">
      <button type="button" class="btn-cancel" id="logoutCancelBtn">Batal</button>
      <button type="button" class="btn-confirm" id="logoutConfirmBtn">Ya, Keluar</button>
    </div>
  </div>
</div>

<style>
    .user-menu { position: relative; }

    .user-profile {
        background: none; border: none; cursor: pointer;
        display: flex; align-items: center; gap: 10px;
        font-family: inherit;
    }

    .dropdown-menu {
        position: absolute; top: calc(100% + 10px); right: 0;
        min-width: 200px; background: #fff; border-radius: 10px;
        box-shadow: 0 8px 24px rgba(0,0,0,.12); padding: 8px;
        display: none; z-index: 100;
    }

    .dropdown-menu.open { display: block; }

    .dropdown-item {
        display: flex; align-items: center; gap: 10px;
        width: 100%; padding: 10px 12px; border-radius: 8px;
        text-decoration: none; color: #3F3F46; font-size: 14px;
        background: none; border: none; cursor: pointer; text-align: left;
        font-family: inherit;
    }

    .dropdown-item:hover { background: #F6F6F6; }

    .dropdown-item i { width: 16px; color: #7A7A7A; }

    .dropdown-item-danger { color: #B3261E; }
    .dropdown-item-danger i { color: #B3261E; }
    .dropdown-item-danger:hover { background: #FDECEA; }

    .dropdown-divider { height: 1px; background: #ECECEC; margin: 6px 4px; }

    .chevron { transition: transform .2s ease; }
    .user-profile[aria-expanded="true"] .chevron { transform: rotate(180deg); }

    /* Modal konfirmasi logout */
    .confirm-overlay {
        display: none;
        position: fixed; inset: 0; background: rgba(0,0,0,.45);
        align-items: center; justify-content: center; z-index: 1000;
    }

    .confirm-overlay.open { display: flex; }

    .confirm-box {
        background: #fff; border-radius: 14px; padding: 28px 24px;
        width: 90%; max-width: 320px; text-align: center;
        box-shadow: 0 12px 32px rgba(0,0,0,.18);
    }

    .confirm-icon {
        width: 52px; height: 52px; border-radius: 50%;
        background: #FDECEA; color: #B3261E;
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 16px; font-size: 20px;
    }

    .confirm-box h3 { font-size: 16px; color: #1F1F1F; margin-bottom: 6px; }
    .confirm-box p { font-size: 13px; color: #7A7A7A; margin-bottom: 20px; }

    .confirm-actions { display: flex; gap: 10px; }

    .confirm-actions button {
        flex: 1; padding: 10px 0; border-radius: 8px; border: none;
        font-family: inherit; font-size: 14px; font-weight: 500; cursor: pointer;
    }

    .btn-cancel { background: #F1F1F1; color: #3F3F46; }
    .btn-cancel:hover { background: #E6E6E6; }

    .btn-confirm { background: #B3261E; color: #fff; }
    .btn-confirm:hover { background: #96201A; }

    /* Quick navigate suggestions */
    .quicknav-suggestions {
        display: none;
        position: absolute; top: calc(100% + 8px); left: 0; right: 0;
        background: #fff; border-radius: 10px;
        box-shadow: 0 8px 24px rgba(0,0,0,.12);
        padding: 6px; z-index: 100; max-height: 280px; overflow-y: auto;
    }

    .quicknav-suggestions.open { display: block; }

    .quicknav-item {
        display: flex; align-items: center; gap: 10px;
        padding: 9px 12px; border-radius: 8px; cursor: pointer;
        text-decoration: none; color: #3F3F46; font-size: 14px;
    }

    .quicknav-item i { width: 16px; color: #7A7A7A; }

    .quicknav-item:hover,
    .quicknav-item.highlighted { background: #F6F6F6; }

    .quicknav-empty {
        padding: 10px 12px; font-size: 13px; color: #ABABAB;
    }

    /* Notification dropdown */
    .notif-menu { position: relative; }

    .notif-dropdown {
        display: none;
        position: absolute; top: calc(100% + 10px); right: 0;
        width: 320px; max-width: 88vw; background: #fff; border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0,0,0,.14); z-index: 100; overflow: hidden;
    }

    .notif-dropdown.open { display: block; }

    .notif-dropdown-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 14px 16px; border-bottom: 1px solid #ECECEC;
        font-size: 14px; font-weight: 600; color: #1F1F1F;
    }

    .notif-mark-read {
        background: none; border: none; cursor: pointer;
        font-family: inherit; font-size: 12px; font-weight: 500;
        color: #2F6B3F;
    }

    .notif-mark-read:hover { text-decoration: underline; }

    .notif-list { max-height: 340px; overflow-y: auto; }

    .notif-item {
        display: flex; align-items: flex-start; gap: 12px;
        padding: 12px 16px; border-bottom: 1px solid #F5F5F5;
        position: relative;
    }

    .notif-item:last-child { border-bottom: none; }
    .notif-item.unread { background: #F7FBF8; }

    .notif-icon {
        width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        font-size: 13px;
    }

    .notif-icon-warning { background: #FFF3CD; color: #8A6D00; }
    .notif-icon-info { background: #E3F1FF; color: #0B5FA5; }
    .notif-icon-success { background: #E3F5E8; color: #2F6B3F; }

    .notif-text { display: flex; flex-direction: column; gap: 2px; flex: 1; min-width: 0; }
    .notif-title { font-size: 13px; font-weight: 600; color: #1F1F1F; }
    .notif-desc { font-size: 12px; color: #6B6B6B; }
    .notif-time { font-size: 11px; color: #ABABAB; margin-top: 2px; }

    .notif-dot {
        width: 8px; height: 8px; border-radius: 50%; background: #2F9E5F;
        flex-shrink: 0; margin-top: 6px;
    }

    .notif-empty {
        padding: 24px 16px; text-align: center; font-size: 13px; color: #ABABAB;
    }
</style>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // ---- Isi nama & role dari localStorage (bukan Auth::user() Blade,
        // karena auth di app ini pakai token API + localStorage) ----
        const userMenuToggle = document.getElementById('userMenuToggle');
        const userDropdown = document.getElementById('userDropdown');
        const userNameEl = document.getElementById('userName');
        const userRoleEl = document.getElementById('userRole');

        // Nama diambil apa adanya dari `users.nama` (lewat API), supaya header,
        // detail transaksi, dan laporan selalu menampilkan nama yang sama.
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

        // Foto profil disimpan lokal per user (fitur upload di Pengaturan).
        (function muatFotoHeader() {
            try {
                const raw = localStorage.getItem('auth_user');
                const id = raw ? JSON.parse(raw).id : null;
                const foto = id ? localStorage.getItem('profil_foto_' + id) : null;
                const avatar = document.getElementById('userAvatar');
                if (avatar && foto) avatar.src = foto;
            } catch (e) { /* pakai default */ }
        })();

        // Data di localStorage cuma potret saat login — kalau nama/role di
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
            } catch (e) {
                // offline / token bermasalah — biarkan tampilan dari localStorage
            }
        })();

        // ---- Quick navigate search ----
        const searchablePages = @json($searchablePages);

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

        // ---- Notifikasi: buka/tutup dropdown ----
        const notifToggle = document.getElementById('notifToggle');
        const notifDropdown = document.getElementById('notifDropdown');
        const notifMarkRead = document.getElementById('notifMarkRead');

        notifToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            // Tutup dropdown profil dulu kalau lagi kebuka, biar nggak dobel.
            userDropdown.classList.remove('open');
            userMenuToggle.setAttribute('aria-expanded', 'false');

            const isOpen = notifDropdown.classList.toggle('open');
            notifToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        notifMarkRead.addEventListener('click', function () {
            document.querySelectorAll('.notif-item.unread').forEach(function (item) {
                item.classList.remove('unread');
                const dot = item.querySelector('.notif-dot');
                if (dot) dot.remove();
            });
            const badge = notifToggle.querySelector('.badge');
            if (badge) badge.remove();
        });

        document.addEventListener('click', function (e) {
            if (!document.getElementById('notifMenu').contains(e.target)) {
                notifDropdown.classList.remove('open');
                notifToggle.setAttribute('aria-expanded', 'false');
            }
        });

        // ---- Buka/tutup dropdown ----
        userMenuToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            // Tutup dropdown notifikasi dulu kalau lagi kebuka.
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

        // ---- Konfirmasi logout ----
        const logoutBtn = document.getElementById('logoutBtn');
        const logoutOverlay = document.getElementById('logoutConfirmOverlay');
        const logoutCancelBtn = document.getElementById('logoutCancelBtn');
        const logoutConfirmBtn = document.getElementById('logoutConfirmBtn');

        // Klik "Keluar" di dropdown -> tutup dropdown, buka modal konfirmasi
        logoutBtn.addEventListener('click', function () {
            userDropdown.classList.remove('open');
            userMenuToggle.setAttribute('aria-expanded', 'false');
            logoutOverlay.classList.add('open');
        });

        // Klik "Batal" atau area gelap di luar box -> tutup modal, batal logout
        logoutCancelBtn.addEventListener('click', function () {
            logoutOverlay.classList.remove('open');
        });

        logoutOverlay.addEventListener('click', function (e) {
            if (e.target === logoutOverlay) {
                logoutOverlay.classList.remove('open');
            }
        });

        // Klik "Ya, Keluar" -> baru beneran logout (instan, tidak nunggu server)
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
</script>
@endpush