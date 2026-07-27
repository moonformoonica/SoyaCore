@extends('layouts.app')

@section('title', 'Pesanan')

@push('styles')
@vite('resources/css/kasir/pesanan.css')
@endpush

@section('content')
<div class="pesanan-content">

    <div id="pesError" class="pes-error"></div>
    <div id="pesSuccess" class="pes-success-note"></div>

    <div class="pes-layout">

        {{-- KIRI: kategori + grid menu --}}
        <div class="pes-left">

            {{-- Pesanan masuk dari SoyaScan (self-order, status pending).
                 Disembunyikan otomatis kalau tidak ada antrean. --}}
            <div class="pes-masuk" id="pesMasukWrap" style="display:none;">
                <div class="pes-masuk-head">
                    <span class="pm-judul">
                        <i class="fa-solid fa-mobile-screen-button"></i>
                        Pesanan Masuk
                        <span class="pm-badge" id="pesMasukCount">0</span>
                    </span>
                    <span class="pm-note">dari SoyaScan &middot; diperbarui otomatis</span>
                </div>
                <div class="pes-masuk-row" id="pesMasukRow"></div>
            </div>

            <div class="pes-kategori-row" id="kategoriRow"></div>

            <div class="pes-search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="7"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                </svg>
                <input type="text" id="pesSearch" placeholder="Cari Produk (nama / kategori)" autocomplete="off">
            </div>

            <div class="pes-menu-grid" id="menuGrid">
                <div class="pes-empty">Memuat menu...</div>
            </div>

        </div>

        {{-- KANAN: keranjang / daftar pesanan --}}
        <div class="pes-right">
            <h3>Daftar Pesanan</h3>

            <div class="pes-section">
                <span class="pes-section-label">Pelanggan</span>

                <div class="pes-cust-search-row">
                    <div class="pes-cust-search">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="7"></circle>
                            <path d="m21 21-4.35-4.35"></path>
                        </svg>
                        <input type="text" id="custSearch" placeholder="Cari Pelanggan (nama / No. WhatsApp)">
                    </div>
                    <button type="button" id="custAddBtn" class="pes-cust-add-btn" title="Daftarkan pelanggan baru">
                        <i class="fa-solid fa-user-plus"></i>
                    </button>
                </div>

                {{-- hasil pencarian: bisa lebih dari satu kalau dicari per nama --}}
                <div id="custResults" class="pes-cust-results"></div>

                {{-- ver 1: pelanggan ketemu (sudah pernah beli) --}}
                <div id="custFoundCard" class="pes-cust-found" style="display:none;">
                    <div class="pes-cust-found-info">
                        <div class="cf-nama" id="custFoundNama"></div>
                        <div class="cf-meta" id="custFoundMeta"></div>
                    </div>
                    <button type="button" id="custLoyaltyBtn" class="pes-loyalty-btn" title="Redeem poin pelanggan">
                        <i class="fa-solid fa-gift"></i> Loyalty
                    </button>
                    <button type="button" id="custGantiBtn" class="pes-cust-ganti-btn" title="Cari pelanggan lain">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                {{-- ver 2: pelanggan baru — daftar pakai nama + no WA --}}
                <div id="custNewForm" class="pes-cust-new" style="display:none;">
                    <input type="text" id="custNama" class="pes-input" placeholder="Nama pelanggan">
                    <input type="text" id="custNoWa" class="pes-input" placeholder="No. WhatsApp" inputmode="numeric" maxlength="12" style="margin-bottom:0;">
                </div>

                <div id="custLockedNote" class="pes-locked-note" style="display:none;">
                    Data pelanggan terkunci — pesanan sudah dimulai.
                </div>
            </div>

            <div class="pes-section">
                <span class="pes-section-label">Tipe Pesanan</span>
                <div class="pes-toggle-row">
                    <button type="button" class="pes-toggle-btn active pes-tipe-btn" data-tipe="dine_in">
                        <i class="fa-solid fa-utensils"></i> Makan di Tempat
                    </button>
                    <button type="button" class="pes-toggle-btn pes-tipe-btn" data-tipe="takeaway">
                        <i class="fa-solid fa-bag-shopping"></i> Bawa Pulang
                    </button>
                </div>
                <div id="nomorMejaWrap" style="margin-top: 8px;">
                    <input type="text" id="nomorMeja" class="pes-input" placeholder="Nomor meja (opsional)" style="margin-bottom: 0;">
                </div>
            </div>

            <div class="pes-section">
                <span class="pes-section-label">Item Pesanan</span>
                <div class="pes-cart-list" id="cartList">
                    <div class="pes-cart-empty">Belum ada item dipesan.</div>
                </div>
            </div>

            <div class="pes-section">
                <span class="pes-section-label">Diskon Transaksi</span>
                <p class="pes-diskon-note">Diskon berlaku untuk semua pelanggan tanpa syarat loyalty.</p>
                <button type="button" class="pes-diskon-preset pes-diskon-gmaps" data-persen="20">Rating Google Maps</button>
                <div class="pes-diskon-presets">
                    <button type="button" class="pes-diskon-preset" data-persen="10">10%</button>
                    <button type="button" class="pes-diskon-preset" data-persen="20">20%</button>
                    <button type="button" class="pes-diskon-preset" data-persen="30">30%</button>
                </div>
                <button type="button" class="pes-diskon-terapkan-btn" id="diskonTerapkanBtn">Terapkan Diskon</button>
            </div>

            <div class="pes-section">
                <div class="pes-summary-row">
                    <span>Subtotal</span>
                    <span id="ringkasanSubtotal">Rp 0</span>
                </div>
                <div class="pes-summary-row diskon">
                    <span>Diskon</span>
                    <span id="ringkasanDiskon">Rp 0</span>
                </div>
                <div class="pes-summary-row total">
                    <span>Total</span>
                    <span id="ringkasanTotal">Rp 0</span>
                </div>
            </div>

            <div class="pes-section">
                <span class="pes-section-label">Metode Pembayaran</span>
                <div class="pes-toggle-row">
                    <button type="button" class="pes-toggle-btn pes-metode-btn" data-metode="cash">
                        <i class="fa-solid fa-money-bill-1"></i> Tunai
                    </button>
                    <button type="button" class="pes-toggle-btn pes-metode-btn" data-metode="qris">
                        <i class="fa-solid fa-qrcode"></i> QRIS
                    </button>
                </div>
            </div>

            <button type="button" class="pes-bayar-btn" id="bayarBtn" disabled>Bayar Sekarang</button>
            <button type="button" class="pes-batal-btn" id="batalBtn">Batalkan Pesanan</button>

        </div>

    </div>

</div>
{{-- ============ MODAL REDEEM POIN ============
     Opsi dibaca langsung dari LoyaltyRedemptionCatalog (sumber tunggal di
     backend), jadi kalau owner mengubah poin/katalog tidak perlu menyentuh
     file ini. --}}
@php $katalogRedeem = \App\Support\LoyaltyRedemptionCatalog::all(); @endphp

<div class="pes-redeem-backdrop" id="redeemBackdrop">
    <div class="pes-redeem-modal">

        <div class="pes-redeem-head">
            <h3>Redeem Poin</h3>
            <button type="button" class="pes-redeem-close" id="redeemClose">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="pes-redeem-cust">
            <span class="rc-nama" id="redeemNama">—</span>
            <span class="rc-poin" id="redeemPoin">0 poin</span>
        </div>

        <div class="pes-redeem-note" id="redeemNote"></div>

        <div class="pes-redeem-list">
            @foreach ($katalogRedeem as $kode => $item)
                {{-- Reward yang dinonaktifkan manager tidak ditawarkan ke kasir --}}
                @if (! ($item['is_active'] ?? true)) @continue @endif
                <button type="button"
                        class="pes-redeem-item"
                        data-kode="{{ $kode }}"
                        data-poin="{{ $item['poin'] }}"
                        data-min="{{ $item['min_subtotal'] ?? 0 }}">
                    <span class="ri-info">
                        <span class="ri-label">{{ $item['label'] }}</span>
                        @if (($item['min_subtotal'] ?? 0) > 0)
                            <span class="ri-syarat">min. belanja Rp {{ number_format($item['min_subtotal'], 0, ',', '.') }}</span>
                        @else
                            <span class="ri-syarat">{{ $item['tipe'] === 'diskon' ? 'Potongan harga' : 'Menu gratis' }}</span>
                        @endif
                    </span>
                    <span class="ri-poin">{{ $item['poin'] }} poin</span>
                </button>
            @endforeach
        </div>

    </div>
</div>

@endsection

@push('scripts')
@vite('resources/js/kasir/pesanan.js')
@endpush