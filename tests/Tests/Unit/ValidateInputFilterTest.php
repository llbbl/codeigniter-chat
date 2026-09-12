<?php

namespace Tests\Unit;

use App\Filters\ValidateInputFilter;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use Config\App;
use Config\Services;

/**
 * @internal
 */
final class ValidateInputFilterTest extends CIUnitTestCase
{
    private ValidateInputFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filter = new ValidateInputFilter();
        Services::resetSingle('negotiator');
        Services::resetSingle('response');
        Services::resetSingle('validation');
    }

    public function testValidInputPassesThrough(): void
    {
        $request = $this->request(['message' => 'A valid message']);

        $this->assertNull($this->filter->before($request, ['message']));
    }

    public function testInvalidInputReturnsJsonValidationError(): void
    {
        $request = $this->request(['message' => '']);
        $request->setHeader('Accept', 'application/json');

        $response = $this->filter->before($request, ['message']);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('application/json; charset=UTF-8', $response->getHeaderLine('Content-Type'));
        $this->assertSame([
            'success' => false,
            'type' => 'validation',
            'message' => 'Message validation failed',
            'errors' => ['message' => 'Message is required'],
        ], json_decode($response->getBody(), true));
    }

    public function testInvalidInputReturnsXmlValidationError(): void
    {
        $request = $this->request(['message' => '']);
        $request->setHeader('Accept', 'application/xml');

        $response = $this->filter->before($request, ['message']);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('application/xml; charset=UTF-8', $response->getHeaderLine('Content-Type'));
        $this->assertSame(
            '<?xml version="1.0" encoding="UTF-8"?><response><success>false</success><type>validation</type><message>Message validation failed</message><errors><message>Message is required</message></errors></response>',
            $response->getBody(),
        );
    }

    public function testBrowserAcceptHeaderReturnsToTheFormWithValidationErrors(): void
    {
        $request = $this->request(['message' => '']);
        $request->setHeader('Accept', 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8');

        $response = $this->filter->before($request, ['message']);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('Message validation failed', session()->getFlashdata('error'));
    }

    /**
     * @param array<string, string> $post
     */
    private function request(array $post): IncomingRequest
    {
        $request = new IncomingRequest(
            new App(),
            new URI('https://example.com/chat/update'),
            null,
            new UserAgent(),
        );
        $request->setGlobal('post', $post);
        $request->setGlobal('request', $post);
        $request->setGlobal('server', ['REQUEST_METHOD' => 'POST']);

        return $request;
    }
}
