<!DOCTYPE html>
<html>
<head>
    <title>CodeIgniter Shoutbox</title>
    <meta name="csrf-token" content="<?= esc(csrf_hash()) ?>">
    <?php
    // Use the Vite helper to load assets
    helper('vite');
    ?>
    <script type="text/javascript">
        // Define constants for use in the external JavaScript file
        const CHAT_ROUTES = {
            update: "<?= esc(site_url('chat/update')) ?>",
            backend: "<?= esc(site_url('chat/backend')) ?>"
        };
        const CSRF_TOKEN_NAME = "<?= csrf_token() ?>";
        const CURRENT_USERNAME = "<?= esc(session()->get('username')) ?>";
    </script>
    <!-- Load assets built with Vite (jQuery is imported in the JS file) -->
    <?= vite_tags('src/js/chat.js') ?>
</head>
<body>
    <div id="wrapper">
    <div id="user-info">
        Welcome, <b><?= esc(session()->get('username')) ?></b>! 
        <a href="<?= esc(site_url('auth/logout')) ?>" class="logout-btn">Logout</a>
    </div>
    <p id="messagewindow"><span id="loading">Loading...</span></p>
    <form id="chatform">
    <div id="txt">
        Message: <input type="text" name="content" id="content" value="" />
        <div id="content-error" class="error"></div>
    </div>

    <div id="contentLoading" class="contentLoading">  
        <img src="<?= esc(base_url('images/blueloading.gif')) ?>" alt="Loading data, please wait...">  
    </div><br />

    <input type="submit" value="ok" /><br />
    </form>
    </div>
</body>
</html>
