<?php

namespace App\Controllers;

/**
 * Home controller
 * 
 * Handles the main landing page and redirects users based on login status
 */
class Home extends BaseController
{
    /**
     * Index method - redirects based on login status
     * 
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function index()
    {
        // If user is not logged in, redirect to login page
        if (!$this->isLoggedIn()) {
            return redirect()->to('/auth/login');
        }

        // Show the home page with links to different chat implementations
        return $this->respondWithView('home');
    }
}
