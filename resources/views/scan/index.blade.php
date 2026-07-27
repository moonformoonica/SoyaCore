<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#2f9e44">
    <title>SoyaScan — Pesan Menu GresSOY</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    @vite('resources/css/scan/index.css')
</head>
<body>

<div class="scan">

    {{-- ============================ HEADER ============================ --}}
    <header class="scan-header">
        <div class="scan-brand">
            <img src="{{ asset('images/Logo.png') }}" alt="GresSOY">
        </div>

        <div class="scan-welcome">
            <h1>Selamat datang di GresSOY</h1>
            <p>Pilih menu, pesanan langsung masuk ke kasir</p>
        </div>

        <div class="scan-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="7"></circle>
                <path d="m21 21-4.35-4.35"></path>
            </svg>
            <input type="text" id="scanSearch" placeholder="Cari Menu" autocomplete="off">
        </div>
    </header>

    {{-- ========================= KATEGORI CHIPS ======================= --}}
    <nav class="scan-cats" id="scanCats"></nav>

    {{-- =========================== LIST MENU ========================= --}}
    <main class="scan-list" id="scanList">
        <div class="scan-loading">Memuat menu…</div>
    </main>

    {{-- ======================= CART BAR MENGAMBANG =================== --}}
    <button type="button" class="scan-cartbar" id="scanCartBar" hidden>
        <span class="cb-left">
            <span class="cb-count" id="cbCount">0</span>
            <span>Lihat Pesanan</span>
        </span>
        <span class="cb-right">
            <span id="cbTotal">Rp 0</span>
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m9 18 6-6-6-6"></path>
            </svg>
        </span>
    </button>

</div>

{{-- ================== BOTTOM SHEET: PILIH UKURAN ==================== --}}
<div class="scan-sheet" id="variantSheet" hidden>
    <div class="backdrop" data-close="variant"></div>
    <div class="panel">
        <div class="grip"></div>
        <p class="sheet-title" id="varTitle">Pilih Ukuran</p>
        <p class="sheet-sub" id="varSub"></p>
        <div id="varList"></div>
    </div>
</div>

{{-- ================== BOTTOM SHEET: KERANJANG ====================== --}}
<div class="scan-sheet" id="cartSheet" hidden>
    <div class="backdrop" data-close="cart"></div>
    <div class="panel">
        <div class="grip"></div>
        <p class="sheet-title">Pesanan Kamu</p>
        <p class="sheet-sub">Cek pesanan lalu isi data untuk kasir.</p>

        <div class="scan-error" id="cartError"></div>

        <div id="cartItems"></div>

        <form class="scan-form" id="orderForm" autocomplete="off">
            <div class="scan-field">
                <label for="fNama">Nama<span class="req">*</span></label>
                <input type="text" id="fNama" placeholder="Nama kamu" required>
            </div>
            <div class="scan-field">
                <label for="fWa">Nomor WhatsApp<span class="req">*</span></label>
                <input type="tel" id="fWa" placeholder="08xxxxxxxxxx" inputmode="numeric" maxlength="12" required>
            </div>
            <div class="scan-field">
                <label for="fMeja">Nomor Meja<span class="req">*</span></label>
                <input type="text" id="fMeja" placeholder="Contoh: 5" required>
            </div>

            <div class="scan-field">
                <label>Metode Pembayaran<span class="req">*</span></label>
                <div class="scan-pay-row">
                    <button type="button" class="scan-pay-btn" data-metode="cash">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="6" width="20" height="12" rx="2"></rect>
                            <circle cx="12" cy="12" r="2.5"></circle>
                            <path d="M6 12h.01M18 12h.01"></path>
                        </svg>
                        Tunai
                    </button>
                    <button type="button" class="scan-pay-btn" data-metode="qris">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7" rx="1"></rect>
                            <rect x="14" y="3" width="7" height="7" rx="1"></rect>
                            <rect x="3" y="14" width="7" height="7" rx="1"></rect>
                            <path d="M14 14h3v3M21 14v.01M14 21h.01M17 21h4v-4"></path>
                        </svg>
                        QRIS
                    </button>
                </div>
            </div>

            <div class="scan-total-row">
                <span>Total</span>
                <span class="amount" id="cartTotal">Rp 0</span>
            </div>

            <button type="submit" class="scan-primary" id="submitOrder">Pesan Sekarang</button>
        </form>
    </div>
</div>

{{-- ===================== OVERLAY SUKSES ============================ --}}
<div class="scan-done" id="doneOverlay" hidden>
    <div class="done-box">

        <div class="done-check">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 6 9 17l-5-5"></path>
            </svg>
        </div>

        <h2 class="done-title">Pesanan Berhasil! 🎉</h2>
        <p class="done-sub">Pesananmu sudah masuk ke kasir</p>

        <div class="done-kode" id="doneKode">#A01</div>

        <div class="done-detail">
            <div class="done-row">
                <span>Nama</span>
                <strong id="doneNama">—</strong>
            </div>
            <div class="done-row">
                <span>No Meja</span>
                <strong id="doneMeja">—</strong>
            </div>
            <div class="done-row">
                <span>Total Bayar</span>
                <strong id="doneTotal">Rp 0</strong>
            </div>
            <div class="done-row">
                <span>Estimasi</span>
                <strong>10–15 menit</strong>
            </div>
        </div>

        <div class="done-poin">
            <div class="ico">🎁</div>
            <p>Selamat! Kamu dapat <strong id="donePoin">+0 poin</strong><br>dari transaksi ini</p>
        </div>

        <button type="button" class="done-btn" id="doneClose">Pilih Menu Lain</button>

    </div>
</div>

@vite('resources/js/scan/index.js')

</body>
</html>
