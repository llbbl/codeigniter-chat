<?php

namespace App\Controllers\Api\V1;

use App\Controllers\Chat;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Stable v1 contract for message reads and writes.
 */
final class MessagesController extends Chat
{
    public function list(): ResponseInterface
    {
        return $this->jsonBackend();
    }

    public function create(): ResponseInterface
    {
        // The path defines the representation; callers do not need the legacy
        // X-Requested-With convention or a particular Accept header.
        $this->request->setHeader('Accept', 'application/json');

        return $this->update();
    }

    public function search(): ResponseInterface
    {
        $input = $this->validatedSearchInput();

        if (isset($input['error'])) {
            return $this->respondWithJson([
                'error' => [
                    'type' => 'validation',
                    'message' => $input['error'],
                ],
            ], 400);
        }

        $filters = $input['filters'];
        $result = $this->chatRepository->searchMessages(
            $filters['text'],
            $filters['user'],
            $filters['from'],
            $filters['to'],
            $input['page'],
            $input['perPage'],
        );

        return $this->respondWithJson([
            'messages' => $result['messages'],
            'pagination' => $result['pagination'],
            'filters' => $filters,
        ]);
    }

    public function listXml(): ResponseInterface
    {
        return $this->backend();
    }

    /**
     * @return array{
     *     filters?: array{text: ?string, user: ?string, from: ?int, to: ?int},
     *     page?: int,
     *     perPage?: int,
     *     error?: string
     * }
     */
    private function validatedSearchInput(): array
    {
        $text = $this->optionalString('text', 500);
        $user = $this->optionalString('user', 255);
        $from = $this->optionalUnsignedInteger('from');
        $to = $this->optionalUnsignedInteger('to');
        $page = $this->positiveInteger('page', 1, PHP_INT_MAX);
        $perPage = $this->positiveInteger('per_page', 10, 100);

        foreach ([$text, $user, $from, $to, $page, $perPage] as $value) {
            if (isset($value['error'])) {
                return ['error' => $value['error']];
            }
        }

        if ($text['value'] === null && $user['value'] === null && $from['value'] === null && $to['value'] === null) {
            return ['error' => 'Provide at least one of text, user, from, or to.'];
        }

        if ($from['value'] !== null && $to['value'] !== null && $from['value'] > $to['value']) {
            return ['error' => 'The from timestamp must be less than or equal to to.'];
        }

        return [
            'filters' => [
                'text' => $text['value'],
                'user' => $user['value'],
                'from' => $from['value'],
                'to' => $to['value'],
            ],
            'page' => $page['value'],
            'perPage' => $perPage['value'],
        ];
    }

    /** @return array{value?: ?string, error?: string} */
    private function optionalString(string $name, int $maxLength): array
    {
        $value = $this->request->getGet($name);

        if ($value === null) {
            return ['value' => null];
        }
        if (! is_string($value)) {
            return ['error' => "The {$name} filter must be a string."];
        }

        $value = trim($value);
        if ($value === '') {
            return ['error' => "The {$name} filter must not be empty."];
        }
        if (mb_strlen($value) > $maxLength) {
            return ['error' => "The {$name} filter must not exceed {$maxLength} characters."];
        }

        return ['value' => $value];
    }

    /** @return array{value?: ?int, error?: string} */
    private function optionalUnsignedInteger(string $name): array
    {
        $value = $this->request->getGet($name);

        if ($value === null) {
            return ['value' => null];
        }
        if (! is_string($value) || preg_match('/^(0|[1-9]\d*)$/', $value) !== 1 || (float) $value > PHP_INT_MAX) {
            return ['error' => "The {$name} filter must be a non-negative Unix timestamp."];
        }

        return ['value' => (int) $value];
    }

    /** @return array{value?: int, error?: string} */
    private function positiveInteger(string $name, int $default, int $maximum): array
    {
        $value = $this->request->getGet($name);

        if ($value === null) {
            return ['value' => $default];
        }
        if (! is_string($value) || preg_match('/^[1-9]\d*$/', $value) !== 1 || (float) $value > $maximum) {
            return ['error' => "The {$name} parameter must be an integer between 1 and {$maximum}."];
        }

        return ['value' => (int) $value];
    }
}
