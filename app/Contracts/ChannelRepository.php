<?php

namespace App\Contracts;

interface ChannelRepository
{
    public function generalChannelId(): int;

    public function ensureGeneralMembership(int $userId): int;

    /** @return list<array<string, mixed>> */
    public function listForUser(int $userId): array;

    /** @return array<string, mixed>|null */
    public function findChannel(int $channelId): ?array;

    public function isMember(int $channelId, int $userId): bool;

    public function isCreator(int $channelId, int $userId): bool;

    public function createPublic(string $name, string $slug, ?string $topic, int $creatorId): int|false;

    public function join(int $channelId, int $userId): bool;

    public function leave(int $channelId, int $userId): bool;

    /** @param array{name?: string, slug?: string, topic?: ?string, archived_at?: ?string} $changes */
    public function updateChannel(int $channelId, array $changes): bool;

    public function findOrCreateDm(int $firstUserId, int $secondUserId): int|false;

    /** @return list<int> */
    public function memberIds(int $channelId): array;

    public function markRead(int $channelId, int $userId): bool;

    public function messageChannelId(int $messageId): ?int;
}
