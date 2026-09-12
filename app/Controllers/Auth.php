<?php

namespace App\Controllers;

use App\Contracts\AuditLogger;
use App\Contracts\UserRepository;
use App\Helpers\UserHelper;
use App\Helpers\WebSocketTokenHelper;

/**
 * Auth Controller
 *
 * Handles user authentication including registration, login, and logout.
 *
 * ============================================================================
 * DEPENDENCY INJECTION PATTERN (for beginners)
 * ============================================================================
 *
 * This controller uses dependency injection to receive repository and audit
 * logger contracts.
 *
 * Key points:
 * - Dependencies are required and explicit.
 * - The controller never reaches into the service container.
 * - Config\Services selects concrete implementations for HTTP requests.
 * - Tests can inject small interface stubs directly.
 *
 * ============================================================================
 */
class Auth extends BaseController
{
    /**
     * User repository for authentication operations.
     *
     * @var UserRepository
     */
    protected UserRepository $userRepository;

    private readonly AuditLogger $auditLogger;

    /**
     * Constructor - receives dependencies via injection.
     *
     * Config\Services is the composition root. Both dependencies are required,
     * which keeps the controller's runtime needs visible and testable.
     */
    public function __construct(UserRepository $userRepository, AuditLogger $auditLogger)
    {
        $this->userRepository = $userRepository;
        $this->auditLogger = $auditLogger;
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
            // The route's validate:registration filter has already validated this input.
            $data = [
                'username' => $this->request->getPost('username'),
                'email' => $this->request->getPost('email'),
                'password' => $this->request->getPost('password'),
                'password_confirm' => $this->request->getPost('password_confirm'),
            ];

            // Get sanitized inputs
            $sanitized = $this->sanitizeInput($data);
            $username = $sanitized['username'];
            $email = $sanitized['email'];
            $password = $data['password']; // Don't sanitize password as it will be hashed

            // Create the user
            try {
                $userId = $this->userRepository->createUser($username, $email, $password);

                if (!$userId) {
                    return $this->handleDatabaseError('Failed to create user account', [
                        'username' => $username,
                    ]);
                }
            } catch (\Exception $e) {
                return $this->handleDatabaseError('Error creating user account', [
                    'error' => $e->getMessage(),
                    'username' => $username,
                ]);
            }

            $this->auditLogger->record('auth.register', (int) $userId, ['username_attempted' => $username]);
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
            // The route's validate:login filter has already validated this input.
            $data = [
                'username' => $this->request->getPost('username'),
                'password' => $this->request->getPost('password'),
            ];

            // Get inputs - username should be sanitized but password should not
            $username = $this->sanitizeInput(['username' => $data['username']])['username'];
            $password = $data['password'];

            // Verify credentials
            try {
                $user = $this->userRepository->verifyCredentials($username, $password);

                if (!$user) {
                    $this->auditLogger->record('auth.login.failure', null, ['username_attempted' => $username, 'reason' => 'invalid_credentials']);
                    $this->logMessage('warning', 'Failed login attempt for username: ' . $username);
                    return $this->handleAuthenticationError('Invalid username or password');
                }
            } catch (\Exception $e) {
                return $this->handleDatabaseError('Error verifying credentials', [
                    'error' => $e->getMessage(),
                    'username' => $username,
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

            $this->auditLogger->record('auth.login.success', (int) $user['id'], ['username_attempted' => $username]);
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
            $userId = $this->getCurrentUserId();

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

            if ($username) {
                $this->auditLogger->record('auth.logout', $userId, ['username_attempted' => $username]);
                $this->logMessage('info', 'User logged out: ' . $username);
            }

            // Clear user session using UserHelper
            UserHelper::clearUserSession();

            // Redirect to login page
            return redirect()->to('/auth/login')->with('success', 'You have been logged out successfully.');
        } catch (\Throwable $e) {
            // Catch any unexpected exceptions
            return $this->handleException($e);
        }
    }
}
