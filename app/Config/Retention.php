<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use InvalidArgumentException;

/**
 * @phpstan-type RetentionPolicy array{column: non-empty-string, max_age_days: positive-int, action: 'archive'|'delete'}
 */
final class Retention extends BaseConfig
{
    /** @var array<string, RetentionPolicy> */
    public array $policies = [
        'messages' => [
            'column' => 'time',
            'max_age_days' => 90,
            'action' => 'archive',
        ],
        'archived_messages' => [
            'column' => 'archived_at',
            'max_age_days' => 730,
            'action' => 'delete',
        ],
        'csp_reports' => [
            'column' => 'reported_at',
            'max_age_days' => 30,
            'action' => 'delete',
        ],
        'audit_log' => [
            'column' => 'created_at',
            'max_age_days' => 365,
            'action' => 'delete',
        ],
    ];

    /**
     * @return array<string, RetentionPolicy>
     */
    public function selectedPolicies(?string $name = null): array
    {
        $this->validatePolicies();

        if ($name === null) {
            return $this->policies;
        }
        if (! isset($this->policies[$name])) {
            throw new InvalidArgumentException("Unknown retention policy: {$name}");
        }

        return [$name => $this->policies[$name]];
    }

    private function validatePolicies(): void
    {
        foreach ($this->policies as $table => $policy) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table) !== 1) {
                throw new InvalidArgumentException("Invalid retention table name: {$table}");
            }
            if (! is_array($policy)) {
                throw new InvalidArgumentException("Retention policy {$table} must be an array.");
            }

            $column = $policy['column'] ?? null;
            if (! is_string($column) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) !== 1) {
                throw new InvalidArgumentException("Retention policy {$table} has an invalid age column.");
            }

            $maxAgeDays = $policy['max_age_days'] ?? null;
            if (! is_int($maxAgeDays) || $maxAgeDays < 1) {
                throw new InvalidArgumentException("Retention policy {$table} must have a positive max_age_days value.");
            }

            $action = $policy['action'] ?? null;
            if (! in_array($action, ['archive', 'delete'], true)) {
                throw new InvalidArgumentException("Retention policy {$table} has an unsupported action.");
            }
            if ($action === 'archive' && $table !== 'messages') {
                throw new InvalidArgumentException('Only the messages policy currently supports archiving.');
            }
        }
    }
}
