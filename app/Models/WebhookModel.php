<?php

namespace App\Models;

use App\Contracts\WebhookDispatcher;
use App\Contracts\WebhookRepository;
use CodeIgniter\Model;
use JsonException;

final class WebhookModel extends Model implements WebhookDispatcher, WebhookRepository
{
    public const MAX_PAYLOAD_BYTES = 102_400;
    public const MAX_ATTEMPTS = 5;

    protected $table = 'webhooks';
    protected $primaryKey = 'id';
    protected $useTimestamps = true;
    protected $returnType = 'array';
    protected $allowedFields = ['user_id', 'url', 'url_hash', 'secret', 'events', 'active'];

    public function forUser(int $userId): array
    {
        return $this->select('id, url, events, active, created_at, updated_at')
            ->where('user_id', $userId)
            ->orderBy('id', 'DESC')
            ->findAll();
    }

    public function findForUser(int $webhookId, int $userId): ?array
    {
        $row = $this->where('id', $webhookId)->where('user_id', $userId)->first();

        return is_array($row) ? $row : null;
    }

    public function createForUser(int $userId, array $webhook): int|false
    {
        $id = $this->insert(['user_id' => $userId, ...$webhook], true);

        return $id === false ? false : (int) $id;
    }

    public function updateForUser(int $webhookId, int $userId, array $changes): bool
    {
        if ($this->findForUser($webhookId, $userId) === null) {
            return false;
        }

        return $this->update($webhookId, $changes);
    }

    public function deleteForUser(int $webhookId, int $userId): bool
    {
        return $this->where('id', $webhookId)->where('user_id', $userId)->delete();
    }

    public function deliveriesForUser(int $webhookId, int $userId, int $limit = 50): array
    {
        return $this->db->table('webhook_deliveries AS deliveries')
            ->select('deliveries.id, deliveries.event, deliveries.status, deliveries.attempts, deliveries.response_status, deliveries.last_error, deliveries.next_attempt_at, deliveries.delivered_at, deliveries.created_at, deliveries.updated_at')
            ->join('webhooks AS webhooks', 'webhooks.id = deliveries.webhook_id')
            ->where('deliveries.webhook_id', $webhookId)
            ->where('webhooks.user_id', $userId)
            ->orderBy('deliveries.id', 'DESC')
            ->limit(max(1, min(100, $limit)))
            ->get()
            ->getResultArray();
    }

    public function redeliverForUser(int $deliveryId, int $userId): bool
    {
        $delivery = $this->db->table('webhook_deliveries AS deliveries')
            ->select('deliveries.id')
            ->join('webhooks AS webhooks', 'webhooks.id = deliveries.webhook_id')
            ->where('deliveries.id', $deliveryId)
            ->where('webhooks.user_id', $userId)
            ->get()
            ->getRowArray();
        if ($delivery === null) {
            return false;
        }

        return $this->db->table('webhook_deliveries')->where('id', $deliveryId)->update([
            'status' => 'pending',
            'attempts' => 0,
            'response_status' => null,
            'response_body' => null,
            'last_error' => null,
            'next_attempt_at' => null,
            'delivered_at' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function enqueue(string $event, array $payload, ?int $channelId = null): int
    {
        try {
            $json = json_encode([
                'id' => bin2hex(random_bytes(16)),
                'event' => $event,
                'created_at' => gmdate(DATE_ATOM),
                'data' => $payload,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            return 0;
        }
        if (strlen($json) > self::MAX_PAYLOAD_BYTES) {
            return 0;
        }

        $rows = $this->eligibleWebhooks($channelId);
        $now = date('Y-m-d H:i:s');
        $count = 0;
        foreach ($rows as $row) {
            $events = json_decode((string) $row['events'], true);
            if (! is_array($events) || ! in_array($event, $events, true)) {
                continue;
            }
            $inserted = $this->db->table('webhook_deliveries')->insert([
                'webhook_id' => (int) $row['id'],
                'event' => $event,
                'payload' => $json,
                'status' => 'pending',
                'attempts' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $count += $inserted ? 1 : 0;
        }

        return $count;
    }

    public function dispatch(string $event, array $payload, ?int $channelId = null): int
    {
        return $this->enqueue($event, $payload, $channelId);
    }

    public function dueDeliveries(int $limit = 25): array
    {
        $now = date('Y-m-d H:i:s');

        return $this->db->table('webhook_deliveries AS deliveries')
            ->select('deliveries.*, webhooks.url, webhooks.secret')
            ->join('webhooks AS webhooks', 'webhooks.id = deliveries.webhook_id')
            ->groupStart()
                ->where('deliveries.status', 'pending')
                ->orGroupStart()
                    ->where('deliveries.status', 'retrying')
                    ->where('deliveries.next_attempt_at <=', $now)
                ->groupEnd()
                ->orGroupStart()
                    ->where('deliveries.status', 'processing')
                    ->where('deliveries.next_attempt_at <=', $now)
                ->groupEnd()
            ->groupEnd()
            ->where('webhooks.active', true)
            ->orderBy('deliveries.id', 'ASC')
            ->limit(max(1, min(100, $limit)))
            ->get()
            ->getResultArray();
    }

    public function claimDelivery(
        int $deliveryId,
        string $status,
        int $attempts,
        ?string $expectedNextAttemptAt,
        string $leaseUntil,
    ): bool {
        $updated = $this->db->table('webhook_deliveries')
            ->where('id', $deliveryId)
            ->where('status', $status)
            ->where('attempts', $attempts)
            ->where('next_attempt_at', $expectedNextAttemptAt)
            ->update([
                'status' => 'processing',
                'next_attempt_at' => $leaseUntil,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        return $updated && $this->db->affectedRows() === 1;
    }

    public function markDelivered(int $deliveryId, int $attempts, int $responseStatus, string $responseBody): bool
    {
        return $this->db->table('webhook_deliveries')->where('id', $deliveryId)->update([
            'status' => 'delivered',
            'attempts' => $attempts,
            'response_status' => $responseStatus,
            'response_body' => mb_substr($responseBody, 0, 2_000),
            'last_error' => null,
            'next_attempt_at' => null,
            'delivered_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function markFailed(
        int $deliveryId,
        int $attempts,
        ?int $responseStatus,
        string $responseBody,
        string $error,
        ?string $nextAttemptAt,
    ): bool {
        return $this->db->table('webhook_deliveries')->where('id', $deliveryId)->update([
            'status' => $attempts >= self::MAX_ATTEMPTS ? 'dropped' : 'retrying',
            'attempts' => $attempts,
            'response_status' => $responseStatus,
            'response_body' => mb_substr($responseBody, 0, 2_000),
            'last_error' => mb_substr($error, 0, 2_000),
            'next_attempt_at' => $attempts >= self::MAX_ATTEMPTS ? null : $nextAttemptAt,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function eligibleWebhooks(?int $channelId): array
    {
        $builder = $this->db->table('webhooks AS hooks')
            ->select('hooks.id, hooks.events')
            ->where('hooks.active', true);
        if ($channelId !== null) {
            $builder->join(
                'channel_members AS membership',
                'membership.user_id = hooks.user_id AND membership.channel_id = ' . (int) $channelId,
            );
        }

        return $builder->get()->getResultArray();
    }
}
