<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SoyaCore</title>
    <link rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    @vite('resources/css/login.css')
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
            <div id="loginError" class="auth-error" style="display:none;"></div>
            <form id="loginForm" method="POST" action="#">
                @csrf
                <!-- EMAIL -->
                <div class="input-group">
                    <label>Email</label>
                    <input
                        type="email"
                        name="email"
                        id="email"
                        placeholder="Masukkan email"
                        required>
                    <small class="input-error" id="emailError" style="display:none;"></small>
                </div>

                <!-- PASSWORD -->
                <div class="input-group">
                    <label>Kata Sandi</label>
                    <div class="input-box">
                        <input
                            id="password"
                            type="password"
                            name="password"
                            placeholder="Masukkan kata sandi"
                            required>
                        <i
                            id="togglePassword"
                            class="fa-regular fa-eye toggle-password">
                        </i>
                    </div>
                    <small class="input-error" id="passwordError" style="display:none;"></small>
                </div>
                <div class="login-option">
                    <label class="remember">
                    <input
                            type="checkbox"
                            name="remember">
                        <span>Ingat saya</span>
                    </label>
                    <a href="/forgot-password" class="forgot-password">
                        Lupa Password?
                    </a>
                </div>

                <button type="submit" id="loginBtn">
                    Masuk
                </button>
            </form>
                <div class="footer">
                    Belum memiliki akun?
                    <a href="/contact-admin">
                        Hubungi Admin
                    </a>
                    </div>
                </div>
    </div>
</div>
@vite('resources/js/auth/login.js')
</body>
</html>