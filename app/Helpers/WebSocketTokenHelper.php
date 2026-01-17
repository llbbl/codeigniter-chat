<?php

namespace App\Helpers;

/**
 * WebSocket Token Helper
 *
 * This helper handles the generation and validation of tokens for WebSocket authentication.
 *
 * ============================================================================
 * HOW WEBSOCKET TOKEN AUTHENTICATION WORKS
 * ============================================================================
 *
 * The Problem:
 * ------------
 * WebSocket connections cannot use traditional HTTP session cookies for authentication
 * because the WebSocket server runs as a separate process from your web server.
 * The WebSocket server has no access to PHP sessions or cookies.
 *
 * The Solution:
 * -------------
 * We use a simple token-based authentication system:
 *
 * 1. When a user logs into the web application, we generate a unique token
 * 2. This token is stored in the user's PHP session (which the web app CAN access)
 * 3. The token is passed to the JavaScript frontend
 * 4. When connecting to WebSocket, the client includes the token as a URL parameter:
 *    ws://localhost:8080?token=abc123&user_id=1
 * 5. The WebSocket server validates this token by checking a shared storage mechanism
 *
 * Token Storage:
 * --------------
 * For this simple implementation, we store tokens in a JSON file.
 * In a production app, you would use:
 * - Redis (recommended for performance)
 * - Database table
 * - Memcached
 *
 * Security Notes:
 * ---------------
 * - Tokens expire after a configurable time (default: 24 hours)
 * - Each token is tied to a specific user ID
 * - Tokens are randomly generated using cryptographically secure functions
 * - In production, always use WSS (WebSocket Secure) over HTTPS
 *
 * ============================================================================
 */
class WebSocketTokenHelper
{
    /**
     * The file path where tokens are stored
     *
     * This is a simple file-based storage for learning purposes.
     * In production, use Redis or a database for better performance and reliability.
     *
     * @var string
     */
    private static string $tokenFile = WRITEPATH . 'websocket_tokens.json';

    /**
     * How long tokens remain valid (in seconds)
     *
     * Default: 86400 seconds = 24 hours
     *
     * @var int
     */
    private static int $tokenExpiry = 86400;

    /**
     * Generate a new WebSocket token for a user
     *
     * This method creates a cryptographically secure random token,
     * associates it with the user, and stores it for later validation.
     *
     * @param int $userId The ID of the user to generate a token for
     * @return string The generated token
     *
     * @example
     * // In your login controller after successful authentication:
     * $token = WebSocketTokenHelper::generateToken($user['id']);
     * session()->set('websocket_token', $token);
     */
    public static function generateToken(int $userId): string
    {
        // Generate a cryptographically secure random token
        // bin2hex converts binary data to hexadecimal representation
        // random_bytes generates cryptographically secure pseudo-random bytes
        $token = bin2hex(random_bytes(32)); // 64 character hex string

        // Load existing tokens from storage
        $tokens = self::loadTokens();

        // Remove any existing tokens for this user (one token per user)
        // This prevents token accumulation and ensures clean state
        foreach ($tokens as $existingToken => $data) {
            if ($data['user_id'] === $userId) {
                unset($tokens[$existingToken]);
            }
        }

        // Store the new token with metadata
        $tokens[$token] = [
            'user_id'    => $userId,           // Which user owns this token
            'created_at' => time(),            // When the token was created (Unix timestamp)
            'expires_at' => time() + self::$tokenExpiry  // When the token expires
        ];

        // Save tokens back to storage
        self::saveTokens($tokens);

        return $token;
    }

    /**
     * Validate a WebSocket token
     *
     * This method checks if a token is valid and returns the associated user ID.
     * A token is valid if:
     * 1. It exists in our storage
     * 2. It belongs to the claimed user
     * 3. It hasn't expired
     *
     * @param string $token The token to validate
     * @param int $userId The user ID claiming to own this token
     * @return bool True if the token is valid, false otherwise
     *
     * @example
     * // In your WebSocket server onOpen method:
     * $token = $queryParams['token'] ?? '';
     * $userId = (int)($queryParams['user_id'] ?? 0);
     * if (!WebSocketTokenHelper::validateToken($token, $userId)) {
     *     $conn->close(); // Reject the connection
     * }
     */
    public static function validateToken(string $token, int $userId): bool
    {
        // Don't process empty tokens
        if (empty($token) || $userId <= 0) {
            return false;
        }

        // Load tokens from storage
        $tokens = self::loadTokens();

        // Check if the token exists
        if (!isset($tokens[$token])) {
            return false;
        }

        $tokenData = $tokens[$token];

        // Check if the token belongs to the claimed user
        // This prevents someone from using another user's token
        if ($tokenData['user_id'] !== $userId) {
            return false;
        }

        // Check if the token has expired
        if (time() > $tokenData['expires_at']) {
            // Clean up expired token
            self::revokeToken($token);
            return false;
        }

        // Token is valid!
        return true;
    }

    /**
     * Revoke (delete) a specific token
     *
     * Call this when a user logs out to invalidate their WebSocket token.
     *
     * @param string $token The token to revoke
     * @return void
     *
     * @example
     * // In your logout controller:
     * $token = session()->get('websocket_token');
     * if ($token) {
     *     WebSocketTokenHelper::revokeToken($token);
     * }
     */
    public static function revokeToken(string $token): void
    {
        $tokens = self::loadTokens();

        if (isset($tokens[$token])) {
            unset($tokens[$token]);
            self::saveTokens($tokens);
        }
    }

    /**
     * Revoke all tokens for a specific user
     *
     * Useful when you want to force a user to re-authenticate,
     * or when changing passwords/security settings.
     *
     * @param int $userId The user ID whose tokens should be revoked
     * @return void
     */
    public static function revokeUserTokens(int $userId): void
    {
        $tokens = self::loadTokens();

        foreach ($tokens as $token => $data) {
            if ($data['user_id'] === $userId) {
                unset($tokens[$token]);
            }
        }

        self::saveTokens($tokens);
    }

    /**
     * Clean up expired tokens from storage
     *
     * This is a maintenance method that removes expired tokens.
     * You could call this periodically (e.g., via a cron job) to keep
     * the token storage clean.
     *
     * @return int The number of tokens removed
     */
    public static function cleanupExpiredTokens(): int
    {
        $tokens = self::loadTokens();
        $currentTime = time();
        $removedCount = 0;

        foreach ($tokens as $token => $data) {
            if ($currentTime > $data['expires_at']) {
                unset($tokens[$token]);
                $removedCount++;
            }
        }

        if ($removedCount > 0) {
            self::saveTokens($tokens);
        }

        return $removedCount;
    }

    /**
     * Get the user ID associated with a token (without full validation)
     *
     * This is useful when you need to know who a token belongs to
     * before doing full validation.
     *
     * @param string $token The token to look up
     * @return int|null The user ID, or null if token doesn't exist
     */
    public static function getUserIdFromToken(string $token): ?int
    {
        $tokens = self::loadTokens();

        if (isset($tokens[$token])) {
            return $tokens[$token]['user_id'];
        }

        return null;
    }

    /**
     * Load tokens from the storage file
     *
     * @return array Array of tokens with their metadata
     */
    private static function loadTokens(): array
    {
        // If the file doesn't exist yet, return an empty array
        if (!file_exists(self::$tokenFile)) {
            return [];
        }

        // Read and decode the JSON file
        $contents = file_get_contents(self::$tokenFile);
        $tokens = json_decode($contents, true);

        // Return empty array if JSON decode failed
        return is_array($tokens) ? $tokens : [];
    }

    /**
     * Save tokens to the storage file
     *
     * @param array $tokens The tokens array to save
     * @return void
     */
    private static function saveTokens(array $tokens): void
    {
        // Ensure the writable directory exists
        $directory = dirname(self::$tokenFile);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Save as formatted JSON for easier debugging
        // In production, you might use JSON_UNESCAPED_SLASHES for smaller file size
        file_put_contents(
            self::$tokenFile,
            json_encode($tokens, JSON_PRETTY_PRINT)
        );
    }
}
