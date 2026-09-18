<?php

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\UsesApplication;

/**
 * @internal
 */
final class ApiDocsTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use UsesApplication;

    public function testApiDocsRendersBundledSwaggerUiWithEmbeddedSpec(): void
    {
        $result = $this->call('get', '/api/docs');

        $result->assertOK();
        $this->assertStringContainsString('public, max-age=300', $result->response()->getHeaderLine('Cache-Control'));
        $body = $result->getBody();
        $this->assertStringContainsString('id="swagger-ui"', $body);
        $this->assertMatchesRegularExpression('#/dist/(?:(?:js/)?api-docs(?:-[A-Za-z0-9_-]+)?|src/js/api-docs)\\.js#', $body);
        $this->assertStringNotContainsString('cdn.jsdelivr.net', $body);

        preg_match('/data-openapi-base64="([^"]+)"/', $body, $matches);
        $this->assertArrayHasKey(1, $matches);
        $spec = base64_decode(html_entity_decode($matches[1], ENT_QUOTES), true);
        $this->assertIsString($spec);
        $this->assertStringContainsString('openapi: 3.1.0', $spec);
        $this->assertStringContainsString('title: CodeIgniter Chat API', $spec);
    }
}
