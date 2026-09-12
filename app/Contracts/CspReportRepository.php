<?php

namespace App\Contracts;

interface CspReportRepository
{
    /** @param array<string, mixed> $report */
    public function saveReport(array $report): bool;

    public function countReports(): int;

    /** @return list<array{violated_directive: string, count: int|string}> */
    public function directiveBreakdown(): array;

    /** @return list<array<string, mixed>> */
    public function recent(int $limit = 50): array;

    public function pruneOlderThan(string $cutoff): bool;
}
