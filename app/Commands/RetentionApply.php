<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;
use Config\Retention;
use InvalidArgumentException;
use Throwable;

final class RetentionApply extends BaseCommand
{
    private const DEFAULT_BATCH_SIZE = 5_000;

    protected $group = 'Maintenance';
    protected $name = 'retention:apply';
    protected $description = 'Applies configured archive and deletion retention policies.';
    protected $usage = 'retention:apply [--dry-run] [--policy=messages] [--batch-size=5000]';
    protected $options = [
        '--dry-run' => 'Report eligible rows without changing the database.',
        '--policy' => 'Run one named retention policy instead of all policies.',
        '--batch-size' => 'Rows per delete/archive transaction, from 1 to 10000 (default: 5000).',
    ];

    public function run(array $params): int
    {
        $policyName = $params['policy'] ?? CLI::getOption('policy');
        $batchSize = self::parseBatchSize($params['batch-size'] ?? CLI::getOption('batch-size') ?? (string) self::DEFAULT_BATCH_SIZE);
        $dryRun = array_key_exists('dry-run', $params) || CLI::getOption('dry-run') === true;

        if ($policyName !== null && (! is_string($policyName) || $policyName === '')) {
            CLI::error('The --policy option requires a policy name.');

            return 1;
        }
        if ($batchSize === null) {
            CLI::error('The --batch-size option must be an integer between 1 and 10000.');

            return 1;
        }

        try {
            $policies = config(Retention::class)->selectedPolicies($policyName);
            $db = db_connect();
            if (! $db instanceof BaseConnection) {
                throw new InvalidArgumentException('Retention requires a database connection.');
            }

            $total = 0;
            foreach ($policies as $table => $policy) {
                $this->assertSchema($db, $table, $policy['column']);
                $count = $this->applyPolicy($db, $table, $policy, $batchSize, $dryRun);
                $total += $count;
                $verb = $dryRun ? 'Would process' : ($policy['action'] === 'archive' ? 'Archived' : 'Deleted');
                CLI::write(sprintf(
                    '%s %s row%s for %s.',
                    $verb,
                    number_format($count),
                    $count === 1 ? '' : 's',
                    $table,
                ), $dryRun ? 'yellow' : 'green');
            }

            CLI::write(sprintf(
                '%s %s row%s across %s polic%s.',
                $dryRun ? 'Dry run found' : 'Retention processed',
                number_format($total),
                $total === 1 ? '' : 's',
                number_format(count($policies)),
                count($policies) === 1 ? 'y' : 'ies',
            ));
        } catch (Throwable $exception) {
            CLI::error('Unable to apply retention: ' . $exception->getMessage());

            return 1;
        }

        return 0;
    }

    public static function parseBatchSize(mixed $value): ?int
    {
        if (! is_string($value) || preg_match('/^[1-9]\d*$/', $value) !== 1) {
            return null;
        }
        $batchSize = (int) $value;

        return $batchSize <= 10_000 ? $batchSize : null;
    }

    /** @param array{column: string, max_age_days: int, action: 'archive'|'delete'} $policy */
    private function applyPolicy(
        BaseConnection $db,
        string $table,
        array $policy,
        int $batchSize,
        bool $dryRun,
    ): int {
        $cutoffTimestamp = time() - ($policy['max_age_days'] * 86_400);
        $cutoff = $table === 'messages' ? $cutoffTimestamp : date('Y-m-d H:i:s', $cutoffTimestamp);
        $eligible = $this->countEligible($db, $table, $policy['column'], $cutoff);

        if ($dryRun || $eligible === 0) {
            return $eligible;
        }
        if ($policy['action'] === 'archive') {
            $exitCode = $this->call('messages:archive', [
                'older-than' => $policy['max_age_days'] . 'd',
                'channel' => 'all',
                'batch-size' => (string) $batchSize,
            ]);
            if ($exitCode !== 0) {
                throw new InvalidArgumentException('The messages archive command failed.');
            }

            return $eligible;
        }

        return $this->deleteInBatches($db, $table, $policy['column'], $cutoff, $batchSize);
    }

    private function countEligible(BaseConnection $db, string $table, string $column, int|string $cutoff): int
    {
        return $db->table($table)->where("{$column} <", $cutoff)->countAllResults();
    }

    private function deleteInBatches(
        BaseConnection $db,
        string $table,
        string $column,
        int|string $cutoff,
        int $batchSize,
    ): int {
        $deleted = 0;

        do {
            $rows = $db->table($table)
                ->select('id')
                ->where("{$column} <", $cutoff)
                ->orderBy('id')
                ->limit($batchSize)
                ->get()
                ->getResultArray();
            if ($rows === []) {
                break;
            }

            $ids = array_map(static fn (array $row): int => (int) $row['id'], $rows);
            $db->transException(true)->transStart();
            $db->table($table)->whereIn('id', $ids)->delete();
            $db->transComplete();
            $deleted += count($ids);
        } while (count($rows) === $batchSize);

        return $deleted;
    }

    private function assertSchema(BaseConnection $db, string $table, string $column): void
    {
        if (! $db->tableExists($table)) {
            throw new InvalidArgumentException("Retention table does not exist: {$table}");
        }
        if (! $db->fieldExists('id', $table) || ! $db->fieldExists($column, $table)) {
            throw new InvalidArgumentException("Retention table {$table} is missing id or {$column}.");
        }
    }
}
