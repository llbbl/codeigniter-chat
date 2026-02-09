<?php

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use App\Models\ChatModel;
use Config\Services;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Chat Controller Feature Tests
 *
 * Note: CI4 7.0 has stricter CORS handling that conflicts with withSession()
 * and wildcard origins. These tests set an explicit origin to avoid the issue.
 *
 * @internal
 */
final class ChatTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private MockObject $mockChatModel;
    private array $sampleMessages;
    private array $samplePagination;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock for the ChatModel
        $this->mockChatModel = $this->createMock(ChatModel::class);

        // Sample data for testing
        $this->sampleMessages = [
            [
                'id' => 1,
                'user' => 'Test User',
                'msg' => 'Test Message 1',
                'time' => time() - 100
            ],
            [
                'id' => 2,
                'user' => 'Test User',
                'msg' => 'Test Message 2',
                'time' => time() - 50
            ]
        ];

        $this->samplePagination = [
            'page' => 1,
            'perPage' => 10,
            'totalItems' => 2,
            'totalPages' => 1,
            'hasNext' => false,
            'hasPrev' => false
        ];

        // Set up the mock to return sample data
        $this->mockChatModel->method('getMsgPaginated')
            ->willReturn([
                'messages' => $this->sampleMessages,
                'pagination' => $this->samplePagination
            ]);

        // Replace the service with our mock
        Services::injectMock('chatModel', $this->mockChatModel);
    }

    public function testIndexReturnsView(): void
    {
        $result = $this->withHeaders(['Origin' => 'http://localhost'])
                       ->withSession(['logged_in' => true, 'username' => 'Test User'])
                       ->call('get', '/chat');

        $result->assertOK();
        $result->assertSee('CodeIgniter Shoutbox');
    }

    public function testBackendReturnsXml(): void
    {
        $result = $this->withHeaders(['Origin' => 'http://localhost'])
                       ->withSession(['logged_in' => true, 'username' => 'Test User'])
                       ->call('get', '/chat/backend');

        $result->assertOK();
        $result->assertHeader('Content-Type', 'text/xml');

        // Check XML content in the body
        $body = $result->response()->getBody();
        $this->assertStringContainsString('<messages>', $body);
        $this->assertStringContainsString('<message>', $body);
        $this->assertStringContainsString('<author>Test User</author>', $body);
    }

    public function testJsonReturnsView(): void
    {
        $result = $this->withHeaders(['Origin' => 'http://localhost'])
                       ->withSession(['logged_in' => true, 'username' => 'Test User'])
                       ->call('get', '/chat/json');

        $result->assertOK();
        $result->assertSee('JSON edition');
    }

    public function testJsonBackendReturnsJson(): void
    {
        $result = $this->withHeaders(['Origin' => 'http://localhost'])
                       ->withSession(['logged_in' => true, 'username' => 'Test User'])
                       ->call('get', '/chat/jsonBackend');

        $result->assertOK();
        $result->assertHeader('Content-Type', 'application/json; charset=UTF-8');

        $json = json_decode($result->getJSON(), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('messages', $json);
        $this->assertArrayHasKey('pagination', $json);
        $this->assertEquals(2, count($json['messages']));
    }

    public function testHtmlReturnsView(): void
    {
        $result = $this->withHeaders(['Origin' => 'http://localhost'])
                       ->withSession(['logged_in' => true, 'username' => 'Test User'])
                       ->call('get', '/chat/html');

        $result->assertOK();
        $result->assertSee('CodeIgniter Shoutbox');
    }

    public function testVueReturnsViewOrRedirectsIfNotLoggedIn(): void
    {
        $result = $this->withHeaders(['Origin' => 'http://localhost'])
                       ->call('get', '/chat/vue');

        // Without authentication, should redirect to login
        $result->assertRedirect();
    }

    public function testVueApiReturnsJson(): void
    {
        $result = $this->withHeaders(['Origin' => 'http://localhost'])
                       ->withSession(['logged_in' => true, 'username' => 'Test User'])
                       ->call('get', '/chat/vueApi');

        $result->assertOK();
        $result->assertHeader('Content-Type', 'application/json; charset=UTF-8');

        $json = json_decode($result->getJSON(), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('messages', $json);
        $this->assertArrayHasKey('pagination', $json);
    }

    public function testUpdateRequiresAuthentication(): void
    {
        $result = $this->withHeaders(['Origin' => 'http://localhost'])
                       ->call('post', '/chat/update', [
                           'message' => 'Test Message',
                           'action' => 'postmsg',
                           csrf_token() => csrf_hash()
                       ]);

        // Should redirect to login when not authenticated
        $result->assertRedirect();
    }

    public function testUpdateRequiresValidMessage(): void
    {
        // Test with empty message - should fail validation
        $result = $this->withHeaders(['Origin' => 'http://localhost'])
                       ->withSession(['logged_in' => true, 'username' => 'Test User'])
                       ->call('post', '/chat/update', [
                           'message' => '',
                           'action' => 'postmsg',
                           csrf_token() => csrf_hash()
                       ]);

        // Response should indicate failure - either via JSON error or redirect
        $this->assertTrue(
            $result->isRedirect() ||
            ($result->getJSON() !== null && json_decode($result->getJSON(), true)['success'] === false) ||
            $result->response()->getStatusCode() !== 200
        );
    }

    protected function tearDown(): void
    {
        Services::resetSingle('chatModel');
        parent::tearDown();
    }
}
