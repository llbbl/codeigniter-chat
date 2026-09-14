<?php

namespace App\Controllers\Api\V1;

use App\Contracts\PushSubscriptionRepository;
use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

final class PushSubscriptionsController extends BaseController
{
    public function __construct(private readonly PushSubscriptionRepository $subscriptions)
    {
    }

    public function create(): ResponseInterface
    {
        $payload = $this->request->getJSON(true);
        $subscription = $this->normalize($payload);
        if ($subscription === null) {
            return $this->respondWithJson([
                'error' => ['type' => 'validation', 'message' => 'A valid HTTPS push subscription is required.'],
            ], 422);
        }

        $saved = $this->subscriptions->saveForUser((int) $this->session->get('user_id'), $subscription);
        if (! $saved) {
            return $this->respondWithJson([
                'error' => ['type' => 'storage', 'message' => 'The push subscription could not be saved.'],
            ], 500);
        }

        return $this->respondWithJson(['success' => true], 201);
    }

    public function delete(): ResponseInterface
    {
        $payload = $this->request->getJSON(true);
        $endpoint = is_array($payload) ? trim((string) ($payload['endpoint'] ?? '')) : '';
        if (! $this->validEndpoint($endpoint)) {
            return $this->respondWithJson([
                'error' => ['type' => 'validation', 'message' => 'A valid HTTPS endpoint is required.'],
            ], 422);
        }

        $this->subscriptions->deleteForUser((int) $this->session->get('user_id'), hash('sha256', $endpoint));

        return $this->response->setStatusCode(204);
    }

    /** @return array{endpoint: string, endpoint_hash: string, p256dh: string, auth: string, content_encoding: string}|null */
    private function normalize(mixed $payload): ?array
    {
        if (! is_array($payload)) {
            return null;
        }

        $endpoint = trim((string) ($payload['endpoint'] ?? ''));
        $keys = is_array($payload['keys'] ?? null) ? $payload['keys'] : [];
        $p256dh = trim((string) ($keys['p256dh'] ?? ''));
        $auth = trim((string) ($keys['auth'] ?? ''));

        if (! $this->validEndpoint($endpoint) || $p256dh === '' || $auth === '' || strlen($p256dh) > 255 || strlen($auth) > 255) {
            return null;
        }

        return [
            'endpoint' => $endpoint,
            'endpoint_hash' => hash('sha256', $endpoint),
            'p256dh' => $p256dh,
            'auth' => $auth,
            'content_encoding' => 'aes128gcm',
        ];
    }

    private function validEndpoint(string $endpoint): bool
    {
        return strlen($endpoint) <= 2048
            && filter_var($endpoint, FILTER_VALIDATE_URL) !== false
            && parse_url($endpoint, PHP_URL_SCHEME) === 'https';
    }
}
