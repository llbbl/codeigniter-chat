<?php

namespace Tests\Feature;

use App\Contracts\PushSubscriptionRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use Tests\Support\UsesApplication;

/**
 * @internal
 */
final class PushSubscriptionsTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use UsesApplication;

    protected function tearDown(): void
    {
        Services::resetSingle('pushSubscriptionRepository');
        Services::resetSingle('response');
        parent::tearDown();
    }

    public function testAuthenticatedUserCanSaveSubscription(): void
    {
        $repository = $this->createMock(PushSubscriptionRepository::class);
        $repository->expects($this->once())->method('saveForUser')->with(
            42,
            $this->callback(static fn (array $row): bool =>
                $row['endpoint'] === 'https://push.example.test/subscription/123'
                && $row['endpoint_hash'] === hash('sha256', $row['endpoint'])
                && $row['p256dh'] === 'public-key'
                && $row['auth'] === 'auth-secret'),
        )->willReturn(true);
        Services::injectMock('pushSubscriptionRepository', $repository);

        $result = $this->jsonRequest('post', [
            'endpoint' => 'https://push.example.test/subscription/123',
            'keys' => ['p256dh' => 'public-key', 'auth' => 'auth-secret'],
        ]);

        $this->assertSame(201, $result->response()->getStatusCode());
        $this->assertSame(['success' => true], json_decode($result->getJSON(), true, flags: JSON_THROW_ON_ERROR));
    }

    public function testInvalidSubscriptionIsRejected(): void
    {
        $repository = $this->createMock(PushSubscriptionRepository::class);
        $repository->expects($this->never())->method('saveForUser');
        Services::injectMock('pushSubscriptionRepository', $repository);

        $result = $this->jsonRequest('post', [
            'endpoint' => 'http://push.example.test/insecure',
            'keys' => ['p256dh' => '', 'auth' => ''],
        ]);

        $this->assertSame(422, $result->response()->getStatusCode());
    }

    public function testAuthenticatedUserCanDeleteOwnSubscription(): void
    {
        $endpoint = 'https://push.example.test/subscription/123';
        $repository = $this->createMock(PushSubscriptionRepository::class);
        $repository->expects($this->once())->method('deleteForUser')
            ->with(42, hash('sha256', $endpoint))->willReturn(true);
        Services::injectMock('pushSubscriptionRepository', $repository);

        $result = $this->jsonRequest('delete', ['endpoint' => $endpoint]);

        $this->assertSame(204, $result->response()->getStatusCode());
    }

    public function testSubscriptionRouteRequiresAuthentication(): void
    {
        $result = $this->withHeaders(['Origin' => 'http://localhost'])
            ->call('post', '/api/v1/push-subscriptions', [csrf_token() => csrf_hash()]);

        $this->assertSame(401, $result->response()->getStatusCode());
        $result->assertHeader('Content-Type', 'application/json; charset=UTF-8');
    }

    /** @param array<string, mixed> $payload */
    private function jsonRequest(string $method, array $payload): \CodeIgniter\Test\TestResponse
    {
        return $this->withHeaders([
            'Origin' => 'http://localhost',
            'Content-Type' => 'application/json',
            'X-CSRF-TOKEN' => csrf_hash(),
        ])->withSession([
            'logged_in' => true,
            'username' => 'alice',
            'user_id' => 42,
        ])->withBody(json_encode($payload, JSON_THROW_ON_ERROR))
            ->call($method, '/api/v1/push-subscriptions');
    }
}
