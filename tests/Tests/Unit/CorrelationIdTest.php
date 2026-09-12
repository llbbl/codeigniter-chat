<?php

namespace Tests\Unit;

use App\Filters\RequestIdFilter;
use App\Services\CorrelationId;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use Config\App;
use Config\Services;

/**
 * @internal
 */
final class CorrelationIdTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Services::resetSingle('response');
    }

    public function testGeneratesOneIdentifierAndAddsItToTheRequest(): void
    {
        $request = new IncomingRequest(new App(), new URI('https://example.com/chat'), null, new UserAgent());
        $correlationId = new CorrelationId($request);

        $first = $correlationId->get();

        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $first);
        $this->assertSame($first, $correlationId->get());
        $this->assertSame($first, $request->getHeaderLine(CorrelationId::HEADER_NAME));
    }

    public function testRequestFilterAddsTheSameIdentifierToTheResponse(): void
    {
        $request = new IncomingRequest(new App(), new URI('https://example.com/chat'), null, new UserAgent());
        $correlationId = new CorrelationId($request);
        $filter = new RequestIdFilter($correlationId);

        $this->assertNull($filter->before($request));

        $response = Services::response();
        $result = $filter->after($request, $response);

        $this->assertSame($request->getHeaderLine(CorrelationId::HEADER_NAME), $result->getHeaderLine(CorrelationId::HEADER_NAME));
    }
}
