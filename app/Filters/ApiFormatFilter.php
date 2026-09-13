<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Selects the representation before authentication and validation filters run.
 */
final class ApiFormatFilter implements FilterInterface
{
    public const FORMAT_HEADER = 'X-App-Response-Format';

    public function before(RequestInterface $request, $arguments = null)
    {
        $request->setHeader(self::FORMAT_HEADER, 'json');

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ?ResponseInterface
    {
        return null;
    }
}
