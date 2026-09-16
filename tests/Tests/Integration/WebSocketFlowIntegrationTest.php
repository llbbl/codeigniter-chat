<?php

namespace Tests\Integration;

use App\Helpers\WebSocketTokenHelper;
use App\Libraries\ChatWebSocketServer;
use App\Models\ChannelModel;
use App\Models\ChatModel;
use App\Models\MessageReactionModel;
use App\Models\UserModel;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\Group;
use Ratchet\ConnectionInterface;
use Tests\Support\IntegrationTestCase;

#[Group('integration-websocket')]
final class WebSocketFlowIntegrationTest extends IntegrationTestCase
{
    public function testChannelSubscriptionsDeliverMessagesOnlyToMembersAndSignalUnread(): void
    {
        $users = new UserModel();
        $aliceId = $users->createUser('alice', 'alice-channels@example.com', 'Password123!');
        $bobId = $users->createUser('bob', 'bob-channels@example.com', 'Password123!');
        $malloryId = $users->createUser('mallory', 'mallory-channels@example.com', 'Password123!');
        $this->assertIsInt($aliceId);
        $this->assertIsInt($bobId);
        $this->assertIsInt($malloryId);

        $channels = new ChannelModel();
        $channelId = $channels->createPublic('Members', 'members-only', null, $aliceId);
        $this->assertIsInt($channelId);
        $channels->join($channelId, $bobId);

        $alice = new RecordingConnection('/?token=' . WebSocketTokenHelper::generateToken($aliceId) . "&user_id={$aliceId}");
        $bob = new RecordingConnection('/?token=' . WebSocketTokenHelper::generateToken($bobId) . "&user_id={$bobId}");
        $mallory = new RecordingConnection('/?token=' . WebSocketTokenHelper::generateToken($malloryId) . "&user_id={$malloryId}");
        $server = new ChatWebSocketServer(chatModel: new ChatModel(), users: $users, channels: $channels);

        $this->expectOutputRegex('/Chat WebSocket Server started.*New connection!.*New connection!.*New connection!/s');
        $server->onOpen($alice);
        $server->onOpen($bob);
        $server->onOpen($mallory);
        $server->onMessage($alice, json_encode(['type' => 'channel_subscribe', 'channel_id' => $channelId], JSON_THROW_ON_ERROR));
        $server->onMessage($mallory, json_encode(['type' => 'channel_subscribe', 'channel_id' => $channelId], JSON_THROW_ON_ERROR));

        $alice->sent = [];
        $bob->sent = [];
        $malloryDenied = json_decode($mallory->sent[array_key_last($mallory->sent)], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('CHANNEL_ACCESS_DENIED', $malloryDenied['data']['code']);
        $mallory->sent = [];

        $server->onMessage($alice, json_encode([
            'action' => 'sendMessage',
            'username' => 'mallory',
            'message' => 'Members can see this',
            'channel_id' => $channelId,
        ], JSON_THROW_ON_ERROR));

        $aliceMessage = json_decode($alice->sent[0], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('newMessage', $aliceMessage['action']);
        $this->assertSame('alice', $aliceMessage['data']['user']);
        $this->assertSame($channelId, $aliceMessage['data']['channel_id']);
        $bobUnread = json_decode($bob->sent[0], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(['type' => 'channel_unread', 'channel_id' => $channelId], $bobUnread);
        $this->assertSame([], $mallory->sent);

        $server->onMessage($bob, json_encode(['type' => 'channel_subscribe', 'channel_id' => $channelId], JSON_THROW_ON_ERROR));
        $server->onMessage($bob, json_encode(['action' => 'getMessages', 'channel_id' => $channelId], JSON_THROW_ON_ERROR));
        $messages = json_decode($bob->sent[array_key_last($bob->sent)], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($channelId, $messages['data']['channel_id']);
        $this->assertSame('Members can see this', $messages['data']['messages'][0]['msg']);
    }

    public function testAuthenticatedConnectionPersistsAndBroadcastsAMessage(): void
    {
        $users = new UserModel();
        $userId = $users->createUser('socketuser', 'socketuser@example.com', 'Password123!');
        $this->assertIsInt($userId);
        $token = WebSocketTokenHelper::generateToken($userId);
        $sender = new RecordingConnection("/?token={$token}&user_id={$userId}");
        $observer = new RecordingConnection("/?token={$token}&user_id={$userId}");
        $server = new ChatWebSocketServer(chatModel: new ChatModel(), users: $users);

        $this->expectOutputRegex('/Chat WebSocket Server started.*New connection!.*New connection!/s');
        $server->onOpen($sender);
        $server->onOpen($observer);
        $server->onMessage($sender, json_encode([
            'action' => 'sendMessage',
            'username' => 'spoofed-user',
            'message' => 'Broadcast integration message',
        ], JSON_THROW_ON_ERROR));

        $this->assertSame(2, $server->getClientCount());
        $this->assertTrue($server->isUserConnected($userId));
        $this->assertFalse($sender->closed);
        $this->assertMessageInDatabase('socketuser', 'Broadcast integration message');

        $broadcast = json_decode($observer->sent[array_key_last($observer->sent)], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('newMessage', $broadcast['action']);
        $this->assertSame('socketuser', $broadcast['data']['user']);
        $this->assertSame('Broadcast integration message', $broadcast['data']['msg']);
    }

    public function testGetMessagesSearchReturnsADistinguishableResult(): void
    {
        $this->hasInDatabase('messages', [
            'user' => 'socketuser',
            'msg' => 'A uniquely searchable websocket message',
            'time' => 1_700_000_000,
        ]);
        $connection = new RecordingConnection('/');
        $server = new ChatWebSocketServer(chatModel: new ChatModel(), requireAuth: false);

        $this->expectOutputRegex('/Chat WebSocket Server started.*New connection!/s');
        $server->onOpen($connection);
        $server->onMessage($connection, json_encode([
            'action' => 'getMessages',
            'requestId' => 42,
            'page' => 1,
            'perPage' => 5,
            'search' => [
                'text' => 'uniquely searchable',
                'user' => 'socketuser',
                'from' => 1_600_000_000,
                'to' => 1_800_000_000,
            ],
        ], JSON_THROW_ON_ERROR));

        $response = json_decode($connection->sent[0], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('searchResults', $response['action']);
        $this->assertSame(42, $response['data']['requestId']);
        $this->assertSame('A uniquely searchable websocket message', $response['data']['messages'][0]['msg']);
        $this->assertSame('socketuser', $response['data']['filters']['user']);
    }

    public function testGetMessagesSearchRejectsAnInvalidRange(): void
    {
        $connection = new RecordingConnection('/');
        $server = new ChatWebSocketServer(chatModel: new ChatModel(), requireAuth: false);

        $this->expectOutputRegex('/Chat WebSocket Server started.*New connection!/s');
        $server->onOpen($connection);
        $server->onMessage($connection, json_encode([
            'action' => 'getMessages',
            'search' => ['from' => 20, 'to' => 10],
        ], JSON_THROW_ON_ERROR));

        $response = json_decode($connection->sent[0], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('error', $response['action']);
        $this->assertSame('INVALID_SEARCH', $response['data']['code']);
    }

    public function testAuthenticatedTypingEventsReachTheOtherClient(): void
    {
        $users = new UserModel();
        $senderId = $users->createUser('alice', 'alice-typing@example.com', 'Password123!');
        $observerId = $users->createUser('bob', 'bob-typing@example.com', 'Password123!');
        $this->assertIsInt($senderId);
        $this->assertIsInt($observerId);
        $senderToken = WebSocketTokenHelper::generateToken($senderId);
        $observerToken = WebSocketTokenHelper::generateToken($observerId);
        $sender = new RecordingConnection("/?token={$senderToken}&user_id={$senderId}");
        $observer = new RecordingConnection("/?token={$observerToken}&user_id={$observerId}");
        $server = new ChatWebSocketServer(chatModel: new ChatModel(), users: $users);

        $this->expectOutputRegex('/Chat WebSocket Server started.*New connection!.*New connection!/s');
        $server->onOpen($sender);
        $server->onOpen($observer);
        $server->onMessage($sender, json_encode([
            'type' => 'typing_start',
            'user_id' => $senderId,
            'username' => 'Alice',
        ], JSON_THROW_ON_ERROR));

        $typingState = json_decode($observer->sent[array_key_last($observer->sent)], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('typing_state', $typingState['type']);
        $this->assertSame([['user_id' => $senderId, 'username' => 'Alice']], $typingState['users']);

        $server->onMessage($sender, json_encode([
            'type' => 'typing_stop',
            'user_id' => $senderId,
            'username' => 'Alice',
        ], JSON_THROW_ON_ERROR));
        $stoppedState = json_decode($observer->sent[array_key_last($observer->sent)], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame([], $stoppedState['users']);
    }

    public function testPresenceBroadcastsAndOnlyGoesOfflineAfterTheLastConnectionCloses(): void
    {
        $model = new UserModel();
        $aliceId = $model->createUser('alice', 'alice@example.com', 'Password123!');
        $bobId = $model->createUser('bob', 'bob@example.com', 'Password123!');
        $this->assertIsInt($aliceId);
        $this->assertIsInt($bobId);
        $model->updateProfile($aliceId, ['display_name' => 'Alice A.', 'presence' => 'away']);

        $aliceToken = WebSocketTokenHelper::generateToken($aliceId);
        $bobToken = WebSocketTokenHelper::generateToken($bobId);
        $aliceFirst = new RecordingConnection("/?token={$aliceToken}&user_id={$aliceId}");
        $aliceSecond = new RecordingConnection("/?token={$aliceToken}&user_id={$aliceId}");
        $bob = new RecordingConnection("/?token={$bobToken}&user_id={$bobId}");
        $server = new ChatWebSocketServer(chatModel: new ChatModel(), users: $model);

        $this->expectOutputRegex('/Chat WebSocket Server started.*New connection!.*New connection!.*New connection!.*has disconnected.*has disconnected/s');
        $server->onOpen($aliceFirst);
        $server->onOpen($aliceSecond);
        $server->onOpen($bob);

        $presence = json_decode($bob->sent[array_key_last($bob->sent)], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('presence_state', $presence['type']);
        $this->assertSame(['Alice A.', 'bob'], array_column($presence['users'], 'display_name'));
        $this->assertSame(['away', 'online'], array_column($presence['users'], 'presence'));

        $server->onMessage($aliceFirst, json_encode(['type' => 'presence_update', 'presence' => 'busy'], JSON_THROW_ON_ERROR));
        $presence = json_decode($bob->sent[array_key_last($bob->sent)], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('busy', $presence['users'][0]['presence']);

        $bobMessagesBeforeClose = count($bob->sent);
        $server->onClose($aliceFirst);
        $this->assertSame($bobMessagesBeforeClose, count($bob->sent), 'Closing one tab must not mark the user offline.');

        $server->onClose($aliceSecond);
        $presence = json_decode($bob->sent[array_key_last($bob->sent)], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(['bob'], array_column($presence['users'], 'username'));

        $alice = $model->findUserById($aliceId);
        $this->assertNotNull($alice);
        $this->assertSame('offline', $alice['presence']);
        $this->assertNotNull($alice['last_seen_at']);
    }

    public function testAuthenticatedReactionIsPersistedAndBroadcastWithCurrentState(): void
    {
        $users = new UserModel();
        $aliceId = $users->createUser('alice', 'alice-reaction@example.com', 'Password123!');
        $bobId = $users->createUser('bob', 'bob-reaction@example.com', 'Password123!');
        $this->assertIsInt($aliceId);
        $this->assertIsInt($bobId);
        $messageId = (new ChatModel())->insertMsg('alice', 'WebSocket reaction target', time());
        $this->assertIsInt($messageId);

        $aliceToken = WebSocketTokenHelper::generateToken($aliceId);
        $bobToken = WebSocketTokenHelper::generateToken($bobId);
        $alice = new RecordingConnection("/?token={$aliceToken}&user_id={$aliceId}");
        $bob = new RecordingConnection("/?token={$bobToken}&user_id={$bobId}");
        $reactions = new MessageReactionModel();
        $server = new ChatWebSocketServer(chatModel: new ChatModel(), users: $users, reactions: $reactions);

        $this->expectOutputRegex('/Chat WebSocket Server started.*New connection!.*New connection!/s');
        $server->onOpen($alice);
        $server->onOpen($bob);
        $server->onMessage($alice, json_encode([
            'type' => 'reaction_add',
            'message_id' => $messageId,
            'emoji' => '👍',
        ], JSON_THROW_ON_ERROR));

        $broadcast = json_decode($bob->sent[array_key_last($bob->sent)], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('reaction', $broadcast['type']);
        $this->assertSame($messageId, $broadcast['message_id']);
        $this->assertSame('👍', $broadcast['emoji']);
        $this->assertSame(1, $broadcast['count']);
        $this->assertSame(['alice'], $broadcast['users']);
        $this->assertSame(1, $reactions->where('message_id', $messageId)->countAllResults());

        $server->onMessage($alice, json_encode([
            'type' => 'reaction_remove',
            'message_id' => $messageId,
            'emoji' => '👍',
        ], JSON_THROW_ON_ERROR));
        $broadcast = json_decode($bob->sent[array_key_last($bob->sent)], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(0, $broadcast['count']);
        $this->assertSame([], $broadcast['users']);
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
