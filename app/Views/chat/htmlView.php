<!DOCTYPE html>
<html>
<head>
    <title>CodeIgniter Shoutbox</title>
    <?php
    // Use the Vite helper to load assets
    helper('vite');
    ?>
    <!-- Load assets built with Vite -->
    <?= vite_tags('src/js/chat-html.js') ?>
</head>
<body>
    <div id="wrapper">
    <p id="messagewindow">

    <?= esc($html ?? '') ?>

    </p>
    <?php if (session()->has('errors')): ?>
        <div class="error">
            <?php foreach (session('errors') as $error): ?>
                <p><?= esc($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <form id="chatform" action="<?= esc(site_url('chat/update')) ?>" method="post">
    <?= csrf_field() ?>
    <div id="author">
        Name: <input type="text" name="name" id="name" value="<?= esc(old('name')) ?>" />
        <div id="name-error" class="error"></div>
    </div><br />

    <div id="txt">
        Message: <input type="text" name="message" id="content" value="<?= esc(old('message')) ?>" />
        <div id="content-error" class="error"></div>
    </div>

    <br />
    <input type="hidden" name="html_redirect" value="true" />
    <input type="submit" value="ok" /><br />
    </form>
    </div>
</body>
</html>
