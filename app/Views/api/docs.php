<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Interactive documentation for the CodeIgniter Chat v1 API">
    <title>CodeIgniter Chat API documentation</title>
    <?php helper('vite'); ?>
    <?= vite_css_tag('src/js/api-docs.js') ?>
</head>
<body>
    <main>
        <div
            id="swagger-ui"
            data-openapi-base64="<?= esc($openapiBase64, 'attr') ?>"
            aria-label="CodeIgniter Chat API documentation"
        ></div>
    </main>
    <?= vite_js_tag('src/js/api-docs.js') ?>
</body>
</html>
