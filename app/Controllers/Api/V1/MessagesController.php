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

    public function listXml(): ResponseInterface
    {
        return $this->backend();
    }
}
