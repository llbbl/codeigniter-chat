import { resolve } from 'node:path';
import { svelte, vitePreprocess } from '@sveltejs/vite-plugin-svelte';
import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vite';

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
  ],
  // Base public path when served in production
  base: '/',

  // Define the build output directory
  build: {
    // Output directory for the built files
    outDir: 'public/dist',

    // Target ES2020 to retain support for browsers older than Vite 7's default
    // "baseline-widely-available" (mid-2023). Adjust upward if older support is
    // no longer needed.
    target: 'es2020',

    // Generate manifest.json in the output directory
    manifest: true,

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
