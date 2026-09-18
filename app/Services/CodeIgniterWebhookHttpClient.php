<?php

namespace App\Services;

use App\Contracts\WebhookHttpClient;
use CodeIgniter\HTTP\CURLRequest;

final class CodeIgniterWebhookHttpClient implements WebhookHttpClient
{
    public function __construct(private readonly CURLRequest $client)
    {
    }

    public function send(string $url, string $payload, array $headers): array
    {
        $response = $this->client->post($url, [
            'allow_redirects' => false,
            'body' => $payload,
            'connect_timeout' => 5,
            'headers' => $headers,
            'http_errors' => false,
            'timeout' => 10,
        ]);

        return [
            'status' => $response->getStatusCode(),
            'body' => $response->getBody(),
        ];
    }
}
