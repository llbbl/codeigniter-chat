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
        // If user is already logged in, redirect to chat
        if ($this->isLoggedIn()) {
            return redirect()->to('/chat');
        }

        // Otherwise, redirect to login page
        return redirect()->to('/auth/login');
    }
}
