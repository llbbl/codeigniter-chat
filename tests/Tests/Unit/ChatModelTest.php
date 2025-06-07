<?php

namespace Tests\Unit;

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
                                ->onlyMethods(['orderBy', 'limit', 'get', 'insert', 'where', 'countAllResults'])
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

    public function testInvalidateCache(): void
    {
        // Mock data
        $name = 'Test User';
        $message = 'Test Message';
        $timestamp = time();
        $insertId = 123; // Mock insert ID

        // Set up the insert method expectation
        $this->chatModel->expects($this->once())
                       ->method('insert')
                       ->willReturn($insertId);

        // Set up cache invalidation expectation - this tests the invalidateCache method
        $this->cacheMock->expects($this->once())
                       ->method('deleteMatching')
                       ->with('chat_messages_*')
                       ->willReturn(true);

        // Call insertMsg which will call invalidateCache
        $this->chatModel->insertMsg($name, $message, $timestamp);
    }

    public function testGetMsgPaginatedCacheMiss(): void
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
                       ->with('chat_messages_page_2_5')
                       ->willReturn(null);

        // Set up countAllResults to return 15 (for pagination)
        $this->chatModel->expects($this->once())
                       ->method('countAllResults')
                       ->willReturn(15);

        // Set up the method chain expectations for database query
        $this->chatModel->expects($this->once())
                       ->method('orderBy')
                       ->with('time', 'DESC')
                       ->willReturnSelf();

        $this->chatModel->expects($this->once())
                       ->method('limit')
                       ->with(5, 5) // perPage = 5, offset = (page-1)*perPage = (2-1)*5 = 5
                       ->willReturnSelf();

        $this->chatModel->expects($this->once())
                       ->method('get')
                       ->willReturn($mockResult);

        // Expected pagination data
        $expectedPagination = [
            'page' => 2,
            'perPage' => 5,
            'totalItems' => 15,
            'totalPages' => 3,
            'hasNext' => true,
            'hasPrev' => true
        ];

        // Expected result
        $expectedResult = [
            'messages' => $sampleData,
            'pagination' => $expectedPagination
        ];

        // Set up cache save expectation
        $this->cacheMock->expects($this->once())
                       ->method('save')
                       ->with('chat_messages_page_2_5', $expectedResult, 300)
                       ->willReturn(true);

        // Call the method
        $result = $this->chatModel->getMsgPaginated(2, 5);

        // Assert the result
        $this->assertSame($expectedResult, $result);
    }

    public function testGetMsgPaginatedCacheHit(): void
    {
        // Sample data that would be returned from the cache
        $sampleData = [
            ['id' => 1, 'user' => 'User1', 'msg' => 'Message 1', 'time' => time()],
            ['id' => 2, 'user' => 'User2', 'msg' => 'Message 2', 'time' => time()],
        ];

        // Expected pagination data
        $expectedPagination = [
            'page' => 2,
            'perPage' => 5,
            'totalItems' => 15,
            'totalPages' => 3,
            'hasNext' => true,
            'hasPrev' => true
        ];

        // Expected result
        $expectedResult = [
            'messages' => $sampleData,
            'pagination' => $expectedPagination
        ];

        // Set up cache hit
        $this->cacheMock->expects($this->once())
                       ->method('get')
                       ->with('chat_messages_page_2_5')
                       ->willReturn($expectedResult);

        // Database methods should not be called
        $this->chatModel->expects($this->never())
                       ->method('countAllResults');

        $this->chatModel->expects($this->never())
                       ->method('orderBy');

        $this->chatModel->expects($this->never())
                       ->method('limit');

        $this->chatModel->expects($this->never())
                       ->method('get');

        // Call the method
        $result = $this->chatModel->getMsgPaginated(2, 5);

        // Assert the result
        $this->assertSame($expectedResult, $result);
    }

    public function testGetMsgByUserPaginatedCacheMiss(): void
    {
        // Sample data that would be returned from the database
        $sampleData = [
            ['id' => 1, 'user' => 'TestUser', 'msg' => 'Message 1', 'time' => time()],
            ['id' => 2, 'user' => 'TestUser', 'msg' => 'Message 2', 'time' => time()],
        ];

        // Username to filter by
        $username = 'TestUser';

        // Create mock result object
        $mockResult = $this->getMockBuilder('stdClass')
                          ->addMethods(['getResultArray'])
                          ->getMock();
        $mockResult->method('getResultArray')->willReturn($sampleData);

        // Create mock builder for where method
        $mockWhere = $this->getMockBuilder('stdClass')
                         ->addMethods(['countAllResults'])
                         ->getMock();
        $mockWhere->method('countAllResults')->willReturn(10);

        // Set up cache miss
        $this->cacheMock->expects($this->once())
                       ->method('get')
                       ->with('chat_messages_user_' . md5($username) . '_page_2_5')
                       ->willReturn(null);

        // Set up the method chain expectations for database query
        $this->chatModel->expects($this->exactly(2))
                       ->method('where')
                       ->with('user', $username)
                       ->willReturnSelf();

        $this->chatModel->expects($this->once())
                       ->method('orderBy')
                       ->with('time', 'DESC')
                       ->willReturnSelf();

        $this->chatModel->expects($this->once())
                       ->method('limit')
                       ->with(5, 5) // perPage = 5, offset = (page-1)*perPage = (2-1)*5 = 5
                       ->willReturnSelf();

        $this->chatModel->expects($this->once())
                       ->method('get')
                       ->willReturn($mockResult);

        // Expected pagination data
        $expectedPagination = [
            'page' => 2,
            'perPage' => 5,
            'totalItems' => 10,
            'totalPages' => 2,
            'hasNext' => false,
            'hasPrev' => true
        ];

        // Expected result
        $expectedResult = [
            'messages' => $sampleData,
            'pagination' => $expectedPagination
        ];

        // Set up cache save expectation
        $this->cacheMock->expects($this->once())
                       ->method('save')
                       ->with('chat_messages_user_' . md5($username) . '_page_2_5', $expectedResult, 300)
                       ->willReturn(true);

        // Call the method
        $result = $this->chatModel->getMsgByUserPaginated($username, 2, 5);

        // Assert the result
        $this->assertSame($expectedResult, $result);
    }

    public function testGetMsgByUserPaginatedCacheHit(): void
    {
        // Sample data that would be returned from the cache
        $sampleData = [
            ['id' => 1, 'user' => 'TestUser', 'msg' => 'Message 1', 'time' => time()],
            ['id' => 2, 'user' => 'TestUser', 'msg' => 'Message 2', 'time' => time()],
        ];

        // Username to filter by
        $username = 'TestUser';

        // Expected pagination data
        $expectedPagination = [
            'page' => 2,
            'perPage' => 5,
            'totalItems' => 10,
            'totalPages' => 2,
            'hasNext' => false,
            'hasPrev' => true
        ];

        // Expected result
        $expectedResult = [
            'messages' => $sampleData,
            'pagination' => $expectedPagination
        ];

        // Set up cache hit
        $this->cacheMock->expects($this->once())
                       ->method('get')
                       ->with('chat_messages_user_' . md5($username) . '_page_2_5')
                       ->willReturn($expectedResult);

        // Database methods should not be called
        $this->chatModel->expects($this->never())
                       ->method('where');

        $this->chatModel->expects($this->never())
                       ->method('orderBy');

        $this->chatModel->expects($this->never())
                       ->method('limit');

        $this->chatModel->expects($this->never())
                       ->method('get');

        // Call the method
        $result = $this->chatModel->getMsgByUserPaginated($username, 2, 5);

        // Assert the result
        $this->assertSame($expectedResult, $result);
    }

    public function testGetMsgByUser(): void
    {
        // Sample data that would be returned from getMsgByUserPaginated
        $sampleData = [
            ['id' => 1, 'user' => 'TestUser', 'msg' => 'Message 1', 'time' => time()],
            ['id' => 2, 'user' => 'TestUser', 'msg' => 'Message 2', 'time' => time()],
        ];

        // Username to filter by
        $username = 'TestUser';

        // Create a partial mock of the ChatModel that only mocks getMsgByUserPaginated
        $partialMock = $this->getMockBuilder(ChatModel::class)
                           ->onlyMethods(['getMsgByUserPaginated'])
                           ->getMock();

        // Set up the getMsgByUserPaginated method expectation
        $partialMock->expects($this->once())
                   ->method('getMsgByUserPaginated')
                   ->with($username, 1, 10)
                   ->willReturn([
                       'messages' => $sampleData,
                       'pagination' => [
                           'page' => 1,
                           'perPage' => 10,
                           'totalItems' => 2,
                           'totalPages' => 1,
                           'hasNext' => false,
                           'hasPrev' => false
                       ]
                   ]);

        // Call the method
        $result = $partialMock->getMsgByUser($username);

        // Assert the result
        $this->assertSame($sampleData, $result);
    }

    public function testGetMsgByTimeRangePaginatedCacheMiss(): void
    {
        // Sample data that would be returned from the database
        $sampleData = [
            ['id' => 1, 'user' => 'User1', 'msg' => 'Message 1', 'time' => 1000],
            ['id' => 2, 'user' => 'User2', 'msg' => 'Message 2', 'time' => 2000],
        ];

        // Time range to filter by
        $startTime = 1000;
        $endTime = 2000;

        // Create mock result object
        $mockResult = $this->getMockBuilder('stdClass')
                          ->addMethods(['getResultArray'])
                          ->getMock();
        $mockResult->method('getResultArray')->willReturn($sampleData);

        // Create mock builder for where method
        $mockWhere = $this->getMockBuilder('stdClass')
                         ->addMethods(['countAllResults'])
                         ->getMock();
        $mockWhere->method('countAllResults')->willReturn(5);

        // Set up cache miss
        $this->cacheMock->expects($this->once())
                       ->method('get')
                       ->with('chat_messages_time_' . $startTime . '_' . $endTime . '_page_2_5')
                       ->willReturn(null);

        // Set up the method chain expectations for database query
        $whereCallIndex = 0;
        $this->chatModel->expects($this->exactly(4))
                       ->method('where')
                       ->willReturnCallback(function($field, $value) use (&$whereCallIndex, $startTime, $endTime) {
                           // First two calls are for countAllResults
                           // Next two calls are for the actual query
                           if ($whereCallIndex == 0 || $whereCallIndex == 2) {
                               $this->assertEquals('time >=', $field);
                               $this->assertEquals($startTime, $value);
                           } else {
                               $this->assertEquals('time <=', $field);
                               $this->assertEquals($endTime, $value);
                           }
                           $whereCallIndex++;
                           return $this->chatModel;
                       });

        $this->chatModel->expects($this->once())
                       ->method('orderBy')
                       ->with('time', 'DESC')
                       ->willReturnSelf();

        $this->chatModel->expects($this->once())
                       ->method('limit')
                       ->with(5, 5) // perPage = 5, offset = (page-1)*perPage = (2-1)*5 = 5
                       ->willReturnSelf();

        $this->chatModel->expects($this->once())
                       ->method('get')
                       ->willReturn($mockResult);

        // Expected pagination data
        $expectedPagination = [
            'page' => 2,
            'perPage' => 5,
            'totalItems' => 5,
            'totalPages' => 1,
            'hasNext' => false,
            'hasPrev' => true
        ];

        // Expected result
        $expectedResult = [
            'messages' => $sampleData,
            'pagination' => $expectedPagination
        ];

        // Set up cache save expectation
        $this->cacheMock->expects($this->once())
                       ->method('save')
                       ->with('chat_messages_time_' . $startTime . '_' . $endTime . '_page_2_5', $expectedResult, 300)
                       ->willReturn(true);

        // Call the method
        $result = $this->chatModel->getMsgByTimeRangePaginated($startTime, $endTime, 2, 5);

        // Assert the result
        $this->assertSame($expectedResult, $result);
    }

    public function testGetMsgByTimeRangePaginatedCacheHit(): void
    {
        // Sample data that would be returned from the cache
        $sampleData = [
            ['id' => 1, 'user' => 'User1', 'msg' => 'Message 1', 'time' => 1000],
            ['id' => 2, 'user' => 'User2', 'msg' => 'Message 2', 'time' => 2000],
        ];

        // Time range to filter by
        $startTime = 1000;
        $endTime = 2000;

        // Expected pagination data
        $expectedPagination = [
            'page' => 2,
            'perPage' => 5,
            'totalItems' => 5,
            'totalPages' => 1,
            'hasNext' => false,
            'hasPrev' => true
        ];

        // Expected result
        $expectedResult = [
            'messages' => $sampleData,
            'pagination' => $expectedPagination
        ];

        // Set up cache hit
        $this->cacheMock->expects($this->once())
                       ->method('get')
                       ->with('chat_messages_time_' . $startTime . '_' . $endTime . '_page_2_5')
                       ->willReturn($expectedResult);

        // Database methods should not be called
        $this->chatModel->expects($this->never())
                       ->method('where');

        $this->chatModel->expects($this->never())
                       ->method('orderBy');

        $this->chatModel->expects($this->never())
                       ->method('limit');

        $this->chatModel->expects($this->never())
                       ->method('get');

        // Call the method
        $result = $this->chatModel->getMsgByTimeRangePaginated($startTime, $endTime, 2, 5);

        // Assert the result
        $this->assertSame($expectedResult, $result);
    }

    public function testGetMsgByTimeRange(): void
    {
        // Sample data that would be returned from getMsgByTimeRangePaginated
        $sampleData = [
            ['id' => 1, 'user' => 'User1', 'msg' => 'Message 1', 'time' => 1000],
            ['id' => 2, 'user' => 'User2', 'msg' => 'Message 2', 'time' => 2000],
        ];

        // Time range to filter by
        $startTime = 1000;
        $endTime = 2000;

        // Create a partial mock of the ChatModel that only mocks getMsgByTimeRangePaginated
        $partialMock = $this->getMockBuilder(ChatModel::class)
                           ->onlyMethods(['getMsgByTimeRangePaginated'])
                           ->getMock();

        // Set up the getMsgByTimeRangePaginated method expectation
        $partialMock->expects($this->once())
                   ->method('getMsgByTimeRangePaginated')
                   ->with($startTime, $endTime, 1, 10)
                   ->willReturn([
                       'messages' => $sampleData,
                       'pagination' => [
                           'page' => 1,
                           'perPage' => 10,
                           'totalItems' => 2,
                           'totalPages' => 1,
                           'hasNext' => false,
                           'hasPrev' => false
                       ]
                   ]);

        // Call the method
        $result = $partialMock->getMsgByTimeRange($startTime, $endTime);

        // Assert the result
        $this->assertSame($sampleData, $result);
    }

    protected function tearDown(): void
    {
        // Clean up any mocks
        Services::resetSingle('cache');
        parent::tearDown();
    }
}
