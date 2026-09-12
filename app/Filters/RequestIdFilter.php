<?php

namespace App\Filters;

use App\Services\CorrelationId;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class RequestIdFilter implements FilterInterface
{
    public function __construct(private readonly ?CorrelationId $correlationId = null)
    {
    }

    public function before(RequestInterface $request, $arguments = null)
    {
        $id = ($this->correlationId ?? service('correlationId'))->start();
        service('response')->setHeader(CorrelationId::HEADER_NAME, $id);

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ?ResponseInterface
    {
        $response->setHeader(
            CorrelationId::HEADER_NAME,
            ($this->correlationId ?? service('correlationId'))->get(),
        );

        return $response;
    }
}
