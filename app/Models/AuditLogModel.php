<?php

namespace App\Models;

use App\Contracts\AuditLogRepository;
use App\Contracts\AuditLogStore;
use App\Entities\AuditLogEntity;
use CodeIgniter\Model;

class AuditLogModel extends Model implements AuditLogRepository, AuditLogStore
{
    protected $table = 'audit_log';
    protected $primaryKey = 'id';
    protected $returnType = AuditLogEntity::class;
    protected $allowedFields = ['event_type', 'user_id', 'username_attempted', 'ip_address', 'user_agent', 'context', 'created_at'];

    public function saveAudit(array $record): bool
    {
        return $this->insert($record) !== false;
    }

    public function recent(?string $eventType, ?string $ipAddress, int $limit = 100): array
    {
        if ($eventType !== null && $eventType !== '') {
            $this->where('event_type', $eventType);
        }
        if ($ipAddress !== null && $ipAddress !== '') {
            $this->where('ip_address', $ipAddress);
        }
        return $this->orderBy('created_at', 'DESC')->findAll($limit);
    }
    public function pruneOlderThan(string $cutoff): bool
    {
        return $this->where('created_at <', $cutoff)->delete();
    }
}
