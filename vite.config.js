import { defineConfig } from 'vite';
import { resolve } from 'path';
import vue from '@vitejs/plugin-vue';
import { svelte, vitePreprocess } from '@sveltejs/vite-plugin-svelte';

export default defineConfig({
  // Both Vue and Svelte plugins can coexist in the same Vite config
  // Vite will use the appropriate plugin based on file extension (.vue or .svelte)
  plugins: [
    vue(),
    svelte({
      // Enable preprocessing for SCSS in Svelte components
      // vitePreprocess uses Vite's built-in preprocessors
      preprocess: vitePreprocess()
    })
  ],
  // Base public path when served in production
  base: '/',

  // Define the build output directory
  build: {
    // Output directory for the built files
    outDir: 'public/dist',

    // Generate manifest.json in the output directory
    manifest: true,

    // Configure rollup options
    rollupOptions: {
      input: {
        // Entry points for our JavaScript files
        'chat': resolve(__dirname, 'src/js/chat.js'),
        'chat-json': resolve(__dirname, 'src/js/chat-json.js'),
        'chat-html': resolve(__dirname, 'src/js/chat-html.js'),
        'chat-vue': resolve(__dirname, 'src/vue/main.js'), // Vue.js entry point
        'chat-svelte': resolve(__dirname, 'src/svelte/main.js'), // Svelte entry point
        'styles': resolve(__dirname, 'src/css/chat.scss'),
        'zipcodes': resolve(__dirname, 'src/css/zipcodes.scss')
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
        }
      }
    }
  },

  // Configure the development server
  server: {
    // Serve from the project root
    root: '.',
    // Open the browser on server start
    open: false,
    // Configure CORS
    cors: true
  }
});
