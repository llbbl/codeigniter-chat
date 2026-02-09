<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Models\ChatModel;
use Config\Services;
use CodeIgniter\Cache\CacheInterface;

/**
 * ChatModel Unit Tests
 *
 * Note: Many of the original tests in this file attempted to mock
 * parent class methods (orderBy, limit, get, etc.) which are implemented
 * via __call magic method in CodeIgniter's Model class. PHPUnit 12+
 * does not allow mocking methods that don't exist on the class.
 *
 * For proper testing of ChatModel database operations, use:
 * - ChatModelPaginationTest.php (database integration tests)
 * - Feature tests with actual database
 *
 * The tests below focus on what can be unit tested without database mocking.
 *
 * @internal
 */
final class ChatModelTest extends CIUnitTestCase
{
    protected ChatModel $chatModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chatModel = new ChatModel();
    }

    public function testModelHasCorrectTable(): void
    {
        $this->assertEquals('messages', $this->chatModel->table);
    }

    public function testModelHasCorrectPrimaryKey(): void
    {
        $this->assertEquals('id', $this->chatModel->primaryKey);
    }

    public function testModelHasCorrectAllowedFields(): void
    {
        $allowedFields = $this->chatModel->allowedFields;

        $this->assertContains('user', $allowedFields);
        $this->assertContains('msg', $allowedFields);
        $this->assertContains('time', $allowedFields);
    }

    public function testCacheKeyIsConfigured(): void
    {
        $reflection = new \ReflectionProperty($this->chatModel, 'cacheKey');
        $reflection->setAccessible(true);
        $this->assertEquals('chat_messages', $reflection->getValue($this->chatModel));
    }

    public function testCacheTTLIsConfigured(): void
    {
        $reflection = new \ReflectionProperty($this->chatModel, 'cacheTTL');
        $reflection->setAccessible(true);
        $this->assertEquals(300, $reflection->getValue($this->chatModel));
    }

    public function testGetMsgByUserSignature(): void
    {
        // Test that getMsgByUser method exists and has correct signature
        $this->assertTrue(method_exists($this->chatModel, 'getMsgByUser'));

        $reflection = new \ReflectionMethod($this->chatModel, 'getMsgByUser');
        $params = $reflection->getParameters();

        $this->assertCount(2, $params);
        $this->assertEquals('username', $params[0]->getName());
        $this->assertEquals('limit', $params[1]->getName());
        $this->assertEquals(10, $params[1]->getDefaultValue());
    }

    public function testGetMsgByTimeRangeSignature(): void
    {
        // Test that getMsgByTimeRange method exists and has correct signature
        $this->assertTrue(method_exists($this->chatModel, 'getMsgByTimeRange'));

        $reflection = new \ReflectionMethod($this->chatModel, 'getMsgByTimeRange');
        $params = $reflection->getParameters();

        $this->assertCount(3, $params);
        $this->assertEquals('startTime', $params[0]->getName());
        $this->assertEquals('endTime', $params[1]->getName());
        $this->assertEquals('limit', $params[2]->getName());
        $this->assertEquals(10, $params[2]->getDefaultValue());
    }

    public function testGetMsgPaginatedMethodExists(): void
    {
        $this->assertTrue(method_exists($this->chatModel, 'getMsgPaginated'));

        $reflection = new \ReflectionMethod($this->chatModel, 'getMsgPaginated');
        $params = $reflection->getParameters();

        $this->assertCount(2, $params);
        $this->assertEquals('page', $params[0]->getName());
        $this->assertEquals('perPage', $params[1]->getName());
        $this->assertEquals(1, $params[0]->getDefaultValue());
        $this->assertEquals(10, $params[1]->getDefaultValue());
    }

    public function testInsertMsgMethodExists(): void
    {
        $this->assertTrue(method_exists($this->chatModel, 'insertMsg'));

        $reflection = new \ReflectionMethod($this->chatModel, 'insertMsg');
        $params = $reflection->getParameters();

        $this->assertCount(3, $params);
        $this->assertEquals('name', $params[0]->getName());
        $this->assertEquals('message', $params[1]->getName());
        $this->assertEquals('current', $params[2]->getName());
    }

    public function testInvalidateCacheMethodExists(): void
    {
        $this->assertTrue(method_exists($this->chatModel, 'invalidateCache'));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
