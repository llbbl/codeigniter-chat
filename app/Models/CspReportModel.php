<?php

namespace App\Models;

use CodeIgniter\Model;

class CspReportModel extends Model
{
    protected $table = 'csp_reports';
    protected $primaryKey = 'id';
    protected $allowedFields = ['document_uri', 'violated_directive', 'blocked_uri', 'source_file', 'line_number', 'user_agent', 'reported_at'];

    /** @return list<array<string, mixed>> */
    public function recent(int $limit = 50): array
    {
        return $this->orderBy('reported_at', 'DESC')->findAll($limit);
    }

    /** @return list<array{violated_directive: string, count: int|string}> */
    public function directiveBreakdown(): array
    {
        return $this->select('violated_directive, COUNT(*) AS count')
            ->groupBy('violated_directive')->orderBy('count', 'DESC')->findAll();
    }

    public function pruneOlderThan(string $cutoff): bool
    {
        return $this->where('reported_at <', $cutoff)->delete();
    }
}
