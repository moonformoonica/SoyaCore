<header class="app-header">

  <button type="button" class="toggle-btn" id="sidebarToggle" aria-label="Toggle sidebar">
    <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
      <path d="M15 6l-6 6 6 6"></path>
      <path d="M20 6l-6 6 6 6"></path>
    </svg>
  </button>

  @php
      $searchablePages = [
          ['label' => 'Dashboard', 'url' => route('dashboard'), 'icon' => 'fa-table-cells-large', 'role' => null],
          ['label' => 'Transaksi', 'url' => route('manager.transaksi'), 'icon' => 'fa-money-bill-1', 'role' => null],
          ['label' => 'Menu', 'url' => route('manager.menu'), 'icon' => 'fa-cube', 'role' => null],
          ['label' => 'Loyalty', 'url' => route('manager.loyalty'), 'icon' => 'fa-ticket', 'role' => null],
          ['label' => 'Laporan', 'url' => route('manager.laporan'), 'icon' => 'fa-chart-column', 'role' => 'manager'],
          ['label' => 'Laporan Kasir', 'url' => route('manager.laporan.kasir'), 'icon' => 'fa-user-group', 'role' => 'manager'],
      ];
  @endphp

  <script type="application/json" id="quickNavData">@json($searchablePages)</script>

  <div class="search-box" id="quickNav" style="position: relative;">
    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="11" cy="11" r="7"></circle>
      <path d="m21 21-4.35-4.35"></path>
    </svg>
    <input type="text" id="quickNavInput" placeholder="Cari..." autocomplete="off">
    <div class="quicknav-suggestions" id="quickNavSuggestions"></div>
  </div>

  <div class="header-actions">

    <div class="notif-menu" id="notifMenu">
      <button type="button" class="notif-btn" id="notifToggle" aria-label="Notifikasi" aria-haspopup="true" aria-expanded="false">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"></path>
          <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
        </svg>
        <span class="badge" id="notifBadge" style="display:none;"></span>
      </button>

      <div class="notif-dropdown" id="notifDropdown">
        <div class="notif-dropdown-header">
          <span>Notifikasi</span>
          <button type="button" class="notif-mark-read" id="notifMarkRead">Tandai semua dibaca</button>
        </div>
        <div class="notif-list" id="notifList">
          <div class="notif-empty">Memuat notifikasi…</div>
        </div>
      </div>

    </div>

    {{-- Profile dropdown --}}
    <div class="user-menu" id="userMenu">

      <button type="button" class="user-profile" id="userMenuToggle" aria-haspopup="true" aria-expanded="false">
        {{-- Tanpa foto bawaan. Sebelumnya di sini ada `images/profil.jpg`,
             foto satu orang yang muncul di SEMUA akun, jadi Andrian, Evan, dan
             Aden sama-sama tampil sebagai orang itu sampai mereka mengunggah
             fotonya sendiri. Penggantinya inisial nama, dan `#userAvatar`
             tetap ada supaya JS pengaturan bisa mengisinya begitu ada foto. --}}
        <span class="avatar">
          <span class="avatar-inisial" id="userAvatarInisial">&nbsp;</span>
          <img id="userAvatar" data-default="" alt="Foto profil" hidden>
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

@push('scripts')
@vite('resources/js/header.js')
@endpush