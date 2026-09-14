<?php

namespace App\Contracts;

interface PushSubscriptionRepository
{
    /** @param array{endpoint: string, endpoint_hash: string, p256dh: string, auth: string, content_encoding: string} $subscription */
    public function saveForUser(int $userId, array $subscription): bool;

    public function deleteForUser(int $userId, string $endpointHash): bool;
}
