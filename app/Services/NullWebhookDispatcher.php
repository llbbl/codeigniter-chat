<?php

namespace App\Services;

use App\Contracts\WebhookDispatcher;

final class NullWebhookDispatcher implements WebhookDispatcher
{
    public function dispatch(string $event, array $payload, ?int $channelId = null): int
    {
        return 0;
    }
}
