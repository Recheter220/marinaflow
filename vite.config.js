import { defineConfig } from 'vite';
import { fileURLToPath, URL } from 'node:url';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

// Inside Sail the dev server must listen on the same port compose maps through
// (see VITE_PORT in .env / compose.yaml). laravel-vite-plugin sees LARAVEL_SAIL=1
// and binds to 0.0.0.0 with the right HMR host on its own.
const vitePort = Number(process.env.VITE_PORT ?? 5173);

export default defineConfig({
    plugins: [
        // O Vitest sobe o Vite em modo `serve`, que o plugin do Laravel recusa
        // quando há `CI` no ambiente (GitHub Actions). Os testes não usam o plugin.
        !process.env.VITEST &&
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.jsx'],
                refresh: true,
            }),
        react(),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    server: {
        port: vitePort,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    test: {
        environment: 'jsdom',
        globals: true,
        setupFiles: ['./tests/js/setup.js'],
        include: ['tests/js/**/*.test.{js,jsx}'],
    },
});
