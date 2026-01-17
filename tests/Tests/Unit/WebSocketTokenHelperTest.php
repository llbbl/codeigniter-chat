<?php

namespace Tests\Unit;

use App\Helpers\WebSocketTokenHelper;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * WebSocketTokenHelper Unit Tests
 *
 * These tests verify the token generation, validation, and revocation
 * functionality of the WebSocketTokenHelper class.
 */
class WebSocketTokenHelperTest extends CIUnitTestCase
{
    /**
     * Path to the token storage file
     *
     * @var string
     */
    private string $tokenFile;

    /**
     * Set up test environment
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Store the path to clean up after tests
        $this->tokenFile = WRITEPATH . 'websocket_tokens.json';

        // Clean up any existing tokens before each test
        if (file_exists($this->tokenFile)) {
            unlink($this->tokenFile);
        }
    }

    /**
     * Clean up after each test
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up token file after tests
        if (file_exists($this->tokenFile)) {
            unlink($this->tokenFile);
        }
    }

    /**
     * Test that generateToken creates a valid token string
     */
    public function testGenerateTokenCreatesValidToken(): void
    {
        $userId = 1;
        $token = WebSocketTokenHelper::generateToken($userId);

        // Token should be a 64-character hex string (32 bytes = 64 hex chars)
        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    /**
     * Test that generateToken creates unique tokens for different users
     */
    public function testGenerateTokenCreatesUniqueTokens(): void
    {
        $token1 = WebSocketTokenHelper::generateToken(1);
        $token2 = WebSocketTokenHelper::generateToken(2);

        $this->assertNotEquals($token1, $token2);
    }

    /**
     * Test that generateToken replaces old tokens for the same user
     */
    public function testGenerateTokenReplacesOldToken(): void
    {
        $userId = 1;

        $token1 = WebSocketTokenHelper::generateToken($userId);
        $token2 = WebSocketTokenHelper::generateToken($userId);

        // New token should be different
        $this->assertNotEquals($token1, $token2);

        // Old token should no longer be valid
        $this->assertFalse(WebSocketTokenHelper::validateToken($token1, $userId));

        // New token should be valid
        $this->assertTrue(WebSocketTokenHelper::validateToken($token2, $userId));
    }

    /**
     * Test that validateToken returns true for valid token and user_id
     */
    public function testValidateTokenWithValidCredentials(): void
    {
        $userId = 1;
        $token = WebSocketTokenHelper::generateToken($userId);

        $result = WebSocketTokenHelper::validateToken($token, $userId);

        $this->assertTrue($result);
    }

    /**
     * Test that validateToken returns false for wrong user_id
     */
    public function testValidateTokenWithWrongUserId(): void
    {
        $userId = 1;
        $wrongUserId = 2;
        $token = WebSocketTokenHelper::generateToken($userId);

        $result = WebSocketTokenHelper::validateToken($token, $wrongUserId);

        $this->assertFalse($result);
    }

    /**
     * Test that validateToken returns false for non-existent token
     */
    public function testValidateTokenWithNonExistentToken(): void
    {
        $result = WebSocketTokenHelper::validateToken('nonexistenttoken123', 1);

        $this->assertFalse($result);
    }

    /**
     * Test that validateToken returns false for empty token
     */
    public function testValidateTokenWithEmptyToken(): void
    {
        $result = WebSocketTokenHelper::validateToken('', 1);

        $this->assertFalse($result);
    }

    /**
     * Test that validateToken returns false for invalid user_id
     */
    public function testValidateTokenWithInvalidUserId(): void
    {
        $token = WebSocketTokenHelper::generateToken(1);

        $result = WebSocketTokenHelper::validateToken($token, 0);

        $this->assertFalse($result);

        $result = WebSocketTokenHelper::validateToken($token, -1);

        $this->assertFalse($result);
    }

    /**
     * Test that revokeToken invalidates a token
     */
    public function testRevokeToken(): void
    {
        $userId = 1;
        $token = WebSocketTokenHelper::generateToken($userId);

        // Token should be valid before revocation
        $this->assertTrue(WebSocketTokenHelper::validateToken($token, $userId));

        // Revoke the token
        WebSocketTokenHelper::revokeToken($token);

        // Token should be invalid after revocation
        $this->assertFalse(WebSocketTokenHelper::validateToken($token, $userId));
    }

    /**
     * Test that revoking a non-existent token doesn't cause errors
     */
    public function testRevokeNonExistentToken(): void
    {
        // Should not throw an exception
        WebSocketTokenHelper::revokeToken('nonexistenttoken123');

        // Test passes if no exception was thrown
        $this->assertTrue(true);
    }

    /**
     * Test that revokeUserTokens invalidates all tokens for a user
     */
    public function testRevokeUserTokens(): void
    {
        $userId = 1;
        $token = WebSocketTokenHelper::generateToken($userId);

        // Token should be valid before revocation
        $this->assertTrue(WebSocketTokenHelper::validateToken($token, $userId));

        // Revoke all tokens for the user
        WebSocketTokenHelper::revokeUserTokens($userId);

        // Token should be invalid after revocation
        $this->assertFalse(WebSocketTokenHelper::validateToken($token, $userId));
    }

    /**
     * Test that revokeUserTokens doesn't affect other users
     */
    public function testRevokeUserTokensDoesNotAffectOtherUsers(): void
    {
        $userId1 = 1;
        $userId2 = 2;

        $token1 = WebSocketTokenHelper::generateToken($userId1);
        $token2 = WebSocketTokenHelper::generateToken($userId2);

        // Revoke tokens for user 1 only
        WebSocketTokenHelper::revokeUserTokens($userId1);

        // User 1's token should be invalid
        $this->assertFalse(WebSocketTokenHelper::validateToken($token1, $userId1));

        // User 2's token should still be valid
        $this->assertTrue(WebSocketTokenHelper::validateToken($token2, $userId2));
    }

    /**
     * Test getUserIdFromToken returns correct user_id
     */
    public function testGetUserIdFromToken(): void
    {
        $userId = 42;
        $token = WebSocketTokenHelper::generateToken($userId);

        $result = WebSocketTokenHelper::getUserIdFromToken($token);

        $this->assertEquals($userId, $result);
    }

    /**
     * Test getUserIdFromToken returns null for non-existent token
     */
    public function testGetUserIdFromTokenWithNonExistentToken(): void
    {
        $result = WebSocketTokenHelper::getUserIdFromToken('nonexistenttoken123');

        $this->assertNull($result);
    }

    /**
     * Test cleanupExpiredTokens removes old tokens
     */
    public function testCleanupExpiredTokens(): void
    {
        // This test would require modifying the token expiry time
        // For now, we just verify the method can be called without errors
        $removed = WebSocketTokenHelper::cleanupExpiredTokens();

        $this->assertIsInt($removed);
        $this->assertGreaterThanOrEqual(0, $removed);
    }

    /**
     * Test that multiple tokens can be stored for different users
     */
    public function testMultipleUsersCanHaveTokens(): void
    {
        $token1 = WebSocketTokenHelper::generateToken(1);
        $token2 = WebSocketTokenHelper::generateToken(2);
        $token3 = WebSocketTokenHelper::generateToken(3);

        // All tokens should be valid for their respective users
        $this->assertTrue(WebSocketTokenHelper::validateToken($token1, 1));
        $this->assertTrue(WebSocketTokenHelper::validateToken($token2, 2));
        $this->assertTrue(WebSocketTokenHelper::validateToken($token3, 3));
    }
}
