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
        'resources/js/sidebar.js',
    ])

    <link rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

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

</body>
</html>