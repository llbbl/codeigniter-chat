<?php

namespace App\Contracts;

interface AuditLogRepository
{
    /** @return list<array<string, mixed>|object> */
    public function recent(?string $eventType, ?string $ipAddress, int $limit = 100): array;
}
