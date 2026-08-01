import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // Semua jalur di bawah HARUS benar-benar ada. Vite menyelesaikan
            // daftar ini di awal proses build, jadi satu jalur salah membuat
            // `npm run build` gagal total, sementara `npm run dev` tetap
            // terlihat normal karena memuat berkas sesuai permintaan.
            input: [
                'resources/css/app.css',
                'resources/js/app.js',

                'resources/css/header.css',
                'resources/js/header.js',

                'resources/css/sidebar.css',
                'resources/js/sidebar.js',

                // Dimuat layouts/app.blade.php untuk SETIAP halaman, jadi
                // ketinggalannya membuat seluruh halaman ber-layout gagal
                // render begitu di-build.
                'resources/css/profil.css',

                'resources/css/login.css',
                'resources/js/auth/login.js',

                'resources/css/dashboard.css',
                // `dashboard/index.css` BUKAN duplikat `dashboard.css`.
                // Yang pertama isi halamannya (kartu statistik, panel, grafik),
                // yang kedua kerangka layout dan ikut lewat app.css. Halaman
                // dashboard memanggilnya lewat @vite, jadi tanpa baris ini
                // `npm run build` menghasilkan manifest tanpa entri itu dan
                // halaman Dashboard gagal render di production, sementara
                // `npm run dev` tetap terlihat normal.
                'resources/css/dashboard/index.css',
                'resources/js/dashboard/index.js',

                'resources/css/manager/transaksi.css',
                'resources/js/manager/transaksi.js',

                'resources/css/manager/laporan/index.css',
                'resources/js/manager/laporan/index.js',
                // Halaman Laporan Kasir punya CSS sendiri dan memanggilnya
                // lewat @vite. Sempat tertinggal di sini sementara berkas JS-nya
                // terdaftar, jadi halamannya ikut gagal render saat di-build.
                'resources/css/manager/laporan/kasir.css',
                'resources/js/manager/laporan/kasir.js',

                'resources/css/manager/menu/index.css',
                'resources/js/manager/menu/index.js',
                'resources/css/manager/menu/create.css',
                'resources/js/manager/menu/create.js',
                'resources/css/manager/menu/edit.css',
                'resources/js/manager/menu/edit.js',

                'resources/css/manager/loyalty.css',
                'resources/js/manager/loyalty.js',

                'resources/css/kasir/pesanan.css',
                'resources/js/kasir/pesanan.js',

                'resources/css/pengaturan/index.css',
                'resources/js/pengaturan/index.js',

                'resources/css/scan/index.css',
                'resources/js/scan/index.js',
            ],

            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
