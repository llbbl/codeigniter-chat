<?php

namespace App\Commands;

use App\Models\AuditLogModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class AuditPrune extends BaseCommand
{
    protected $group = 'Security';
    protected $name = 'audit:prune';
    protected $description = 'Prunes old audit events.';
    protected $options = ['--older-than' => 'Retention period such as 90d'];
    public function run(array $params)
    {
        $days = (int) rtrim(CLI::getOption('older-than') ?? '90d', 'd');
        new AuditLogModel()->pruneOlderThan(date('Y-m-d H:i:s', strtotime('-' . max(1, $days) . ' days')));
    }
}
