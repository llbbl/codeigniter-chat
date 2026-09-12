<?php

namespace App\Contracts;

interface AuditLogger
{
    /** @param array<string, mixed> $context */
    public function record(string $eventType, ?int $userId, array $context = []): void;
}
