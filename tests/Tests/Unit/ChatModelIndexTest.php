<?php

namespace Tests\Unit;

use App\Models\ChatModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Test database indexes for chat messages
 */
class ChatModelIndexTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $migrate = true;
    protected $migrateOnce = false;
    protected $namespace = 'Tests\Support';

    protected ChatModel $chatModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chatModel = new ChatModel();
    }

    public function testDatabaseIndexesExist(): void
    {
        // Get database connection
        $db = \Config\Database::connect();
        
        // Check if indexes exist on messages table
        $indexes = $db->getIndexData('messages');
        
        $indexNames = [];
        foreach ($indexes as $index) {
            $indexNames[] = $index->name;
        }
        
        // We should have the primary key index at minimum
        $this->assertContains('PRIMARY', $indexNames, 'Primary key index should exist');
        
        // Log current indexes for debugging
        log_message('debug', 'Current indexes on messages table: ' . json_encode($indexNames));
    }

    public function testQueryPerformanceMonitoring(): void
    {
        // Test that the performance monitoring method works
        $method = new \ReflectionMethod($this->chatModel, 'monitorQueryPerformance');
        $method->setAccessible(true);
        
        // Create a simple test query
        $query = "SELECT * FROM messages ORDER BY time DESC LIMIT 10";
        
        // This should not throw an exception
        $this->assertNull($method->invoke($this->chatModel, $query));
    }

    public function testOptimizedPaginatedQuery(): void
    {
        // Insert some test data
        for ($i = 0; $i < 5; $i++) {
            $this->chatModel->insertMsg('user' . $i, 'Test message ' . $i, time() - $i);
        }
        
        // Test the optimized paginated method
        $result = $this->chatModel->getMsgPaginatedOptimized(1, 3);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('messages', $result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertCount(3, $result['messages']);
    }

    public function testTimeIndexOptimization(): void
    {
        // Insert test messages with different timestamps
        $baseTime = time();
        for ($i = 0; $i < 10; $i++) {
            $this->chatModel->insertMsg('testuser', 'Message ' . $i, $baseTime + $i);
        }
        
        // Test time range query
        $startTime = $baseTime + 2;
        $endTime = $baseTime + 7;
        
        $messages = $this->chatModel->getMsgByTimeRange($startTime, $endTime, 10);
        
        $this->assertIsArray($messages);
        $this->assertLessThanOrEqual(6, count($messages)); // Should return 5-6 messages in range
        
        // Verify messages are in the time range
        foreach ($messages as $message) {
            $this->assertGreaterThanOrEqual($startTime, $message['time']);
            $this->assertLessThanOrEqual($endTime, $message['time']);
        }
    }

    public function testUserIndexOptimization(): void
    {
        // Insert test messages from different users
        $baseTime = time();
        for ($i = 0; $i < 10; $i++) {
            $user = ($i % 2 === 0) ? 'user1' : 'user2';
            $this->chatModel->insertMsg($user, 'Message ' . $i, $baseTime + $i);
        }
        
        // Test user-specific query
        $user1Messages = $this->chatModel->getMsgByUser('user1', 10);
        $user2Messages = $this->chatModel->getMsgByUser('user2', 10);
        
        $this->assertIsArray($user1Messages);
        $this->assertIsArray($user2Messages);
        
        // Verify all messages belong to the correct user
        foreach ($user1Messages as $message) {
            $this->assertEquals('user1', $message['user']);
        }
        
        foreach ($user2Messages as $message) {
            $this->assertEquals('user2', $message['user']);
        }
        
        // Should have 5 messages each
        $this->assertEquals(5, count($user1Messages));
        $this->assertEquals(5, count($user2Messages));
    }

    public function testPaginationWithIndexes(): void
    {
        // Insert 25 test messages
        $baseTime = time();
        for ($i = 0; $i < 25; $i++) {
            $this->chatModel->insertMsg('testuser', 'Message ' . $i, $baseTime + $i);
        }
        
        // Test pagination
        $page1 = $this->chatModel->getMsgPaginated(1, 10);
        $page2 = $this->chatModel->getMsgPaginated(2, 10);
        $page3 = $this->chatModel->getMsgPaginated(3, 10);
        
        $this->assertCount(10, $page1['messages']);
        $this->assertCount(10, $page2['messages']);
        $this->assertCount(5, $page3['messages']);
        
        // Verify pagination data
        $this->assertEquals(1, $page1['pagination']['page']);
        $this->assertEquals(2, $page2['pagination']['page']);
        $this->assertEquals(3, $page3['pagination']['page']);
        $this->assertEquals(25, $page1['pagination']['totalItems']);
        $this->assertEquals(3, $page1['pagination']['totalPages']);
    }
}