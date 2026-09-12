<?php

namespace App\Services;

use App\Contracts\AuditLogger;

final class NullAuditLogger implements AuditLogger
{
    public function record(string $eventType, ?int $userId, array $context = []): void
    {
    }
}
