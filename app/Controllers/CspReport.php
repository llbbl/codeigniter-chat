<?php

namespace App\Controllers;

use App\Models\CspReportModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Controller for handling Content Security Policy (CSP) violation reports
 */
class CspReport extends BaseController
{
    public function __construct(private readonly CspReportModel $reports = new CspReportModel())
    {
    }
    /**
     * Handle CSP violation reports
     *
     * @return ResponseInterface
     */
    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        $json = $this->request->getJSON(true);
        $reports = $this->normalize($json);
        if ($reports === []) {
            return $this->respondWithJson(['error' => 'Invalid CSP report format'], 400);
        }

        foreach ($reports as $report) {
            $this->reports->insert($report);
            $this->logMessage('warning', 'CSP Violation: ' . json_encode($report));
        }
        $this->reports->pruneOlderThan(date('Y-m-d H:i:s', strtotime('-30 days')));

        // Return a 204 No Content response
        return $this->response->setStatusCode(204);
    }

    public function admin(): string
    {
        return $this->respondWithView('admin/cspReports', [
            'total' => $this->reports->countAllResults(),
            'breakdown' => $this->reports->directiveBreakdown(),
            'reports' => $this->reports->recent(),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function normalize(mixed $payload): array
    {
        if (is_array($payload) && isset($payload['csp-report']) && is_array($payload['csp-report'])) {
            return [$this->row($payload['csp-report'])];
        }
        if (! is_array($payload) || ! array_is_list($payload)) {
            return [];
        }
        $rows = [];
        foreach ($payload as $report) {
            if (is_array($report) && ($report['type'] ?? null) === 'csp-violation' && is_array($report['body'] ?? null)) {
                $rows[] = $this->row($report['body']);
            }
        }
        return $rows;
    }

    /** @param array<string, mixed> $report @return array<string, mixed> */
    private function row(array $report): array
    {
        return [
            'document_uri' => $report['document-uri'] ?? $report['documentURL'] ?? null,
            'violated_directive' => $report['violated-directive'] ?? $report['effective-directive'] ?? $report['effectiveDirective'] ?? 'unknown',
            'blocked_uri' => $report['blocked-uri'] ?? $report['blockedURL'] ?? null,
            'source_file' => $report['source-file'] ?? $report['sourceFile'] ?? null,
            'line_number' => $report['line-number'] ?? $report['lineNumber'] ?? null,
            'user_agent' => $this->request->getHeaderLine('User-Agent'),
            'reported_at' => date('Y-m-d H:i:s'),
        ];
    }
}
