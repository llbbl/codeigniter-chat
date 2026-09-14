import { resolve } from 'node:path';
import { svelte, vitePreprocess } from '@sveltejs/vite-plugin-svelte';
import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
  // Both Vue and Svelte plugins can coexist in the same Vite config
  // Vite will use the appropriate plugin based on file extension (.vue or .svelte)
  plugins: [
    vue(),
    svelte({
      // Enable preprocessing for SCSS in Svelte components
      // vitePreprocess uses Vite's built-in preprocessors
      preprocess: vitePreprocess(),
    }),
    VitePWA({
      // PHP pages live at the site root while Vite assets live in /dist.
      // Emitting the worker at / lets it control both framework routes.
      outDir: 'public',
      buildBase: '/',
      srcDir: 'src',
      filename: 'sw.js',
      strategies: 'injectManifest',
      registerType: 'autoUpdate',
      injectRegister: false,
      // The manifest is a source-controlled public file because this project
      // serves PHP entry pages rather than Vite-generated HTML.
      manifest: false,
      injectManifest: {
        globDirectory: 'public',
        globPatterns: [
          'dist/**/*.{js,css,woff,woff2}',
          'offline.html',
          'manifest.webmanifest',
          'pwa-192x192.png',
          'pwa-512x512.png',
          'maskable-icon-512x512.png',
          'apple-touch-icon-180x180.png',
        ],
      },
    }),
  ],
  // Base public path when served in production
  base: '/',

  // Define the build output directory
  build: {
    // Output directory for the built files
    outDir: 'public/dist',

    // public/ is already the web root. Copying it into public/dist would
    // duplicate the manifest, icons, and generated service worker recursively.
    copyPublicDir: false,

    // Target ES2020 to retain support for browsers older than Vite 7's default
    // "baseline-widely-available" (mid-2023). Adjust upward if older support is
    // no longer needed.
    target: 'es2020',

    // Generate manifest.json where App\Helpers\ViteHelper reads it.
    manifest: 'manifest.json',

    // Configure rollup options
    rollupOptions: {
      input: {
        // Entry points for our JavaScript files
        chat: resolve(import.meta.dirname, 'src/js/chat.js'),
        'chat-json': resolve(import.meta.dirname, 'src/js/chat-json.js'),
        'chat-html': resolve(import.meta.dirname, 'src/js/chat-html.js'),
        'chat-vue': resolve(import.meta.dirname, 'src/vue/main.js'), // Vue.js entry point
        'chat-svelte': resolve(import.meta.dirname, 'src/svelte/main.js'), // Svelte entry point
        styles: resolve(import.meta.dirname, 'src/css/chat.scss'),
        zipcodes: resolve(import.meta.dirname, 'src/css/zipcodes.scss'),
      },
      output: {
        // Configure output file naming
        entryFileNames: 'js/[name]-[hash].js',
        chunkFileNames: 'js/[name]-[hash].js',
        assetFileNames: (assetInfo) => {
          // Put CSS files in the css directory
          if (assetInfo.name.endsWith('.css')) {
            return 'css/[name]-[hash][extname]';
          }
          // Put other assets in the assets directory
          return 'assets/[name]-[hash][extname]';
        },
      },
    },
  },

  // Configure the development server
  server: {
    // Open the browser on server start
    open: false,
    // Configure CORS
    cors: true,
  },
});
