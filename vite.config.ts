import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, loadEnv } from 'vite';

export default defineConfig(({ command, mode }) => {
    let server;
    let detectTls: string | false = false;

    if (command === 'serve') {
        const appUrl = loadEnv(mode, process.cwd(), '').APP_URL;

        if (!appUrl) {
            throw new Error('APP_URL must be set when running the Vite development server.');
        }

        const appHost = new URL(appUrl).hostname;

        server = {
            host: '127.0.0.1',
            hmr: {
                host: appHost,
            },
        };
        detectTls = appHost;
    }

    return {
        server,
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.tsx'],
                refresh: true,
                detectTls,
                fonts: [
                    bunny('Instrument Sans', {
                        weights: [400, 500, 600],
                    }),
                ],
            }),
            inertia(),
            react({
                babel: {
                    plugins: ['babel-plugin-react-compiler'],
                },
            }),
            tailwindcss(),
            wayfinder({
                formVariants: true,
            }),
        ],
    };
});
