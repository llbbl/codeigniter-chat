<?php

namespace App\Controllers;

use App\Models\AuditLogModel;

class AuditLog extends BaseController
{
    public function __construct(private readonly AuditLogModel $logs = new AuditLogModel())
    {
    }
    public function index(): string
    {
        return $this->respondWithView('admin/auditLog', ['logs' => $this->logs->recent($this->request->getGet('event_type'), $this->request->getGet('ip'))]);
    }
}
