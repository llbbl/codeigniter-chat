<?php

namespace Tests\Integration;

use App\Commands\DeliverWebhooks;
use App\Contracts\WebhookHttpClient;
use App\Models\ChannelModel;
use App\Models\ChatModel;
use App\Models\MessageReactionModel;
use App\Models\UserModel;
use App\Models\WebhookModel;
use App\Services\CodeIgniterWebhookHttpClient;
use App\Services\WebhookDeliveryService;
use App\Services\WebhookUrlValidator;
use CodeIgniter\Test\TestResponse;
use Config\Services;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\Support\IntegrationTestCase;

/**
 * @internal
 */
#[Group('integration')]
#[Group('webhooks')]
final class WebhookIntegrationTest extends IntegrationTestCase
{
    public function testAuthenticatedCrudHistoryAndRedeliveryAreOwnerScoped(): void
    {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');

        $invalid = $this->jsonRequest($alice, 'post', '/api/v1/webhooks', [
            'url' => 'ftp://example.test/hook',
            'events' => ['message.created'],
        ]);
        $this->assertSame(422, $invalid->response()->getStatusCode());

        $created = $this->jsonRequest($alice, 'post', '/api/v1/webhooks', [
            'url' => 'https://1.1.1.1/hooks/chat',
            'events' => ['message.created', 'reaction.added'],
            'active' => true,
        ]);
        $this->assertSame(201, $created->response()->getStatusCode());
        $payload = $this->json($created);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $payload['secret']);
        $webhookId = (int) $payload['webhook']['id'];

        $listed = $this->json($this->jsonRequest($alice, 'get', '/api/v1/webhooks'));
        $this->assertCount(1, $listed['webhooks']);
        $this->assertArrayNotHasKey('secret', $listed['webhooks'][0]);
        $this->assertSame(['message.created', 'reaction.added'], $listed['webhooks'][0]['events']);
        $this->assertSame([], $this->json($this->jsonRequest($bob, 'get', '/api/v1/webhooks'))['webhooks']);

        $updated = $this->jsonRequest($alice, 'patch', "/api/v1/webhooks/{$webhookId}", [
            'events' => ['channel.created'],
            'active' => false,
        ]);
        $updated->assertOK();
        $this->assertFalse($this->json($updated)['webhook']['active']);

        $deliveryId = $this->insertDelivery($webhookId, 'channel.created', 'dropped', 5);
        $denied = $this->jsonRequest($bob, 'post', "/api/v1/webhook-deliveries/{$deliveryId}/redeliver");
        $this->assertSame(404, $denied->response()->getStatusCode());
        $history = $this->json($this->jsonRequest($alice, 'get', "/api/v1/webhooks/{$webhookId}/deliveries"));
        $this->assertSame('dropped', $history['deliveries'][0]['status']);

        $redelivered = $this->jsonRequest($alice, 'post', "/api/v1/webhook-deliveries/{$deliveryId}/redeliver");
        $this->assertSame(202, $redelivered->response()->getStatusCode());
        $this->seeInDatabase('webhook_deliveries', ['id' => $deliveryId, 'status' => 'pending', 'attempts' => 0]);

        $deleted = $this->jsonRequest($alice, 'delete', "/api/v1/webhooks/{$webhookId}");
        $this->assertSame(204, $deleted->response()->getStatusCode());
        $this->dontSeeInDatabase('webhooks', ['id' => $webhookId]);
        $this->dontSeeInDatabase('webhook_deliveries', ['id' => $deliveryId]);

        $privateTarget = $this->jsonRequest($alice, 'post', '/api/v1/webhooks', [
            'url' => 'https://10.0.0.1/hooks/chat',
            'events' => ['message.created'],
        ]);
        $this->assertSame(422, $privateTarget->response()->getStatusCode());
    }

    public function testMessageReactionAndChannelCreationEnqueueSubscribedEvents(): void
    {
        $alice = $this->createUser('alice');
        $webhooks = new WebhookModel();
        $webhookId = $this->createWebhook($webhooks, (int) $alice['id'], [
            'message.created',
            'reaction.added',
            'channel.created',
        ]);

        $channels = new ChannelModel(webhooks: $webhooks);
        $channelId = $channels->createPublic('Webhook Room', 'webhook-room', null, (int) $alice['id']);
        $this->assertIsInt($channelId);
        $messages = new ChatModel(webhooks: $webhooks);
        $messageId = $messages->insertMsg('alice', 'Dispatch all the things', 1_700_000_000, $channelId);
        $this->assertIsInt($messageId);
        $reactions = new MessageReactionModel(webhooks: $webhooks);
        $this->assertTrue($reactions->add($messageId, (int) $alice['id'], '🎉'));
        $this->assertTrue($reactions->add($messageId, (int) $alice['id'], '🎉'));

        $deliveries = db_connect('tests')->table('webhook_deliveries')
            ->select('event, payload')
            ->where('webhook_id', $webhookId)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();
        $this->assertSame(['channel.created', 'message.created', 'reaction.added'], array_column($deliveries, 'event'));
        $this->assertCount(3, $deliveries, 'Idempotent reaction writes must not enqueue duplicate events.');
        $messagePayload = json_decode((string) $deliveries[1]['payload'], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($messageId, $messagePayload['data']['message']['id']);
    }

    public function testDeliveryPostsVerifiableSignatureToLocalHttpEndpoint(): void
    {
        $alice = $this->createUser('alice');
        $webhooks = new WebhookModel();
        $captureFile = tempnam(sys_get_temp_dir(), 'webhook-capture-');
        $this->assertIsString($captureFile);
        [$process, $port] = $this->startReceiver($captureFile);

        try {
            $webhookId = $this->createWebhook(
                $webhooks,
                (int) $alice['id'],
                ['message.created'],
                "http://127.0.0.1:{$port}/receive",
                'test-signing-secret',
            );
            $this->assertSame(1, $webhooks->enqueue('message.created', ['message' => ['id' => 42]]));
            $client = new CodeIgniterWebhookHttpClient(Services::curlrequest([], null, null, false));
            $result = (new WebhookDeliveryService(
                $webhooks,
                $client,
                static fn (): int => 1_700_000_000,
                new WebhookUrlValidator(allowHttpLoopback: true),
            ))->deliver();
            $this->assertSame(['processed' => 1, 'delivered' => 1, 'retrying' => 0, 'dropped' => 0], $result);
            $this->seeInDatabase('webhook_deliveries', ['webhook_id' => $webhookId, 'status' => 'delivered', 'attempts' => 1]);

            $capture = json_decode((string) file_get_contents($captureFile), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('message.created', $capture['headers']['x-webhook-event']);
            $this->assertSame('1700000000', $capture['headers']['x-webhook-timestamp']);
            $expected = 'v1=' . hash_hmac('sha256', '1700000000.' . $capture['body'], 'test-signing-secret');
            $this->assertSame($expected, $capture['headers']['x-webhook-signature']);
        } finally {
            proc_terminate($process);
            proc_close($process);
            unlink($captureFile);
        }
    }

    public function testPrivateChannelEventsOnlyReachVisibleUsers(): void
    {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');
        $charlie = $this->createUser('charlie');
        $webhooks = new WebhookModel();
        $aliceWebhook = $this->createWebhook($webhooks, (int) $alice['id'], ['message.created', 'reaction.added']);
        $bobWebhook = $this->createWebhook($webhooks, (int) $bob['id'], ['message.created', 'reaction.added']);

        $channels = new ChannelModel(webhooks: $webhooks);
        $dmId = $channels->findOrCreateDm((int) $alice['id'], (int) $charlie['id']);
        $this->assertIsInt($dmId);
        $messages = new ChatModel(webhooks: $webhooks);
        $messageId = $messages->insertMsg('alice', 'Private conversation', 1_700_000_000, $dmId);
        $this->assertIsInt($messageId);
        $reactions = new MessageReactionModel(webhooks: $webhooks);
        $this->assertTrue($reactions->add($messageId, (int) $charlie['id'], '🔒'));

        $publicId = $channels->createPublic('Members only', 'members-only', null, (int) $alice['id']);
        $this->assertIsInt($publicId);
        $publicMessageId = $messages->insertMsg('alice', 'Join before reading', 1_700_000_001, $publicId);
        $this->assertIsInt($publicMessageId);

        $this->assertSame(3, db_connect('tests')->table('webhook_deliveries')->where('webhook_id', $aliceWebhook)->countAllResults());
        $this->assertSame(0, db_connect('tests')->table('webhook_deliveries')->where('webhook_id', $bobWebhook)->countAllResults());
    }

    public function testDeliveryRejectsHostnamesResolvingToPrivateAddresses(): void
    {
        $validator = new WebhookUrlValidator(static fn (string $host): array => ['10.0.0.1'], false);
        $this->assertFalse($validator->isAllowed('https://internal.example/hooks/chat'));

        $alice = $this->createUser('alice');
        $webhooks = new WebhookModel();
        $webhookId = $this->createWebhook(
            $webhooks,
            (int) $alice['id'],
            ['message.created'],
            'https://internal.example/hooks/chat',
        );
        $webhooks->enqueue('message.created', ['message' => ['id' => 99]]);
        $calls = 0;
        $client = new class($calls) implements WebhookHttpClient {
            public function __construct(private int &$calls)
            {
            }

            public function send(string $url, string $payload, array $headers): array
            {
                ++$this->calls;

                return ['status' => 204, 'body' => ''];
            }
        };
        $result = (new WebhookDeliveryService($webhooks, $client, urlValidator: $validator))->deliver();

        $this->assertSame(0, $calls);
        $this->assertSame(1, $result['dropped']);
        $this->seeInDatabase('webhook_deliveries', ['webhook_id' => $webhookId, 'status' => 'dropped', 'attempts' => 5]);
    }

    public function testFailuresUseExponentialBackoffAndDropAfterFiveAttempts(): void
    {
        $alice = $this->createUser('alice');
        $webhooks = new WebhookModel();
        $webhookId = $this->createWebhook($webhooks, (int) $alice['id'], ['message.created']);
        $webhooks->enqueue('message.created', ['message' => ['id' => 7]]);
        $client = new class implements WebhookHttpClient {
            public function send(string $url, string $payload, array $headers): array
            {
                return ['status' => 503, 'body' => 'unavailable'];
            }
        };
        $service = new WebhookDeliveryService($webhooks, $client, static fn (): int => 1_700_000_000);

        $selected = $webhooks->dueDeliveries()[0];
        $this->assertTrue($webhooks->claimDelivery(
            (int) $selected['id'],
            (string) $selected['status'],
            (int) $selected['attempts'],
            null,
            '2000-01-01 00:00:00',
        ));
        $this->assertFalse($webhooks->claimDelivery(
            (int) $selected['id'],
            (string) $selected['status'],
            (int) $selected['attempts'],
            null,
            '2000-01-01 00:00:00',
        ));
        $expired = $webhooks->dueDeliveries()[0];
        $this->assertSame('processing', $expired['status']);
        $this->assertTrue($webhooks->claimDelivery(
            (int) $expired['id'],
            (string) $expired['status'],
            (int) $expired['attempts'],
            (string) $expired['next_attempt_at'],
            '2000-01-01 00:01:00',
        ));
        $this->assertFalse($webhooks->claimDelivery(
            (int) $expired['id'],
            (string) $expired['status'],
            (int) $expired['attempts'],
            (string) $expired['next_attempt_at'],
            '2000-01-01 00:01:00',
        ));

        $this->assertSame(60, WebhookDeliveryService::backoffSeconds(1));
        $this->assertSame(120, WebhookDeliveryService::backoffSeconds(2));
        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $result = $service->deliver();
            $this->assertSame(1, $result['processed']);
            if ($attempt < 5) {
                $this->assertSame(1, $result['retrying']);
                db_connect('tests')->table('webhook_deliveries')->where('webhook_id', $webhookId)->update([
                    'next_attempt_at' => '2000-01-01 00:00:00',
                ]);
            }
        }

        $this->seeInDatabase('webhook_deliveries', ['webhook_id' => $webhookId, 'status' => 'dropped', 'attempts' => 5]);
    }

    public function testOversizedPayloadIsNotEnqueuedAndCommandLimitIsValidated(): void
    {
        $alice = $this->createUser('alice');
        $webhooks = new WebhookModel();
        $this->createWebhook($webhooks, (int) $alice['id'], ['message.created']);

        $this->assertSame(0, $webhooks->enqueue('message.created', ['message' => str_repeat('x', WebhookModel::MAX_PAYLOAD_BYTES)]));
        $this->assertSame(25, DeliverWebhooks::parseLimit('25'));
        $this->assertNull(DeliverWebhooks::parseLimit('0'));
        $this->assertNull(DeliverWebhooks::parseLimit('101'));
    }

    /** @return array<string, mixed> */
    private function createUser(string $username): array
    {
        $model = new UserModel();
        $id = $model->createUser($username, "{$username}@example.com", 'Password123!');
        $this->assertIsInt($id);
        $user = $model->findUserById($id);
        $this->assertIsArray($user);

        return $user;
    }

    /** @param list<string> $events */
    private function createWebhook(
        WebhookModel $model,
        int $userId,
        array $events,
        string $url = 'https://1.1.1.1/hooks/chat',
        string $secret = 'secret',
    ): int {
        $id = $model->createForUser($userId, [
            'url' => $url,
            'url_hash' => hash('sha256', $url),
            'secret' => $secret,
            'events' => json_encode($events, JSON_THROW_ON_ERROR),
            'active' => true,
        ]);
        $this->assertIsInt($id);

        return $id;
    }

    private function insertDelivery(int $webhookId, string $event, string $status, int $attempts): int
    {
        $database = db_connect('tests');
        $database->table('webhook_deliveries')->insert([
            'webhook_id' => $webhookId,
            'event' => $event,
            'payload' => '{}',
            'status' => $status,
            'attempts' => $attempts,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $database->insertID();
    }

    /** @return array{resource, int} */
    private function startReceiver(string $captureFile): array
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        if ($socket === false) {
            throw new RuntimeException("Unable to reserve a local port: {$errorMessage} ({$errorCode}).");
        }
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        if (! is_string($address)) {
            throw new RuntimeException('Unable to read the local test receiver address.');
        }
        $port = (int) substr($address, strrpos($address, ':') + 1);
        $process = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:{$port}", TESTPATH . '_support/WebhookReceiver.php'],
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes,
            ROOTPATH,
            [...getenv(), 'WEBHOOK_CAPTURE_FILE' => $captureFile],
        );
        if (! is_resource($process)) {
            throw new RuntimeException('Unable to start the local webhook receiver.');
        }
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }
        for ($attempt = 0; $attempt < 50; ++$attempt) {
            $connection = @fsockopen('127.0.0.1', $port);
            if (is_resource($connection)) {
                fclose($connection);

                return [$process, $port];
            }
            usleep(20_000);
        }
        proc_terminate($process);
        proc_close($process);

        throw new RuntimeException('The local webhook receiver did not start.');
    }

    /** @param array<string, mixed> $user @param array<string, mixed> $payload */
    private function jsonRequest(array $user, string $method, string $path, array $payload = []): TestResponse
    {
        $test = $this->loginAs($user)->withHeaders([
            'Origin' => 'http://localhost',
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-CSRF-TOKEN' => csrf_hash(),
        ]);
        if ($payload !== []) {
            $test->withBody(json_encode($payload, JSON_THROW_ON_ERROR));
        }

        return $test->call($method, $path);
    }

    /** @return array<string, mixed> */
    private function json(TestResponse $response): array
    {
        return json_decode($response->getJSON(), true, flags: JSON_THROW_ON_ERROR);
    }
}
