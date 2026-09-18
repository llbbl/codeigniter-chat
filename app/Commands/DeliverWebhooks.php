<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;
use Throwable;

final class DeliverWebhooks extends BaseCommand
{
    protected $group = 'Chat';
    protected $name = 'webhooks:deliver';
    protected $description = 'Delivers pending and retry-due outgoing webhook requests.';
    protected $usage = 'webhooks:deliver --limit=25';
    protected $options = [
        '--limit' => 'Maximum deliveries to process, from 1 to 100 (default: 25).',
    ];

    public function run(array $params): int
    {
        $limit = self::parseLimit($params['limit'] ?? CLI::getOption('limit') ?? '25');
        if ($limit === null) {
            CLI::error('The --limit option must be an integer between 1 and 100.');

            return 1;
        }

        try {
            $result = Services::webhookDeliveryService()->deliver($limit);
        } catch (Throwable $exception) {
            CLI::error('Unable to deliver webhooks: ' . $exception->getMessage());

            return 1;
        }

        CLI::write(sprintf(
            'Processed %d deliveries: %d delivered, %d retrying, %d dropped.',
            $result['processed'],
            $result['delivered'],
            $result['retrying'],
            $result['dropped'],
        ), 'green');

        return 0;
    }

    public static function parseLimit(mixed $value): ?int
    {
        if (! is_string($value) || preg_match('/^[1-9]\d*$/', $value) !== 1) {
            return null;
        }
        $limit = (int) $value;

        return $limit <= 100 ? $limit : null;
    }
}
