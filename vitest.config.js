import { defineConfig } from 'vitest/config';

/**
 * A separate file rather than a `test` key on vite.config.js, and deliberately
 * so: vitest boots a Vite server of its own, and vite.config.js loads
 * laravel-vite-plugin, whose `configureServer` hook writes public/hot. A stale
 * hot file points every rendered page at a Vite server that is no longer
 * listening, so the site serves nothing. Running the tests must not be able to
 * do that. vitest reads vitest.config.js in preference to vite.config.js, so
 * the build config — plugins and all — is simply never loaded here.
 */
export default defineConfig({
    test: {
        // The modules under test are browser code: they read the DOM, listen
        // for clicks and consult window.matchMedia.
        environment: 'jsdom',
        include: ['tests/js/**/*.test.js'],
    },
});
