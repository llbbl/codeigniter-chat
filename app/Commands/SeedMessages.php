<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;
use Config\Services;
use Throwable;

final class SeedMessages extends BaseCommand
{
    private const BENCHMARK_USER_COUNT = 100;
    private const BENCHMARK_PASSWORD = 'Benchmark123!';
    private const BATCH_SIZE = 1_000;
    private const MAX_MESSAGES = 1_000_000;

    protected $group = 'Chat';
    protected $name = 'seed:messages';
    protected $description = 'Replaces local benchmark data with a deterministic number of chat messages.';
    protected $usage = 'seed:messages <count>';
    protected $arguments = [
        'count' => 'Number of benchmark messages to create (1-1,000,000).',
    ];

    public function run(array $params): int
    {
        if (ENVIRONMENT === 'production') {
            CLI::error('Benchmark seeding is disabled in production.');

            return 1;
        }

        if (! filter_var(env('BENCHMARK_MODE', false), FILTER_VALIDATE_BOOL)) {
            CLI::error('Set BENCHMARK_MODE=true to seed benchmark data.');

            return 1;
        }

        $count = self::parseCount($params[0] ?? null);

        if ($count === null) {
            CLI::error('Count must be an integer between 1 and ' . number_format(self::MAX_MESSAGES) . '.');

            return 1;
        }

        $db = db_connect();

        try {
            $this->replaceBenchmarkData($db, $count);
            Services::cache()->clean();
        } catch (Throwable $exception) {
            CLI::error('Unable to seed benchmark messages: ' . $exception->getMessage());

            return 1;
        }

        CLI::write(sprintf(
            'Seeded %s messages and %d benchmark users.',
            number_format($count),
            self::BENCHMARK_USER_COUNT,
        ), 'green');

        return 0;
    }

    public static function parseCount(mixed $value): ?int
    {
        if (! is_string($value) || ! ctype_digit($value)) {
            return null;
        }

        $count = (int) $value;

        return $count >= 1 && $count <= self::MAX_MESSAGES ? $count : null;
    }

    private function replaceBenchmarkData(BaseConnection $db, int $count): void
    {
        $db->transException(true)->transStart();

        $this->seedUsers($db);

        $messages = $db->table('messages');
        $messages->like('user', 'k6-user-', 'after')->delete();

        for ($offset = 0; $offset < $count; $offset += self::BATCH_SIZE) {
            $batchSize = min(self::BATCH_SIZE, $count - $offset);
            $rows = [];

            for ($index = 0; $index < $batchSize; $index++) {
                $sequence = $offset + $index + 1;
                $rows[] = [
                    'user' => self::usernameFor($sequence),
                    'msg' => sprintf('Benchmark message %08d', $sequence),
                    'time' => 1_700_000_000 + $sequence,
                ];
            }

            $messages->insertBatch($rows);
        }

        $db->transComplete();
    }

    private function seedUsers(BaseConnection $db): void
    {
        $users = $db->table('users');
        $password = password_hash(self::BENCHMARK_PASSWORD, PASSWORD_DEFAULT);
        $now = date('Y-m-d H:i:s');

        for ($sequence = 1; $sequence <= self::BENCHMARK_USER_COUNT; $sequence++) {
            $username = self::usernameFor($sequence);
            $existing = $users->select('id')->where('username', $username)->get()->getRowArray();
            $row = [
                'email' => sprintf('k6-user-%03d@example.test', $sequence),
                'password' => $password,
                'updated_at' => $now,
            ];

            if ($existing === null) {
                $users->insert($row + [
                    'username' => $username,
                    'created_at' => $now,
                ]);
            } else {
                $users->where('id', $existing['id'])->update($row);
            }
        }
    }

    private static function usernameFor(int $sequence): string
    {
        return sprintf('k6-user-%03d', (($sequence - 1) % self::BENCHMARK_USER_COUNT) + 1);
    }
}
