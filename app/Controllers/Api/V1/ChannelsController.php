<?php

namespace App\Controllers\Api\V1;

use App\Contracts\ChannelRepository;
use App\Contracts\ChatRepository;
use App\Contracts\UserRepository;
use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

final class ChannelsController extends BaseController
{
    public function __construct(
        private readonly ChannelRepository $channels,
        private readonly ChatRepository $messages,
        private readonly UserRepository $users,
    ) {
    }

    public function list(): ResponseInterface
    {
        return $this->channelJson(['channels' => $this->channels->listForUser($this->userId())]);
    }

    public function create(): ResponseInterface
    {
        $input = $this->payload();
        $error = $this->validateChannelFields($input, false);
        if ($error !== null) {
            return $this->error('validation', $error, 400);
        }

        try {
            $channelId = $this->channels->createPublic(
                trim((string) $input['name']),
                trim((string) $input['slug']),
                $this->nullableTrimmedString($input['topic'] ?? null),
                $this->userId(),
            );
        } catch (Throwable) {
            return $this->error('conflict', 'The channel slug is already in use.', 409);
        }

        if ($channelId === false) {
            return $this->error('database', 'The channel could not be created.', 500);
        }

        return $this->channelJson(['channel' => $this->channels->findChannel($channelId)], 201);
    }

    public function join(int $channelId): ResponseInterface
    {
        $channel = $this->channels->findChannel($channelId);
        if ($channel === null) {
            return $this->error('not_found', 'Channel not found.', 404);
        }
        if ($channel['channel_type'] !== 'public' || $channel['archived_at'] !== null) {
            return $this->error('authorization', 'This channel cannot be joined.', 403);
        }

        if (! $this->channels->join($channelId, $this->userId())) {
            return $this->error('database', 'The channel could not be joined.', 500);
        }

        return $this->channelJson(['channel' => $this->channels->findChannel($channelId)]);
    }

    public function leave(int $channelId): ResponseInterface
    {
        $channel = $this->channels->findChannel($channelId);
        if ($channel === null) {
            return $this->error('not_found', 'Channel not found.', 404);
        }
        if ($channel['slug'] === 'general') {
            return $this->error('validation', 'The #general channel cannot be left.', 400);
        }
        if ($channel['channel_type'] === 'dm') {
            return $this->error('validation', 'Direct-message membership cannot be changed.', 400);
        }
        if (! $this->channels->isMember($channelId, $this->userId())) {
            return $this->error('authorization', 'You are not a channel member.', 403);
        }

        $this->channels->leave($channelId, $this->userId());

        return $this->channelJson(['success' => true]);
    }

    public function update(int $channelId): ResponseInterface
    {
        $channel = $this->channels->findChannel($channelId);
        if ($channel === null) {
            return $this->error('not_found', 'Channel not found.', 404);
        }
        if ($channel['channel_type'] !== 'public' || ! $this->channels->isCreator($channelId, $this->userId())) {
            return $this->error('authorization', 'Only the channel creator can update this channel.', 403);
        }

        $input = $this->payload();
        if ($input === []) {
            return $this->error('validation', 'Provide at least one channel field to update.', 400);
        }
        $error = $this->validateChannelFields($input, true);
        if ($error !== null) {
            return $this->error('validation', $error, 400);
        }

        $changes = [];
        foreach (['name', 'slug'] as $field) {
            if (array_key_exists($field, $input)) {
                $changes[$field] = trim((string) $input[$field]);
            }
        }
        if (array_key_exists('topic', $input)) {
            $changes['topic'] = $this->nullableTrimmedString($input['topic']);
        }
        if (array_key_exists('archived', $input)) {
            $archived = filter_var($input['archived'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            if ($archived === null) {
                return $this->error('validation', 'The archived field must be a boolean.', 400);
            }
            $changes['archived_at'] = $archived ? date('Y-m-d H:i:s') : null;
        }
        if ($changes === []) {
            return $this->error('validation', 'No supported channel fields were provided.', 400);
        }

        try {
            $updated = $this->channels->updateChannel($channelId, $changes);
        } catch (Throwable) {
            return $this->error('conflict', 'The channel slug is already in use.', 409);
        }

        if (! $updated) {
            return $this->error('database', 'The channel could not be updated.', 500);
        }

        return $this->channelJson(['channel' => $this->channels->findChannel($channelId)]);
    }

    public function createDm(): ResponseInterface
    {
        $input = $this->payload();
        $targetUserId = $input['user_id'] ?? null;
        if (is_int($targetUserId) || (is_string($targetUserId) && ctype_digit($targetUserId))) {
            $user = $this->users->findUserById((int) $targetUserId);
        } else {
            $username = $input['username'] ?? null;
            if (! is_string($username) || trim($username) === '') {
                return $this->error('validation', 'A user_id or username is required.', 400);
            }
            $user = $this->users->findUserByUsername(trim($username));
        }
        if ($user === null) {
            return $this->error('not_found', 'User not found.', 404);
        }
        if ((int) $user['id'] === $this->userId()) {
            return $this->error('validation', 'A direct message requires another user.', 400);
        }

        $channelId = $this->channels->findOrCreateDm($this->userId(), (int) $user['id']);
        if ($channelId === false) {
            return $this->error('database', 'The direct message could not be created.', 500);
        }

        return $this->channelJson(['channel' => $this->channels->findChannel($channelId)], 201);
    }

    public function messages(int $channelId): ResponseInterface
    {
        $membershipError = $this->memberChannel($channelId);
        if ($membershipError instanceof ResponseInterface) {
            return $membershipError;
        }

        $page = $this->positiveIntegerQuery('page', 1, PHP_INT_MAX);
        $perPage = $this->positiveIntegerQuery('per_page', 10, 100);
        if (is_string($page) || is_string($perPage)) {
            return $this->error('validation', is_string($page) ? $page : $perPage, 400);
        }

        $result = $this->messages->getMsgPaginated($page, $perPage, $channelId);
        $this->channels->markRead($channelId, $this->userId());

        return $this->channelJson($result);
    }

    public function createMessage(int $channelId): ResponseInterface
    {
        $membershipError = $this->memberChannel($channelId);
        if ($membershipError instanceof ResponseInterface) {
            return $membershipError;
        }

        $message = $this->payload()['message'] ?? null;
        if (! is_string($message) || trim($message) === '' || mb_strlen($message) > 500) {
            return $this->error('validation', 'Message must contain between 1 and 500 characters.', 400);
        }

        $messageId = $this->messages->insertMsg((string) $this->getCurrentUsername(), esc(trim($message)), time(), $channelId);
        if ($messageId === false) {
            return $this->error('database', 'The message could not be created.', 500);
        }
        $this->channels->markRead($channelId, $this->userId());

        return $this->channelJson([
            'message' => [
                'id' => (int) $messageId,
                'channel_id' => $channelId,
                'user' => $this->getCurrentUsername(),
                'msg' => esc(trim($message)),
            ],
        ], 201);
    }

    public function searchMessages(int $channelId): ResponseInterface
    {
        $membershipError = $this->memberChannel($channelId);
        if ($membershipError instanceof ResponseInterface) {
            return $membershipError;
        }

        $textInput = $this->optionalQueryString('text', 500);
        $userInput = $this->optionalQueryString('user', 255);
        $fromInput = $this->optionalTimestamp('from');
        $toInput = $this->optionalTimestamp('to');
        $page = $this->positiveIntegerQuery('page', 1, PHP_INT_MAX);
        $perPage = $this->positiveIntegerQuery('per_page', 10, 100);
        foreach ([$textInput, $userInput, $fromInput, $toInput] as $input) {
            if ($input['error'] !== null) {
                return $this->error('validation', $input['error'], 400);
            }
        }
        if (is_string($page) || is_string($perPage)) {
            return $this->error('validation', is_string($page) ? $page : $perPage, 400);
        }
        $text = $textInput['value'];
        $user = $userInput['value'];
        $from = $fromInput['value'];
        $to = $toInput['value'];
        if ($text === null && $user === null && $from === null && $to === null) {
            return $this->error('validation', 'Provide at least one search filter.', 400);
        }
        if (is_int($from) && is_int($to) && $from > $to) {
            return $this->error('validation', 'The from timestamp must be less than or equal to to.', 400);
        }

        $result = $this->messages->searchMessages($text, $user, $from, $to, $page, $perPage, $channelId);

        return $this->channelJson([
            ...$result,
            'filters' => ['text' => $text, 'user' => $user, 'from' => $from, 'to' => $to],
        ]);
    }

    private function memberChannel(int $channelId): ?ResponseInterface
    {
        $channel = $this->channels->findChannel($channelId);
        if ($channel === null) {
            return $this->error('not_found', 'Channel not found.', 404);
        }
        if (! $this->channels->isMember($channelId, $this->userId())) {
            return $this->error('authorization', 'You are not a channel member.', 403);
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        $json = $this->request->getJSON(true);

        return is_array($json) ? $json : $this->request->getPost();
    }

    /** @param array<string, mixed> $input */
    private function validateChannelFields(array $input, bool $partial): ?string
    {
        if (! $partial || array_key_exists('name', $input)) {
            if (! is_string($input['name'] ?? null) || trim((string) $input['name']) === '' || mb_strlen(trim((string) $input['name'])) > 80) {
                return 'Channel name must contain between 1 and 80 characters.';
            }
        }
        if (! $partial || array_key_exists('slug', $input)) {
            if (! is_string($input['slug'] ?? null) || preg_match('/^[a-z0-9-]{3,50}$/', trim((string) $input['slug'])) !== 1) {
                return 'Channel slug must match ^[a-z0-9-]{3,50}$.';
            }
            if (trim((string) $input['slug']) === 'general' && $partial) {
                return 'The #general slug is reserved.';
            }
        }
        if (array_key_exists('topic', $input) && $input['topic'] !== null && (! is_string($input['topic']) || mb_strlen(trim($input['topic'])) > 200)) {
            return 'Channel topic must not exceed 200 characters.';
        }

        return null;
    }

    private function nullableTrimmedString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function positiveIntegerQuery(string $name, int $default, int $maximum): int|string
    {
        $value = $this->request->getGet($name);
        if ($value === null) {
            return $default;
        }
        if (! is_string($value) || preg_match('/^[1-9]\d*$/', $value) !== 1 || (float) $value > $maximum) {
            return "The {$name} parameter must be an integer between 1 and {$maximum}.";
        }

        return (int) $value;
    }

    /** @return array{value: ?string, error: ?string} */
    private function optionalQueryString(string $name, int $maximum): array
    {
        $value = $this->request->getGet($name);
        if ($value === null) {
            return ['value' => null, 'error' => null];
        }
        if (! is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > $maximum) {
            return ['value' => null, 'error' => "The {$name} filter must be a non-empty string of at most {$maximum} characters."];
        }

        return ['value' => trim($value), 'error' => null];
    }

    /** @return array{value: ?int, error: ?string} */
    private function optionalTimestamp(string $name): array
    {
        $value = $this->request->getGet($name);
        if ($value === null) {
            return ['value' => null, 'error' => null];
        }
        if (! is_string($value) || preg_match('/^(0|[1-9]\d*)$/', $value) !== 1 || (float) $value > PHP_INT_MAX) {
            return ['value' => null, 'error' => "The {$name} filter must be a non-negative Unix timestamp."];
        }

        return ['value' => (int) $value, 'error' => null];
    }

    private function userId(): int
    {
        return (int) $this->getCurrentUserId();
    }

    private function error(string $type, string $message, int $status): ResponseInterface
    {
        return $this->channelJson(['error' => ['type' => $type, 'message' => $message]], $status);
    }

    private function channelJson(mixed $data, int $status = 200): ResponseInterface
    {
        return $this->respondWithJson($data, $status)->setHeader('X-CSRF-TOKEN', csrf_hash());
    }
}
