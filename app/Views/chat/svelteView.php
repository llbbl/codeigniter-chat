<!DOCTYPE html>
<html>
<head>
    <title>CodeIgniter Chat - Svelte Edition</title>
    <meta name="csrf-token" content="<?= esc(csrf_hash()) ?>">
    <?php
    /**
     * ========================================================================
     * SVELTE VIEW TEMPLATE
     * ========================================================================
     *
     * This PHP view serves as the entry point for the Svelte chat application.
     * It's responsible for:
     *
     * 1. Setting up the HTML document structure
     * 2. Passing server-side data to the Svelte app via window globals
     * 3. Loading the compiled Svelte assets using Vite
     *
     * COMPARISON WITH vueView.php:
     * ----------------------------
     * This file is nearly identical to vueView.php. The main difference is:
     * - Different title ("Svelte Edition" vs "Vue.js Edition")
     * - Loads src/svelte/main.js instead of src/vue/main.js
     * - Uses svelteApi endpoint instead of vueApi
     *
     * The pattern of passing data via window globals is the same because
     * both frameworks need the same information from the PHP backend.
     *
     * ========================================================================
     */

    // Load the Vite helper to include compiled assets
    helper('vite');
    ?>
    <script type="text/javascript">
        /**
         * ====================================================================
         * FRONTEND CONFIGURATION FOR SVELTE CHAT APPLICATION
         * ====================================================================
         *
         * These global variables are passed from PHP to JavaScript to configure
         * the Svelte chat application. This is a common pattern when you need
         * to pass server-side data (like session info) to your frontend.
         *
         * WHY USE WINDOW GLOBALS?
         * -----------------------
         * In a traditional server-rendered application, we need a way to pass
         * data from PHP to JavaScript. Options include:
         *
         * 1. Window globals (used here) - Simple and widely supported
         * 2. Data attributes - Good for small amounts of data
         * 3. JSON in a <script> tag - Similar to window globals
         * 4. API calls on mount - Adds latency but keeps concerns separated
         *
         * We use window globals because:
         * - They're immediately available when the app starts
         * - No additional HTTP requests needed
         * - Simple to implement and understand
         *
         * Security Notes:
         * - All values are escaped using esc() to prevent XSS attacks
         * - The WebSocket token is tied to the user's session and expires
         * - Never expose sensitive data like passwords or API keys here
         *
         * ====================================================================
         */

        // API routes for chat operations
        // These URLs are used by the Svelte app to communicate with the backend
        window.CHAT_ROUTES = {
            // POST endpoint for sending new messages
            update: "<?= esc(site_url('chat/update')) ?>",
            // GET endpoint for fetching messages (JSON format)
            svelteApi: "<?= esc(site_url('chat/svelteApi')) ?>"
        };

        // CSRF protection token name (CodeIgniter's built-in XSS protection)
        // This token must be included in all POST requests
        window.CSRF_TOKEN_NAME = "<?= csrf_token() ?>";

        // Current logged-in user's information
        // These are read from the PHP session and passed to the frontend
        window.CURRENT_USERNAME = "<?= esc(session()->get('username')) ?>";
        window.CURRENT_USER_ID = <?= (int) session()->get('user_id') ?>;

        /**
         * WebSocket Authentication Token
         *
         * This token is generated when the user logs in (see Auth::processLogin)
         * and is used to authenticate WebSocket connections. The WebSocket server
         * runs as a separate process and cannot access PHP sessions, so we use
         * this token-based approach.
         *
         * The token will be included in the WebSocket connection URL:
         * ws://localhost:8080?token=xxx&user_id=123
         *
         * This is the SAME token used by the Vue.js version - both frontends
         * use the same authentication mechanism.
         *
         * @see App\Helpers\WebSocketTokenHelper for token generation/validation
         * @see App\Libraries\ChatWebSocketServer for server-side validation
         */
        window.WEBSOCKET_TOKEN = "<?= esc(session()->get('websocket_token') ?? '') ?>";
    </script>

    <!--
        Load assets built with Vite

        The vite_tags() helper does the following:
        - In development: Links to Vite dev server for HMR (Hot Module Replacement)
        - In production: Loads the compiled and hashed assets from public/dist/

        This is the Svelte entry point (src/svelte/main.js), which imports:
        - App.svelte (main component)
        - chat.scss (shared styles)
    -->
    <?= vite_tags('src/svelte/main.js') ?>
</head>
<body>
    <!--
        Svelte Mount Point

        The Svelte app will be mounted to this div element.
        In main.js, we use:
          mount(App, { target: document.getElementById('app'), ... })

        The "Loading application..." message is displayed while the JavaScript
        loads and parses. It's immediately replaced when Svelte mounts.
    -->
    <div id="app">
        <!-- Svelte will mount here -->
        <div class="loading-app">Loading application...</div>
    </div>
</body>
</html>
