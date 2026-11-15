<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SoyaCore</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    @vite(['resources/css/design-tokens.css', 'resources/css/login.css'])
</head>
<body>

<div class="container">

    <!-- LEFT -->
    <div class="left">
        <div class="brand-wrap">
            <img src="{{ asset('images/logo2.png') }}" class="logo" alt="GresSOY">
        </div>

    </div>

    <!-- RIGHT -->

    <div class="right">
        <div class="login-card">
            <h1>Selamat Datang Kembali!</h1>
            <p>
                Silakan masuk menggunakan akun Anda
                untuk mengakses sistem Point of Sale
                GresSOY.
            </p>
            <div id="loginError" class="auth-error" role="alert" aria-live="polite" style="display:none;"></div>
            <form id="loginForm" method="POST" action="/api/login">
                <!-- EMAIL -->
                <div class="input-group">
                    <label for="email">Email</label>
                    <input
                        type="email"
                        name="email"
                        id="email"
                        placeholder="Masukkan email"
                        autocomplete="username"
                        aria-describedby="emailError"
                        required>
                    <small class="input-error" id="emailError" style="display:none;"></small>
                </div>

                <!-- PASSWORD -->
                <div class="input-group">
                    <label for="password">Kata Sandi</label>
                    <div class="input-box">
                        <input
                            id="password"
                            type="password"
                            name="password"
                            placeholder="Masukkan kata sandi"
                            autocomplete="current-password"
                            aria-describedby="passwordError"
                            required>
                        <button
                            type="button"
                            id="togglePassword"
                            class="fa-regular fa-eye toggle-password"
                            aria-label="Tampilkan kata sandi"
                            aria-pressed="false">
                        </button>
                    </div>
                    <small class="input-error" id="passwordError" style="display:none;"></small>
                </div>
                <p class="login-help" id="loginHelp">Lupa kata sandi? Hubungi manager toko untuk reset akses.</p>

                <button type="submit" id="loginBtn">
                    Masuk
                </button>
            </form>
                <p class="footer-note">Belum punya akun? Minta manager toko membuatkan akun.</p>
                </div>
    </div>
</div>
@vite('resources/js/auth/login.js')
</body>
</html>
