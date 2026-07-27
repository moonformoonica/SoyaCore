@php
    $active = $active ?? 'dashboard';
@endphp

<aside class="sidebar" id="sidebar">

    <div>

        <div class="logo">
            <img src="{{ asset('images/logo.png') }}" alt="Gre'Soy">
        </div>

        <nav class="menu">

            <a href="{{ route('dashboard') }}"
                class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="fa-solid fa-table-cells-large"></i>
                <span>Dashboard</span>
            </a>

            <a href="{{ route('pesanan') }}" data-role="kasir"
                class="{{ request()->routeIs('pesanan') ? 'active' : '' }}">
                <i class="fa-solid fa-cart-shopping"></i>
                <span>Pesanan</span>
            </a>

            <a href="{{ route('manager.transaksi') }}" class="{{ request()->routeIs('manager.transaksi') ? 'active' : '' }}">
                <i class="fa-regular fa-money-bill-1"></i>
                <span>Transaksi</span>
            </a>

            <a href="{{ route('manager.menu') }}" class="{{ request()->routeIs('manager.menu') ? 'active' : '' }}">
                <i class="fa-solid fa-cube"></i>
                <span>Menu</span>
            </a>

            <a href="{{ route('manager.loyalty') }}" class="{{ request()->routeIs('manager.loyalty') ? 'active' : '' }}">
                <i class="fa-solid fa-ticket"></i>
                <span>Loyalty</span>
            </a>

            <a href="{{ route('manager.laporan') }}" data-role="manager"
                class="{{ request()->routeIs('manager.laporan') ? 'active' : '' }}">
                <i class="fa-solid fa-chart-column"></i>
                <span>Laporan</span>
            </a>

        </nav>

    </div>

</aside>