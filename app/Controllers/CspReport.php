<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Controller for handling Content Security Policy (CSP) violation reports
 */
class CspReport extends BaseController
{
    /**
     * Handle CSP violation reports
     *
     * @return ResponseInterface
     */
    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        // Get the raw POST data
        $json = $this->request->getJSON(true);

        if (empty($json) || !isset($json['csp-report'])) {
            return $this->respondWithJson(['error' => 'Invalid CSP report format'], 400);
        }

        $report = $json['csp-report'];

        // Log the CSP violation
        $this->logMessage('warning', 'CSP Violation: ' . json_encode($report));

        // Return a 204 No Content response
        return $this->response->setStatusCode(204);
    }
}
