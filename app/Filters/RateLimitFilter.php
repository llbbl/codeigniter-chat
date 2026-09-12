<?php

namespace App\Filters;

use App\Services\CorrelationId;
use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\Config\Services;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\RateLimit;
use InvalidArgumentException;

class RateLimitFilter implements FilterInterface
{
    private readonly RateLimit $config;

    private readonly CacheInterface $cache;

    public function __construct(?RateLimit $config = null, ?CacheInterface $cache = null)
    {
        $this->config = $config ?? config(RateLimit::class);
        $this->cache = $cache ?? Services::cache();
    }

    public function before(RequestInterface $request, $arguments = null)
    {
        $profile = $arguments[0] ?? 'default';
        [$maxRequests, $timeWindow] = $this->profile($profile, $this->isAuthenticated());
        $key = $this->cacheKey($profile, $this->identifier($request));
        $now = time();
        $history = $this->history($this->cache->get($key), $now, $timeWindow);

        if (count($history) >= $maxRequests) {
            return $this->tooManyRequests($request, max(1, $timeWindow - ($now - $history[0])));
        }

        $history[] = $now;
        $this->cache->save($key, $history, $timeWindow);
    }

    /**
     * We don't have anything to do after the controller.
     *
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @param array|null        $arguments
     *
     * @return void
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ?ResponseInterface
    {
        return null;
    }

    /**
     * @return array{int, int}
     */
    private function profile(string $profile, bool $authenticated): array
    {
        if (! isset($this->config->profiles[$profile])) {
            throw new InvalidArgumentException("Unknown rate-limit profile: {$profile}");
        }

        return $this->config->profiles[$profile][$authenticated ? 'authenticated' : 'anonymous'];
    }

    private function isAuthenticated(): bool
    {
        return session()->get('user_id') !== null;
    }

    private function identifier(RequestInterface $request): string
    {
        $userId = session()->get('user_id');
        if ($userId !== null) {
            return 'user-' . $userId;
        }

        $ipAddress = $request->getIPAddress();
        if ($ipAddress !== '' && $ipAddress !== '0.0.0.0') {
            return 'ip-' . $ipAddress;
        }

        return 'client-' . hash('sha256', $ipAddress . '|' . $request->getHeaderLine('User-Agent'));
    }

    private function cacheKey(string $profile, string $identifier): string
    {
        // Cache keys cannot contain ':' under Config\Cache::$reservedCharacters.
        return 'ratelimit_' . $profile . '_' . hash('sha256', $identifier);
    }

    /**
     * @return list<int>
     */
    private function history(mixed $value, int $now, int $timeWindow): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            $value,
            static fn (mixed $timestamp): bool => is_int($timestamp) && $timestamp > ($now - $timeWindow),
        ));
    }

    private function tooManyRequests(RequestInterface $request, int $retryAfter): ResponseInterface
    {
        $response = service('response')
            ->setStatusCode(429)
            ->setHeader('Retry-After', (string) $retryAfter);
        $format = $this->preferredFormat($request);

        if ($format === 'json') {
            $correlationId = $this->correlationId($request);

            return $response->setJSON([
                'error' => [
                    'type' => 'rate_limit',
                    'message' => 'Too many requests. Please try again later.',
                    'details' => ['retry_after' => $retryAfter],
                    'correlation_id' => $correlationId,
                ],
            ])->setHeader(CorrelationId::HEADER_NAME, $correlationId);
        }

        if ($format === 'xml') {
            $correlationId = $this->correlationId($request);

            return $response
                ->setHeader(CorrelationId::HEADER_NAME, $correlationId)
                ->setHeader('Content-Type', 'application/xml; charset=UTF-8')
                ->setBody('<?xml version="1.0" encoding="UTF-8"?><response><success>false</success><type>rate_limit</type><message>Too many requests. Please try again later.</message><retry_after>' . $retryAfter . '</retry_after><correlation_id>' . $correlationId . '</correlation_id></response>');
        }

        return $response->setBody('Too many requests. Please try again later.');
    }

    private function preferredFormat(RequestInterface $request): string
    {
        if ($request->isAJAX()) {
            return 'json';
        }

        if (! $request instanceof IncomingRequest) {
            return 'text';
        }

        return match ($request->negotiate('media', [
            'text/plain',
            'application/json',
            'application/xml',
            'text/xml',
        ])) {
            'application/json' => 'json',
            'application/xml', 'text/xml' => 'xml',
            default => 'text',
        };
    }

    private function correlationId(RequestInterface $request): string
    {
        $correlationId = $request->getHeaderLine(CorrelationId::HEADER_NAME);
        if ($correlationId !== '') {
            return $correlationId;
        }

        $correlationId = bin2hex(random_bytes(16));
        $request->setHeader(CorrelationId::HEADER_NAME, $correlationId);

        return $correlationId;
    }
}
