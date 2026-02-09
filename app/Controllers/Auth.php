<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Helpers\UserHelper;
use App\Helpers\WebSocketTokenHelper;
use CodeIgniter\I18n\Time;

/**
 * Auth Controller
 *
 * Handles user authentication including registration, login, and logout.
 *
 * ============================================================================
 * DEPENDENCY INJECTION PATTERN (for beginners)
 * ============================================================================
 *
 * This controller uses Dependency Injection (DI) to receive its UserModel.
 * See the Chat controller for a detailed explanation of the DI pattern.
 *
 * Key points:
 * - The UserModel is passed via the constructor, not created internally
 * - This makes the controller easier to test with mock objects
 * - The service container provides instances during normal HTTP requests
 * - Tests can inject mock UserModels to avoid database calls
 *
 * ============================================================================
 */
class Auth extends BaseController
{
    /**
     * User model instance for authentication operations.
     *
     * This property holds the UserModel that handles user data and
     * authentication. It's injected via the constructor for testability.
     *
     * @var UserModel
     */
    protected UserModel $userModel;

    /**
     * Constructor - receives dependencies via injection.
     *
     * Implements the Dependency Injection pattern for better testability
     * and loose coupling. The UserModel is received as a parameter rather
     * than being created internally with `new UserModel()`.
     *
     * During normal HTTP requests, if no model is provided, the controller
     * automatically fetches one from the service container via service('userModel').
     *
     * Example usage in tests:
     *   $mockUserModel = $this->createMock(UserModel::class);
     *   $mockUserModel->method('verifyCredentials')->willReturn(['id' => 1, 'username' => 'test']);
     *   $controller = new Auth($mockUserModel);
     *
     * @param UserModel|null $userModel The user model instance. If null, the service
     *                                  container will provide one. Pass a mock here for testing.
     */
    public function __construct(?UserModel $userModel = null)
    {
        // Fetch from service container if not injected (normal HTTP requests)
        // See Config\Services::userModel() for the service definition
        $this->userModel = $userModel ?? service('userModel');
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

            // ================================================================
            // WEBSOCKET TOKEN GENERATION
            // ================================================================
            // Generate a WebSocket authentication token for this user.
            // This token will be used by the Vue.js frontend to authenticate
            // WebSocket connections. The WebSocket server cannot access PHP
            // sessions, so we use this token-based approach instead.
            //
            // The token is stored in the session and passed to JavaScript,
            // which includes it in the WebSocket connection URL.
            // ================================================================
            $websocketToken = WebSocketTokenHelper::generateToken($user['id']);
            session()->set('websocket_token', $websocketToken);

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

            // ================================================================
            // WEBSOCKET TOKEN REVOCATION
            // ================================================================
            // Before clearing the session, revoke the WebSocket token.
            // This ensures that any existing WebSocket connections using this
            // token will fail to reconnect, and the token cannot be reused.
            //
            // Security Note: Revoking tokens on logout is important to prevent
            // session hijacking attacks where someone might capture the token
            // and try to use it after the user has logged out.
            // ================================================================
            $websocketToken = session()->get('websocket_token');
            if ($websocketToken) {
                WebSocketTokenHelper::revokeToken($websocketToken);
            }

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
