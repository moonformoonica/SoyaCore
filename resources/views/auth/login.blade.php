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

        <img src="{{ asset('images/aset.png') }}" class="logo" alt="Logo">

    </div>

    <!-- RIGHT -->

    <div class="right">

        <div class="login-card">

            <h1>Selamat Datang Kembali!</h1>

            <p>
                Silakan masuk menggunakan akun Anda
                untuk mengakses sistem Point of Sale
                Gres'Soy.
            </p>

            {{-- Error Login (dari JS, ditampilkan dinamis) --}}
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

                <!-- Remember + Lupa PasswordR -->

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

<script>

const password = document.getElementById("password");
const toggle = document.getElementById("togglePassword");

toggle.addEventListener("click", function () {

    if (password.type === "password") {

        password.type = "text";

        toggle.classList.remove("fa-eye");
        toggle.classList.add("fa-eye-slash");

    } else {

        password.type = "password";

        toggle.classList.remove("fa-eye-slash");
        toggle.classList.add("fa-eye");

    }

});

// ====== Proses login ke API ======
const loginForm = document.getElementById('loginForm');
const loginBtn = document.getElementById('loginBtn');
const loginError = document.getElementById('loginError');
const emailError = document.getElementById('emailError');
const passwordError = document.getElementById('passwordError');

function resetErrors() {
    loginError.style.display = 'none';
    loginError.textContent = '';
    emailError.style.display = 'none';
    passwordError.style.display = 'none';
}

loginForm.addEventListener('submit', async function (e) {
    e.preventDefault();
    resetErrors();

    loginBtn.disabled = true;
    loginBtn.textContent = 'Memproses...';

    const email = document.getElementById('email').value;
    const passwordValue = password.value;

    try {
        const res = await fetch('/api/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ email, password: passwordValue }),
        });

        const result = await res.json();

        if (!res.ok) {
            // 422 = validasi Laravel (email/password field errors), atau kredensial salah
            if (res.status === 422 && result.errors) {
                if (result.errors.email) {
                    emailError.textContent = result.errors.email[0];
                    emailError.style.display = 'block';
                }
                if (result.errors.password) {
                    passwordError.textContent = result.errors.password[0];
                    passwordError.style.display = 'block';
                }
                if (!result.errors.email && !result.errors.password) {
                    loginError.textContent = result.message || 'Email atau password salah.';
                    loginError.style.display = 'block';
                }
            } else if (res.status === 403) {
                loginError.textContent = result.message || 'Akun ini sudah dinonaktifkan. Hubungi manager.';
                loginError.style.display = 'block';
            } else {
                loginError.textContent = result.message || 'Login gagal. Silakan coba lagi.';
                loginError.style.display = 'block';
            }
            return;
        }

        // Sukses: simpan token & data user
        localStorage.setItem('auth_token', result.token);
        localStorage.setItem('auth_user', JSON.stringify(result.user));

        // Arahkan sesuai role — manager ke dashboard, kasir ke transaksi
        if (result.user.role === 'manager') {
            window.location.href = '/dashboard';
        } else {
            window.location.href = '/pesanan';
        }

    } catch (err) {
        loginError.textContent = 'Tidak bisa terhubung ke server. Cek koneksi kamu.';
        loginError.style.display = 'block';
    } finally {
        loginBtn.disabled = false;
        loginBtn.textContent = 'Masuk';
    }
});

</script>

</body>
</html>