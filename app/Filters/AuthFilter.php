<?php

namespace App\Filters;

use App\Libraries\ErrorHandler;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    /**
     * Check if user is logged in, redirect to login page if not
     *
     * @param RequestInterface $request
     * @param array|null       $arguments
     *
     * @return mixed
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // If user is not logged in, redirect to login page
        if (!session()->get('logged_in')) {
            if (
                $request->getHeaderLine(ApiFormatFilter::FORMAT_HEADER) === 'json'
                || str_contains($request->getHeaderLine('Accept'), 'application/json')
            ) {
                return service('errorHandler')
                    ->setContext($request, service('response'))
                    ->handleError(
                        ErrorHandler::ERROR_TYPE_AUTHENTICATION,
                        'Authentication required',
                        statusCode: 401,
                        logLevel: ErrorHandler::LOG_LEVEL_WARNING,
                    );
            }

            return redirect()->to('/auth/login')->with('error', 'Please log in to access this page.');
        }
    }

    /**
     * We don't have anything to do after the controller.
     *
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @param array|null        $arguments
     *
     * @return void
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do nothing
    }
}
