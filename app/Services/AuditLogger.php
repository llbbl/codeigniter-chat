<?php

namespace App\Services;

use App\Contracts\AuditLogger as AuditLoggerContract;
use App\Contracts\AuditLogStore;
use CodeIgniter\HTTP\RequestInterface;

class AuditLogger implements AuditLoggerContract
{
    public function __construct(private readonly AuditLogStore $store, private readonly RequestInterface $request)
    {
    }
    public function record(string $eventType, ?int $userId, array $context = []): void
    {
        $this->store->saveAudit([
            'event_type' => $eventType, 'user_id' => $userId,
            'username_attempted' => $context['username_attempted'] ?? null,
            'ip_address' => $this->request->getIPAddress(), 'user_agent' => $this->request->getHeaderLine('User-Agent'),
            'context' => json_encode($context, JSON_UNESCAPED_SLASHES), 'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
