<?php

namespace App\Contracts;

interface AuditLogStore
{
    /** @param array<string, mixed> $record */
    public function saveAudit(array $record): bool;
}
