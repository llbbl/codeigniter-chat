<!DOCTYPE html>
<html>
<head>
    <title>CodeIgniter Chat - Vue.js Edition</title>
    <meta name="csrf-token" content="<?= esc(csrf_hash()) ?>">
    <?php
    // Use the Vite helper to load assets
    helper('vite');
    ?>
    <script type="text/javascript">
        // Define constants for use in the Vue application
        window.CHAT_ROUTES = {
            update: "<?= esc(site_url('chat/update')) ?>",
            vueApi: "<?= esc(site_url('chat/vueApi')) ?>"
        };
        window.CSRF_TOKEN_NAME = "<?= csrf_token() ?>";
        window.CURRENT_USERNAME = "<?= esc(session()->get('username')) ?>";
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