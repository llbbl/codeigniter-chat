<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Helpers\UserHelper;
use CodeIgniter\I18n\Time;
use OpenApi\Attributes as OA;

/**
 * Auth Controller
 * 
 * Handles user authentication including registration, login, and logout
 */
#[OA\Tag(name: "Authentication", description: "User authentication and authorization")]
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

    // API Methods

    /**
     * API Login endpoint
     */
    #[OA\Post(
        path: "/auth/login",
        summary: "User login",
        description: "Authenticate a user and return a JWT token",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "password"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "user@example.com"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "password123")
                ]
            )
        ),
        tags: ["Authentication"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Login successful",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "token", type: "string", example: "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."),
                        new OA\Property(property: "user", ref: "#/components/schemas/User")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 422, description: "Validation Error")
        ]
    )]
    public function apiLogin()
    {
        $json = $this->request->getJSON();
        
        if (!$json) {
            return $this->failValidationErrors('Invalid JSON input');
        }

        $email = $json->email ?? '';
        $password = $json->password ?? '';

        if (empty($email) || empty($password)) {
            return $this->failValidationErrors('Email and password are required');
        }

        try {
            $user = $this->userModel->where('email', $email)->first();
            
            if (!$user || !password_verify($password, $user['password'])) {
                return $this->failUnauthorized('Invalid credentials');
            }

            // Generate JWT token (simplified - in production use proper JWT library)
            $payload = base64_encode(json_encode([
                'user_id' => $user['id'],
                'email' => $user['email'],
                'exp' => time() + 86400 // 24 hours
            ]));

            return $this->respond([
                'success' => true,
                'token' => 'Bearer.' . $payload,
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['username'],
                    'email' => $user['email']
                ]
            ]);
        } catch (\Exception $e) {
            return $this->failServerError('Authentication failed');
        }
    }

    /**
     * API Register endpoint
     */
    #[OA\Post(
        path: "/auth/register",
        summary: "User registration",
        description: "Register a new user account",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "email", "password"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "John Doe"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "user@example.com"),
                    new OA\Property(property: "password", type: "string", format: "password", minLength: 8, example: "password123")
                ]
            )
        ),
        tags: ["Authentication"],
        responses: [
            new OA\Response(
                response: 201,
                description: "Registration successful",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "User registered successfully"),
                        new OA\Property(property: "user", ref: "#/components/schemas/User")
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Validation Error")
        ]
    )]
    public function apiRegister()
    {
        $json = $this->request->getJSON();
        
        if (!$json) {
            return $this->failValidationErrors('Invalid JSON input');
        }

        $name = $json->name ?? '';
        $email = $json->email ?? '';
        $password = $json->password ?? '';

        if (empty($name) || empty($email) || empty($password)) {
            return $this->failValidationErrors('Name, email and password are required');
        }

        if (strlen($password) < 8) {
            return $this->failValidationErrors('Password must be at least 8 characters');
        }

        try {
            // Check if user already exists
            $existingUser = $this->userModel->where('email', $email)->first();
            if ($existingUser) {
                return $this->failValidationErrors('Email already registered');
            }

            // Create new user
            $userData = [
                'username' => $name,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $userId = $this->userModel->insert($userData);

            if (!$userId) {
                return $this->failServerError('Failed to create user');
            }

            $user = $this->userModel->find($userId);

            return $this->respondCreated([
                'success' => true,
                'message' => 'User registered successfully',
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['username'],
                    'email' => $user['email']
                ]
            ]);
        } catch (\Exception $e) {
            return $this->failServerError('Registration failed');
        }
    }
}
