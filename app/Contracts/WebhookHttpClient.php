<?php

namespace App\Contracts;

interface WebhookHttpClient
{
    /**
     * @param array<string, string> $headers
     *
     * @return array{status: int, body: string}
     */
    public function send(string $url, string $payload, array $headers): array;
}
