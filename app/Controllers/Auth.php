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
    protected UserModel $userModel;

    /**
     * Constructor - loads the model
     * 
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @param LoggerInterface   $logger
     * 
     * @return void
     */
    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);
        $this->userModel = new UserModel();
    }

    /**
     * Display the registration form
     * 
     * @return string
     */
    public function register(): string
    {
        return $this->respondWithView('auth/register');
    }

    /**
     * Process the registration form
     * 
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function processRegistration(): \CodeIgniter\HTTP\RedirectResponse
    {
        try {
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
                // Use the error handler for validation errors
                return $this->handleValidationError($validation, 'Registration validation failed');
            }

            // Get sanitized inputs
            $sanitized = $this->sanitizeInput($data);
            $username = $sanitized['username'];
            $email = $sanitized['email'];
            $password = $data['password']; // Don't sanitize password as it will be hashed

            // Create the user
            try {
                $userId = $this->userModel->createUser($username, $email, $password);

                if (!$userId) {
                    return $this->handleDatabaseError('Failed to create user account', [
                        'username' => $username
                    ]);
                }
            } catch (\Exception $e) {
                return $this->handleDatabaseError('Error creating user account', [
                    'error' => $e->getMessage(),
                    'username' => $username
                ]);
            }

            $this->logMessage('info', 'New user registered: ' . $username);

            // Set success message and redirect to login
            return redirect()->to('/auth/login')->with('success', 'Registration successful! You can now log in.');
        } catch (\Throwable $e) {
            // Catch any unexpected exceptions
            return $this->handleException($e);
        }
    }

    /**
     * Display the login form
     * 
     * @return string
     */
    public function login(): string
    {
        return $this->respondWithView('auth/login');
    }

    /**
     * Process the login form
     * 
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function processLogin(): \CodeIgniter\HTTP\RedirectResponse
    {
        try {
            // Get data for validation
            $data = [
                'username' => $this->request->getPost('username'),
                'password' => $this->request->getPost('password')
            ];

            // Validate login data using UserHelper
            $validation = UserHelper::validateLogin($data);

            if ($validation !== true) {
                // Use the error handler for validation errors
                return $this->handleValidationError($validation, 'Login validation failed');
            }

            // Get inputs - username should be sanitized but password should not
            $username = $this->sanitizeInput(['username' => $data['username']])['username'];
            $password = $data['password'];

            // Verify credentials
            try {
                $user = $this->userModel->verifyCredentials($username, $password);

                if (!$user) {
                    $this->logMessage('warning', 'Failed login attempt for username: ' . $username);
                    return $this->handleAuthenticationError('Invalid username or password');
                }
            } catch (\Exception $e) {
                return $this->handleDatabaseError('Error verifying credentials', [
                    'error' => $e->getMessage(),
                    'username' => $username
                ]);
            }

            // Set user session using UserHelper
            UserHelper::setUserSession($user);

            $this->logMessage('info', 'User logged in: ' . $username);

            // Redirect to chat
            return redirect()->to('/chat');
        } catch (\Throwable $e) {
            // Catch any unexpected exceptions
            return $this->handleException($e);
        }
    }

    /**
     * Log the user out
     * 
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function logout(): \CodeIgniter\HTTP\RedirectResponse
    {
        try {
            $username = $this->getCurrentUsername();

            // Clear user session using UserHelper
            UserHelper::clearUserSession();

            if ($username) {
                $this->logMessage('info', 'User logged out: ' . $username);
            }

            // Redirect to login page
            return redirect()->to('/auth/login')->with('success', 'You have been logged out successfully.');
        } catch (\Throwable $e) {
            // Catch any unexpected exceptions
            return $this->handleException($e);
        }
    }
}
