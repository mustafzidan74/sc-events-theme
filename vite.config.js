import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
  // Base path for assets
  base: './',

  // Build configuration
  build: {
    // Output directory
    outDir: 'dist',

    // Generate manifest for WordPress integration
    manifest: true,

    // Rollup options
    rollupOptions: {
      input: {
        // Frontend scripts
        'frontend-main': resolve(__dirname, 'src/js/frontend-main.js'),
        'frontend-styles': resolve(__dirname, 'src/css/frontend-main.css'),

        // Admin dashboard scripts
        'admin-main': resolve(__dirname, 'src/js/admin-main.js'),
        'admin-styles': resolve(__dirname, 'src/css/admin-main.css'),
      },
      output: {
        // Asset file naming
        entryFileNames: 'js/[name].[hash].js',
        chunkFileNames: 'js/[name].[hash].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name.endsWith('.css')) {
            return 'css/[name].[hash][extname]';
          }
          return 'assets/[name].[hash][extname]';
        },
      },
    },

    // Minification
    minify: 'terser',
    terserOptions: {
      compress: {
        drop_console: true,
        drop_debugger: true,
      },
    },

    // CSS code splitting
    cssCodeSplit: true,

    // Source maps for production (optional)
    sourcemap: false,
  },

  // CSS configuration
  css: {
    postcss: {
      plugins: [
        // Add autoprefixer and cssnano via postcss.config.js
      ],
    },
  },

  // Development server
  server: {
    // Port for dev server
    port: 3000,

    // CORS for WordPress
    cors: true,

    // Hot Module Replacement
    hmr: {
      host: 'localhost',
    },

    // Watch options
    watch: {
      usePolling: true,
    },
  },

  // Resolve aliases
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src'),
      '@css': resolve(__dirname, 'src/css'),
      '@js': resolve(__dirname, 'src/js'),
    },
  },
});
