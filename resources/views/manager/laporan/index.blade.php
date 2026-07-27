@extends('layouts.app')

@section('title', 'Laporan')

@push('styles')
@vite('resources/css/manager/laporan/index.css')
@endpush

@section('content')
<div class="lap-content">

    <div class="lap-header">
        <div>
            <h1>Laporan</h1>
            <p>Segmentasi pelanggan &amp; rekomendasi upsell — periode <span id="periodeLabel">-</span></p>
        </div>
        <div class="lap-header-actions">
            <div class="lap-date-range">
                <input type="date" id="exportStart">
                <span>s/d</span>
                <input type="date" id="exportEnd">
            </div>
            <button type="button" class="btn-unduh" id="unduhBtn">
                <i class="fa-solid fa-download"></i> Unduh
            </button>
        </div>
    </div>


    <div id="lapError" class="lap-error"></div>

    <div class="lap-cards">
        <div class="lap-card">
            <div class="label"><span class="dot" style="background:#63c088;"></span>Pelanggan Baru</div>
            <div class="value" id="cardBaru">0</div>
        </div>
        <div class="lap-card">
            <div class="label"><span class="dot" style="background:#5aa9e6;"></span>Butuh Perhatian</div>
            <div class="value" id="cardPerhatian">0</div>
        </div>
        <div class="lap-card">
            <div class="label"><span class="dot" style="background:#f4c542;"></span>Potensial</div>
            <div class="value" id="cardPotensial">0</div>
        </div>
        <div class="lap-card">
            <div class="label"><span class="dot" style="background:#e05a5a;"></span>Loyal</div>
            <div class="value" id="cardLoyal">0</div>
        </div>
    </div>

    <div class="lap-grid">
        <div class="panel panel-full">
            <h3>Total Revenue per Ukuran</h3>
            <div class="panel-sub">Total pendapatan dikelompokkan per ukuran minuman (data sama dengan dashboard)</div>
            <canvas id="revenueUkuranChart"></canvas>
        </div>

        <div class="panel">
            <h3>Segmentasi Pelanggan (RFM)</h3>
            <div class="panel-sub">Snapshot satu periode penuh, tidak berubah per tanggal</div>
            <canvas id="rfmDonut"></canvas>
        </div>

        <div class="panel">
            <h3>Detail RFM per Pelanggan</h3>
            <div class="panel-sub">Recency (hari sejak transaksi terakhir) · Frequency (jumlah transaksi) · Monetary (total belanja)</div>

            <div class="lap-filters">
                <div class="lap-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="7"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                    <input type="text" id="rfmSearch" placeholder="Cari nama pelanggan">
                </div>
                <select id="rfmSegmenFilter">
                    <option value="">Semua Segmen</option>
                    <option value="Pelanggan Baru">Pelanggan Baru</option>
                    <option value="Butuh Perhatian">Butuh Perhatian</option>
                    <option value="Potensial">Potensial</option>
                    <option value="Loyal">Loyal</option>
                </select>
            </div>

            <div class="table-scroll">
                <table class="lap-table">
                    <thead>
                        <tr><th>Nama</th><th>Recency</th><th>Frequency</th><th>Monetary</th><th>RFM Score</th><th>Segmen</th></tr>
                    </thead>
                    <tbody id="rfmTableBody">
                        <tr><td colspan="6" class="lap-loading">Memuat data...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel panel-full">
            <h3>Rekomendasi Switch / Upsell</h3>
            <div class="panel-sub">Pola pembelian pelanggan &amp; saran upsell ukuran/produk</div>

            <div class="lap-filters">
                <div class="lap-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="7"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                    <input type="text" id="switchSearch" placeholder="Cari rekomendasi (misal 'upsize', 'coba rasa')">
                </div>
            </div>

            <div class="table-scroll">
                <table class="lap-table">
                    <thead>
                        <tr>
                            <th>Nama</th><th>Rasa Favorit</th><th>Ukuran Saat Ini</th>
                            <th>Reguler</th><th>Large</th><th>Botol</th>
                            <th>Total Transaksi</th><th>Qty/Kunjungan</th><th>Total Belanja</th><th>Rekomendasi</th>
                        </tr>
                    </thead>
                    <tbody id="switchTableBody">
                        <tr><td colspan="10" class="lap-loading">Memuat data...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
@vite('resources/js/manager/laporan/index.js')
@endpush