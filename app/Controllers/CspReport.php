<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Controller for handling Content Security Policy (CSP) violation reports
 */
class CspReport extends Controller
{
    /**
     * Constructor.
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Do Not Edit This Line
        parent::initController($request, $response, $logger);
    }

    /**
     * Handle CSP violation reports
     *
     * @return ResponseInterface
     */
    public function index()
    {
        // Get the raw POST data
        $json = $this->request->getJSON(true);

        if (empty($json) || !isset($json['csp-report'])) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Invalid CSP report format']);
        }

        $report = $json['csp-report'];

        // Log the CSP violation
        log_message('warning', 'CSP Violation: ' . json_encode($report));

        // Return a 204 No Content response
        return $this->response->setStatusCode(204);
    }
}