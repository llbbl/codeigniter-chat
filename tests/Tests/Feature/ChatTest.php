<?php

namespace Tests\Feature;

use App\Contracts\ChatRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use PHPUnit\Framework\MockObject\Stub;
use Tests\Support\UsesApplication;

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
    use UsesApplication;

    private Stub $mockChatModel;
    private array $sampleMessages;
    private array $samplePagination;

    protected function setUp(): void
    {
        parent::setUp();
        Services::resetSingle('response');

        // Create a mock for the ChatModel
        $this->mockChatModel = $this->createStub(ChatRepository::class);

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
        Services::injectMock('chatRepository', $this->mockChatModel);
    }

    public function testIndexReturnsView(): void
    {
        $result = $this->withHeaders(['Origin' => 'http://localhost'])
                       ->withSession(['logged_in' => true, 'username' => 'Test User'])
                       ->call('get', '/chat');

        $result->assertOK();
        $result->assertSee('CodeIgniter Shoutbox');
    }

    public function testSecurityHeadersAreAppliedGlobally(): void
    {
        $result = $this->withHeaders(['Origin' => 'http://localhost'])
                       ->withSession(['logged_in' => true, 'username' => 'Test User'])
                       ->call('get', '/chat');

        $result->assertHeader('X-Frame-Options', 'DENY');
        $result->assertHeader('X-Content-Type-Options', 'nosniff');
        $result->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertSame('', $result->response()->getHeaderLine('Strict-Transport-Security'));
    }

    public function testSecurityHeadersAreAppliedToUnauthenticatedRedirects(): void
    {
        $result = $this->withHeaders(['Origin' => 'http://localhost'])
                       ->call('get', '/chat');

        $result->assertRedirect();
        $result->assertHeader('X-Frame-Options', 'DENY');
        $result->assertHeader('X-Content-Type-Options', 'nosniff');
        $result->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function testSecurityHeadersAreAppliedToCsrfRejections(): void
    {
        $securityConfig = new \Config\Security();
        $securityConfig->redirect = true;
        Services::injectMock('security', new \CodeIgniter\Security\Security($securityConfig));

        $result = $this->withHeaders(['Origin' => 'http://localhost'])
                       ->withSession(['logged_in' => true, 'username' => 'Test User'])
                       ->call('post', '/chat/update', [
                           'message' => 'Test Message',
                           'action' => 'postmsg',
                       ]);

        $result->assertRedirect();
        $result->assertHeader('X-Frame-Options', 'DENY');
        $result->assertHeader('X-Content-Type-Options', 'nosniff');
        $result->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function testFrameRelaxedSecurityHeaderOverrideIsAppliedThroughTheFilterPipeline(): void
    {
        $result = $this->withRoutes([
            [
                'GET',
                'frame-relaxed-test',
                static fn (): string => 'OK',
                ['filter' => 'securityHeaders:frame-relaxed'],
            ],
        ])->call('get', '/frame-relaxed-test');

        $result->assertOK();
        $result->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function testFrameRelaxedSecurityHeaderOverrideDoesNotReplaceControllerHeaders(): void
    {
        $result = $this->withRoutes([
            [
                'GET',
                'controller-frame-header-test',
                static fn () => service('response')
                    ->setHeader('X-Frame-Options', 'ALLOW-FROM https://example.com')
                    ->setBody('OK'),
                ['filter' => 'securityHeaders:frame-relaxed'],
            ],
        ])->call('get', '/controller-frame-header-test');

        $result->assertOK();
        $result->assertHeader('X-Frame-Options', 'ALLOW-FROM https://example.com');
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
        Services::resetSingle('chatRepository');
        Services::resetSingle('response');
        Services::resetSingle('security');
        parent::tearDown();
    }
}
