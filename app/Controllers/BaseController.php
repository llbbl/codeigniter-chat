<?php

namespace App\Controllers;

use App\Libraries\ErrorHandler;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Class BaseController
 *
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 * Extend this class in any new controllers:
 *     class Home extends BaseController
 *
 * For security be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Instance of the main Request object.
     *
     * @var CLIRequest|IncomingRequest
     */
    protected $request;

    /**
     * Instance of the main Response object.
     *
     * @var ResponseInterface
     */
    protected $response;

    /**
     * Instance of the logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Instance of the session.
     *
     * @var \CodeIgniter\Session\Session
     */
    protected $session;

    /**
     * Instance of the error handler.
     *
     * @var ErrorHandler
     */
    protected $errorHandler;

    /**
     * An array of helpers to be loaded automatically upon
     * class instantiation. These helpers will be available
     * to all other controllers that extend BaseController.
     *
     * @var list<string>
     */
    protected $helpers = ['form', 'url', 'text', 'html'];

    /**
     * Initialize the controller.
     *
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @param LoggerInterface   $logger
     *
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger): void
    {
        // Do Not Edit This Line
        parent::initController($request, $response, $logger);

        // Store objects for later use
        $this->response = $response;
        $this->logger = $logger;

        // Initialize session
        $this->session = service('session');

        // Initialize error handler
        $this->errorHandler = new ErrorHandler();

        // Load helpers
        helper($this->helpers);
    }

    /**
     * Return a JSON response with the specified data.
     *
     * @param mixed $data    The data to be converted to JSON
     * @param int   $status  The HTTP status code
     *
     * @return ResponseInterface
     */
    protected function respondWithJson(mixed $data, int $status = 200): \CodeIgniter\HTTP\ResponseInterface
    {
        // Set cache headers for JSON responses
        // - max-age: Client should cache for 60 seconds
        // - stale-while-revalidate: Client can use stale response for up to 30 seconds while revalidating
        // - private: Response is intended for a single user and shouldn't be stored in shared caches
        return $this->response->setStatusCode($status)
                             ->setHeader('Cache-Control', 'private, max-age=60, stale-while-revalidate=30')
                             ->setHeader('Expires', gmdate('D, d M Y H:i:s', time() + 60) . ' GMT')
                             ->setJSON($data);
    }

    /**
     * Return an XML response with the specified data.
     *
     * @param string $xml     The XML string
     * @param int    $status  The HTTP status code
     *
     * @return ResponseInterface
     */
    protected function respondWithXml(string $xml, int $status = 200)
    {
        // Set cache headers for XML responses
        // - max-age: Client should cache for 60 seconds
        // - stale-while-revalidate: Client can use stale response for up to 30 seconds while revalidating
        // - private: Response is intended for a single user and shouldn't be stored in shared caches
        return $this->response->setStatusCode($status)
                             ->setHeader('Content-Type', 'text/xml')
                             ->setHeader('Cache-Control', 'private, max-age=60, stale-while-revalidate=30')
                             ->setHeader('Expires', gmdate('D, d M Y H:i:s', time() + 60) . ' GMT')
                             ->setBody($xml);
    }

    /**
     * Return a view response with appropriate caching headers.
     *
     * @param string $view       The view file to load
     * @param array  $data       The data to pass to the view
     * @param array  $options    View options
     * @param bool   $cacheable  Whether the view should be cacheable (default: false)
     * @param int    $cacheTime  Cache time in seconds (default: 300 - 5 minutes)
     *
     * @return string
     */
    protected function respondWithView(string $view, array $data = [], array $options = [], bool $cacheable = false, int $cacheTime = 300)
    {
        // If the view is cacheable, set appropriate cache headers
        if ($cacheable) {
            // For static content, we can use a longer cache time
            $this->response->setHeader('Cache-Control', 'public, max-age=' . $cacheTime . ', stale-while-revalidate=60')
                          ->setHeader('Expires', gmdate('D, d M Y H:i:s', time() + $cacheTime) . ' GMT');
        } else {
            // For dynamic content, we should prevent caching
            $this->response->setHeader('Cache-Control', 'no-store, max-age=0, must-revalidate')
                          ->setHeader('Expires', '0');
        }

        return view($view, $data, $options);
    }

    /**
     * Log a message with the specified level.
     *
     * @param string $level   The log level (debug, info, notice, warning, error, critical, alert, emergency)
     * @param string $message The message to log
     * @param array  $context Additional context data
     *
     * @return void
     */
    protected function logMessage(string $level, string $message, array $context = [])
    {
        log_message($level, $message, $context);
    }

    /**
     * Check if the user is logged in.
     *
     * @return bool
     */
    protected function isLoggedIn(): bool
    {
        return (bool) $this->session->get('logged_in');
    }

    /**
     * Get the current user's ID.
     *
     * @return int|null
     */
    protected function getCurrentUserId(): ?int
    {
        return $this->session->get('user_id');
    }

    /**
     * Get the current user's username.
     *
     * @return string|null
     */
    protected function getCurrentUsername(): ?string
    {
        return $this->session->get('username');
    }

    /**
     * Sanitize input data.
     *
     * @param array $data The data to sanitize
     *
     * @return array
     */
    protected function sanitizeInput(array $data): array
    {
        $sanitized = [];
        foreach ($data as $key => $value) {
            $sanitized[$key] = is_string($value) ? esc($value) : $value;
        }
        return $sanitized;
    }

    /**
     * Handle a validation error.
     *
     * @param array  $errors     The validation errors
     * @param string $message    The error message
     * @param int    $statusCode The HTTP status code
     *
     * @return mixed
     */
    protected function handleValidationError(array $errors, string $message = 'Validation failed', int $statusCode = 400)
    {
        return $this->errorHandler->handleError(
            ErrorHandler::ERROR_TYPE_VALIDATION,
            $message,
            $errors,
            $statusCode,
            ErrorHandler::LOG_LEVEL_WARNING
        );
    }

    /**
     * Handle a database error.
     *
     * @param string $message    The error message
     * @param array  $errors     Additional error details
     * @param int    $statusCode The HTTP status code
     *
     * @return mixed
     */
    protected function handleDatabaseError(string $message, array $errors = [], int $statusCode = 500)
    {
        return $this->errorHandler->handleError(
            ErrorHandler::ERROR_TYPE_DATABASE,
            $message,
            $errors,
            $statusCode,
            ErrorHandler::LOG_LEVEL_ERROR
        );
    }

    /**
     * Handle an authentication error.
     *
     * @param string $message    The error message
     * @param array  $errors     Additional error details
     * @param int    $statusCode The HTTP status code
     *
     * @return mixed
     */
    protected function handleAuthenticationError(string $message, array $errors = [], int $statusCode = 401)
    {
        return $this->errorHandler->handleError(
            ErrorHandler::ERROR_TYPE_AUTHENTICATION,
            $message,
            $errors,
            $statusCode,
            ErrorHandler::LOG_LEVEL_WARNING
        );
    }

    /**
     * Handle an authorization error.
     *
     * @param string $message    The error message
     * @param array  $errors     Additional error details
     * @param int    $statusCode The HTTP status code
     *
     * @return mixed
     */
    protected function handleAuthorizationError(string $message, array $errors = [], int $statusCode = 403)
    {
        return $this->errorHandler->handleError(
            ErrorHandler::ERROR_TYPE_AUTHORIZATION,
            $message,
            $errors,
            $statusCode,
            ErrorHandler::LOG_LEVEL_WARNING
        );
    }

    /**
     * Handle a not found error.
     *
     * @param string $message    The error message
     * @param array  $errors     Additional error details
     * @param int    $statusCode The HTTP status code
     *
     * @return mixed
     */
    protected function handleNotFoundError(string $message, array $errors = [], int $statusCode = 404)
    {
        return $this->errorHandler->handleError(
            ErrorHandler::ERROR_TYPE_NOT_FOUND,
            $message,
            $errors,
            $statusCode,
            ErrorHandler::LOG_LEVEL_INFO
        );
    }

    /**
     * Handle a server error.
     *
     * @param string $message    The error message
     * @param array  $errors     Additional error details
     * @param int    $statusCode The HTTP status code
     *
     * @return mixed
     */
    protected function handleServerError(string $message, array $errors = [], int $statusCode = 500)
    {
        return $this->errorHandler->handleError(
            ErrorHandler::ERROR_TYPE_SERVER,
            $message,
            $errors,
            $statusCode,
            ErrorHandler::LOG_LEVEL_ERROR
        );
    }

    /**
     * Handle an exception.
     *
     * @param Throwable $exception  The exception to handle
     * @param string    $type       The type of error
     * @param int       $statusCode The HTTP status code
     *
     * @return mixed
     */
    protected function handleException(
        Throwable $exception, 
        string $type = ErrorHandler::ERROR_TYPE_SERVER, 
        int $statusCode = 500
    ) {
        return $this->errorHandler->handleException($exception, $type, $statusCode);
    }
}
