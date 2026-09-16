<?php

namespace App\Contracts;

interface ChatRepository
{
    /**
     * @return array{
     *     messages: list<array<string, mixed>>,
     *     pagination: array<string, int|bool|float>
     * }
     */
    public function getMsgPaginated(int $page = 1, int $perPage = 10, ?int $channelId = null): array;

    /**
     * @return array{
     *     messages: list<array<string, mixed>>,
     *     pagination: array{limit: int, nextBefore: ?int, hasMore: bool}
     * }
     */
    public function getMessageHistory(int $channelId, ?int $before = null, int $limit = 25): array;

    /**
     * @return array{
     *     messages: list<array<string, mixed>>,
     *     pagination: array<string, int|bool|float>
     * }
     */
    public function searchMessages(
        ?string $text = null,
        ?string $user = null,
        ?int $from = null,
        ?int $to = null,
        int $page = 1,
        int $perPage = 10,
        ?int $channelId = null,
    ): array;

    public function insertMsg(string $name, string $message, int $current, ?int $channelId = null): int|bool;

    public function archiveMessagesBefore(int $timestamp, ?int $channelId = null, int $batchSize = 1_000): int;

    /** @return iterable<array{id: int|string, channel_id: int|string, user: string, msg: string, time: int|string, archived: int|string}> */
    public function exportMessages(?int $channelId = null, ?string $username = null, int $batchSize = 500): iterable;
}
