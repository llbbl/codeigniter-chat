<?php

namespace App\Contracts;

interface WebhookDispatcher
{
    /** @param array<string, mixed> $payload */
    public function dispatch(string $event, array $payload, ?int $channelId = null): int;
}
