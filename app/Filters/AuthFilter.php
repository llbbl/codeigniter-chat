<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    /**
     * Check if user is logged in, redirect to login page if not
     *
     * @param RequestInterface $request
     * @param array|null $arguments
     * @return mixed
     */
    public function before(RequestInterface $request, ?array $arguments = null): mixed
    {
        // If user is not logged in, redirect to login page
        if (!session()->get('logged_in')) {
            return redirect()->to('/auth/login')->with('error', 'Please log in to access this page.');
        }
    }

    /**
     * We don't have anything to do after the controller.
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param array|null $arguments
     * @return mixed
     */
    public function after(RequestInterface $request, ResponseInterface $response, ?array $arguments = null): void
    {
        // Do nothing
    }
}