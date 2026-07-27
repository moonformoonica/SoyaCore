{{--
    resources/views/manager/loyalty-manager.blade.php

    Sidebar & header sudah ada di layouts/app.blade.php, jadi file ini
    tinggal @extends layout itu — sidebar & header otomatis ikut tampil.

    Perubahan dari versi sebelumnya:
    - Section "Tingkatan Membership" (bronze/silver/gold) DIHAPUS,
      termasuk kolom "Tingkatan" di tabel riwayat karena datanya
      sudah tidak dikelola dari halaman ini.
    - Stat cards tetap 4 (Total member, Total poin aktif, Reward
      ditukar bulan ini, Member baru bulan ini) — datanya masih dummy,
      nanti tinggal disambungkan ke controller.
    - Katalog reward sekarang mengikuti daftar tetap (fixed) yang sudah
      ditentukan: diskon_10, diskon_20, diskon_50, gratis_original,
      gratis_coffee_kopi, gratis_honey_lemon, gratis_mango_monggo.
      Detail (nama, ikon, ukuran minuman) sudah dikunci di JS, manager
      hanya bisa mengatur poin & minimal pembelian per item.

    1. Taruh loyalty.css di resources/css/manager/loyalty.css
    2. Taruh manager.loyalty.js di resources/js/manager.loyalty.js
    3. Route tinggal return view('manager.loyalty-manager')
    4. Jalankan "npm run dev" / "npm run build"
--}}

@extends('layouts.app')

@section('title', 'Loyalty - Manager')

@push('styles')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/manager/loyalty.css'])
@endpush

@section('content')
<div class="loyalty-manager">

    <div class="page-head">
        <h1>Loyalty</h1>
        <p>Kelola poin dan hadiah untuk pelanggan setia {{ $brandName ?? "Gre's Soy" }}.</p>
    </div>

    <div class="stats" id="lm-stats" data-role="manager">
        {{-- diisi otomatis oleh manager.loyalty.js --}}
    </div>

    <div class="settings-card" data-role="manager">
        <div class="field">
            <label for="rpPerPoint">Rp belanja per 1 poin</label>
            <input type="number" id="rpPerPoint" value="{{ $rpPerPoint ?? 10000 }}">
        </div>
        <button class="btn btn-add" id="btnSaveRule" style="align-self:flex-end">Simpan aturan</button>
    </div>

    {{-- kasir: cek poin pelanggan tanpa perlu mulai transaksi dulu --}}
    <div class="lm-cek-poin" id="lmCekPoin" data-role="kasir" style="display:none;">
        <h2 class="lm-cek-poin-title">Cek Poin Pelanggan</h2>
        <p class="lm-cek-poin-sub">Cari berdasarkan No. WhatsApp.</p>
        <div class="lm-cek-poin-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="lmCariNoWa" placeholder="No. WhatsApp pelanggan" inputmode="numeric" maxlength="12">
        </div>
        <div id="lmCekPoinResult" class="lm-cek-poin-result"></div>
    </div>

    <div class="section-head">
        <div>
            <h2>Katalog reward</h2>
            <p class="section-sub">Minimal penukaran 150 poin.</p>
        </div>
    </div>
    <div class="rewards" id="lm-rewards"></div>

    <div class="section-head" data-role="manager"><h2>Riwayat poin terbaru</h2></div>
    <div class="table-card" data-role="manager">
        <table>
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Aktivitas</th>
                    <th>Ukuran</th>
                    <th>Poin</th>
                    <th>Tanggal</th>
                </tr>
            </thead>
            <tbody id="lm-history"></tbody>
        </table>
        <div class="lm-history-footer" id="lm-history-footer"></div>
    </div>

    <div class="toast" id="lm-toast"></div>
</div>
@endsection

@push('scripts')
    @vite(['resources/js/manager/loyalty.js'])
@endpush