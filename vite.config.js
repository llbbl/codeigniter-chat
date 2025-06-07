import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
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
