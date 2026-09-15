<?php

namespace Tests\Unit;

use App\Libraries\ChatWebSocketServer;
use App\Models\ChatModel;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Ratchet\ConnectionInterface;

final class ChatWebSocketTypingTest extends TestCase
{
    public function testTypingStateIsAuthenticatedDeduplicatedAndExpires(): void
    {
        $this->expectOutputRegex('/Chat WebSocket Server started.*New connection!.*New connection!.*New connection!/s');
        $sender = new TypingRecordingConnection('/?user_id=42');
        $observer = new TypingRecordingConnection('/?user_id=43');
        $unauthenticated = new TypingRecordingConnection('/');
        $server = new ChatWebSocketServer(chatModel: $this->createStub(ChatModel::class), requireAuth: false);

        $server->onOpen($sender);
        $server->onOpen($observer);
        $server->onOpen($unauthenticated);

        $server->onMessage($sender, $this->typingEvent('typing_start', 42, 'Alice'));
        $this->assertSame([], $sender->sent);
        $this->assertSame([['user_id' => 42, 'username' => 'Alice']], $this->typingUsers($observer->sent[0]));

        $server->onMessage($sender, $this->typingEvent('typing_start', 42, 'Alice'));
        $server->onMessage($observer, $this->typingEvent('typing_start', 99, 'Mallory'));
        $server->onMessage($unauthenticated, $this->typingEvent('typing_start', 0, 'Anonymous'));
        $this->assertCount(1, $observer->sent, 'Repeated and unauthenticated typing events must not rebroadcast state.');

        $server->pruneInactiveTypers(time() + 5);
        $this->assertSame([], $this->typingUsers($observer->sent[1]));
    }

    public function testDisconnectRemovesTypingUserImmediately(): void
    {
        $this->expectOutputRegex('/Chat WebSocket Server started.*New connection!.*New connection!.*has disconnected/s');
        $sender = new TypingRecordingConnection('/?user_id=42');
        $observer = new TypingRecordingConnection('/?user_id=43');
        $server = new ChatWebSocketServer(chatModel: $this->createStub(ChatModel::class), requireAuth: false);

        $server->onOpen($sender);
        $server->onOpen($observer);
        $server->onMessage($sender, $this->typingEvent('typing_start', 42, 'Alice'));
        $server->onClose($sender);

        $this->assertSame([], $this->typingUsers($observer->sent[1]));
    }

    private function typingEvent(string $type, int $userId, string $username): string
    {
        return json_encode([
            'type' => $type,
            'user_id' => $userId,
            'username' => $username,
        ], JSON_THROW_ON_ERROR);
    }

    /** @return list<array{user_id: int, username: string}> */
    private function typingUsers(string $payload): array
    {
        $decoded = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('typing_state', $decoded['type']);

        return $decoded['users'];
    }
}

final class TypingRecordingConnection implements ConnectionInterface
{
    public ServerRequest $httpRequest;

    /** @var list<string> */
    public array $sent = [];

    public function __construct(string $uri)
    {
        $this->httpRequest = new ServerRequest('GET', $uri);
    }

    public function send($data): ConnectionInterface
    {
        $this->sent[] = (string) $data;

        return $this;
    }

    public function close(): void
    {
    }
}
