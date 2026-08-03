
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
        {{-- Cadangannya diambil dari konstanta model, bukan teks yang diketik
             ulang di sini. Ejaan yang disalin ke banyak berkas pasti lambat
             laun berbeda: nilai lama di baris ini sempat tertinggal sebagai
             "Gre's Soy" saat merek lain sudah jadi GresSOY. --}}
        <p>Kelola poin dan hadiah untuk pelanggan setia
            {{ $brandName ?? \App\Models\PengaturanToko::DEFAULT_NAMA_TOKO }}.</p>
    </div>

    <div class="stats" id="lm-stats" data-role="manager">
    </div>

    <div class="settings-card" data-role="manager">
        <div class="field">
            <label for="rpPerPoint">Rp belanja per 1 poin</label>
            <input type="number" id="rpPerPoint" value="{{ $rpPerPoint ?? 10000 }}">
        </div>
        <button class="btn btn-add" id="btnSaveRule" style="align-self:flex-end">Simpan aturan</button>
    </div>

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

        {{-- Tombol ini sebelumnya tidak pernah ada di markup, padahal
             loyalty.js sudah mencarinya lewat id `btnAddReward`. Akibatnya form
             tambah reward tidak punya satu pun jalan untuk dibuka. --}}
        <button type="button" class="btn-add-reward" id="btnAddReward" data-role="manager">
            <i class="fa-solid fa-plus"></i>
            Tambah reward
        </button>
    </div>
    <div class="rewards" id="lm-rewards"></div>

    <div class="section-head" data-role="manager">
        <h2>Riwayat poin terbaru</h2>
        <div class="custom-select" id="lm-history-sort">
            <button type="button" class="custom-select-trigger">
                <span class="selected-label">Terbaru</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            </button>
            <ul class="custom-options">
                <li data-value="terbaru" class="selected">Terbaru</li>
                <li data-value="poin_desc">Poin tertinggi</li>
                <li data-value="poin_asc">Poin terendah</li>
            </ul>
        </div>
    </div>
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

    {{-- =========================
            MODAL TAMBAH REWARD
        =========================
        Dulu form ini disisipkan sebagai kartu kesembilan di dalam grid katalog,
        jadi ia ikut selebar satu kolom dan memanjang jauh ke bawah sampai
        merusak tinggi barisnya. Sebagai modal, lebarnya ditentukan sendiri dan
        katalog di belakangnya tidak berubah bentuk. --}}
    <div class="rw-modal-backdrop" id="rewardModalBackdrop" data-role="manager">
        <div class="rw-modal" role="dialog" aria-modal="true" aria-labelledby="rewardModalTitle">
            <div class="rw-modal-head">
                <h3 id="rewardModalTitle">Tambah reward</h3>
                <button type="button" class="rw-modal-close" id="rewardModalClose" aria-label="Tutup">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="rw-modal-body" id="rewardModalBody"></div>
        </div>
    </div>

    <div class="toast" id="lm-toast"></div>
</div>
@endsection

@push('scripts')
    @vite(['resources/js/manager/loyalty.js'])
@endpush