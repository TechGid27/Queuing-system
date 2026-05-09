import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    build: {
        rollupOptions: {
            output: {
                // Keep font files alongside the CSS so relative paths resolve
                assetFileNames: (assetInfo) => {
                    if (/\.(woff2?|ttf|eot)$/.test(assetInfo.name)) {
                        return 'fonts/[name]-[hash][extname]';
                    }
                    return 'assets/[name]-[hash][extname]';
                },
            },
        },
    },
});
