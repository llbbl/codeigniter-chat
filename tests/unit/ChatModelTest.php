<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\MockBuilder;
use App\Models\ChatModel;

/**
 * @internal
 */
final class ChatModelTest extends CIUnitTestCase
{
    protected $chatModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chatModel = $this->getMockBuilder(ChatModel::class)
                                ->onlyMethods(['orderBy', 'limit', 'get', 'insert', 'where'])
                                ->getMock();
    }

    public function testGetMsg(): void
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

        // Set up the method chain expectations
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

        // Call the method
        $result = $this->chatModel->insertMsg($name, $message, $timestamp);

        // Assert the result
        $this->assertSame($insertId, $result);
    }
}
