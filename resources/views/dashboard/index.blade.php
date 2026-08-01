@extends('layouts.app')

@section('title', 'Dashboard')

@push('styles')
@vite('resources/css/dashboard/index.css')
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
                <div id="trendKosong" class="dash-empty" hidden>Tidak ada data penjualan di rentang tanggal ini.</div>
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
                        <thead><tr><th>#</th><th>Nama Pelanggan</th><th>Total</th><th>Monetary</th><th>Frekuensi</th></tr></thead>
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