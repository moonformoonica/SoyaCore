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

        localStorage.setItem('auth_token', result.token);
        localStorage.setItem('auth_user', JSON.stringify(result.user));

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
