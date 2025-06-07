<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Helpers\UserHelper;
use CodeIgniter\I18n\Time;

/**
 * Auth Controller
 * 
 * Handles user authentication including registration, login, and logout
 */
class Auth extends BaseController
{
    /**
     * User model instance
     * 
     * @var UserModel
     */
    protected $userModel;

    /**
     * Constructor - loads the model
     * 
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @param LoggerInterface   $logger
     * 
     * @return void
     */
    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->userModel = new UserModel();
    }

    /**
     * Display the registration form
     * 
     * @return string
     */
    public function register()
    {
        return $this->respondWithView('auth/register');
    }

    /**
     * Process the registration form
     * 
     * @return \CodeIgniter\HTTP\RedirectResponse
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
        $validation = UserHelper::validateRegistration($data);

        if ($validation !== true) {
            // Return to the registration form with errors
            return redirect()->back()->withInput()->with('errors', $validation);
        }

        // Get sanitized inputs
        $sanitized = $this->sanitizeInput($data);
        $username = $sanitized['username'];
        $email = $sanitized['email'];
        $password = $data['password']; // Don't sanitize password as it will be hashed

        // Create the user
        $userId = $this->userModel->createUser($username, $email, $password);

        if (!$userId) {
            $this->logMessage('error', 'Failed to create user account for username: ' . $username);
            return redirect()->back()->withInput()->with('error', 'Failed to create user account. Please try again.');
        }

        $this->logMessage('info', 'New user registered: ' . $username);

        // Set success message and redirect to login
        return redirect()->to('/auth/login')->with('success', 'Registration successful! You can now log in.');
    }

    /**
     * Display the login form
     * 
     * @return string
     */
    public function login()
    {
        return $this->respondWithView('auth/login');
    }

    /**
     * Process the login form
     * 
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function processLogin()
    {
        // Get data for validation
        $data = [
            'username' => $this->request->getPost('username'),
            'password' => $this->request->getPost('password')
        ];

        // Validate login data using UserHelper
        $validation = UserHelper::validateLogin($data);

        if ($validation !== true) {
            // Return to the login form with errors
            return redirect()->back()->withInput()->with('errors', $validation);
        }

        // Get inputs - username should be sanitized but password should not
        $username = $this->sanitizeInput(['username' => $data['username']])['username'];
        $password = $data['password'];

        // Verify credentials
        $user = $this->userModel->verifyCredentials($username, $password);

        if (!$user) {
            $this->logMessage('warning', 'Failed login attempt for username: ' . $username);
            return redirect()->back()->withInput()->with('error', 'Invalid username or password');
        }

        // Set user session using UserHelper
        UserHelper::setUserSession($user);

        $this->logMessage('info', 'User logged in: ' . $username);

        // Redirect to chat
        return redirect()->to('/chat');
    }

    /**
     * Log the user out
     * 
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function logout()
    {
        $username = $this->getCurrentUsername();

        // Clear user session using UserHelper
        UserHelper::clearUserSession();

        if ($username) {
            $this->logMessage('info', 'User logged out: ' . $username);
        }

        // Redirect to login page
        return redirect()->to('/auth/login')->with('success', 'You have been logged out successfully.');
    }
}
