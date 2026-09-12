<?php

namespace App\Libraries;

use App\Services\CorrelationId;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * Error Handler Library
 *
 * Provides consistent error handling across the application
 */
class ErrorHandler
{
    use ResponseTrait;

    /**
     * Log levels for different error types
     */
    public const LOG_LEVEL_INFO = 'info';
    public const LOG_LEVEL_WARNING = 'warning';
    public const LOG_LEVEL_ERROR = 'error';
    public const LOG_LEVEL_CRITICAL = 'critical';

    /**
     * Error types
     */
    public const ERROR_TYPE_VALIDATION = 'validation';
    public const ERROR_TYPE_DATABASE = 'database';
    public const ERROR_TYPE_AUTHENTICATION = 'authentication';
    public const ERROR_TYPE_AUTHORIZATION = 'authorization';
    public const ERROR_TYPE_NOT_FOUND = 'not_found';
    public const ERROR_TYPE_SERVER = 'server';

    protected RequestInterface $request;

    protected ResponseInterface $response;

    public function __construct(
        ?RequestInterface $request = null,
        ?ResponseInterface $response = null,
        private readonly ?CorrelationId $correlationId = null,
    ) {
        $this->request = $request ?? service('request');
        $this->response = $response ?? service('response');
    }

    public function setContext(RequestInterface $request, ResponseInterface $response): self
    {
        $this->request = $request;
        $this->response = $response;

        return $this;
    }

    /**
     * Handle an error and return an appropriate response
     *
     * @param string $type       The type of error
     * @param string $message    The error message
     * @param array  $errors     Additional error details
     * @param int    $statusCode The HTTP status code
     * @param string $logLevel   The log level
     * @param bool   $logError   Whether to log the error
     *
     * @return mixed
     */
    public function handleError(
        string $type,
        string $message,
        array $errors = [],
        int $statusCode = 400,
        string $logLevel = self::LOG_LEVEL_ERROR,
        bool $logError = true
    ) {
        // Log the error if requested
        if ($logError) {
            $this->logError($message, $logLevel, $errors);
        }

        $correlationId = $this->getCorrelationId();

        if (in_array($type, [self::ERROR_TYPE_AUTHENTICATION, self::ERROR_TYPE_AUTHORIZATION], true)) {
            $this->auditSecurityError($type, $message, $correlationId);
        }

        // For AJAX or API requests, return JSON
        if (($this->request instanceof IncomingRequest && $this->request->isAJAX())
            || strpos($this->request->getHeaderLine('Accept'), 'application/json') !== false
        ) {
            return $this->respondJSON($type, $message, $errors, $statusCode, $correlationId);
        }

        // For HTML requests, redirect with flash data
        return $this->respondHTML($type, $message, $errors, $correlationId);
    }

    /**
     * Handle an exception and return an appropriate response
     *
     * @param Throwable $exception  The exception to handle
     * @param string    $type       The type of error
     * @param int       $statusCode The HTTP status code
     * @param string    $logLevel   The log level
     * @param bool      $logError   Whether to log the error
     *
     * @return mixed
     */
    public function handleException(
        Throwable $exception,
        string $type = self::ERROR_TYPE_SERVER,
        int $statusCode = 500,
        string $logLevel = self::LOG_LEVEL_ERROR,
        bool $logError = true
    ) {
        $exceptionDetails = [
            'exception' => $exception::class,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ];

        if ($logError) {
            $this->logError($exception->getMessage(), $logLevel, $exceptionDetails);
        }

        $clientMessage = ENVIRONMENT === 'production'
            ? 'An unexpected error occurred.'
            : $exception->getMessage();
        $clientDetails = ENVIRONMENT === 'production' ? [] : $exceptionDetails;

        return $this->handleError($type, $clientMessage, $clientDetails, $statusCode, $logLevel, false);
    }

    /**
     * Log an error message
     *
     * @param string $message  The error message
     * @param string $logLevel The log level
     * @param array  $context  Additional context data
     *
     * @return void
     */
    protected function logError(string $message, string $logLevel, array $context = []): void
    {
        $correlationId = $this->getCorrelationId();
        $context['correlation_id'] = $correlationId;
        $logMessage = '[correlation_id: {correlation_id}] ' . $message;

        $details = array_diff_key($context, ['correlation_id' => true]);
        if ($details !== []) {
            $context['details'] = json_encode($details, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
            $logMessage .= ' details={details}';
        }

        log_message($logLevel, $logMessage, $context);
    }

    /**
     * Return a JSON response for an error
     *
     * @param string $type       The type of error
     * @param string $message    The error message
     * @param array  $errors     Additional error details
     * @param int    $statusCode The HTTP status code
     *
     * @return ResponseInterface
     */
    protected function respondJSON(string $type, string $message, array $errors, int $statusCode, string $correlationId)
    {
        $response = [
            'error' => [
                'type' => $type,
                'message' => $message,
                'details' => $errors,
                'correlation_id' => $correlationId,
            ],
        ];

        return $this->respond($response, $statusCode)
            ->setHeader(CorrelationId::HEADER_NAME, $correlationId);
    }

    /**
     * Return an HTML response for an error
     *
     * @param string $type    The type of error
     * @param string $message The error message
     * @param array  $errors  Additional error details
     *
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    protected function respondHTML(string $type, string $message, array $errors, string $correlationId)
    {
        $session = session();

        // Set flash data
        $session->setFlashdata('error', $message);
        $session->setFlashdata('correlation_id', $correlationId);

        if (!empty($errors)) {
            $session->setFlashdata('errors', $errors);
        }

        // Redirect back with input
        return redirect()->back()
            ->withInput()
            ->setHeader(CorrelationId::HEADER_NAME, $correlationId);
    }

    private function getCorrelationId(): string
    {
        if ($this->correlationId !== null) {
            return $this->correlationId->get();
        }

        $existing = $this->request->getHeaderLine(CorrelationId::HEADER_NAME);
        if ($existing !== '') {
            return $existing;
        }

        $correlationId = bin2hex(random_bytes(16));
        $this->request->setHeader(CorrelationId::HEADER_NAME, $correlationId);

        return $correlationId;
    }

    private function auditSecurityError(string $type, string $message, string $correlationId): void
    {
        try {
            $userId = session()->get('user_id');
            service('auditLogger')->record('error.' . $type, is_numeric($userId) ? (int) $userId : null, [
                'message' => $message,
                'correlation_id' => $correlationId,
            ]);
        } catch (Throwable $exception) {
            log_message('warning', '[correlation_id: {correlation_id}] Unable to write security error to audit log: {message}', [
                'correlation_id' => $correlationId,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
