<?php

namespace App\Controllers\Api\V1;

use App\Contracts\WebhookRepository;
use App\Controllers\BaseController;
use App\Services\WebhookUrlValidator;
use CodeIgniter\HTTP\ResponseInterface;
use JsonException;
use Throwable;

final class WebhooksController extends BaseController
{
    private const EVENTS = ['message.created', 'reaction.added', 'channel.created'];

    public function __construct(
        private readonly WebhookRepository $webhooks,
        private readonly WebhookUrlValidator $urlValidator,
    ) {
    }

    public function list(): ResponseInterface
    {
        return $this->webhookJson([
            'webhooks' => array_map($this->publicWebhook(...), $this->webhooks->forUser($this->userId())),
        ]);
    }

    public function create(): ResponseInterface
    {
        $input = $this->normalize($this->request->getJSON(true), true);
        if (isset($input['error'])) {
            return $this->validationError($input['error']);
        }

        $secret = bin2hex(random_bytes(32));
        try {
            $id = $this->webhooks->createForUser($this->userId(), [
                'url' => $input['url'],
                'url_hash' => hash('sha256', $input['url']),
                'secret' => $secret,
                'events' => json_encode($input['events'], JSON_THROW_ON_ERROR),
                'active' => $input['active'],
            ]);
        } catch (Throwable) {
            return $this->webhookJson([
                'error' => ['type' => 'conflict', 'message' => 'A webhook already exists for this URL.'],
            ], 409);
        }
        if ($id === false) {
            return $this->storageError();
        }

        $webhook = $this->webhooks->findForUser($id, $this->userId());

        return $this->webhookJson([
            'webhook' => $this->publicWebhook($webhook ?? ['id' => $id, ...$input]),
            'secret' => $secret,
        ], 201)->setHeader('X-CSRF-TOKEN', csrf_hash());
    }

    public function update(int $webhookId): ResponseInterface
    {
        if ($this->webhooks->findForUser($webhookId, $this->userId()) === null) {
            return $this->notFound();
        }
        $input = $this->normalize($this->request->getJSON(true), false);
        if (isset($input['error'])) {
            return $this->validationError($input['error']);
        }
        if ($input === []) {
            return $this->validationError('Provide at least one of url, events, or active.');
        }

        $changes = $input;
        if (isset($changes['url'])) {
            $changes['url_hash'] = hash('sha256', $changes['url']);
        }
        if (isset($changes['events'])) {
            try {
                $changes['events'] = json_encode($changes['events'], JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return $this->validationError('events must be a valid event list.');
            }
        }

        try {
            $updated = $this->webhooks->updateForUser($webhookId, $this->userId(), $changes);
        } catch (Throwable) {
            return $this->webhookJson([
                'error' => ['type' => 'conflict', 'message' => 'A webhook already exists for this URL.'],
            ], 409);
        }
        if (! $updated) {
            return $this->storageError();
        }

        $webhook = $this->webhooks->findForUser($webhookId, $this->userId());

        return $this->webhookJson(['webhook' => $this->publicWebhook($webhook)])
            ->setHeader('X-CSRF-TOKEN', csrf_hash());
    }

    public function delete(int $webhookId): ResponseInterface
    {
        if ($this->webhooks->findForUser($webhookId, $this->userId()) === null) {
            return $this->notFound();
        }
        if (! $this->webhooks->deleteForUser($webhookId, $this->userId())) {
            return $this->storageError();
        }

        return $this->response
            ->setStatusCode(204)
            ->setHeader('Cache-Control', 'no-store')
            ->setHeader('X-CSRF-TOKEN', csrf_hash());
    }

    public function deliveries(int $webhookId): ResponseInterface
    {
        if ($this->webhooks->findForUser($webhookId, $this->userId()) === null) {
            return $this->notFound();
        }
        $limit = $this->request->getGet('limit') ?? '50';
        if (! is_string($limit) || preg_match('/^[1-9]\d*$/', $limit) !== 1 || (int) $limit > 100) {
            return $this->validationError('limit must be an integer between 1 and 100.');
        }

        return $this->webhookJson([
            'deliveries' => $this->webhooks->deliveriesForUser($webhookId, $this->userId(), (int) $limit),
        ]);
    }

    public function redeliver(int $deliveryId): ResponseInterface
    {
        if (! $this->webhooks->redeliverForUser($deliveryId, $this->userId())) {
            return $this->webhookJson([
                'error' => ['type' => 'not_found', 'message' => 'Webhook delivery not found.'],
            ], 404);
        }

        return $this->webhookJson(['success' => true], 202)
            ->setHeader('X-CSRF-TOKEN', csrf_hash());
    }

    /** @return array{url?: string, events?: list<string>, active?: bool, error?: string} */
    private function normalize(mixed $payload, bool $creating): array
    {
        if (! is_array($payload)) {
            return ['error' => 'A JSON object is required.'];
        }
        $normalized = [];
        if ($creating || array_key_exists('url', $payload)) {
            $url = is_string($payload['url'] ?? null) ? trim($payload['url']) : '';
            if (! $this->urlValidator->isAllowed($url)) {
                return ['error' => 'url must be an HTTPS URL (HTTP loopback is allowed outside production).'];
            }
            $normalized['url'] = $url;
        }
        if ($creating || array_key_exists('events', $payload)) {
            $events = $payload['events'] ?? null;
            if (! is_array($events) || $events === [] || array_is_list($events) === false) {
                return ['error' => 'events must be a non-empty list of supported event names.'];
            }
            $events = array_values(array_unique($events));
            if (array_filter($events, static fn (mixed $event): bool => ! is_string($event) || ! in_array($event, self::EVENTS, true)) !== []) {
                return ['error' => 'events contains an unsupported event name.'];
            }
            /** @var list<string> $events */
            $normalized['events'] = $events;
        }
        if ($creating || array_key_exists('active', $payload)) {
            $active = $payload['active'] ?? true;
            if (! is_bool($active)) {
                return ['error' => 'active must be a boolean.'];
            }
            $normalized['active'] = $active;
        }

        return $normalized;
    }

    /** @param array<string, mixed> $webhook
     * @return array<string, mixed>
     */
    private function publicWebhook(array $webhook): array
    {
        unset($webhook['secret'], $webhook['url_hash'], $webhook['user_id']);
        $events = json_decode((string) ($webhook['events'] ?? '[]'), true);
        $webhook['events'] = is_array($events) ? array_values($events) : [];
        $webhook['active'] = (bool) ($webhook['active'] ?? false);

        return $webhook;
    }

    private function userId(): int
    {
        return (int) $this->session->get('user_id');
    }

    private function validationError(string $message): ResponseInterface
    {
        return $this->webhookJson(['error' => ['type' => 'validation', 'message' => $message]], 422);
    }

    private function notFound(): ResponseInterface
    {
        return $this->webhookJson(['error' => ['type' => 'not_found', 'message' => 'Webhook not found.']], 404);
    }

    private function storageError(): ResponseInterface
    {
        return $this->webhookJson(['error' => ['type' => 'storage', 'message' => 'The webhook could not be saved.']], 500);
    }

    private function webhookJson(mixed $data, int $status = 200): ResponseInterface
    {
        $response = $this->respondWithJson($data, $status);
        $response->removeHeader('Cache-Control');

        return $response->setHeader('Cache-Control', 'no-store');
    }
}
