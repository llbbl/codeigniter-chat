<?php

namespace App\Services;

use CodeIgniter\HTTP\RequestInterface;

final class CorrelationId
{
    public const HEADER_NAME = 'X-Correlation-ID';

    private ?string $value = null;

    public function __construct(private readonly RequestInterface $request)
    {
    }

    public function start(): string
    {
        $this->value = bin2hex(random_bytes(16));
        $this->request->setHeader(self::HEADER_NAME, $this->value);

        return $this->value;
    }

    public function get(): string
    {
        return $this->value ?? $this->start();
    }
}
