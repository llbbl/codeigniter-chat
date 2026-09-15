<?php

namespace App\Controllers\Api\V1;

use App\Contracts\ReactionRepository;
use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

final class ReactionsController extends BaseController
{
    public function __construct(private readonly ReactionRepository $reactions)
    {
    }

    public function list(int $messageId): ResponseInterface
    {
        if (! $this->reactions->messageExists($messageId)) {
            return $this->notFound();
        }

        return $this->reactionJson($this->reactions->forMessage($messageId, $this->currentUserId()));
    }

    public function create(int $messageId): ResponseInterface
    {
        if (! $this->reactions->messageExists($messageId)) {
            return $this->notFound();
        }

        $payload = $this->request->getJSON(true);
        $emoji = is_array($payload) ? $this->validEmoji($payload['emoji'] ?? null) : null;
        if ($emoji === null) {
            return $this->validationError();
        }

        if (! $this->reactions->add($messageId, $this->currentUserId(), $emoji)) {
            return $this->reactionJson([
                'error' => ['type' => 'storage', 'message' => 'The reaction could not be saved.'],
            ], 500);
        }

        return $this->reactionJson($this->state($messageId, $emoji), 201)
            ->setHeader('X-CSRF-TOKEN', csrf_hash());
    }

    public function delete(int $messageId, string $emoji): ResponseInterface
    {
        if (! $this->reactions->messageExists($messageId)) {
            return $this->notFound();
        }

        $emoji = $this->validEmoji(rawurldecode($emoji));
        if ($emoji === null) {
            return $this->validationError();
        }

        $this->reactions->remove($messageId, $this->currentUserId(), $emoji);

        return $this->response
            ->setStatusCode(204)
            ->setHeader('Cache-Control', 'no-store')
            ->setHeader('X-CSRF-TOKEN', csrf_hash());
    }

    /** @return array{message_id: int, reaction: array{emoji: string, count: int, users: list<string>, reacted_by_current_user: bool}} */
    private function state(int $messageId, string $emoji): array
    {
        foreach ($this->reactions->forMessage($messageId, $this->currentUserId()) as $reaction) {
            if ($reaction['emoji'] === $emoji) {
                return ['message_id' => $messageId, 'reaction' => $reaction];
            }
        }

        return [
            'message_id' => $messageId,
            'reaction' => ['emoji' => $emoji, 'count' => 0, 'users' => [], 'reacted_by_current_user' => false],
        ];
    }

    private function validEmoji(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        if (
            $value === ''
            || mb_strlen($value) > 10
            || strlen($value) > 64
            || preg_match('/[\p{C}\p{Z}]/u', $value) === 1
            || preg_match('/\p{Extended_Pictographic}/u', $value) !== 1
        ) {
            return null;
        }

        return $value;
    }

    private function currentUserId(): int
    {
        return (int) $this->session->get('user_id');
    }

    private function notFound(): ResponseInterface
    {
        return $this->reactionJson([
            'error' => ['type' => 'not_found', 'message' => 'Message not found.'],
        ], 404);
    }

    private function validationError(): ResponseInterface
    {
        return $this->reactionJson([
            'error' => ['type' => 'validation', 'message' => 'emoji must be a valid emoji of at most 10 characters.'],
        ], 422);
    }

    private function reactionJson(mixed $data, int $status = 200): ResponseInterface
    {
        $response = $this->respondWithJson($data, $status);
        $response->removeHeader('Cache-Control');

        return $response->setHeader('Cache-Control', 'no-store');
    }
}
