<!DOCTYPE html>
<html lang="en" data-theme="<?= esc($profile['theme'] ?? 'system') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your profile</title>
</head>
<body>
    <main>
        <h1>Your profile</h1>
        <p>Profile settings are also available from the Vue and Svelte chat clients.</p>
        <p><a href="<?= site_url('chat') ?>">Return to chat</a></p>
    </main>
</body>
</html>
