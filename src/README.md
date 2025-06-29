# Front-end Build System with Vite

This project uses [Vite](https://vitejs.dev/) as a front-end build system to process CSS and JavaScript files.

## Directory Structure

- `src/css/`: Contains the source CSS files
- `src/js/`: Contains the source JavaScript files
- `public/dist/`: Contains the built files (generated by Vite)

## Development

To start the development server:

```bash
npm run dev
```

This will start a development server at http://localhost:5173/ that will automatically reload when you make changes to the source files.

## Building for Production

To build the assets for production:

```bash
npm run build
```

This will generate optimized files in the `public/dist/` directory.

## How It Works

1. The source files are located in the `src/` directory.
2. Vite processes these files and outputs them to the `public/dist/` directory.
3. The `ViteHelper` class in `app/Helpers/ViteHelper.php` handles loading the correct assets in the views.
4. In development mode, assets are loaded from the Vite development server.
5. In production mode, assets are loaded from the `public/dist/` directory.

## Adding New Assets

1. Add new CSS files to `src/css/`
2. Add new JavaScript files to `src/js/`
3. Update the `vite.config.js` file to include the new files in the `input` object
4. Import CSS files in JavaScript files using `import '../css/your-file.css'`
5. Use the `vite_tags()` helper function in views to load the assets

## Updating jQuery

Currently, jQuery is loaded separately from the Vite build system. To include it in the build system:

1. Install jQuery using npm:
   ```bash
   npm install jquery
   ```

2. Import jQuery in your JavaScript files:
   ```javascript
   import $ from 'jquery';
   ```

3. Update the views to remove the separate jQuery script tag