@extends('layouts.app')

@section('title', 'Transaksi')

@section('content')

<div class="transaction-page">

    <div class="page-header">

        <div>
            <h1>Transaksi</h1>
            <p>Riwayat seluruh transaksi penjualan GresSOY</p>
        </div>

        <button class="download-btn" data-role="manager">
            <i class="fa-solid fa-download"></i>
            Unduh
        </button>

    </div>

    {{-- =========================
            CARD STATISTIK
    ========================== --}}

    <div class="stats-grid" data-role="manager">
        <div class="stat-card">
            <h5>Total Revenue</h5>
            <h2 id="statTotalRevenue">Rp 0</h2>
            <span>Periode data laporan</span>
        </div>

        <div class="stat-card">
            <h5>Jumlah Transaksi</h5>
            <h2 id="statJumlahTransaksi">0</h2>
            <span>Total keseluruhan</span>
        </div>

        <div class="stat-card">
            <h5>Rata-rata Nilai</h5>
            <h2 id="statRataNilai">Rp 0</h2>
            <span>Per transaksi</span>
        </div>

        <div class="stat-card">
            <h5>Pelanggan Aktif</h5>
            <h2 id="statPelangganAktif">0</h2>
            <span>Telah bertransaksi ≥10 kali</span>
        </div>

    </div>

    <p class="stats-note" id="statsNote" data-role="manager" style="display:none;">
        <i class="fa-solid fa-circle-info"></i>
        Kartu di atas mengikuti <strong>rentang tanggal</strong> saja, filter Sumber, Kasir,
        Status, Metode, Redeem, dan kata kunci pencarian belum memengaruhinya.
    </p>

    <div class="stats-grid" data-role="kasir" style="display:none;">

        <div class="stat-card">
            <h5>Total Transaksi Hari ini</h5>
            <h2 id="kasirTotalTransaksi">–</h2>
            <span>Transaksi milik kamu</span>
        </div>

        <div class="stat-card">
            <h5>Omzet Hari ini</h5>
            <h2 id="kasirOmzet">Rp 0</h2>
            <span>Dari transaksi lunas</span>
        </div>

        <div class="stat-card">
            <h5>Proses</h5>
            <h2 id="kasirProses">0</h2>
            <span>Menunggu pembayaran</span>
        </div>

        <div class="stat-card">
            <h5>Selesai</h5>
            <h2 id="kasirSelesai">0</h2>
            <span>Sudah dibayar</span>
        </div>
    </div>

    {{-- =========================
            FILTER
    ========================== --}}

    <div class="filter-bar">
        <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input
                type="text"
                id="trxSearch"
                placeholder="Cari kode pesanan / nama pelanggan">
        </div>

            <div class="filter-dropdowns" data-role="manager">

                <div class="custom-select" data-name="sumber">
                    <button type="button" class="custom-select-trigger">
                        <span class="selected-label">Sumber</span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>
                    <ul class="custom-options">
                        <li data-value="">Semua Sumber</li>
                        <li data-value="self_order">SoyaScan</li>
                        <li data-value="kasir">Kasir</li>
                        {{-- Transaksi Juni-Juli hasil impor CSV, sebelum
                             SoyaCore dipakai. Read-only. --}}
                        <li data-value="historis">Impor CSV (Juni-Juli)</li>
                    </ul>
                    <input type="hidden" name="sumber" value="">
                </div>

                <div class="custom-select" data-name="urut">
                    <button type="button" class="custom-select-trigger">
                        <span class="selected-label">Terbaru</span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>
                    <ul class="custom-options">
                        <li data-value="">Terbaru</li>
                        <li data-value="terlama">Terlama</li>
                    </ul>
                    <input type="hidden" name="urut" value="">
                </div>

                <div class="custom-select" data-name="redeem">
                    <button type="button" class="custom-select-trigger">
                        <span class="selected-label">Redeem</span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>
                    <ul class="custom-options">
                        <li data-value="">Semua</li>
                        <li data-value="true">Pakai Redeem</li>
                        <li data-value="false">Tanpa Redeem</li>
                    </ul>
                    <input type="hidden" name="redeem" value="">
                </div>

                <div class="custom-select" data-name="kasir">
                    <button type="button" class="custom-select-trigger">
                        <span class="selected-label">Kasir</span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>
                    <ul class="custom-options" id="trxKasirOptions">
                        <li data-value="">Semua Kasir</li>
                    </ul>
                    <input type="hidden" name="kasir" value="">
                </div>

                <div class="custom-select" data-name="status">
                    <button type="button" class="custom-select-trigger">
                        <span class="selected-label">Status</span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>
                    <ul class="custom-options">
                        <li data-value="">Semua Status</li>
                        <li data-value="pending">Proses</li>
                        <li data-value="lunas">Selesai</li>
                        <li data-value="batal">Batal</li>
                        <li data-value="batal_sebagian">Batal Sebagian</li>
                    </ul>
                    <input type="hidden" name="status" value="">
                </div>

                <div class="custom-select" data-name="metode">
                    <button type="button" class="custom-select-trigger">
                        <span class="selected-label">Metode</span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>
                    <ul class="custom-options">
                        {{-- Nilai harus persis seperti yang diterima backend:
                             'cash' | 'qris' (bukan 'tunai'), kalau tidak 422. --}}
                        <li data-value="">Semua Metode</li>
                        <li data-value="cash">Tunai</li>
                        <li data-value="qris">QRIS</li>
                    </ul>
                    <input type="hidden" name="metode" value="">
                </div>

            </div>

            <div class="trx-date-range">
                <i class="fa-regular fa-calendar"></i>
                <input type="date" id="trxTanggalMulai" aria-label="Tanggal mulai">
                <span class="trx-date-sd">s/d</span>
                <input type="date" id="trxTanggalSelesai" aria-label="Tanggal selesai">
                <button type="button" class="trx-date-reset" id="trxTanggalReset" title="Hapus filter tanggal">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

    </div>

    {{-- =========================
            TABLE
    ========================== --}}

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Id Transaksi</th>
                    <th>Id Pelanggan</th>
                    <th>Id Pesanan</th>
                    <th>Sumber</th>
                    <th>Total</th>
                    <th>Metode</th>
                    <th>Status</th>
                    <th>Poin</th>
                    <th>Waktu</th>
                    <th>Detail</th>
                </tr>
            </thead>

            <tbody id="trxTableBody">
                <tr>
                    <td colspan="9" class="trx-state">Memuat transaksi...</td>
                </tr>
            </tbody>
        </table>

        <div class="table-footer">
            <span id="trxInfo">—</span>
            <div class="pagination" id="trxPagination"></div>
        </div>
    </div>

    {{-- =========================
            MODAL DETAIL TRANSAKSI
    ========================== --}}

    <div class="detail-modal-backdrop" id="detailModalBackdrop">
        <div class="detail-modal">

            <div class="detail-modal-header">
                <h3 id="detailKodePesanan">—</h3>
                <button type="button" class="detail-modal-close" id="detailModalClose">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div id="detailModalLoading" class="detail-modal-loading">Memuat detail transaksi...</div>
            <div id="detailModalError" class="detail-modal-error"></div>

            <div id="detailModalBody" style="display:none;">

                <div class="detail-modal-meta">
                    <div>
                        <span class="detail-modal-label">Status</span>
                        <span id="detailStatus" class="status">—</span>
                    </div>
                    <div>
                        <span class="detail-modal-label">Waktu Dibuat</span>
                        <span id="detailCreatedAt">—</span>
                    </div>
                    <div>
                        <span class="detail-modal-label">Waktu Lunas</span>
                        <span id="detailWaktuLunas">—</span>
                    </div>
                </div>

                <div class="detail-modal-meta">
                    <div>
                        <span class="detail-modal-label">Pelanggan</span>
                        <span id="detailPelanggan">—</span>
                    </div>
                    <div>
                        <span class="detail-modal-label">Kasir</span>
                        <span id="detailKasir">—</span>
                    </div>
                    <div>
                        <span class="detail-modal-label">Metode Bayar</span>
                        <span id="detailMetodeBayar">—</span>
                    </div>
                </div>

                <div class="table-scroll">
                    <table class="detail-item-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Ukuran</th>
                                <th>Qty</th>
                                <th>Harga Satuan</th>
                                <th>Subtotal</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody id="detailItemsBody"></tbody>
                    </table>
                </div>

                <div class="detail-modal-summary">
                    <div><span>Subtotal</span><span id="detailSubtotal">Rp 0</span></div>
                    <div><span>Diskon</span><span id="detailDiskon">Rp 0</span></div>
                    <div class="total"><span>Total</span><span id="detailTotal">Rp 0</span></div>
                    <div><span>Poin Didapat</span><span id="detailPoin">0</span></div>
                </div>

                <div class="detail-aksi" id="detailAksi" style="display:none;">

                    <span class="detail-aksi-label">Selesaikan Pesanan</span>

                    <div class="detail-aksi-metode">
                        <button type="button" class="aksi-metode-btn" data-metode="cash">
                            <i class="fa-solid fa-money-bill-1"></i> Tunai
                        </button>
                        <button type="button" class="aksi-metode-btn" data-metode="qris">
                            <i class="fa-solid fa-qrcode"></i> QRIS
                        </button>
                    </div>

                    <div class="detail-aksi-note" id="detailAksiNote"></div>

                    <div class="detail-aksi-row">
                        <button type="button" class="aksi-batal-btn" id="aksiBatalBtn">Batalkan</button>
                        <button type="button" class="aksi-lunas-btn" id="aksiLunasBtn" disabled>Tandai Lunas</button>
                    </div>

                </div>

                <div class="detail-aksi" id="detailKoreksi" style="display:none;">

                    <span class="detail-aksi-label">Batalkan / Koreksi Pesanan</span>

                    <div class="koreksi-field">
                        <label for="koreksiAlasan">Alasan <span class="wajib">*</span></label>
                        <input type="text" id="koreksiAlasan" maxlength="200"
                               placeholder="Contoh: Pelanggan salah pesan ukuran" autocomplete="off">
                        <p class="koreksi-hint">Minimal 3 karakter, ditulis kasir, tidak diisi otomatis.</p>
                    </div>

                    <div class="koreksi-field">
                        <label>Item yang dibatalkan</label>
                        <p class="koreksi-hint">
                            Biarkan kosong untuk membatalkan <strong>seluruh</strong> pesanan.
                        </p>
                        <div id="koreksiItems" class="koreksi-items"></div>
                    </div>

                    <div class="detail-aksi-note" id="koreksiNote"></div>

                    <div class="detail-aksi-row">
                        <button type="button" class="aksi-batal-btn" id="koreksiKirimBtn">Proses Pembatalan</button>
                    </div>

                </div>

                <div class="koreksi-hasil" id="koreksiHasil" style="display:none;"></div>

            </div>

        </div>
    </div>

@endsection

@push('scripts')
@vite('resources/js/manager/transaksi.js')
@endpush