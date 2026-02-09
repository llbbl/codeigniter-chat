<?php

namespace Tests\Unit;

use App\Filters\AuthFilter;
use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use Config\App;

/**
 * AuthFilter Unit Tests
 *
 * This test class verifies that the AuthFilter correctly protects routes
 * by checking if users are logged in. It tests both scenarios:
 * - When a user IS logged in (should allow access)
 * - When a user is NOT logged in (should redirect to login page)
 *
 * These tests are important for security - they ensure that protected
 * routes cannot be accessed by unauthenticated users.
 *
 * @internal
 */
final class AuthFilterTest extends CIUnitTestCase
{
    /**
     * The filter instance we're testing
     */
    private AuthFilter $filter;

    /**
     * A mock request object for testing
     */
    private IncomingRequest $request;

    /**
     * Set up the test environment before each test
     *
     * This method runs before every test method. We create fresh instances
     * of the filter and request objects to ensure each test starts with
     * a clean state (isolation between tests).
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create a new AuthFilter instance to test
        $this->filter = new AuthFilter();

        // Create a mock request object
        // The filter needs a request object but doesn't actually use it
        // for anything other than passing it through
        $this->request = new IncomingRequest(
            new App(),
            new URI('http://example.com/chat'),
            null,
            new UserAgent()
        );
    }

    /**
     * Clean up after each test
     *
     * Reset any session data to prevent test pollution.
     * This ensures one test's session state doesn't affect another test.
     */
    protected function tearDown(): void
    {
        // Clear session data
        $_SESSION = [];

        parent::tearDown();
    }

    // =========================================================================
    // POSITIVE TEST CASES (User IS logged in - should allow access)
    // =========================================================================

    /**
     * Test: Logged-in users should be allowed through the filter
     *
     * When a user has 'logged_in' set to true in their session,
     * the filter should return null (which means "allow the request to proceed").
     *
     * This verifies authenticated users can access protected routes.
     */
    public function testLoggedInUserIsAllowedThrough(): void
    {
        // Arrange: Set up a logged-in user session
        $session = Services::session();
        $session->set('logged_in', true);
        $session->set('username', 'testuser');

        // Act: Run the filter's before() method
        $result = $this->filter->before($this->request);

        // Assert: Filter should return null (allow request to proceed)
        // When a filter returns null, CodeIgniter continues processing the request
        $this->assertNull(
            $result,
            'Logged-in users should pass through the filter (return null)'
        );
    }

    /**
     * Test: Users with additional session data should still be allowed
     *
     * This test ensures the filter only checks the 'logged_in' flag
     * and doesn't interfere with other session data that might be present.
     */
    public function testLoggedInUserWithAdditionalSessionDataIsAllowed(): void
    {
        // Arrange: Set up a logged-in user with additional session data
        $session = Services::session();
        $session->set('logged_in', true);
        $session->set('user_id', 123);
        $session->set('username', 'testuser');
        $session->set('email', 'test@example.com');
        $session->set('role', 'admin');

        // Act: Run the filter
        $result = $this->filter->before($this->request);

        // Assert: Should still be allowed through
        $this->assertNull($result);
    }

    // =========================================================================
    // NEGATIVE TEST CASES (User is NOT logged in - should redirect)
    // =========================================================================

    /**
     * Test: Users without login session should be redirected
     *
     * When there's no 'logged_in' session variable at all,
     * the filter should redirect the user to the login page.
     *
     * This is the most common case for unauthenticated access attempts.
     */
    public function testUserWithoutSessionIsRedirectedToLogin(): void
    {
        // Arrange: Ensure no session data exists (fresh session)
        // We don't set any session variables

        // Act: Run the filter
        $result = $this->filter->before($this->request);

        // Assert: Should return a redirect response
        $this->assertInstanceOf(
            RedirectResponse::class,
            $result,
            'Users without a session should be redirected'
        );
    }

    /**
     * Test: Users with logged_in explicitly set to false should be redirected
     *
     * This tests the explicit case where logged_in is false (e.g., after logout).
     * The filter should redirect these users to the login page.
     */
    public function testUserWithLoggedInFalseIsRedirectedToLogin(): void
    {
        // Arrange: Set logged_in to explicitly false
        $session = Services::session();
        $session->set('logged_in', false);

        // Act: Run the filter
        $result = $this->filter->before($this->request);

        // Assert: Should return a redirect response
        $this->assertInstanceOf(
            RedirectResponse::class,
            $result,
            'Users with logged_in=false should be redirected'
        );
    }

    /**
     * Test: Users with logged_in set to null should be redirected
     *
     * Edge case: What if someone sets logged_in to null?
     * The filter should treat this as not logged in and redirect.
     */
    public function testUserWithLoggedInNullIsRedirectedToLogin(): void
    {
        // Arrange: Set logged_in to null
        $session = Services::session();
        $session->set('logged_in', null);

        // Act: Run the filter
        $result = $this->filter->before($this->request);

        // Assert: Should return a redirect response
        $this->assertInstanceOf(
            RedirectResponse::class,
            $result,
            'Users with logged_in=null should be redirected'
        );
    }

    /**
     * Test: Users with other session data but no logged_in should be redirected
     *
     * Edge case: A user might have some session data (e.g., from a form)
     * but still not be logged in. The filter should check specifically
     * for the 'logged_in' flag.
     */
    public function testUserWithOtherSessionDataButNotLoggedInIsRedirected(): void
    {
        // Arrange: Set some session data but NOT logged_in
        $session = Services::session();
        $session->set('some_other_data', 'value');
        $session->set('temp_form_data', ['field' => 'value']);
        // Note: We intentionally do NOT set 'logged_in'

        // Act: Run the filter
        $result = $this->filter->before($this->request);

        // Assert: Should still be redirected
        $this->assertInstanceOf(
            RedirectResponse::class,
            $result,
            'Users without logged_in flag should be redirected even with other session data'
        );
    }

    // =========================================================================
    // EDGE CASES AND TYPE COERCION TESTS
    // =========================================================================

    /**
     * Test: logged_in set to an empty string should redirect
     *
     * PHP's type coercion means empty string evaluates to false.
     * The filter should treat this as not logged in.
     */
    public function testUserWithLoggedInEmptyStringIsRedirected(): void
    {
        // Arrange: Set logged_in to empty string
        $session = Services::session();
        $session->set('logged_in', '');

        // Act: Run the filter
        $result = $this->filter->before($this->request);

        // Assert: Should be redirected (empty string is falsy)
        $this->assertInstanceOf(
            RedirectResponse::class,
            $result,
            'Empty string for logged_in should be treated as not logged in'
        );
    }

    /**
     * Test: logged_in set to integer 0 should redirect
     *
     * PHP treats 0 as falsy, so users with logged_in=0 should be redirected.
     */
    public function testUserWithLoggedInZeroIsRedirected(): void
    {
        // Arrange: Set logged_in to 0
        $session = Services::session();
        $session->set('logged_in', 0);

        // Act: Run the filter
        $result = $this->filter->before($this->request);

        // Assert: Should be redirected (0 is falsy)
        $this->assertInstanceOf(
            RedirectResponse::class,
            $result,
            'Integer 0 for logged_in should be treated as not logged in'
        );
    }

    /**
     * Test: logged_in set to integer 1 should be allowed (truthy)
     *
     * PHP treats 1 as truthy, so users with logged_in=1 should be allowed.
     * This tests that integer 1 works the same as boolean true.
     */
    public function testUserWithLoggedInOneIsAllowed(): void
    {
        // Arrange: Set logged_in to 1
        $session = Services::session();
        $session->set('logged_in', 1);

        // Act: Run the filter
        $result = $this->filter->before($this->request);

        // Assert: Should be allowed (1 is truthy)
        $this->assertNull(
            $result,
            'Integer 1 for logged_in should be treated as logged in'
        );
    }

    /**
     * Test: logged_in set to string "true" should be allowed (truthy)
     *
     * Non-empty strings are truthy in PHP, so "true" string should work.
     * Note: This is a bit permissive and the application should ideally
     * use consistent boolean values.
     */
    public function testUserWithLoggedInStringTrueIsAllowed(): void
    {
        // Arrange: Set logged_in to string "true"
        $session = Services::session();
        $session->set('logged_in', 'true');

        // Act: Run the filter
        $result = $this->filter->before($this->request);

        // Assert: Should be allowed (non-empty string is truthy)
        $this->assertNull(
            $result,
            'String "true" for logged_in should be treated as logged in'
        );
    }

    // =========================================================================
    // AFTER FILTER TESTS
    // =========================================================================

    /**
     * Test: The after() method should do nothing
     *
     * The AuthFilter only needs to run BEFORE the controller executes.
     * The after() method exists because of the FilterInterface contract,
     * but it should not modify anything.
     */
    public function testAfterMethodDoesNothing(): void
    {
        // Arrange: Create a mock response
        $response = Services::response();

        // Act: Call the after method
        // It should return void (null) and not modify anything
        $result = $this->filter->after($this->request, $response);

        // Assert: Should return nothing (void method)
        $this->assertNull($result);
    }
}
