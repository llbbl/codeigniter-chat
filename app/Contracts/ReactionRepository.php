<?php

namespace App\Contracts;

interface ReactionRepository
{
    public function messageExists(int $messageId): bool;

    public function add(int $messageId, int $userId, string $emoji): bool;

    public function remove(int $messageId, int $userId, string $emoji): bool;

    /** @return list<array{emoji: string, count: int, users: list<string>, reacted_by_current_user: bool}> */
    public function forMessage(int $messageId, ?int $currentUserId = null): array;
}
