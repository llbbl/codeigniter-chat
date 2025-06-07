<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

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
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Do Not Edit This Line
        parent::initController($request, $response, $logger);

        // Store objects for later use
        $this->response = $response;
        $this->logger = $logger;

        // Initialize session
        $this->session = service('session');

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
    protected function respondWithJson($data, int $status = 200)
    {
        return $this->response->setStatusCode($status)
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
        return $this->response->setStatusCode($status)
                             ->setHeader('Content-Type', 'text/xml')
                             ->setHeader('Cache-Control', 'no-cache')
                             ->setBody($xml);
    }

    /**
     * Return a view response.
     *
     * @param string $view    The view file to load
     * @param array  $data    The data to pass to the view
     * @param array  $options View options
     *
     * @return string
     */
    protected function respondWithView(string $view, array $data = [], array $options = [])
    {
        return view($view, $data, $options);
    }

    /**
     * Log a message with the specified level.
     *
     * @param string $level   The log level (debug, info, notice, warning, error, critical, alert, emergency)
     * @param string $message The message to log
     *
     * @return void
     */
    protected function logMessage(string $level, string $message)
    {
        log_message($level, $message);
    }

    /**
     * Check if the user is logged in.
     *
     * @return bool
     */
    protected function isLoggedIn()
    {
        return (bool) $this->session->get('logged_in');
    }

    /**
     * Get the current user's ID.
     *
     * @return int|null
     */
    protected function getCurrentUserId()
    {
        return $this->session->get('user_id');
    }

    /**
     * Get the current user's username.
     *
     * @return string|null
     */
    protected function getCurrentUsername()
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
    protected function sanitizeInput(array $data)
    {
        $sanitized = [];
        foreach ($data as $key => $value) {
            $sanitized[$key] = is_string($value) ? esc($value) : $value;
        }
        return $sanitized;
    }
}
