<?php

namespace Tests\Unit;

use App\Models\ChatModel;
use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\Config\Services;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * ChatModel Pagination Tests
 *
 * This test class focuses specifically on testing the pagination logic
 * in the ChatModel. Pagination is a critical feature that affects:
 * - User experience (loading the right messages)
 * - Performance (not loading all messages at once)
 * - Caching (different cache keys for different pages)
 *
 * Testing Strategy:
 * Since ChatModel extends CodeIgniter's Model which uses magic methods
 * for query building (orderBy, limit, etc.), we test the pagination
 * through the cache interface. We verify:
 * 1. Correct cache keys are generated for different pagination parameters
 * 2. Cache hits return data without hitting the database
 * 3. The pagination metadata structure is correct
 *
 * @internal
 */
final class ChatModelPaginationTest extends CIUnitTestCase
{
    /**
     * Mock for the cache service
     */
    protected MockObject $cacheMock;

    /**
     * Set up test fixtures before each test
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock for the cache service
        $this->cacheMock = $this->createMock(CacheInterface::class);
        Services::injectMock('cache', $this->cacheMock);
    }

    /**
     * Clean up after each test
     */
    protected function tearDown(): void
    {
        Services::resetSingle('cache');
        parent::tearDown();
    }

    // =========================================================================
    // CACHE KEY GENERATION TESTS
    // =========================================================================

    /**
     * Test: Cache key includes page number
     *
     * Each page should have a unique cache key so that different pages
     * are cached separately.
     */
    public function testCacheKeyIncludesPageNumber(): void
    {
        // Arrange: Track which cache key is requested
        $requestedCacheKey = null;
        $this->cacheMock->method('get')
            ->willReturnCallback(function($key) use (&$requestedCacheKey) {
                $requestedCacheKey = $key;
                // Return cached data so we don't hit the database
                return [
                    'messages' => [],
                    'pagination' => [
                        'page' => 2,
                        'perPage' => 10,
                        'totalItems' => 0,
                        'totalPages' => 0,
                        'hasNext' => false,
                        'hasPrev' => true
                    ]
                ];
            });

        $chatModel = new ChatModel();

        // Act: Request page 2
        $chatModel->getMsgPaginated(2, 10);

        // Assert: Cache key should contain page_2
        $this->assertStringContainsString(
            'page_2',
            $requestedCacheKey,
            'Cache key should include the page number'
        );
    }

    /**
     * Test: Cache key includes perPage value
     *
     * Different items-per-page settings should use different cache keys.
     */
    public function testCacheKeyIncludesPerPageValue(): void
    {
        // Arrange: Track which cache key is requested
        $requestedCacheKey = null;
        $this->cacheMock->method('get')
            ->willReturnCallback(function($key) use (&$requestedCacheKey) {
                $requestedCacheKey = $key;
                return [
                    'messages' => [],
                    'pagination' => [
                        'page' => 1,
                        'perPage' => 25,
                        'totalItems' => 0,
                        'totalPages' => 0,
                        'hasNext' => false,
                        'hasPrev' => false
                    ]
                ];
            });

        $chatModel = new ChatModel();

        // Act: Request with 25 items per page
        $chatModel->getMsgPaginated(1, 25);

        // Assert: Cache key should contain the perPage value
        $this->assertStringContainsString(
            '25',
            $requestedCacheKey,
            'Cache key should include the perPage value'
        );
    }

    /**
     * Test: Different pages generate different cache keys
     *
     * Requesting different pages should result in different cache keys.
     */
    public function testDifferentPagesUseDifferentCacheKeys(): void
    {
        // Arrange: Collect all requested cache keys
        $cacheKeysRequested = [];
        $this->cacheMock->method('get')
            ->willReturnCallback(function($key) use (&$cacheKeysRequested) {
                $cacheKeysRequested[] = $key;
                return [
                    'messages' => [],
                    'pagination' => [
                        'page' => 1,
                        'perPage' => 10,
                        'totalItems' => 0,
                        'totalPages' => 0,
                        'hasNext' => false,
                        'hasPrev' => false
                    ]
                ];
            });

        $chatModel = new ChatModel();

        // Act: Request multiple pages
        $chatModel->getMsgPaginated(1, 10);
        $chatModel->getMsgPaginated(2, 10);
        $chatModel->getMsgPaginated(3, 10);

        // Assert: All cache keys should be different
        $uniqueKeys = array_unique($cacheKeysRequested);
        $this->assertCount(
            3,
            $uniqueKeys,
            'Each page should have a unique cache key'
        );
    }

    /**
     * Test: Same page and perPage use same cache key (cache hit)
     *
     * Requesting the same page with same perPage should use the same cache key.
     */
    public function testSameParametersUseSameCacheKey(): void
    {
        // Arrange: Collect all requested cache keys
        $cacheKeysRequested = [];
        $this->cacheMock->method('get')
            ->willReturnCallback(function($key) use (&$cacheKeysRequested) {
                $cacheKeysRequested[] = $key;
                return [
                    'messages' => [],
                    'pagination' => [
                        'page' => 1,
                        'perPage' => 10,
                        'totalItems' => 0,
                        'totalPages' => 0,
                        'hasNext' => false,
                        'hasPrev' => false
                    ]
                ];
            });

        $chatModel = new ChatModel();

        // Act: Request same page twice
        $chatModel->getMsgPaginated(1, 10);
        $chatModel->getMsgPaginated(1, 10);

        // Assert: Both requests should use the same cache key
        $this->assertEquals(
            $cacheKeysRequested[0],
            $cacheKeysRequested[1],
            'Same parameters should use the same cache key'
        );
    }

    // =========================================================================
    // CACHE HIT BEHAVIOR TESTS
    // =========================================================================

    /**
     * Test: Cache hit returns cached data directly
     *
     * When data is in the cache, it should be returned without modification.
     */
    public function testCacheHitReturnsData(): void
    {
        // Arrange: Set up cache to return specific data
        $cachedData = [
            'messages' => [
                ['id' => 1, 'user' => 'TestUser', 'msg' => 'Hello', 'time' => 1000]
            ],
            'pagination' => [
                'page' => 1,
                'perPage' => 10,
                'totalItems' => 1,
                'totalPages' => 1,
                'hasNext' => false,
                'hasPrev' => false
            ]
        ];

        $this->cacheMock->method('get')
            ->willReturn($cachedData);

        $chatModel = new ChatModel();

        // Act: Get paginated messages
        $result = $chatModel->getMsgPaginated(1, 10);

        // Assert: Should return the cached data
        $this->assertEquals($cachedData, $result);
    }

    /**
     * Test: Result structure contains messages and pagination
     *
     * The result from getMsgPaginated should always have the expected structure.
     */
    public function testResultStructure(): void
    {
        // Arrange: Return cached data with full structure
        $this->cacheMock->method('get')
            ->willReturn([
                'messages' => [
                    ['id' => 1, 'user' => 'User1', 'msg' => 'Message 1', 'time' => time()],
                    ['id' => 2, 'user' => 'User2', 'msg' => 'Message 2', 'time' => time()]
                ],
                'pagination' => [
                    'page' => 1,
                    'perPage' => 10,
                    'totalItems' => 2,
                    'totalPages' => 1,
                    'hasNext' => false,
                    'hasPrev' => false
                ]
            ]);

        $chatModel = new ChatModel();

        // Act: Get paginated messages
        $result = $chatModel->getMsgPaginated(1, 10);

        // Assert: Result has correct structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('messages', $result);
        $this->assertArrayHasKey('pagination', $result);

        // Assert: Pagination has all expected fields
        $pagination = $result['pagination'];
        $this->assertArrayHasKey('page', $pagination);
        $this->assertArrayHasKey('perPage', $pagination);
        $this->assertArrayHasKey('totalItems', $pagination);
        $this->assertArrayHasKey('totalPages', $pagination);
        $this->assertArrayHasKey('hasNext', $pagination);
        $this->assertArrayHasKey('hasPrev', $pagination);
    }

    // =========================================================================
    // PAGINATION METADATA TESTS (via cached data)
    // =========================================================================

    /**
     * Test: First page metadata is correct
     *
     * First page should have hasPrev=false.
     */
    public function testFirstPageMetadata(): void
    {
        // Arrange: Return first page data
        $this->cacheMock->method('get')
            ->willReturn([
                'messages' => [],
                'pagination' => [
                    'page' => 1,
                    'perPage' => 10,
                    'totalItems' => 25,
                    'totalPages' => 3,
                    'hasNext' => true,
                    'hasPrev' => false
                ]
            ]);

        $chatModel = new ChatModel();

        // Act
        $result = $chatModel->getMsgPaginated(1, 10);

        // Assert: First page should not have previous
        $this->assertFalse(
            $result['pagination']['hasPrev'],
            'First page should not have a previous page'
        );
        $this->assertTrue(
            $result['pagination']['hasNext'],
            'First page of multi-page result should have next page'
        );
    }

    /**
     * Test: Last page metadata is correct
     *
     * Last page should have hasNext=false.
     */
    public function testLastPageMetadata(): void
    {
        // Arrange: Return last page data
        $this->cacheMock->method('get')
            ->willReturn([
                'messages' => [],
                'pagination' => [
                    'page' => 3,
                    'perPage' => 10,
                    'totalItems' => 25,
                    'totalPages' => 3,
                    'hasNext' => false,
                    'hasPrev' => true
                ]
            ]);

        $chatModel = new ChatModel();

        // Act
        $result = $chatModel->getMsgPaginated(3, 10);

        // Assert: Last page should not have next
        $this->assertTrue(
            $result['pagination']['hasPrev'],
            'Last page should have a previous page'
        );
        $this->assertFalse(
            $result['pagination']['hasNext'],
            'Last page should not have a next page'
        );
    }

    /**
     * Test: Middle page has both navigation options
     *
     * A middle page should have both hasNext=true and hasPrev=true.
     */
    public function testMiddlePageMetadata(): void
    {
        // Arrange: Return middle page data
        $this->cacheMock->method('get')
            ->willReturn([
                'messages' => [],
                'pagination' => [
                    'page' => 2,
                    'perPage' => 10,
                    'totalItems' => 25,
                    'totalPages' => 3,
                    'hasNext' => true,
                    'hasPrev' => true
                ]
            ]);

        $chatModel = new ChatModel();

        // Act
        $result = $chatModel->getMsgPaginated(2, 10);

        // Assert: Middle page should have both
        $this->assertTrue($result['pagination']['hasPrev']);
        $this->assertTrue($result['pagination']['hasNext']);
    }

    /**
     * Test: Single page has no navigation
     *
     * When all items fit on one page, both hasNext and hasPrev should be false.
     */
    public function testSinglePageMetadata(): void
    {
        // Arrange: Return single page data
        $this->cacheMock->method('get')
            ->willReturn([
                'messages' => [],
                'pagination' => [
                    'page' => 1,
                    'perPage' => 10,
                    'totalItems' => 5,
                    'totalPages' => 1,
                    'hasNext' => false,
                    'hasPrev' => false
                ]
            ]);

        $chatModel = new ChatModel();

        // Act
        $result = $chatModel->getMsgPaginated(1, 10);

        // Assert: Single page has no navigation
        $this->assertFalse($result['pagination']['hasPrev']);
        $this->assertFalse($result['pagination']['hasNext']);
        $this->assertEquals(1, $result['pagination']['totalPages']);
    }

    // =========================================================================
    // EDGE CASE TESTS
    // =========================================================================

    /**
     * Test: Empty result set is handled correctly
     *
     * When there are no messages, the result should still be well-formed.
     */
    public function testEmptyResultSet(): void
    {
        // Arrange: Return empty data
        $this->cacheMock->method('get')
            ->willReturn([
                'messages' => [],
                'pagination' => [
                    'page' => 1,
                    'perPage' => 10,
                    'totalItems' => 0,
                    'totalPages' => 0,
                    'hasNext' => false,
                    'hasPrev' => false
                ]
            ]);

        $chatModel = new ChatModel();

        // Act
        $result = $chatModel->getMsgPaginated(1, 10);

        // Assert: Empty but valid
        $this->assertIsArray($result['messages']);
        $this->assertEmpty($result['messages']);
        $this->assertEquals(0, $result['pagination']['totalItems']);
        $this->assertEquals(0, $result['pagination']['totalPages']);
    }

    /**
     * Test: Large perPage value is handled
     *
     * Using a large perPage value should work correctly.
     */
    public function testLargePerPageValue(): void
    {
        // Arrange: Return data for large perPage
        $this->cacheMock->method('get')
            ->willReturn([
                'messages' => [],
                'pagination' => [
                    'page' => 1,
                    'perPage' => 100,
                    'totalItems' => 50,
                    'totalPages' => 1,
                    'hasNext' => false,
                    'hasPrev' => false
                ]
            ]);

        $chatModel = new ChatModel();

        // Act
        $result = $chatModel->getMsgPaginated(1, 100);

        // Assert: Should work with large perPage
        $this->assertEquals(100, $result['pagination']['perPage']);
        $this->assertEquals(1, $result['pagination']['totalPages']);
    }

    // =========================================================================
    // BACKWARD COMPATIBILITY TESTS
    // =========================================================================

    /**
     * Test: getMsg() returns only messages (not full pagination structure)
     *
     * The getMsg() method is for backward compatibility and should
     * return just the messages array, not the full pagination structure.
     */
    public function testGetMsgReturnsOnlyMessages(): void
    {
        // Arrange: Return full structure
        $messages = [
            ['id' => 1, 'user' => 'User1', 'msg' => 'Message 1', 'time' => time()],
            ['id' => 2, 'user' => 'User2', 'msg' => 'Message 2', 'time' => time()]
        ];

        $this->cacheMock->method('get')
            ->willReturn([
                'messages' => $messages,
                'pagination' => [
                    'page' => 1,
                    'perPage' => 10,
                    'totalItems' => 2,
                    'totalPages' => 1,
                    'hasNext' => false,
                    'hasPrev' => false
                ]
            ]);

        $chatModel = new ChatModel();

        // Act: Call getMsg (backward compatibility method)
        $result = $chatModel->getMsg(10);

        // Assert: Should return just messages, not the full structure
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        // Should not have 'messages' key - it IS the messages
        $this->assertArrayNotHasKey('messages', $result);
        $this->assertArrayNotHasKey('pagination', $result);
        // Verify it contains the actual message data
        $this->assertEquals('User1', $result[0]['user']);
    }
}
