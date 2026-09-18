<?php

$captureFile = getenv('WEBHOOK_CAPTURE_FILE');
if (! is_string($captureFile) || $captureFile === '') {
    http_response_code(500);
    exit;
}

$headers = [];
foreach (getallheaders() as $name => $value) {
    $headers[strtolower($name)] = $value;
}

file_put_contents($captureFile, json_encode([
    'headers' => $headers,
    'body' => file_get_contents('php://input'),
], JSON_THROW_ON_ERROR));

http_response_code(204);
