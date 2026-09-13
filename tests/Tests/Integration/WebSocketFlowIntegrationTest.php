<?php

namespace Tests\Integration;

use App\Helpers\WebSocketTokenHelper;
use App\Libraries\ChatWebSocketServer;
use App\Models\ChatModel;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\Group;
use Ratchet\ConnectionInterface;
use Tests\Support\IntegrationTestCase;

#[Group('integration-websocket')]
final class WebSocketFlowIntegrationTest extends IntegrationTestCase
{
    public function testAuthenticatedConnectionPersistsAndBroadcastsAMessage(): void
    {
        $token = WebSocketTokenHelper::generateToken(42);
        $sender = new RecordingConnection("/?token={$token}&user_id=42");
        $observer = new RecordingConnection("/?token={$token}&user_id=42");
        $server = new ChatWebSocketServer(chatModel: new ChatModel());

        $this->expectOutputRegex('/Chat WebSocket Server started.*New connection!.*New connection!/s');
        $server->onOpen($sender);
        $server->onOpen($observer);
        $server->onMessage($sender, json_encode([
            'action' => 'sendMessage',
            'username' => 'socketuser',
            'message' => 'Broadcast integration message',
        ], JSON_THROW_ON_ERROR));

        $this->assertSame(2, $server->getClientCount());
        $this->assertTrue($server->isUserConnected(42));
        $this->assertFalse($sender->closed);
        $this->assertMessageInDatabase('socketuser', 'Broadcast integration message');

        $broadcast = json_decode($observer->sent[0], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('newMessage', $broadcast['action']);
        $this->assertSame('socketuser', $broadcast['data']['user']);
        $this->assertSame('Broadcast integration message', $broadcast['data']['msg']);
    }
}

final class RecordingConnection implements ConnectionInterface
{
    public ServerRequest $httpRequest;

    /** @var list<string> */
    public array $sent = [];

    public bool $closed = false;

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
        $this->closed = true;
    }
}
