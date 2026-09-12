<?php

namespace App\Controllers;

use App\Contracts\AuditLogRepository;

class AuditLog extends BaseController
{
    public function __construct(private readonly AuditLogRepository $logs)
    {
    }
    public function index(): string
    {
        return $this->respondWithView('admin/auditLog', ['logs' => $this->logs->recent($this->request->getGet('event_type'), $this->request->getGet('ip'))]);
    }
}
