<?php

namespace Tests\Unit;

use App\Libraries\AppExceptionHandler;
use App\Libraries\ErrorHandler;
use App\Services\CorrelationId;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\Response;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use Config\App;
use RuntimeException;

/**
 * @internal
 */
final class ErrorHandlerTest extends CIUnitTestCase
{
    public function testJsonErrorsUseNestedEnvelopeWithMatchingCorrelationHeader(): void
    {
        $request = new IncomingRequest(new App(), new URI('https://example.com/chat'), null, new UserAgent());
        $request->setHeader('Accept', 'application/json');
        $response = new Response(new App());
        $handler = new ErrorHandler($request, $response, new CorrelationId($request));

        $result = $handler->handleError('validation', 'Invalid input', ['message' => 'Required'], 400, logError: false);
        $payload = json_decode($result->getBody(), true);

        $this->assertSame(400, $result->getStatusCode());
        $this->assertSame('validation', $payload['error']['type']);
        $this->assertSame(['message' => 'Required'], $payload['error']['details']);
        $this->assertSame($result->getHeaderLine(CorrelationId::HEADER_NAME), $payload['error']['correlation_id']);
    }

    public function testExceptionsUseTheSameEnvelope(): void
    {
        $request = new IncomingRequest(new App(), new URI('https://example.com/chat'), null, new UserAgent());
        $request->setHeader('Accept', 'application/json');
        $handler = new ErrorHandler($request, new Response(new App()), new CorrelationId($request));

        $result = $handler->handleException(new RuntimeException('Boom'), logError: false);
        $payload = json_decode($result->getBody(), true);

        $this->assertSame(500, $result->getStatusCode());
        $this->assertSame('server', $payload['error']['type']);
        $this->assertSame('Boom', $payload['error']['message']);
        $this->assertSame(RuntimeException::class, $payload['error']['details']['exception']);
    }

    public function testApplicationExceptionHandlerSendsTheStandardJsonEnvelope(): void
    {
        $request = new IncomingRequest(new App(), new URI('https://example.com/chat'), null, new UserAgent());
        $request->setHeader('Accept', 'application/json');
        $response = new Response(new App());
        $errorHandler = new ErrorHandler($request, $response, new CorrelationId($request));
        $exceptionHandler = new AppExceptionHandler($errorHandler);

        ob_start();
        $exceptionHandler->handle(new RuntimeException('Uncaught'), $request, $response, 500, 1);
        ob_end_clean();

        $payload = json_decode($response->getBody(), true);
        $this->assertSame('server', $payload['error']['type']);
        $this->assertSame($response->getHeaderLine(CorrelationId::HEADER_NAME), $payload['error']['correlation_id']);
    }
}
