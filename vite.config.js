import { defineConfig } from 'vite';

// wp-manifest no longer ships a JS bundle — its settings page is pure PHP + vanilla JS.
// Its shared-settings JS/CSS is copied by tools/copy-php.js instead.

export default defineConfig(() => ({}));
