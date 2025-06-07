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
        // Validation rules
        $rules = [
            'username' => [
                'rules' => 'required|min_length[3]|max_length[50]|alpha_numeric|is_unique[users.username]',
                'errors' => [
                    'required' => 'Username is required',
                    'min_length' => 'Username must be at least 3 characters long',
                    'max_length' => 'Username cannot exceed 50 characters',
                    'alpha_numeric' => 'Username can only contain alphanumeric characters',
                    'is_unique' => 'Username is already taken'
                ]
            ],
            'email' => [
                'rules' => 'required|valid_email|is_unique[users.email]',
                'errors' => [
                    'required' => 'Email is required',
                    'valid_email' => 'Please enter a valid email address',
                    'is_unique' => 'Email is already registered'
                ]
            ],
            'password' => [
                'rules' => 'required|min_length[8]',
                'errors' => [
                    'required' => 'Password is required',
                    'min_length' => 'Password must be at least 8 characters long'
                ]
            ],
            'password_confirm' => [
                'rules' => 'required|matches[password]',
                'errors' => [
                    'required' => 'Password confirmation is required',
                    'matches' => 'Passwords do not match'
                ]
            ]
        ];

        // Run validation
        if (!$this->validate($rules)) {
            // Return to the registration form with errors
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        // Get sanitized inputs
        $username = esc($this->request->getPost('username'));
        $email = esc($this->request->getPost('email'));
        $password = $this->request->getPost('password');

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
        // Validation rules
        $rules = [
            'username' => [
                'rules' => 'required',
                'errors' => [
                    'required' => 'Username is required'
                ]
            ],
            'password' => [
                'rules' => 'required',
                'errors' => [
                    'required' => 'Password is required'
                ]
            ]
        ];

        // Run validation
        if (!$this->validate($rules)) {
            // Return to the login form with errors
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        // Get inputs
        $username = $this->request->getPost('username');
        $password = $this->request->getPost('password');

        // Verify credentials
        $user = $this->userModel->verifyCredentials($username, $password);

        if (!$user) {
            return redirect()->back()->withInput()->with('error', 'Invalid username or password');
        }

        // Set user session
        $userData = [
            'user_id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'logged_in' => true
        ];

        session()->set($userData);

        // Redirect to chat
        return redirect()->to('/chat');
    }

    /**
     * Log the user out
     */
    public function logout()
    {
        // Clear user session
        session()->remove(['user_id', 'username', 'email', 'logged_in']);

        // Redirect to login page
        return redirect()->to('/auth/login')->with('success', 'You have been logged out successfully.');
    }
}