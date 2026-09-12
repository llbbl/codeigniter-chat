<?php

namespace Tests\Unit;

use App\Filters\SecurityHeadersFilter;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\Response;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use Config\App;
use Config\SecurityHeaders;

/**
 * @internal
 */
final class SecurityHeadersFilterTest extends CIUnitTestCase
{
    private IncomingRequest $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->request = new IncomingRequest(
            new App(),
            new URI('https://example.com/chat'),
            null,
            new UserAgent(),
        );
    }

    public function testDevelopmentPolicyOmitsHsts(): void
    {
        $response = $this->apply('development');

        $this->assertFalse($response->hasHeader('Strict-Transport-Security'));
        $this->assertSame('DENY', $response->getHeaderLine('X-Frame-Options'));
        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $response->getHeaderLine('Referrer-Policy'));
    }

    public function testProductionPolicyAddsAllHardeningHeaders(): void
    {
        $response = $this->apply('production');

        $this->assertSame('max-age=31536000; includeSubDomains', $response->getHeaderLine('Strict-Transport-Security'));
        $this->assertSame('DENY', $response->getHeaderLine('X-Frame-Options'));
        $this->assertSame('camera=(), microphone=(), geolocation=()', $response->getHeaderLine('Permissions-Policy'));
        $this->assertSame('same-origin', $response->getHeaderLine('Cross-Origin-Opener-Policy'));
        $this->assertSame('same-site', $response->getHeaderLine('Cross-Origin-Resource-Policy'));
    }

    public function testExistingHeaderIsNotOverwritten(): void
    {
        $response = new Response(new App());
        $response->setHeader('X-Frame-Options', 'SAMEORIGIN');

        $filter = new SecurityHeadersFilter(new SecurityHeaders(), 'production');
        $filter->after($this->request, $response);

        $this->assertSame('SAMEORIGIN', $response->getHeaderLine('X-Frame-Options'));
    }

    public function testNamedOverrideIsAppliedBeforeHeadersAreWritten(): void
    {
        $response = $this->apply('development', ['frame-relaxed']);

        $this->assertSame('SAMEORIGIN', $response->getHeaderLine('X-Frame-Options'));
    }

    public function testConfiguredReportToHeaderIsSerialized(): void
    {
        $config = new SecurityHeaders();
        $config->reportTo = [
            'group' => 'csp-endpoint',
            'max_age' => 86400,
            'endpoints' => [['url' => 'https://example.com/csp-report']],
        ];
        $response = new Response(new App());

        $filter = new SecurityHeadersFilter($config, 'development');
        $filter->after($this->request, $response);

        $this->assertSame(
            '{"group":"csp-endpoint","max_age":86400,"endpoints":[{"url":"https://example.com/csp-report"}]}',
            $response->getHeaderLine('Report-To'),
        );
    }

    /**
     * @param list<string>|null $arguments
     */
    private function apply(string $environment, ?array $arguments = null): Response
    {
        $response = new Response(new App());
        $filter = new SecurityHeadersFilter(new SecurityHeaders(), $environment);
        $filter->after($this->request, $response, $arguments);

        return $response;
    }
}
