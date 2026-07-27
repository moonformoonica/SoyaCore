<!DOCTYPE html>
<html lang="id">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title')</title>

    @vite([
        'resources/css/sidebar.css',
        'resources/css/header.css',
        'resources/css/dashboard.css',
        'resources/css/manager/transaksi.css',
        'resources/css/profil.css',
        'resources/js/manager/menu/index.js',
        'resources/js/manager/menu/create.js',
        'resources/js/profil.js'
        
    ])

    

    <link rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/manager/transaksi.css') }}">

    <style>
        .main { min-width: 0; }
        .content { min-width: 0; overflow-x: hidden; }

        @view-transition {
        navigation: auto;
        }
    </style>

    @stack('styles')

</head>

<body>

<div class="layout">

    {{-- Sidebar --}}
    @include('components.sidebar')

    <div class="main">

        {{-- Header --}}
        @include('components.header')

        <div class="content">

            @yield('content')

        </div>

    </div>

</div>

@stack('scripts')

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const toggleBtn = document.getElementById('sidebarToggle');
        const sidebar = document.querySelector('.sidebar');

        if (!toggleBtn || !sidebar) return;

        toggleBtn.addEventListener('click', function () {
            sidebar.classList.toggle('collapsed');

            localStorage.setItem(
                'sidebar_collapsed',
                sidebar.classList.contains('collapsed') ? '1' : '0'
            );
        });

        // Terapkan preferensi yang tersimpan saat halaman pertama dimuat.
        if (localStorage.getItem('sidebar_collapsed') === '1') {
            sidebar.classList.add('collapsed');
        }

        try {
            const rawUser = localStorage.getItem('auth_user');
            const role = rawUser ? JSON.parse(rawUser).role : null;

            sidebar.querySelectorAll('[data-role]').forEach(function (link) {
                if (link.dataset.role !== role) {
                    link.style.display = 'none';
                }
            });
        } catch (e) {
        }
    });
</script>

</body>
</html>