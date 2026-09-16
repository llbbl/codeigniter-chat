<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;
use Throwable;

final class ArchiveMessages extends BaseCommand
{
    protected $group = 'Chat';
    protected $name = 'messages:archive';
    protected $description = 'Moves old live messages into the archive in bounded transactions.';
    protected $usage = 'messages:archive --older-than=90d --channel=all';
    protected $options = [
        '--older-than' => 'Required age such as 90d, 24h, or 30m.',
        '--channel' => 'Channel ID or all (default: all).',
        '--batch-size' => 'Rows per transaction, from 1 to 10000 (default: 1000).',
    ];

    public function run(array $params): int
    {
        $seconds = self::parseAge($params['older-than'] ?? CLI::getOption('older-than'));
        $channelId = self::parseChannel($params['channel'] ?? CLI::getOption('channel') ?? 'all');
        $batchSize = self::parseBatchSize($params['batch-size'] ?? CLI::getOption('batch-size') ?? '1000');

        if ($seconds === null) {
            CLI::error('The --older-than option is required and must look like 90d, 24h, or 30m.');

            return 1;
        }
        if ($channelId === false) {
            CLI::error('The --channel option must be a positive channel ID or all.');

            return 1;
        }
        if ($batchSize === null) {
            CLI::error('The --batch-size option must be an integer between 1 and 10000.');

            return 1;
        }

        try {
            $count = Services::chatRepository()->archiveMessagesBefore(
                time() - $seconds,
                $channelId,
                $batchSize,
            );
        } catch (Throwable $exception) {
            CLI::error('Unable to archive messages: ' . $exception->getMessage());

            return 1;
        }

        CLI::write(sprintf('Archived %s message%s.', number_format($count), $count === 1 ? '' : 's'), 'green');

        return 0;
    }

    public static function parseAge(mixed $value): ?int
    {
        if (! is_string($value) || preg_match('/^([1-9]\d*)([mhd])$/', strtolower($value), $matches) !== 1) {
            return null;
        }

        $multiplier = match ($matches[2]) {
            'm' => 60,
            'h' => 3_600,
            'd' => 86_400,
        };
        $seconds = (int) $matches[1] * $multiplier;

        return $seconds > 0 ? $seconds : null;
    }

    public static function parseChannel(mixed $value): int|false|null
    {
        if ($value === 'all') {
            return null;
        }
        if (! is_string($value) || preg_match('/^[1-9]\d*$/', $value) !== 1 || (float) $value > PHP_INT_MAX) {
            return false;
        }

        return (int) $value;
    }

    public static function parseBatchSize(mixed $value): ?int
    {
        if (! is_string($value) || preg_match('/^[1-9]\d*$/', $value) !== 1) {
            return null;
        }
        $batchSize = (int) $value;

        return $batchSize <= 10_000 ? $batchSize : null;
    }
}
