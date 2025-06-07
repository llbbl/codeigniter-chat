<?php

namespace App\Libraries;

use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;
use Exception;
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
    const LOG_LEVEL_INFO = 'info';
    const LOG_LEVEL_WARNING = 'warning';
    const LOG_LEVEL_ERROR = 'error';
    const LOG_LEVEL_CRITICAL = 'critical';

    /**
     * Error types
     */
    const ERROR_TYPE_VALIDATION = 'validation';
    const ERROR_TYPE_DATABASE = 'database';
    const ERROR_TYPE_AUTHENTICATION = 'authentication';
    const ERROR_TYPE_AUTHORIZATION = 'authorization';
    const ERROR_TYPE_NOT_FOUND = 'not_found';
    const ERROR_TYPE_SERVER = 'server';

    /**
     * Handle an error and return an appropriate response
     * 
     * @param string $type        The type of error
     * @param string $message     The error message
     * @param array  $errors      Additional error details
     * @param int    $statusCode  The HTTP status code
     * @param string $logLevel    The log level
     * @param bool   $logError    Whether to log the error
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

        // Determine the response format based on the request
        $request = service('request');
        
        // For AJAX or API requests, return JSON
        if ($request->isAJAX() || strpos($request->getHeaderLine('Accept'), 'application/json') !== false) {
            return $this->respondJSON($type, $message, $errors, $statusCode);
        }
        
        // For HTML requests, redirect with flash data
        return $this->respondHTML($type, $message, $errors);
    }

    /**
     * Handle an exception and return an appropriate response
     * 
     * @param Throwable $exception  The exception to handle
     * @param string    $type        The type of error
     * @param int       $statusCode  The HTTP status code
     * @param string    $logLevel    The log level
     * @param bool      $logError    Whether to log the error
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
        $message = $exception->getMessage();
        $errors = [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => ENVIRONMENT === 'development' ? $exception->getTraceAsString() : 'Hidden in production'
        ];
        
        return $this->handleError($type, $message, $errors, $statusCode, $logLevel, $logError);
    }

    /**
     * Log an error message
     * 
     * @param string $message   The error message
     * @param string $logLevel  The log level
     * @param array  $context   Additional context data
     * 
     * @return void
     */
    protected function logError(string $message, string $logLevel, array $context = []): void
    {
        log_message($logLevel, $message, $context);
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
    protected function respondJSON(string $type, string $message, array $errors, int $statusCode)
    {
        $response = [
            'success' => false,
            'type' => $type,
            'message' => $message
        ];
        
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }
        
        return $this->respond($response, $statusCode);
    }

    /**
     * Return an HTML response for an error
     * 
     * @param string $type     The type of error
     * @param string $message  The error message
     * @param array  $errors   Additional error details
     * 
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    protected function respondHTML(string $type, string $message, array $errors)
    {
        $session = session();
        
        // Set flash data
        $session->setFlashdata('error', $message);
        
        if (!empty($errors)) {
            $session->setFlashdata('errors', $errors);
        }
        
        // Redirect back with input
        return redirect()->back()->withInput();
    }
}