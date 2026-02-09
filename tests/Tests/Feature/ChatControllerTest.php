<?php

namespace Tests\Feature;

use App\Controllers\Chat;
use App\Models\ChatModel;
use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\Response;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use Config\App;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * Chat Controller Tests
 *
 * This test class verifies the Chat controller's business logic by testing
 * the controller methods directly with dependency injection.
 *
 * Why test this way?
 * ------------------
 * Feature tests with FeatureTestTrait simulate full HTTP requests but have
 * dependencies on CSRF tokens, CORS filters, session handling, etc. that
 * make them brittle in test environments.
 *
 * By testing the controller directly with injected mocks:
 * - Tests are faster (no HTTP simulation overhead)
 * - Tests are more focused (test the controller, not the framework)
 * - Tests are more reliable (no filter/session issues)
 * - We can still verify the controller's behavior comprehensively
 *
 * Key concepts demonstrated:
 * - Dependency Injection in testing
 * - Mocking the ChatModel to control data
 * - Testing controller methods directly
 * - Verifying response types and content
 *
 * @internal
 */
final class ChatControllerTest extends CIUnitTestCase
{
    /**
     * Mock of the ChatModel
     */
    private MockObject $mockChatModel;

    /**
     * The Chat controller instance being tested
     */
    private Chat $controller;

    /**
     * Sample messages for testing
     */
    private array $sampleMessages;

    /**
     * Sample pagination data
     */
    private array $samplePagination;

    /**
     * Set up test fixtures before each test
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create sample test data
        $this->sampleMessages = [
            [
                'id' => 1,
                'user' => 'Alice',
                'msg' => 'Hello everyone!',
                'time' => time() - 300
            ],
            [
                'id' => 2,
                'user' => 'Bob',
                'msg' => 'Hi Alice!',
                'time' => time() - 200
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

        // Create mock ChatModel
        $this->mockChatModel = $this->createMock(ChatModel::class);
        $this->mockChatModel->method('getMsgPaginated')
            ->willReturn([
                'messages' => $this->sampleMessages,
                'pagination' => $this->samplePagination
            ]);

        // Inject mock into services
        Services::injectMock('chatModel', $this->mockChatModel);

        // Create the controller with the mock
        $this->controller = new Chat($this->mockChatModel);

        // Initialize the controller with request/response/logger
        $this->initializeController();
    }

    /**
     * Initialize the controller with required dependencies
     *
     * CodeIgniter controllers need to be initialized with request, response,
     * and logger objects before they can be used.
     */
    private function initializeController(): void
    {
        $request = new IncomingRequest(
            new App(),
            new URI('http://example.com'),
            null,
            new UserAgent()
        );

        $response = new Response(new App());
        $logger = Services::logger();

        $this->controller->initController($request, $response, $logger);
    }

    /**
     * Clean up after each test
     */
    protected function tearDown(): void
    {
        Services::resetSingle('chatModel');
        $_SESSION = [];
        parent::tearDown();
    }

    // =========================================================================
    // CONTROLLER INSTANTIATION TESTS
    // =========================================================================

    /**
     * Test: Controller can be instantiated with injected ChatModel
     *
     * This tests the Dependency Injection pattern - the controller should
     * accept a ChatModel through its constructor.
     */
    public function testControllerAcceptsDependencyInjection(): void
    {
        // Arrange: Create a mock
        $mockModel = $this->createMock(ChatModel::class);

        // Act: Create controller with injected mock
        $controller = new Chat($mockModel);

        // Assert: Controller should be created successfully
        $this->assertInstanceOf(Chat::class, $controller);
    }

    /**
     * Test: Controller can be instantiated without explicit injection
     *
     * When no ChatModel is passed, the controller should get one from
     * the service container (for normal HTTP requests).
     */
    public function testControllerWorksWithoutExplicitInjection(): void
    {
        // Act: Create controller without passing a model
        $controller = new Chat();

        // Assert: Controller should still be created
        $this->assertInstanceOf(Chat::class, $controller);
    }

    // =========================================================================
    // INDEX METHOD TESTS (XML Chat View)
    // =========================================================================

    /**
     * Test: index() returns a string (the view)
     *
     * The index() method should return the rendered HTML view for
     * the XML-based chat interface.
     */
    public function testIndexReturnsString(): void
    {
        // Act: Call the index method
        $result = $this->controller->index();

        // Assert: Should return a string (rendered view)
        $this->assertIsString($result);
    }

    // =========================================================================
    // BACKEND METHOD TESTS (XML API)
    // =========================================================================

    /**
     * Test: backend() returns a Response object
     *
     * The backend() method should return a Response object with XML content.
     */
    public function testBackendReturnsResponse(): void
    {
        // Act: Call the backend method
        $result = $this->controller->backend();

        // Assert: Should return a Response object
        $this->assertInstanceOf(\CodeIgniter\HTTP\ResponseInterface::class, $result);
    }

    /**
     * Test: backend() response contains XML content
     *
     * The Response body should contain properly formatted XML.
     */
    public function testBackendResponseContainsXml(): void
    {
        // Act
        $response = $this->controller->backend();
        $body = $response->getBody();

        // Assert: Body should be XML
        $this->assertStringContainsString('<?xml', $body);
        $this->assertStringContainsString('<response>', $body);
    }

    /**
     * Test: backend() includes messages in XML
     *
     * The XML response should contain the message data.
     */
    public function testBackendXmlContainsMessages(): void
    {
        // Act
        $response = $this->controller->backend();
        $body = $response->getBody();

        // Assert: Should contain message elements
        $this->assertStringContainsString('<messages>', $body);
        $this->assertStringContainsString('<message>', $body);
        $this->assertStringContainsString('<author>Alice</author>', $body);
        $this->assertStringContainsString('<author>Bob</author>', $body);
    }

    /**
     * Test: backend() includes pagination in XML
     *
     * The XML response should include pagination metadata.
     */
    public function testBackendXmlContainsPagination(): void
    {
        // Act
        $response = $this->controller->backend();
        $body = $response->getBody();

        // Assert: Should contain pagination elements
        $this->assertStringContainsString('<pagination>', $body);
        $this->assertStringContainsString('<page>1</page>', $body);
        $this->assertStringContainsString('<totalItems>2</totalItems>', $body);
    }

    /**
     * Test: backend() handles empty message list
     *
     * When there are no messages, the XML should still be valid
     * with status code 2 (no messages).
     */
    public function testBackendHandlesEmptyMessages(): void
    {
        // Arrange: Mock returns empty messages
        $emptyMock = $this->createMock(ChatModel::class);
        $emptyMock->method('getMsgPaginated')
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

        $controller = new Chat($emptyMock);
        $this->initializeControllerInstance($controller);

        // Act
        $response = $controller->backend();
        $body = $response->getBody();

        // Assert: Should have status 2 (no messages)
        $this->assertStringContainsString('<status>2</status>', $body);
    }

    // =========================================================================
    // JSON VIEW TESTS
    // =========================================================================

    /**
     * Test: json() returns a string (the view)
     *
     * The json() method should return the rendered HTML view for
     * the JSON-based chat interface.
     */
    public function testJsonReturnsString(): void
    {
        // Act
        $result = $this->controller->json();

        // Assert
        $this->assertIsString($result);
    }

    // =========================================================================
    // JSON BACKEND TESTS
    // =========================================================================

    /**
     * Test: jsonBackend() returns a Response object
     *
     * The jsonBackend() method returns a ResponseInterface with JSON content.
     */
    public function testJsonBackendReturnsResponse(): void
    {
        // Act
        $result = $this->controller->jsonBackend();

        // Assert: Should return a Response object
        $this->assertInstanceOf(\CodeIgniter\HTTP\ResponseInterface::class, $result);
    }

    // =========================================================================
    // HTML VIEW TESTS
    // =========================================================================

    /**
     * Test: html() returns a string (the view)
     *
     * The html() method should return the rendered HTML view for
     * the non-JavaScript chat interface.
     */
    public function testHtmlReturnsString(): void
    {
        // Act
        $result = $this->controller->html();

        // Assert
        $this->assertIsString($result);
    }

    /**
     * Test: htmlBackend() returns a string
     *
     * The htmlBackend() method returns rendered HTML content.
     */
    public function testHtmlBackendReturnsString(): void
    {
        // Act
        $result = $this->controller->htmlBackend();

        // Assert
        $this->assertIsString($result);
    }

    // =========================================================================
    // VUE VIEW TESTS
    // =========================================================================

    /**
     * Test: vue() redirects when user is not logged in
     *
     * The vue() method has its own authentication check and should
     * redirect unauthenticated users to the login page.
     */
    public function testVueRedirectsWhenNotLoggedIn(): void
    {
        // Arrange: Ensure user is NOT logged in
        session()->remove('logged_in');

        // Act
        $result = $this->controller->vue();

        // Assert: Should return a redirect response
        $this->assertInstanceOf(
            \CodeIgniter\HTTP\RedirectResponse::class,
            $result,
            'vue() should redirect unauthenticated users'
        );
    }

    /**
     * Test: vue() returns view string when user IS logged in
     *
     * When authenticated, vue() should return the Vue.js chat view.
     */
    public function testVueReturnsViewWhenLoggedIn(): void
    {
        // Arrange: Simulate logged-in user
        session()->set('logged_in', true);
        session()->set('username', 'TestUser');
        session()->set('user_id', 1);

        // Act
        $result = $this->controller->vue();

        // Assert: Should return a string (the view)
        $this->assertIsString($result);
    }

    // =========================================================================
    // VUE API TESTS
    // =========================================================================

    /**
     * Test: vueApi() returns a Response object
     *
     * The vueApi() method is essentially an alias for jsonBackend().
     */
    public function testVueApiReturnsResponse(): void
    {
        // Act
        $result = $this->controller->vueApi();

        // Assert
        $this->assertInstanceOf(\CodeIgniter\HTTP\ResponseInterface::class, $result);
    }

    // =========================================================================
    // CHATMODEL INTERACTION TESTS
    // =========================================================================

    /**
     * Test: Controller uses injected ChatModel
     *
     * Verify that the controller actually uses the injected ChatModel
     * for its data retrieval.
     */
    public function testControllerUsesInjectedChatModel(): void
    {
        // Arrange: Create a mock that expects to be called
        $mockModel = $this->createMock(ChatModel::class);
        $mockModel->expects($this->atLeastOnce())
            ->method('getMsgPaginated')
            ->willReturn([
                'messages' => $this->sampleMessages,
                'pagination' => $this->samplePagination
            ]);

        $controller = new Chat($mockModel);
        $this->initializeControllerInstance($controller);

        // Act: Call a method that uses the model
        // backend() returns a Response object, which is fine - we just want
        // to verify the model was called
        $controller->backend();

        // Assert: The expectation is checked automatically by PHPUnit
        // If getMsgPaginated wasn't called, the test would fail
        $this->assertTrue(true); // Explicit assertion for clarity
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Initialize a specific controller instance
     *
     * Helper method to initialize controllers created during tests.
     *
     * @param Chat $controller The controller to initialize
     */
    private function initializeControllerInstance(Chat $controller): void
    {
        $request = new IncomingRequest(
            new App(),
            new URI('http://example.com'),
            null,
            new UserAgent()
        );

        $response = new Response(new App());
        $logger = Services::logger();

        $controller->initController($request, $response, $logger);
    }
}
