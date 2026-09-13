<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class DeprecationFilter implements FilterInterface
{
    private const SUNSET = 'Thu, 01 Jul 2027 00:00:00 GMT';

    public function before(RequestInterface $request, $arguments = null)
    {
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ResponseInterface
    {
        $successor = $arguments[0] ?? '/api/v1/messages';

        return $response
            ->setHeader('Deprecation', 'true')
            ->setHeader('Sunset', self::SUNSET)
            ->setHeader('Link', '<' . $successor . '>; rel="successor-version"');
    }
}
