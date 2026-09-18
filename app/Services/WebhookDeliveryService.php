<?php

namespace App\Services;

use App\Contracts\WebhookHttpClient;
use App\Contracts\WebhookRepository;
use App\Models\WebhookModel;
use Closure;
use Throwable;

final class WebhookDeliveryService
{
    private const LEASE_SECONDS = 300;

    /** @var Closure(): int */
    private readonly Closure $clock;

    /** @param Closure(): int|null $clock */
    public function __construct(
        private readonly WebhookRepository $webhooks,
        private readonly WebhookHttpClient $client,
        ?Closure $clock = null,
        private readonly WebhookUrlValidator $urlValidator = new WebhookUrlValidator(),
    ) {
        $this->clock = $clock ?? static fn (): int => time();
    }

    /** @return array{processed: int, delivered: int, retrying: int, dropped: int} */
    public function deliver(int $limit = 25): array
    {
        $result = ['processed' => 0, 'delivered' => 0, 'retrying' => 0, 'dropped' => 0];
        foreach ($this->webhooks->dueDeliveries($limit) as $delivery) {
            $now = ($this->clock)();
            if (! $this->webhooks->claimDelivery(
                (int) $delivery['id'],
                (string) $delivery['status'],
                (int) $delivery['attempts'],
                isset($delivery['next_attempt_at']) ? (string) $delivery['next_attempt_at'] : null,
                date('Y-m-d H:i:s', $now + self::LEASE_SECONDS),
            )) {
                continue;
            }
            ++$result['processed'];
            $outcome = $this->deliverOne($delivery);
            ++$result[$outcome];
        }

        return $result;
    }

    public static function backoffSeconds(int $attempts): int
    {
        return 60 * (2 ** max(0, min(WebhookModel::MAX_ATTEMPTS - 1, $attempts - 1)));
    }

    /** @param array<string, mixed> $delivery
     * @return 'delivered'|'retrying'|'dropped'
     */
    private function deliverOne(array $delivery): string
    {
        $payload = (string) ($delivery['payload'] ?? '');
        $attempts = (int) ($delivery['attempts'] ?? 0) + 1;
        if ($payload === '' || strlen($payload) > WebhookModel::MAX_PAYLOAD_BYTES) {
            $attempts = WebhookModel::MAX_ATTEMPTS;
            $this->webhooks->markFailed((int) $delivery['id'], $attempts, null, '', 'Payload is empty or exceeds 100 KB.', null);

            return 'dropped';
        }
        if (! $this->urlValidator->isAllowed((string) ($delivery['url'] ?? ''))) {
            $attempts = WebhookModel::MAX_ATTEMPTS;
            $this->webhooks->markFailed(
                (int) $delivery['id'],
                $attempts,
                null,
                '',
                'Endpoint URL is not allowed or no longer resolves to a public address.',
                null,
            );

            return 'dropped';
        }

        $timestamp = ($this->clock)();
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'CodeIgniter-Chat-Webhooks/1.0',
            'X-Webhook-Delivery' => (string) $delivery['id'],
            'X-Webhook-Event' => (string) $delivery['event'],
            'X-Webhook-Timestamp' => (string) $timestamp,
            'X-Webhook-Signature' => 'v1=' . hash_hmac('sha256', $timestamp . '.' . $payload, (string) $delivery['secret']),
        ];

        try {
            $response = $this->client->send((string) $delivery['url'], $payload, $headers);
            if ($response['status'] >= 200 && $response['status'] < 300) {
                $this->webhooks->markDelivered((int) $delivery['id'], $attempts, $response['status'], $response['body']);

                return 'delivered';
            }
            $error = 'Endpoint returned HTTP ' . $response['status'] . '.';
            $status = $response['status'];
            $body = $response['body'];
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
            $status = null;
            $body = '';
        }

        $dropped = $attempts >= WebhookModel::MAX_ATTEMPTS;
        $nextAttemptAt = $dropped ? null : date('Y-m-d H:i:s', $timestamp + self::backoffSeconds($attempts));
        $this->webhooks->markFailed(
            (int) $delivery['id'],
            $attempts,
            $status,
            $body,
            $error,
            $nextAttemptAt,
        );

        return $dropped ? 'dropped' : 'retrying';
    }
}
