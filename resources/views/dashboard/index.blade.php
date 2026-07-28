@extends('layouts.app')

@section('title', 'Dashboard')

@push('styles')
<style>
    .dash-header {
        background: #14532d; color: #fff; padding: 16px 24px;
        display: flex; justify-content: space-between; align-items: center;
        border-radius: 8px; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;
    }
    .dash-header h1 { font-size: 18px; margin: 0; color: #fff; }
    .filter-form { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
    .filter-form input, .filter-form select, .filter-form button {
        padding: 6px 10px; border-radius: 6px; border: none; font-size: 13px;
    }
    .filter-form button { background: #1f7a4d; color: #fff; cursor: pointer; }
    .filter-sd { color: #fff; font-size: 13px; padding: 0 2px; }

    .cards { display: flex; gap: 1px; background: #cfe8da; border-radius: 8px; overflow: hidden; margin-bottom: 16px; flex-wrap: wrap; }
    .card { flex: 1; min-width: 160px; background: #cfe8da; padding: 16px 24px; }
    .card .label { font-size: 13px; color: #1f4d33; }
    .card .value { font-size: 24px; font-weight: 700; color: #14532d; margin-top: 4px; }

    /* min-width: 0 wajib di semua level grid/flex ini — tanpa ini,
       canvas Chart.js "menahan" lebar kolom supaya tidak bisa menyusut
       balik waktu sidebar dibuka lagi, sehingga dashboard overflow
       ke kanan (muncul scrollbar horizontal). */
    .dash-content { min-width: 0; width: 100%; overflow-x: hidden; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; min-width: 0; }
    .panel { background: #fff; border-radius: 8px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.08); min-width: 0; overflow: hidden; }
    .panel h3 { margin: 0 0 10px; font-size: 14px; color: #14532d; }

    .bottom-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; min-width: 0; }
    .stat-box { background: #fff; border-radius: 8px; padding: 16px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,.08); min-width: 0; }
    .stat-box .num { font-size: 32px; font-weight: 700; color: #14532d; }

    .dash-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .dash-table th, .dash-table td { text-align: left; padding: 4px 6px; border-bottom: 1px solid #eee; }

    .grid3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 16px; min-width: 0; }
    .grid-split { display: grid; grid-template-columns: 1fr 2fr; gap: 16px; margin-bottom: 16px; min-width: 0; align-items: start; }

    .fp-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
    .fp-title { font-size: 14px; font-weight: 600; color: #14532d; }
    .fp-exclude { font-weight: 400; color: #888; font-size: 12px; }
    .fp-record-count { font-size: 12px; color: #666; }
    .fp-search { margin-bottom: 8px; }
    .fp-search input {
        width: 100%; padding: 6px 10px; border: 1px solid #ddd; border-radius: 6px;
        font-size: 12px; outline: none;
    }
    .filter-platform-list { display: flex; flex-direction: column; gap: 10px; }
    .filter-platform-item {
        display: flex; align-items: center; justify-content: space-between;
        font-size: 13px; color: #333; gap: 8px;
    }
    .filter-platform-item span.fp-name { display: flex; align-items: center; gap: 8px; }
    .filter-platform-item input[type="checkbox"] { accent-color: #2f9e5f; }
    .filter-platform-item .fp-count { color: #666; font-size: 13px; }

    .mini-stat { text-align: center; padding: 14px 0 4px; border-top: 1px solid #eee; margin-top: 12px; }
    .mini-stat .num { font-size: 30px; font-weight: 700; color: #14532d; }
    .mini-stat .label { font-size: 12px; color: #666; margin-top: 2px; }

    .dash-pagination { display: flex; align-items: center; justify-content: space-between; margin-top: 10px; font-size: 12px; color: #666; }
    .dash-pagination button {
        padding: 4px 10px; border-radius: 6px; border: 1px solid #ddd; background: #fff; cursor: pointer; font-size: 12px;
    }
    .dash-pagination button:disabled { opacity: .4; cursor: not-allowed; }
    .dash-pagebtns { display: flex; gap: 6px; }
    .dash-pagebtns button.active { background: #2E7D32; border-color: #2E7D32; color: #fff; font-weight: 700; }
    .dash-pagebtns button:empty { display: none; }

    /* Paksa canvas Chart.js selalu pas dengan lebar panel-nya, jangan
       biarkan ukuran piksel bawaan Chart.js mendikte lebar container. */
    .dash-content canvas { max-height: 260px; max-width: 100% !important; width: 100% !important; }
    .dash-error {
        background: #fdecea; color: #b3261e; padding: 10px 14px; border-radius: 6px;
        margin-bottom: 16px; font-size: 13px; display: none;
    }
    .dash-loading { text-align: center; padding: 40px; color: #888; font-size: 14px; }

    /* Tabel dibungkus div supaya kalau kolomnya sempit banget di HP,
       yang scroll cuma tabelnya sendiri (horizontal) — bukan seluruh
       halaman dashboard ikut geser. */
    .table-scroll { width: 100%; overflow-x: auto; }

    /* ===========================
            RESPONSIVE
    =========================== */

    @media (max-width: 900px) {
        .grid, .bottom-row, .grid3, .grid-split {
            grid-template-columns: 1fr;
        }
        .dash-header h1 { font-size: 16px; }
        .card { min-width: 45%; padding: 14px 16px; }
        .card .value { font-size: 20px; }
    }

    @media (max-width: 600px) {
        .dash-header { padding: 12px 16px; }
        .filter-form { width: 100%; }
        .filter-form input,
        .filter-form select,
        .filter-form button { flex: 1; min-width: 0; }
        .card { min-width: 100%; }
        .panel { padding: 12px; }
        .dash-content canvas { max-height: 200px; }
    }
</style>
@endpush

@section('content')
<div class="dash-content">

    <div id="dashError" class="dash-error"></div>
    <div id="dashLoading" class="dash-loading">Memuat data dashboard...</div>

    <div id="dashBody" style="display:none;">

        <div class="dash-header">
            <h1>Soya Sight</h1>
            <form id="filterForm" class="filter-form">
                <input type="date" name="start_date" id="startDate">
                <span class="filter-sd">s/d</span>
                <input type="date" name="end_date" id="endDate">
                <button type="submit">Terapkan</button>
            </form>
        </div>

        <div class="cards">
            <div class="card">
                <div class="label">Total Revenue</div>
                <div class="value" id="cardRevenue">Rp 0</div>
            </div>
            <div class="card">
                <div class="label">Jumlah Transaksi</div>
                <div class="value" id="cardTransaksi">0</div>
            </div>
            <div class="card">
                <div class="label">Rata-rata nilai/Transaksi</div>
                <div class="value" id="cardRata">Rp 0</div>
            </div>
        </div>

        <div class="grid3" id="dashGrid">
            <div class="panel" id="bestSellerPanel">
                <h3>10 Menu Best Seller</h3>
                <canvas id="bestSellerChart"></canvas>
            </div>
            <div class="panel" data-role="manager">
                <h3>10 Menu Kurang Diminati</h3>
                <canvas id="worstSellerChart"></canvas>
            </div>
            <div class="panel" data-role="manager">
                <h3>Tren Penjualan</h3>
                <canvas id="trendChart"></canvas>
            </div>
        </div>

        <div class="grid" data-role="manager">
            <div class="panel">
                <h3>Revenue per Ukuran</h3>
                <canvas id="ukuranChart"></canvas>
            </div>
            <div class="panel">
                <h3>Segmentasi Pelanggan (RFM)</h3>
                <canvas id="rfmChart"></canvas>
            </div>
        </div>

        <div class="panel" data-role="manager" style="margin-bottom:16px;">
            <div class="mini-stat" style="border-top:none; margin-top:0; padding-top:0;">
                <div class="num" id="statPelanggan10x">0</div>
                <div class="label">Pelanggan 10 x beli</div>
            </div>
        </div>

        <div class="grid-split" data-role="manager">
            <div class="panel">
                <div class="fp-head">
                    <span class="fp-title">Filter Platform <span class="fp-exclude">(Exclude)</span></span>
                    <span class="fp-record-count">Record Count</span>
                </div>
                <div class="fp-search">
                    <input type="text" id="filterPlatformSearch" placeholder="Type to search">
                </div>
                <div id="filterPlatformList" class="filter-platform-list"></div>
            </div>
            <div class="panel">
                <h3>Poin Loyalty Pelanggan</h3>
                <div class="table-scroll">
                    <table class="dash-table">
                        <thead><tr><th>#</th><th>Nama Pelanggan</th><th>Total</th><th>Monetary &#9660;</th><th>Frekuensi</th></tr></thead>
                        <tbody id="loyaltyTableBody"></tbody>
                    </table>
                </div>
                <div class="dash-pagination">
                    <span id="loyaltyPageInfo"></span>
                    <div class="dash-pagebtns" id="loyaltyPagination"></div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
@vite(['resources/js/dashboard/index.js'])
@endpush