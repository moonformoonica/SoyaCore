@extends('layouts.app')

@section('title', 'Laporan Kasir')

@push('styles')
@vite('resources/css/manager/laporan/kasir.css')
@endpush

@section('content')
<div class="transaction-page">

    <div class="page-header">
        <div>
            <h1>Laporan Kasir</h1>
            <p>Perbandingan kinerja antar akun kasir, <span id="lkPeriode">memuat…</span></p>
        </div>

        <div class="trx-date-range">
            <i class="fa-regular fa-calendar"></i>
            <input type="date" id="lkMulai" aria-label="Tanggal mulai">
            <span class="trx-date-sd">s/d</span>
            <input type="date" id="lkSelesai" aria-label="Tanggal selesai">
            <button type="button" class="trx-date-reset" id="lkReset" title="Hapus filter tanggal">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    </div>

    <div id="lkError" class="lk-error"></div>

    {{-- =========================
            CARD RINGKASAN
    ========================== --}}
    <div class="stats-grid">

        <div class="stat-card">
            <h5>Total Omzet</h5>
            <h2 id="lkOmzet">Rp 0</h2>
            <span>Seluruh akun kasir</span>
        </div>

        <div class="stat-card">
            <h5>Jumlah Transaksi</h5>
            <h2 id="lkTransaksi">0</h2>
            <span>Sudah dibayar</span>
        </div>

        <div class="stat-card">
            <h5>Akun Kasir</h5>
            <h2 id="lkJumlahKasir">0</h2>
            <span>Punya aktivitas di periode ini</span>
        </div>

        <div class="stat-card">
            <h5>Pembatalan</h5>
            <h2 id="lkPembatalan">0</h2>
            <span>Diproses seluruh akun</span>
        </div>

    </div>

    <p class="lk-sub">
        Terurut dari omzet terbesar. Kolom <strong>Pembatalan</strong> dan
        <strong>Nilai Dibatalkan</strong> menghitung yang diproses akun tersebut,
        bukan yang terjadi pada penjualannya.
    </p>

    {{-- =========================
            TABEL
    ========================== --}}
    <div class="table-card">
        <div class="lk-scroll">
            <table class="lk-table">
                <thead>
                    <tr>
                        <th>Kasir</th>
                        <th>Transaksi</th>
                        <th>Omzet</th>
                        <th>Qty</th>
                        <th>Rata-rata</th>
                        <th>Tunai</th>
                        <th>QRIS</th>
                        <th>Diskon</th>
                        <th>Poin Diberi</th>
                        <th>Poin Ditukar</th>
                        <th>Pembatalan</th>
                        <th>Nilai Dibatalkan</th>
                    </tr>
                </thead>
                <tbody id="lkBody">
                    <tr><td colspan="12" class="lk-state">Memuat data…</td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

@push('scripts')
@vite('resources/js/manager/laporan/kasir.js')
@endpush
