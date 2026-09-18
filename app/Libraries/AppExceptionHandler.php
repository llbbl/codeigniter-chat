<?php

namespace App\Libraries;

use App\Exceptions\EnvValidationException;
use CodeIgniter\Debug\ExceptionHandlerInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

final class AppExceptionHandler implements ExceptionHandlerInterface
{
    public function __construct(private readonly ErrorHandler $errorHandler)
    {
    }

    public function handle(
        Throwable $exception,
        RequestInterface $request,
        ResponseInterface $response,
        int $statusCode,
        int $exitCode,
    ): void {
        if ($exception instanceof EnvValidationException) {
            log_message('critical', 'Application startup rejected invalid environment configuration: {errors}', [
                'errors' => implode('; ', $exception->errors),
            ]);

            $message = ENVIRONMENT === 'production'
                ? 'Service unavailable due to invalid environment configuration.'
                : $exception->getMessage();
            $response
                ->setStatusCode(503)
                ->setContentType('text/plain')
                ->setBody($message)
                ->send();

            return;
        }

        $type = $statusCode === 404
            ? ErrorHandler::ERROR_TYPE_NOT_FOUND
            : ErrorHandler::ERROR_TYPE_SERVER;
        $logLevel = match (true) {
            $statusCode === 404 => ErrorHandler::LOG_LEVEL_INFO,
            $statusCode >= 500 => ErrorHandler::LOG_LEVEL_CRITICAL,
            default => ErrorHandler::LOG_LEVEL_WARNING,
        };

        $this->errorHandler
            ->setContext($request, $response)
            ->handleException($exception, $type, $statusCode, $logLevel)
            ->send();
    }
}
