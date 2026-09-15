<!DOCTYPE html>
<html lang="en" data-theme="<?= esc(session()->get('theme') ?? 'system') ?>">
<head>
    <title>CodeIgniter Chat - Vue.js Edition</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#3f6398">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/apple-touch-icon-180x180.png">
    <meta name="csrf-token" content="<?= esc(csrf_hash()) ?>">
    <?php
    // Use the Vite helper to load assets
    helper('vite');
    ?>
    <script type="text/javascript">
        /**
         * ====================================================================
         * FRONTEND CONFIGURATION FOR VUE.JS CHAT APPLICATION
         * ====================================================================
         *
         * These global variables are passed from PHP to JavaScript to configure
         * the Vue.js chat application. This is a common pattern when you need
         * to pass server-side data (like session info) to your frontend.
         *
         * Security Note:
         * - All values are escaped using esc() to prevent XSS attacks
         * - The WebSocket token is tied to the user's session and expires
         * - Never expose sensitive data like passwords or API keys here
         *
         * ====================================================================
         */

        // API routes for chat operations
        window.CHAT_ROUTES = {
            update: "<?= esc(site_url('api/v1/messages')) ?>",
            messagesApi: "<?= esc(site_url('api/v1/messages')) ?>",
            pushSubscriptions: "<?= esc(site_url('api/v1/push-subscriptions')) ?>",
            profile: "<?= esc(site_url('api/v1/profile')) ?>",
            profileAvatar: "<?= esc(site_url('api/v1/profile/avatar')) ?>",
            profiles: "<?= esc(site_url('api/v1/profiles')) ?>"
        };

        // CSRF protection token name (CodeIgniter's built-in XSS protection)
        window.CSRF_TOKEN_NAME = "<?= csrf_token() ?>";

        // Current logged-in user's information
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
         * @see App\Helpers\WebSocketTokenHelper for token generation/validation
         * @see App\Libraries\ChatWebSocketServer for server-side validation
         */
        window.WEBSOCKET_TOKEN = "<?= esc(session()->get('websocket_token') ?? '') ?>";
        window.PUSH_PUBLIC_KEY = "<?= esc((string) env('push.vapidPublicKey', '')) ?>";
    </script>
    <!-- Load assets built with Vite -->
    <?= vite_tags('src/vue/main.js') ?>
</head>
<body>
    <div id="app">
        <!-- Vue will mount here -->
        <div class="loading-app">Loading application...</div>
    </div>
</body>
</html>
