<?php

namespace Tests\Feature;

use CodeIgniter\Test\FeatureTestCase;
use App\Models\ChatModel;
use Config\Services;

/**
 * @internal
 */
final class ChatTest extends FeatureTestCase
{
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
        Services::injectMock('chatmodel', $this->mockChatModel);
    }
    
    public function testIndexReturnsView(): void
    {
        $result = $this->call('get', '/chat');
        
        $result->assertOK();
        $result->assertSee('Chat Example');
    }
    
    public function testBackendReturnsXml(): void
    {
        $result = $this->call('get', '/chat/backend');
        
        $result->assertOK();
        $result->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $result->assertSee('<messages>', false);
        $result->assertSee('<message>', false);
        $result->assertSee('<author>Test User</author>', false);
    }
    
    public function testJsonReturnsView(): void
    {
        $result = $this->call('get', '/chat/json');
        
        $result->assertOK();
        $result->assertSee('JSON Chat Example');
    }
    
    public function testJsonBackendReturnsJson(): void
    {
        $result = $this->call('get', '/chat/jsonBackend');
        
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
        $result = $this->call('get', '/chat/html');
        
        $result->assertOK();
        $result->assertSee('HTML Chat Example');
    }
    
    public function testVueReturnsViewOrRedirectsIfNotLoggedIn(): void
    {
        $result = $this->call('get', '/chat/vue');
        
        // Either redirects to login or shows the Vue chat
        if ($result->isRedirect()) {
            $result->assertRedirectTo('/auth/login');
        } else {
            $result->assertOK();
            $result->assertSee('Vue.js Chat');
        }
    }
    
    public function testVueApiReturnsJson(): void
    {
        $result = $this->call('get', '/chat/vueApi');
        
        $result->assertOK();
        $result->assertHeader('Content-Type', 'application/json; charset=UTF-8');
        
        $json = json_decode($result->getJSON(), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('messages', $json);
        $this->assertArrayHasKey('pagination', $json);
    }
    
    public function testUpdateRequiresAuthentication(): void
    {
        $result = $this->call('post', '/chat/update', [
            'message' => 'Test Message',
            'action' => 'postmsg'
        ]);
        
        // Should either return an error or redirect to login
        if ($result->isRedirect()) {
            $result->assertRedirectTo('/auth/login');
        } else {
            $json = json_decode($result->getJSON(), true);
            $this->assertArrayHasKey('success', $json);
            $this->assertFalse($json['success']);
        }
    }
    
    public function testUpdateRequiresValidMessage(): void
    {
        // Simulate a logged-in user
        $this->simulateLoggedInUser();
        
        // Test with empty message
        $result = $this->call('post', '/chat/update', [
            'message' => '',
            'action' => 'postmsg'
        ]);
        
        if ($result->getJSON()) {
            $json = json_decode($result->getJSON(), true);
            $this->assertArrayHasKey('success', $json);
            $this->assertFalse($json['success']);
        }
        
        // Test with too long message
        $result = $this->call('post', '/chat/update', [
            'message' => str_repeat('a', 501), // 501 characters
            'action' => 'postmsg'
        ]);
        
        if ($result->getJSON()) {
            $json = json_decode($result->getJSON(), true);
            $this->assertArrayHasKey('success', $json);
            $this->assertFalse($json['success']);
        }
    }
    
    /**
     * Helper method to simulate a logged-in user
     */
    protected function simulateLoggedInUser(): void
    {
        $session = Services::session();
        $session->set('logged_in', true);
        $session->set('username', 'Test User');
    }
    
    protected function tearDown(): void
    {
        Services::resetSingle('chatmodel');
        parent::tearDown();
    }
}