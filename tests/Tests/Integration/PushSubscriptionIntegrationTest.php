<?php

namespace Tests\Integration;

use App\Models\PushSubscriptionModel;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\IntegrationTestCase;

/**
 * @internal
 */
#[Group('integration')]
final class PushSubscriptionIntegrationTest extends IntegrationTestCase
{
    public function testRepositoryPersistsUpdatesScopesAndDeletesSubscription(): void
    {
        $firstUserId = $this->insertUser('alice', 'alice@example.test');
        $secondUserId = $this->insertUser('bob', 'bob@example.test');
        $endpoint = 'https://push.example.test/subscription/123';
        $endpointHash = hash('sha256', $endpoint);
        $model = new PushSubscriptionModel();

        $this->assertTrue($model->saveForUser($firstUserId, $this->subscription($endpoint, 'first-key')));
        $this->seeInDatabase('push_subscriptions', [
            'user_id' => $firstUserId,
            'endpoint_hash' => $endpointHash,
            'p256dh' => 'first-key',
        ]);

        $this->assertTrue($model->saveForUser($firstUserId, $this->subscription($endpoint, 'updated-key')));
        $this->seeInDatabase('push_subscriptions', [
            'user_id' => $firstUserId,
            'endpoint_hash' => $endpointHash,
            'p256dh' => 'updated-key',
        ]);
        $this->assertSame(1, $model->where('endpoint_hash', $endpointHash)->countAllResults());

        $this->assertTrue($model->deleteForUser($secondUserId, $endpointHash));
        $this->seeInDatabase('push_subscriptions', ['user_id' => $firstUserId, 'endpoint_hash' => $endpointHash]);

        $this->assertTrue($model->deleteForUser($firstUserId, $endpointHash));
        $this->dontSeeInDatabase('push_subscriptions', ['endpoint_hash' => $endpointHash]);
    }

    private function insertUser(string $username, string $email): int
    {
        $database = db_connect('tests');
        $database->table('users')->insert([
            'username' => $username,
            'email' => $email,
            'password' => password_hash('secret-password', PASSWORD_DEFAULT),
        ]);

        return (int) $database->insertID();
    }

    /** @return array{endpoint: string, endpoint_hash: string, p256dh: string, auth: string, content_encoding: string} */
    private function subscription(string $endpoint, string $key): array
    {
        return [
            'endpoint' => $endpoint,
            'endpoint_hash' => hash('sha256', $endpoint),
            'p256dh' => $key,
            'auth' => 'auth-secret',
            'content_encoding' => 'aes128gcm',
        ];
    }
}
