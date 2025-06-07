<?php

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\I18n\Time;

class Auth extends BaseController
{
    protected $userModel;

    /**
     * Constructor
     * - loads the model
     */
    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->userModel = new UserModel();
    }

    /**
     * Display the registration form
     */
    public function register()
    {
        return view('auth/register');
    }

    /**
     * Process the registration form
     */
    public function processRegistration()
    {
        // Get data for validation
        $data = [
            'username' => $this->request->getPost('username'),
            'email' => $this->request->getPost('email'),
            'password' => $this->request->getPost('password'),
            'password_confirm' => $this->request->getPost('password_confirm')
        ];

        // Validate registration data using UserHelper
        $validation = \App\Helpers\UserHelper::validateRegistration($data);

        if ($validation !== true) {
            // Return to the registration form with errors
            return redirect()->back()->withInput()->with('errors', $validation);
        }

        // Get sanitized inputs
        $username = esc($data['username']);
        $email = esc($data['email']);
        $password = $data['password'];

        // Create the user
        $userId = $this->userModel->createUser($username, $email, $password);

        if (!$userId) {
            return redirect()->back()->withInput()->with('error', 'Failed to create user account. Please try again.');
        }

        // Set success message and redirect to login
        return redirect()->to('/auth/login')->with('success', 'Registration successful! You can now log in.');
    }

    /**
     * Display the login form
     */
    public function login()
    {
        return view('auth/login');
    }

    /**
     * Process the login form
     */
    public function processLogin()
    {
        // Get data for validation
        $data = [
            'username' => $this->request->getPost('username'),
            'password' => $this->request->getPost('password')
        ];

        // Validate login data using UserHelper
        $validation = \App\Helpers\UserHelper::validateLogin($data);

        if ($validation !== true) {
            // Return to the login form with errors
            return redirect()->back()->withInput()->with('errors', $validation);
        }

        // Get inputs
        $username = $data['username'];
        $password = $data['password'];

        // Verify credentials
        $user = $this->userModel->verifyCredentials($username, $password);

        if (!$user) {
            return redirect()->back()->withInput()->with('error', 'Invalid username or password');
        }

        // Set user session using UserHelper
        \App\Helpers\UserHelper::setUserSession($user);

        // Redirect to chat
        return redirect()->to('/chat');
    }

    /**
     * Log the user out
     */
    public function logout()
    {
        // Clear user session using UserHelper
        \App\Helpers\UserHelper::clearUserSession();

        // Redirect to login page
        return redirect()->to('/auth/login')->with('success', 'You have been logged out successfully.');
    }
}
