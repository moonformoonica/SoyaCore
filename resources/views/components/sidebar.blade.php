@php
    $active = $active ?? 'dashboard';
@endphp

<aside class="sidebar" id="sidebar">

    <div>
        <button type="button" class="sidebar-close" id="sidebarClose" aria-label="Tutup navigasi">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
        <div class="logo">
            <img src="{{ asset('images/Logo.png') }}" alt="Gre'Soy">
        </div>
        <nav class="menu" aria-label="Navigasi utama">
            <a href="{{ route('dashboard') }}"
                aria-label="Dashboard"
                class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="fa-solid fa-table-cells-large" aria-hidden="true"></i>
                <span>Dashboard</span>
            </a>
            <a href="{{ route('pesanan') }}" data-role="kasir" aria-label="Pesanan"
                class="{{ request()->routeIs('pesanan') ? 'active' : '' }}">
                <i class="fa-solid fa-cart-shopping" aria-hidden="true"></i>
                <span>Pesanan</span>
            </a>
            <a href="{{ route('manager.transaksi') }}" aria-label="Transaksi" class="{{ request()->routeIs('manager.transaksi') ? 'active' : '' }}">
                <i class="fa-regular fa-money-bill-1" aria-hidden="true"></i>
                <span>Transaksi</span>
            </a>
            <a href="{{ route('manager.menu') }}" aria-label="Menu" class="{{ request()->routeIs('manager.menu') ? 'active' : '' }}">
                <i class="fa-solid fa-cube" aria-hidden="true"></i>
                <span>Menu</span>
            </a>
            <a href="{{ route('manager.loyalty') }}" aria-label="Loyalty" class="{{ request()->routeIs('manager.loyalty') ? 'active' : '' }}">
                <i class="fa-solid fa-ticket" aria-hidden="true"></i>
                <span>Loyalty</span>
            </a>
            <a href="{{ route('manager.laporan') }}" data-role="manager" aria-label="Laporan"
                class="{{ request()->routeIs('manager.laporan') ? 'active' : '' }}">
                <i class="fa-solid fa-chart-column" aria-hidden="true"></i>
                <span>Laporan</span>
            </a>
            <a href="{{ route('manager.laporan.kasir') }}" data-role="manager" aria-label="Laporan Kasir"
                class="{{ request()->routeIs('manager.laporan.kasir') ? 'active' : '' }}">
                <i class="fa-solid fa-user-group" aria-hidden="true"></i>
                <span>Laporan Kasir</span>
            </a>
        </nav>
    </div>
</aside>
