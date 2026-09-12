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
    public function getMsgPaginated(int $page = 1, int $perPage = 10): array;

    public function insertMsg(string $name, string $message, int $current): int|bool;
}
