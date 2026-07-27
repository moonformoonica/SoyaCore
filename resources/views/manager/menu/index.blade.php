@extends('layouts.app')

@section('title', 'Menu')

@vite([
    'resources/css/manager/menu/index.css',
    'resources/js/manager/menu/index.js'
])

@section('content')

<div class="transaction-page">

    <div class="menu-header-row">

        <div class="menu-header">
            <h1>Menu</h1>
            <p>Kelola daftar menu dan harga jual</p>
        </div>

        <a href="{{ url('/menu/create') }}" class="btn-add" data-role="manager">
            <i class="fa-solid fa-plus"></i>
            Add Product
        </a>

    </div>

    <div id="menuError" class="menu-error"></div>

    <div class="menu-filters">

        <div class="menu-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input
                type="text"
                id="filterSearch"
                placeholder="Cari Produk">
        </div>

        <div class="custom-select" data-name="kategori" id="filterKategoriWrap">
            <button type="button" class="custom-select-trigger">
                <span class="selected-label">Semua Kategori</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6B7280" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            </button>
            <ul class="custom-options" id="filterKategoriOptions">
                <li data-value="" class="selected">Semua Kategori</li>
            </ul>
            <input type="hidden" id="filterKategori" value="">
        </div>

    </div>

    <div class="menu-table-wrap">

        <div class="table-scroll">

            <table class="menu-table">

                <thead>
                    <tr>
                        <th>Nama Menu</th>
                        <th>Kategori</th>
                        <th>Harga</th>
                        <th>Ukuran</th>
                        <th>Rasa</th>
                        <th>Status</th>
                        <th data-role="manager">Action</th>
                    </tr>
                </thead>

                <tbody id="menuTableBody">
                    <tr>
                        <td colspan="7" class="menu-loading">
                            Memuat data...
                        </td>
                    </tr>
                </tbody>

            </table>

        </div>

    </div>

</div>

<div class="confirm-overlay" id="deleteOverlay">

    <div class="confirm-box">

        <h3>Hapus Produk</h3>

        <p id="deleteMessage">
            Apakah anda yakin ingin menghapus produk ini?
        </p>

        <div class="confirm-actions">

            <button
                class="btn-cancel"
                id="deleteCancelBtn">
                Batal
            </button>

            <button
                class="btn-danger"
                id="deleteConfirmBtn">
                Ya, Hapus
            </button>

        </div>

    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    try {
        const rawUser = localStorage.getItem('auth_user');
        const role = rawUser ? JSON.parse(rawUser).role : null;

        if (role === 'kasir') {
            document.querySelectorAll('.transaction-page [data-role="manager"]').forEach(function (el) {
                el.style.display = 'none';
            });
        }
    } catch (e) {
        // localStorage tidak terbaca / rusak — biarkan tampilan default (manager)
    }
});
</script>

@endsection