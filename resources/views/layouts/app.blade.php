<!DOCTYPE html>
<html lang="id">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    @vite([
        'resources/css/design-tokens.css',
        'resources/css/layout.css',
        'resources/css/sidebar.css',
        'resources/css/header.css',
        'resources/js/sidebar.js',
    ])

    <link rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <style>
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
    <button type="button" class="sidebar-backdrop" id="sidebarBackdrop" aria-label="Tutup navigasi" hidden></button>

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
