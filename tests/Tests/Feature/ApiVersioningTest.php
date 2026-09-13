<?php

namespace Tests\Feature;

use App\Contracts\ChatRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use Tests\Support\UsesApplication;

/**
 * @internal
 */
final class ApiVersioningTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use UsesApplication;

    private ChatRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createStub(ChatRepository::class);
        $this->repository->method('getMsgPaginated')->willReturn([
            'messages' => [['id' => 1, 'user' => 'alice', 'msg' => 'Hello', 'time' => 1_700_000_000]],
            'pagination' => [
                'page' => 1,
                'perPage' => 10,
                'totalItems' => 1,
                'totalPages' => 1,
                'hasNext' => false,
                'hasPrev' => false,
            ],
        ]);
        $this->repository->method('insertMsg')->willReturn(1);

        Services::injectMock('chatRepository', $this->repository);
    }

    protected function tearDown(): void
    {
        Services::resetSingle('chatRepository');
        Services::resetSingle('response');
        parent::tearDown();
    }

    public function testVersionedJsonMessagesRouteReturnsCanonicalPayload(): void
    {
        $result = $this->authenticatedGet('/api/v1/messages?page=1&per_page=10');

        $result->assertOK();
        $payload = json_decode($result->getJSON(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('alice', $payload['messages'][0]['user']);
        $this->assertSame(1, $payload['pagination']['totalItems']);
        $this->assertSame('', $result->response()->getHeaderLine('Deprecation'));
    }

    public function testVersionedXmlMessagesRouteReturnsXml(): void
    {
        $result = $this->authenticatedGet('/api/v1/messages/xml');

        $result->assertOK();
        $result->assertHeader('Content-Type', 'text/xml');
        $this->assertStringContainsString('<author>alice</author>', $result->getBody());
    }

    public function testVersionedPostCreatesMessage(): void
    {
        $result = $this->withHeaders([
            'Origin' => 'http://localhost',
        ])->withSession([
            'logged_in' => true,
            'username' => 'alice',
            'user_id' => 1,
        ])->call('post', '/api/v1/messages', [
            'message' => 'Hello from v1',
            'action' => 'postmsg',
            csrf_token() => csrf_hash(),
        ]);

        $result->assertOK();
        $this->assertSame(['success' => true], json_decode($result->getJSON(), true, flags: JSON_THROW_ON_ERROR));
    }

    public function testVersionedPostValidationFailureIsJsonWithoutClientHeaders(): void
    {
        $result = $this->withHeaders(['Origin' => 'http://localhost'])
            ->withSession(['logged_in' => true, 'username' => 'alice', 'user_id' => 1])
            ->call('post', '/api/v1/messages', [
                'message' => '',
                csrf_token() => csrf_hash(),
            ]);

        $this->assertSame(400, $result->response()->getStatusCode());
        $result->assertHeader('Content-Type', 'application/json; charset=UTF-8');
        $payload = json_decode($result->getJSON(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('validation', $payload['error']['type']);
    }

    public function testVersionedRouteReturnsJsonWhenAuthenticationIsMissing(): void
    {
        $result = $this->withHeaders(['Origin' => 'http://localhost'])
            ->call('get', '/api/v1/messages');

        $this->assertSame(401, $result->response()->getStatusCode());
        $result->assertHeader('Content-Type', 'application/json; charset=UTF-8');
        $payload = json_decode($result->getJSON(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('authentication', $payload['error']['type']);
    }

    public function testLegacyRoutesReturnV1PayloadAndDeprecationHeaders(): void
    {
        $canonical = json_decode($this->authenticatedGet('/api/v1/messages')->getJSON(), true, flags: JSON_THROW_ON_ERROR);

        foreach (['/chat/jsonBackend', '/chat/vueApi', '/chat/svelteApi'] as $legacyPath) {
            $result = $this->authenticatedGet($legacyPath);

            $result->assertOK();
            $result->assertHeader('Deprecation', 'true');
            $result->assertHeader('Sunset', 'Thu, 01 Jul 2027 00:00:00 GMT');
            $result->assertHeader('Link', '</api/v1/messages>; rel="successor-version"');
            $this->assertSame($canonical['messages'], json_decode($result->getJSON(), true, flags: JSON_THROW_ON_ERROR)['messages']);
        }
    }

    private function authenticatedGet(string $path): \CodeIgniter\Test\TestResponse
    {
        return $this->withHeaders(['Origin' => 'http://localhost'])
            ->withSession(['logged_in' => true, 'username' => 'alice', 'user_id' => 1])
            ->call('get', $path);
    }
}
