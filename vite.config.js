import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 
                    'resources/js/app.js',  
                    'resources/css/header.css', 
                    'resources/js/header.js', 
                    'resources/css/laporan/index.css', 
                    'resources/js/laporan/index.js', 
                    'resources/css/menu/index.css', 
                    'resources/js/menu/index.js', 
                    'resources/css/menu/edit.css', 
                    'resources/js/menu/edit.js', 
                    'resources/css/manager/loyalty.css',
                    'resources/js/manager/loyalty.js',
                    'resources/css/scan/index.css',
                    'resources/js/scan/index.js'

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
