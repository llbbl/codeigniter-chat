<?php

namespace Tests\Unit;

use App\Filters\RateLimitFilter;
use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use Config\App;

/**
 * RateLimitFilter Unit Tests
 *
 * This test class verifies the rate limiting functionality that protects
 * the application from abuse (spam, DoS attacks, automated scraping).
 *
 * Rate limiting is a critical security feature that:
 * - Prevents spam in chat applications
 * - Protects against denial of service (DoS) attacks
 * - Reduces server load from aggressive bots
 * - Ensures fair resource usage among users
 *
 * The filter tracks requests per user/IP over a time window and blocks
 * requests when the limit is exceeded.
 *
 * Key concepts demonstrated:
 * - Testing time-based behavior
 * - Testing rate limit edge cases
 * - Simulating multiple requests
 * - Testing both guest (IP-based) and authenticated (user-based) limiting
 *
 * @internal
 */
final class RateLimitFilterTest extends CIUnitTestCase
{
    /**
     * The filter instance being tested
     */
    private RateLimitFilter $filter;

    /**
     * Mock request object for testing
     */
    private IncomingRequest $request;

    /**
     * Set up test fixtures before each test
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create a fresh filter instance
        $this->filter = new RateLimitFilter();

        // Create a mock request with a predictable IP address
        $this->request = new IncomingRequest(
            new App(),
            new URI('http://example.com/chat/update'),
            null,
            new UserAgent()
        );
    }

    /**
     * Clean up after each test
     *
     * Important: Clear session data to prevent test pollution.
     * Each test should start with a clean rate limit history.
     */
    protected function tearDown(): void
    {
        // Clear all session data including rate limit history
        $_SESSION = [];

        parent::tearDown();
    }

    // =========================================================================
    // BASIC FUNCTIONALITY TESTS
    // =========================================================================

    /**
     * Test: First request is always allowed
     *
     * A user's first request should never be rate limited.
     * This is the baseline case - if this fails, everything fails.
     */
    public function testFirstRequestIsAllowed(): void
    {
        // Arrange: Fresh session (no previous requests)
        // No setup needed - setUp() already creates clean state

        // Act: Make first request
        $result = $this->filter->before($this->request);

        // Assert: Should return null (allow request)
        // When a filter returns null, CodeIgniter continues processing
        $this->assertNull(
            $result,
            'First request should always be allowed through the filter'
        );
    }

    /**
     * Test: Requests under the limit are allowed
     *
     * Users should be able to make multiple requests as long as
     * they stay within the rate limit (default: 10 requests per 60 seconds).
     */
    public function testRequestsUnderLimitAreAllowed(): void
    {
        // Arrange: Nothing special needed

        // Act: Make 9 requests (under the limit of 10)
        for ($i = 1; $i <= 9; $i++) {
            $result = $this->filter->before($this->request);

            // Assert: Each request should be allowed
            $this->assertNull(
                $result,
                "Request {$i} of 9 should be allowed (under limit of 10)"
            );
        }
    }

    /**
     * Test: Request at exactly the limit is allowed
     *
     * The 10th request (when limit is 10) should still be allowed.
     * Only the 11th request should be blocked.
     */
    public function testRequestAtExactLimitIsAllowed(): void
    {
        // Arrange: Make 9 requests first
        for ($i = 1; $i <= 9; $i++) {
            $this->filter->before($this->request);
        }

        // Act: Make the 10th request (exactly at limit)
        $result = $this->filter->before($this->request);

        // Assert: Should be allowed (limit is inclusive)
        $this->assertNull(
            $result,
            '10th request should be allowed (at the limit, not over)'
        );
    }

    // =========================================================================
    // RATE LIMIT EXCEEDED TESTS
    // =========================================================================

    /**
     * Test: Requests exceeding the limit are blocked
     *
     * Once a user exceeds the rate limit, subsequent requests should
     * be blocked with a 429 Too Many Requests response.
     */
    public function testRequestsExceedingLimitAreBlocked(): void
    {
        // Arrange: Make 10 requests (reach the limit)
        for ($i = 1; $i <= 10; $i++) {
            $this->filter->before($this->request);
        }

        // Act: Make the 11th request (over the limit)
        $result = $this->filter->before($this->request);

        // Assert: Should return a 429 response
        $this->assertNotNull(
            $result,
            '11th request should be blocked (over limit)'
        );

        // Assert: Should be a 429 Too Many Requests status
        $this->assertEquals(
            429,
            $result->getStatusCode(),
            'Blocked requests should return HTTP 429 status'
        );
    }

    /**
     * Test: Multiple requests over limit are all blocked
     *
     * Once rate limited, ALL subsequent requests should be blocked
     * until the time window resets.
     */
    public function testMultipleRequestsOverLimitAreAllBlocked(): void
    {
        // Arrange: Exceed the limit
        for ($i = 1; $i <= 10; $i++) {
            $this->filter->before($this->request);
        }

        // Act & Assert: Try 5 more requests, all should be blocked
        for ($i = 1; $i <= 5; $i++) {
            $result = $this->filter->before($this->request);

            $this->assertNotNull(
                $result,
                "Request {$i} after exceeding limit should be blocked"
            );
            $this->assertEquals(
                429,
                $result->getStatusCode(),
                'All requests over limit should return 429'
            );
        }
    }

    /**
     * Test: Rate limit response includes helpful message
     *
     * The 429 response should include a message explaining the issue
     * so users know why their request was blocked.
     */
    public function testRateLimitResponseIncludesMessage(): void
    {
        // Arrange: Exceed the limit
        for ($i = 1; $i <= 10; $i++) {
            $this->filter->before($this->request);
        }

        // Act: Make request over limit
        $result = $this->filter->before($this->request);

        // Assert: Response body should explain the issue
        $body = $result->getBody();
        $this->assertStringContainsString(
            'Too many requests',
            $body,
            'Rate limit response should explain why request was blocked'
        );
    }

    // =========================================================================
    // USER IDENTIFICATION TESTS
    // =========================================================================

    /**
     * Test: Logged-in users are tracked by user ID
     *
     * Authenticated users should be rate limited by their user ID,
     * not by IP address. This is fairer because:
     * - Multiple users can share an IP (e.g., corporate network)
     * - A user changing IP shouldn't reset their rate limit
     */
    public function testLoggedInUsersAreTrackedByUserId(): void
    {
        // Arrange: Simulate a logged-in user
        $session = Services::session();
        $session->set('user_id', 123);

        // Act: Make requests
        for ($i = 1; $i <= 10; $i++) {
            $this->filter->before($this->request);
        }

        // Assert: 11th request should be blocked
        $result = $this->filter->before($this->request);
        $this->assertEquals(429, $result->getStatusCode());

        // Now verify the session key uses user_id
        $sessionData = session()->get('rate_limit_123');
        $this->assertNotNull(
            $sessionData,
            'Rate limit should be tracked under user_id key'
        );
    }

    /**
     * Test: Different users have separate rate limits
     *
     * User A's requests should not affect User B's rate limit.
     * Each user gets their own quota.
     */
    public function testDifferentUsersHaveSeparateRateLimits(): void
    {
        // Arrange: User 1 makes 9 requests
        $session = Services::session();
        $session->set('user_id', 1);

        for ($i = 1; $i <= 9; $i++) {
            $this->filter->before($this->request);
        }

        // Switch to User 2
        $session->set('user_id', 2);

        // Act: User 2 makes their first request
        $result = $this->filter->before($this->request);

        // Assert: User 2's first request should be allowed
        // (User 1's 9 requests don't affect User 2)
        $this->assertNull(
            $result,
            "User 2's first request should be allowed regardless of User 1's requests"
        );
    }

    // =========================================================================
    // AFTER FILTER TESTS
    // =========================================================================

    /**
     * Test: The after() method does nothing
     *
     * Rate limiting only needs to check BEFORE the controller runs.
     * The after() method exists due to the interface but should not
     * modify the response.
     */
    public function testAfterMethodDoesNothing(): void
    {
        // Arrange: Create a mock response
        $response = Services::response();

        // Act: Call the after method
        $result = $this->filter->after($this->request, $response);

        // Assert: Should return nothing (void method)
        $this->assertNull($result);
    }

    // =========================================================================
    // REQUEST HISTORY MANAGEMENT TESTS
    // =========================================================================

    /**
     * Test: Request history is stored in session
     *
     * The filter uses session storage to track request timestamps.
     * This test verifies that history is properly stored.
     */
    public function testRequestHistoryIsStoredInSession(): void
    {
        // Arrange: Nothing special

        // Act: Make a few requests
        for ($i = 1; $i <= 3; $i++) {
            $this->filter->before($this->request);
        }

        // Assert: Session should contain request history
        // The key format is 'rate_limit_' + identifier (IP for guests)
        $ipAddress = $this->request->getIPAddress();
        $history = session()->get('rate_limit_' . $ipAddress);

        $this->assertIsArray($history);
        $this->assertCount(
            3,
            $history,
            'Session should store history of all requests'
        );
    }

    /**
     * Test: History entries contain timestamps
     *
     * Each entry in the request history should be a Unix timestamp
     * used to determine which requests fall within the time window.
     */
    public function testHistoryEntriesAreTimestamps(): void
    {
        // Arrange & Act: Make a request
        $this->filter->before($this->request);

        // Get the history
        $ipAddress = $this->request->getIPAddress();
        $history = session()->get('rate_limit_' . $ipAddress);

        // Assert: Entry should be a timestamp close to current time
        $this->assertNotEmpty($history);
        $timestamp = $history[0];

        $this->assertIsInt($timestamp);
        // Timestamp should be within last few seconds
        $this->assertGreaterThan(
            time() - 10,
            $timestamp,
            'Timestamp should be recent'
        );
        $this->assertLessThanOrEqual(
            time(),
            $timestamp,
            'Timestamp should not be in the future'
        );
    }
}
