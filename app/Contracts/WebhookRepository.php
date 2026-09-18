<?php

namespace App\Contracts;

interface WebhookRepository extends WebhookDispatcher
{
    /** @return list<array<string, mixed>> */
    public function forUser(int $userId): array;

    /** @return array<string, mixed>|null */
    public function findForUser(int $webhookId, int $userId): ?array;

    /** @param array{url: string, url_hash: string, secret: string, events: string, active: bool} $webhook */
    public function createForUser(int $userId, array $webhook): int|false;

    /** @param array{url?: string, url_hash?: string, events?: string, active?: bool} $changes */
    public function updateForUser(int $webhookId, int $userId, array $changes): bool;

    public function deleteForUser(int $webhookId, int $userId): bool;

    /** @return list<array<string, mixed>> */
    public function deliveriesForUser(int $webhookId, int $userId, int $limit = 50): array;

    public function redeliverForUser(int $deliveryId, int $userId): bool;

    /** @param array<string, mixed> $payload */
    public function enqueue(string $event, array $payload, ?int $channelId = null): int;

    /** @return list<array<string, mixed>> */
    public function dueDeliveries(int $limit = 25): array;

    public function claimDelivery(
        int $deliveryId,
        string $status,
        int $attempts,
        ?string $expectedNextAttemptAt,
        string $leaseUntil,
    ): bool;

    public function markDelivered(int $deliveryId, int $attempts, int $responseStatus, string $responseBody): bool;

    public function markFailed(
        int $deliveryId,
        int $attempts,
        ?int $responseStatus,
        string $responseBody,
        string $error,
        ?string $nextAttemptAt,
    ): bool;
}
