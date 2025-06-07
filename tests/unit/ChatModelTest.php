<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\MockBuilder;
use App\Models\ChatModel;
use Config\Services;
use CodeIgniter\Cache\CacheInterface;

/**
 * @internal
 */
final class ChatModelTest extends CIUnitTestCase
{
    protected $chatModel;
    protected $cacheMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock for the cache service
        $this->cacheMock = $this->createMock(CacheInterface::class);

        // Replace the cache service with our mock
        $this->cacheMock->method('__get')->willReturnSelf();
        Services::injectMock('cache', $this->cacheMock);

        // Create a partial mock of the ChatModel
        $this->chatModel = $this->getMockBuilder(ChatModel::class)
                                ->onlyMethods(['orderBy', 'limit', 'get', 'insert', 'where'])
                                ->getMock();
    }

    public function testGetMsgCacheMiss(): void
    {
        // Sample data that would be returned from the database
        $sampleData = [
            ['id' => 1, 'user' => 'User1', 'msg' => 'Message 1', 'time' => time()],
            ['id' => 2, 'user' => 'User2', 'msg' => 'Message 2', 'time' => time()],
        ];

        // Create mock result object
        $mockResult = $this->getMockBuilder('stdClass')
                          ->addMethods(['getResultArray'])
                          ->getMock();
        $mockResult->method('getResultArray')->willReturn($sampleData);

        // Set up cache miss
        $this->cacheMock->expects($this->once())
                       ->method('get')
                       ->with('chat_messages_10')
                       ->willReturn(null);

        // Set up cache save expectation
        $this->cacheMock->expects($this->once())
                       ->method('save')
                       ->with('chat_messages_10', $sampleData, 300)
                       ->willReturn(true);

        // Set up the method chain expectations for database query
        $this->chatModel->expects($this->once())
                       ->method('orderBy')
                       ->with('id', 'DESC')
                       ->willReturnSelf();

        $this->chatModel->expects($this->once())
                       ->method('limit')
                       ->with(10)
                       ->willReturnSelf();

        $this->chatModel->expects($this->once())
                       ->method('get')
                       ->willReturn($mockResult);

        // Call the method
        $result = $this->chatModel->getMsg();

        // Assert the result
        $this->assertSame($sampleData, $result);
    }

    public function testGetMsgCacheHit(): void
    {
        // Sample data that would be returned from the cache
        $sampleData = [
            ['id' => 1, 'user' => 'User1', 'msg' => 'Message 1', 'time' => time()],
            ['id' => 2, 'user' => 'User2', 'msg' => 'Message 2', 'time' => time()],
        ];

        // Set up cache hit
        $this->cacheMock->expects($this->once())
                       ->method('get')
                       ->with('chat_messages_10')
                       ->willReturn($sampleData);

        // Database methods should not be called
        $this->chatModel->expects($this->never())
                       ->method('orderBy');

        $this->chatModel->expects($this->never())
                       ->method('limit');

        $this->chatModel->expects($this->never())
                       ->method('get');

        // Call the method
        $result = $this->chatModel->getMsg();

        // Assert the result
        $this->assertSame($sampleData, $result);
    }

    public function testInsertMsg(): void
    {
        // Mock data
        $name = 'Test User';
        $message = 'Test Message';
        $timestamp = time();
        $insertId = 123; // Mock insert ID

        // Set up the insert method expectation
        $this->chatModel->expects($this->once())
                       ->method('insert')
                       ->with([
                           'user' => $name,
                           'msg' => $message,
                           'time' => $timestamp
                       ])
                       ->willReturn($insertId);

        // Set up cache invalidation expectation
        $this->cacheMock->expects($this->once())
                       ->method('deleteMatching')
                       ->with('chat_messages_*')
                       ->willReturn(true);

        // Call the method
        $result = $this->chatModel->insertMsg($name, $message, $timestamp);

        // Assert the result
        $this->assertSame($insertId, $result);
    }

    protected function tearDown(): void
    {
        // Clean up any mocks
        Services::resetSingle('cache');
        parent::tearDown();
    }
}
