<?php

namespace Tests\Feature;

use App\Controllers\CspReport;
use App\Models\CspReportModel;
use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\Response;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\App;

/**
 * @internal
 */
final class CspReportTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testCspReportRouteDoesNotRequireCsrfToken(): void
    {
        $result = $this->call('post', '/csp-report', []);

        $this->assertSame(400, $result->response()->getStatusCode());
    }

    public function testLegacyReportIsPersisted(): void
    {
        $model = $this->createMock(CspReportModel::class);
        $model->expects($this->once())->method('insert')->with($this->callback(
            static fn (array $row): bool => $row['violated_directive'] === 'script-src' && $row['document_uri'] === 'https://example.test/chat',
        ));
        $model->expects($this->once())->method('pruneOlderThan')->willReturn(true);

        $response = $this->controller($model, [
            'csp-report' => [
                'document-uri' => 'https://example.test/chat',
                'violated-directive' => 'script-src',
            ],
        ])->index();

        $this->assertSame(204, $response->getStatusCode());
    }

    public function testModernReportsArePersisted(): void
    {
        $model = $this->createMock(CspReportModel::class);
        $model->expects($this->exactly(2))->method('insert');
        $model->expects($this->once())->method('pruneOlderThan')->willReturn(true);

        $response = $this->controller($model, [[
            'type' => 'csp-violation',
            'body' => ['documentURL' => 'https://example.test/chat', 'effectiveDirective' => 'style-src'],
        ], [
            'type' => 'csp-violation',
            'body' => ['documentURL' => 'https://example.test/chat', 'effectiveDirective' => 'script-src'],
        ]])->index();

        $this->assertSame(204, $response->getStatusCode());
    }

    public function testMalformedPayloadIsRejectedWithoutPersistence(): void
    {
        $model = $this->createMock(CspReportModel::class);
        $model->expects($this->never())->method('insert');

        $response = $this->controller($model, ['unexpected' => true])->index();

        $this->assertSame(400, $response->getStatusCode());
    }

    private function controller(CspReportModel $model, array $payload): CspReport
    {
        $request = new IncomingRequest(new App(), new URI('https://example.test/csp-report'), null, new UserAgent());
        $request->setHeader('Content-Type', 'application/reports+json');
        $request->setBody(json_encode($payload, JSON_THROW_ON_ERROR));
        $controller = new CspReport($model);
        $controller->initController($request, new Response(new App()), Services::logger());

        return $controller;
    }
}
