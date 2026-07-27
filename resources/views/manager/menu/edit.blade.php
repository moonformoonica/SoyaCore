@extends('layouts.app')

@section('title', 'Edit Menu')

@vite([
    'resources/css/manager/menu/edit.css',
    'resources/js/manager/menu/edit.js'
])

@section('content')

<div class="transaction-page">

    <div class="create-header-row">

        <div class="create-header">
            <h1>Edit Menu</h1>
            <p>Ubah informasi menu agar tetap sesuai dengan produk yang tersedia.</p>
        </div>

        <div class="header-actions">
            <a href="{{ url('/menu') }}" class="btn-cancel">Batal</a>
            <button type="submit" form="editMenuForm" class="btn-save" id="btnSave">Simpan</button>
        </div>

    </div>

    <div id="editError" class="menu-error"></div>

    <form id="editMenuForm" data-menu-id="{{ $id ?? request()->route('id') }}">

        <div class="create-grid">

            <div class="create-left">

                <div class="form-card">

                    <div class="form-row">

                        <div class="form-group">
                            <label>Nama Produk <span class="req">*</span></label>
                            <input type="text" id="namaProduk" placeholder="Contoh: Original">
                        </div>

                        <div class="form-group">
                            <label>Kategori <span class="req">*</span></label>
                            <select id="kategoriSelect">
                                <option value="">Pilih Kategori</option>
                            </select>
                        </div>

                    </div>

                    <div class="form-group full">
                        <label>Rasa <span class="req">*</span></label>

                        <div class="tag-list" id="rasaTagList">
                            <button type="button" class="tag-add" id="addRasaBtn">
                                <i class="fa-solid fa-plus"></i> Rasa
                            </button>
                        </div>

                        <div class="tag-input-row" id="rasaInputRow" style="display:none;">
                            <input type="text" id="rasaInput" placeholder="Ketik nama rasa, lalu Enter">
                            <button type="button" id="rasaInputCancel" class="tag-input-cancel">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    </div>

                </div>

                <div class="form-card">

                    <label class="section-label">Ukuran <span class="req">*</span></label>

                    <div class="ukuran-grid" id="ukuranGrid">
                        <!-- diisi lewat JS -->
                    </div>

                </div>

            </div>

            <div class="create-right">

                <div class="status-card">

                    <label>Status <span class="req">*</span></label>

                    <div class="status-toggle-box" id="statusToggleBox">
                        <span class="status-dot"></span>
                        <span id="statusToggleLabel">Tersedia</span>

                        <label class="switch">
                            <input type="checkbox" id="statusToggle" checked>
                            <span class="slider"></span>
                        </label>
                    </div>

                </div>

            </div>

        </div>

    </form>

</div>

@endsection