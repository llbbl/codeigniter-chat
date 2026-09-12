<?php

namespace Tests\Unit;

use App\Filters\RateLimitFilter;
use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use Config\App;
use Config\RateLimit;

/**
 * @internal
 */
final class RateLimitFilterTest extends CIUnitTestCase
{
    /** @var array<string, list<int>> */
    private array $cacheEntries = [];

    /** @var array<string, int> */
    private array $cacheTtls = [];

    private RateLimitFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();
        Services::resetSingle('response');
        Services::resetSingle('negotiator');

        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willReturnCallback(fn (string $key) => $this->cacheEntries[$key] ?? null);
        $cache->method('save')->willReturnCallback(function (string $key, mixed $value, int $ttl): bool {
            $this->cacheEntries[$key] = $value;
            $this->cacheTtls[$key] = $ttl;

            return true;
        });

        $this->filter = new RateLimitFilter(new RateLimit(), $cache);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    public function testRequestsUnderProfileLimitPassThrough(): void
    {
        $request = $this->request();

        for ($requestNumber = 1; $requestNumber <= 4; $requestNumber++) {
            $this->assertNull($this->filter->before($request, ['auth']));
        }
    }

    public function testRequestAtProfileLimitReturns429WithRetryAfter(): void
    {
        $request = $this->request();

        for ($requestNumber = 1; $requestNumber <= 5; $requestNumber++) {
            $this->filter->before($request, ['auth']);
        }

        $response = $this->filter->before($request, ['auth']);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(429, $response->getStatusCode());
        $this->assertNotSame('', $response->getHeaderLine('Retry-After'));
        $this->assertStringContainsString('Too many requests', $response->getBody());
    }

    public function testAuthenticatedProfileAllowsMoreRequestsThanAnonymous(): void
    {
        $request = $this->request();
        for ($requestNumber = 1; $requestNumber <= 5; $requestNumber++) {
            $this->filter->before($request, ['auth']);
        }

        Services::session()->set('user_id', 42);

        $this->assertNull($this->filter->before($request, ['auth']));
    }

    public function testHistoryUsesCacheTtlAndAnExpiredCacheEntryStartsFresh(): void
    {
        $request = $this->request();
        $this->filter->before($request, ['write']);

        $key = array_key_first($this->cacheEntries);
        $this->assertNotNull($key);
        $this->assertStringStartsWith('ratelimit_write_', $key);
        $this->assertStringNotContainsString(':', $key);
        $this->assertSame(60, $this->cacheTtls[$key]);

        unset($this->cacheEntries[$key]);
        for ($requestNumber = 1; $requestNumber <= 10; $requestNumber++) {
            $this->assertNull($this->filter->before($request, ['write']));
        }
    }

    public function testJsonResponseMatchesRequestedFormat(): void
    {
        $request = $this->request('application/json');
        $this->exhaustAuthProfile($request);

        $response = $this->filter->before($request, ['auth']);

        $this->assertSame('application/json; charset=UTF-8', $response->getHeaderLine('Content-Type'));
        $payload = json_decode($response->getBody(), true);
        $this->assertSame('rate_limit', $payload['error']['type']);
        $this->assertSame($response->getHeaderLine('X-Correlation-ID'), $payload['error']['correlation_id']);
    }

    public function testXmlResponseMatchesRequestedFormat(): void
    {
        $request = $this->request('application/xml');
        $this->exhaustAuthProfile($request);

        $response = $this->filter->before($request, ['auth']);

        $this->assertSame('application/xml; charset=UTF-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('<type>rate_limit</type>', $response->getBody());
    }

    private function exhaustAuthProfile(IncomingRequest $request): void
    {
        for ($requestNumber = 1; $requestNumber <= 5; $requestNumber++) {
            $this->filter->before($request, ['auth']);
        }
    }

    private function request(string $accept = 'text/plain'): IncomingRequest
    {
        $request = new IncomingRequest(
            new App(),
            new URI('https://example.com/auth/processLogin'),
            null,
            new UserAgent(),
        );
        $request->setHeader('Accept', $accept);
        $request->setHeader('User-Agent', 'RateLimitFilterTest');

        return $request;
    }
}
