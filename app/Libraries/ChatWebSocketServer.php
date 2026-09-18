<?php

namespace App\Libraries;

use App\Contracts\ChannelRepository;
use App\Contracts\ReactionRepository;
use App\Contracts\UserRepository;
use App\Helpers\WebSocketTokenHelper;
use App\Models\ChannelModel;
use App\Models\ChatModel;
use App\Models\MessageReactionModel;
use App\Models\UserModel;
use CodeIgniter\I18n\Time;
use Config\Services;
use Exception;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use SplObjectStorage;

/**
 * Chat WebSocket Server
 *
 * Handles WebSocket connections and messages for the chat application.
 * This server runs as a standalone process (via `php spark websocket:start`)
 * and handles real-time communication between chat clients.
 *
 * ============================================================================
 * AUTHENTICATION FLOW
 * ============================================================================
 *
 * WebSocket connections are authenticated using token-based authentication:
 *
 * 1. User logs into the web application (standard PHP session auth)
 * 2. A WebSocket token is generated and stored in their session
 * 3. The Vue.js frontend receives this token
 * 4. When connecting to WebSocket, the client includes the token in the URL:
 *    ws://localhost:8080?token=abc123&user_id=1
 * 5. This server validates the token in onOpen() before accepting the connection
 * 6. Invalid or missing tokens result in immediate connection closure
 *
 * Why Token Authentication?
 * -------------------------
 * The WebSocket server runs as a separate process from the web server.
 * It cannot access PHP sessions or cookies directly. Tokens provide a way
 * to verify that a WebSocket connection belongs to an authenticated user.
 *
 * ============================================================================
 */
class ChatWebSocketServer implements MessageComponentInterface
{
    private const TYPING_TTL_SECONDS = 5;

    /**
     * Connected clients storage
     *
     * SplObjectStorage is a special PHP data structure that uses objects as keys.
     * This allows us to store metadata (like user_id) for each connection.
     *
     * @var SplObjectStorage
     */
    protected SplObjectStorage $clients;

    /**
     * Chat model instance for database operations
     *
     * @var ChatModel
     */
    protected ChatModel $chatModel;

    /**
     * Whether authentication is required for connections
     *
     * Set to false to allow unauthenticated connections (useful for testing).
     * In production, this should always be true.
     *
     * @var bool
     */
    protected bool $requireAuth;

    private readonly UserRepository $users;

    private readonly ReactionRepository $reactions;

    private readonly ChannelRepository $channels;

    /** @var array<int, list<int>> */
    private array $reactionHistory = [];

    /** @var array<int, array{user_id: int, username: string, display_name: string, avatar_url: ?string, presence: string, last_seen_at: ?string}> */
    private array $presenceUsers = [];

    /**
     * Ephemeral typing state keyed by authenticated user ID.
     *
     * @var array<int, array{user_id: int, username: string, channel_id: int, last_typed_at: int}>
     */
    private array $typingUsers = [];

    /**
     * Constructor
     *
     * Initializes the WebSocket server with its dependencies.
     * Uses dependency injection to allow for easier testing.
     *
     * @param SplObjectStorage|null $clients     Optional: Pre-configured client storage
     * @param ChatModel|null        $chatModel   Optional: Pre-configured chat model
     * @param bool                  $requireAuth Whether to require authentication (default: true)
     */
    public function __construct(
        ?SplObjectStorage $clients = null,
        ?ChatModel $chatModel = null,
        bool $requireAuth = true,
        ?UserRepository $users = null,
        ?ReactionRepository $reactions = null,
        ?ChannelRepository $channels = null,
    ) {
        $this->clients = $clients ?? new SplObjectStorage();
        $this->chatModel = $chatModel ?? new ChatModel(webhooks: Services::webhookRepository());
        $this->requireAuth = $requireAuth;
        $this->users = $users ?? new UserModel();
        $this->reactions = $reactions ?? new MessageReactionModel(webhooks: Services::webhookRepository());
        $this->channels = $channels ?? new ChannelModel(webhooks: Services::webhookRepository());

        $this->logServerStart();
    }

    /**
     * Handle a new WebSocket connection
     *
     * This method is called by Ratchet when a client connects.
     * We validate the authentication token here before accepting the connection.
     *
     * Connection Flow:
     * 1. Parse the query parameters from the connection URL
     * 2. Extract the token and user_id
     * 3. Validate the token using WebSocketTokenHelper
     * 4. If valid, store the connection with user metadata
     * 5. If invalid, close the connection with an error
     *
     * @param ConnectionInterface $conn The new connection
     *
     * @return void
     */
    public function onOpen(ConnectionInterface $conn): void
    {
        // Parse query parameters from the WebSocket URL
        // The URL looks like: ws://localhost:8080?token=abc123&user_id=1
        $queryParams = $this->parseQueryParams($conn);

        // Extract authentication parameters
        $token = $queryParams['token'] ?? '';
        $userId = (int) ($queryParams['user_id'] ?? 0);

        // Validate the token if authentication is required
        if ($this->requireAuth) {
            if (!$this->authenticateConnection($token, $userId, $conn)) {
                // Authentication failed - connection will be closed
                return;
            }
        }

        // Store the connection with metadata
        // We use SplObjectStorage->attach() to associate data with the connection
        $channelId = $userId > 0 ? $this->channels->ensureGeneralMembership($userId) : $this->channels->generalChannelId();
        $this->clients->attach($conn, [
            'user_id'      => $userId,
            'connected_at' => time(),
            'authenticated' => $this->requireAuth ? true : ($userId > 0),
            'channel_id' => $channelId,
        ]);

        if ($this->requireAuth) {
            $this->connectPresence($userId);
        }

        // Log successful connection
        $authStatus = $this->requireAuth ? "(authenticated, user_id: {$userId})" : '(auth disabled)';
        echo 'New connection! (' . spl_object_id($conn) . ") {$authStatus}\n";
    }

    /**
     * Handle incoming messages from clients
     *
     * This method processes messages received from connected clients.
     * Messages are expected to be JSON with an 'action' field indicating
     * what operation to perform.
     *
     * Supported actions:
     * - getMessages: Retrieve chat messages with pagination
     * - sendMessage: Post a new chat message
     *
     * @param ConnectionInterface $from The connection that sent the message
     * @param string              $msg  The message content (JSON string)
     *
     * @return void
     */
    public function onMessage(ConnectionInterface $from, $msg)
    {
        // Parse the JSON message
        $data = json_decode($msg, true);

        // Validate message format
        if (! is_array($data) || (! isset($data['action']) && ! isset($data['type']))) {
            return;
        }

        // Get the user info stored with this connection
        $connectionData = $this->clients[$from] ?? [];
        $userId = $connectionData['user_id'] ?? 0;

        // Typing events use `type`; existing chat operations keep `action`.
        $messageType = $data['type'] ?? $data['action'];

        switch ($messageType) {
            case 'channel_subscribe':
                $this->handleChannelSubscribe($from, $data);
                break;

            case 'getMessages':
                $this->handleGetMessages($from, $data);
                break;

            case 'sendMessage':
                $this->handleSendMessage($from, $data, $userId);
                break;

            case 'typing_start':
            case 'typing_stop':
                $this->handleTypingEvent($from, $data, $messageType);
                break;

            case 'presence_update':
                $this->handlePresenceUpdate($from, $data);
                break;

            case 'reaction_add':
            case 'reaction_remove':
                $this->handleReaction($from, $data, $messageType);
                break;
        }
    }

    /**
     * Handle connection close
     *
     * Called when a client disconnects. We clean up by removing
     * the connection from our storage.
     *
     * @param ConnectionInterface $conn The connection that closed
     *
     * @return void
     */
    public function onClose(ConnectionInterface $conn): void
    {
        // Get user info before removing
        $connectionData = $this->clients[$conn] ?? [];
        $userId = $connectionData['user_id'] ?? 'unknown';

        // Remove the connection from our storage
        $this->clients->detach($conn);

        if (is_int($userId) && ! $this->isUserConnected($userId) && isset($this->typingUsers[$userId])) {
            $channelId = $this->typingUsers[$userId]['channel_id'];
            unset($this->typingUsers[$userId]);
            $this->broadcastTypingState(channelId: $channelId);
        }

        if (is_int($userId) && $userId > 0 && ! $this->isUserConnected($userId) && isset($this->presenceUsers[$userId])) {
            unset($this->presenceUsers[$userId]);
            $this->users->updatePresence($userId, 'offline', date('Y-m-d H:i:s'));
            $this->broadcastPresenceState();
        }

        echo 'Connection ' . spl_object_id($conn) . " (user_id: {$userId}) has disconnected\n";
    }

    /**
     * Handle connection errors
     *
     * Called when an error occurs on a connection. We log the error
     * and close the connection.
     *
     * @param ConnectionInterface $conn The connection that errored
     * @param Exception           $e    The exception that occurred
     *
     * @return void
     */
    public function onError(ConnectionInterface $conn, Exception $e): void
    {
        echo "An error has occurred: {$e->getMessage()}\n";

        $conn->close();
    }

    /**
     * Get the number of currently connected clients
     *
     * Useful for monitoring and debugging.
     *
     * @return int The number of connected clients
     */
    public function getClientCount(): int
    {
        return $this->clients->count();
    }

    /**
     * Check if a specific user is connected
     *
     * @param int $userId The user ID to check
     *
     * @return bool True if the user has an active connection
     */
    public function isUserConnected(int $userId): bool
    {
        foreach ($this->clients as $client) {
            $data = $this->clients[$client];
            if (($data['user_id'] ?? 0) === $userId) {
                return true;
            }
        }
        return false;
    }

    /**
     * Remove typing entries that have not been refreshed recently.
     *
     * The React event loop calls this once per second. The optional timestamp
     * keeps the expiry behavior deterministic in tests.
     */
    public function pruneInactiveTypers(?int $now = null): void
    {
        $now ??= time();
        $changedChannels = [];

        foreach ($this->typingUsers as $userId => $typingUser) {
            if ($now - $typingUser['last_typed_at'] < self::TYPING_TTL_SECONDS) {
                continue;
            }

            unset($this->typingUsers[$userId]);
            $changedChannels[$typingUser['channel_id']] = true;
        }

        foreach (array_keys($changedChannels) as $channelId) {
            $this->broadcastTypingState(channelId: $channelId);
        }
    }

    /**
     * Log server startup message
     *
     * @return void
     */
    private function logServerStart(): void
    {
        echo "Chat WebSocket Server started\n";
        echo 'Authentication: ' . ($this->requireAuth ? 'ENABLED' : 'DISABLED') . "\n";
    }

    /**
     * Parse query parameters from the WebSocket connection URL
     *
     * Ratchet provides access to the HTTP request that initiated the WebSocket
     * handshake. We use this to extract query parameters.
     *
     * @param ConnectionInterface $conn The connection to parse
     *
     * @return array Associative array of query parameters
     */
    private function parseQueryParams(ConnectionInterface $conn): array
    {
        $queryParams = [];

        // The httpRequest property contains the HTTP request that initiated the WebSocket
        if (isset($conn->httpRequest)) {
            $uri = $conn->httpRequest->getUri();
            $queryString = $uri->getQuery();

            // Parse the query string into an associative array
            // Example: "token=abc123&user_id=1" becomes ['token' => 'abc123', 'user_id' => '1']
            parse_str($queryString, $queryParams);
        }

        return $queryParams;
    }

    /**
     * Authenticate a WebSocket connection
     *
     * This method validates the provided token and user_id combination.
     * If authentication fails, it sends an error message and closes the connection.
     *
     * @param string              $token  The authentication token
     * @param int                 $userId The claimed user ID
     * @param ConnectionInterface $conn   The connection to authenticate
     *
     * @return bool True if authenticated, false if rejected
     */
    private function authenticateConnection(string $token, int $userId, ConnectionInterface $conn): bool
    {
        // Check if token and user_id were provided
        if (empty($token) || $userId <= 0) {
            $this->rejectConnection($conn, 'Missing authentication credentials');
            return false;
        }

        // Validate the token using our helper
        // This checks:
        // 1. Token exists in storage
        // 2. Token belongs to the claimed user
        // 3. Token hasn't expired
        if (!WebSocketTokenHelper::validateToken($token, $userId)) {
            $this->rejectConnection($conn, 'Invalid or expired token');
            return false;
        }

        return true;
    }

    /**
     * Reject a connection with an error message
     *
     * Sends an error message to the client before closing the connection.
     * This helps the client understand why the connection was rejected.
     *
     * @param ConnectionInterface $conn   The connection to reject
     * @param string              $reason The reason for rejection
     *
     * @return void
     */
    private function rejectConnection(ConnectionInterface $conn, string $reason): void
    {
        echo "Connection rejected: {$reason}\n";

        // Send error message to the client
        $conn->send(json_encode([
            'action' => 'error',
            'data'   => [
                'message' => $reason,
                'code'    => 'AUTH_FAILED',
            ],
        ]));

        // Close the connection
        $conn->close();
    }

    /**
     * Handle the getMessages action
     *
     * Retrieves paginated chat messages and sends them to the requesting client.
     *
     * @param ConnectionInterface $from The connection requesting messages
     * @param array               $data The request data containing page and perPage
     *
     * @return void
     */
    private function handleGetMessages(ConnectionInterface $from, array $data): void
    {
        $page = $data['page'] ?? 1;
        $perPage = $data['perPage'] ?? 10;
        $requestId = isset($data['requestId']) && is_int($data['requestId']) ? $data['requestId'] : null;
        $channelId = $this->authorizedChannelId($from, $data['channel_id'] ?? null);
        if ($channelId === null) {
            $this->sendChannelError($from, 'CHANNEL_ACCESS_DENIED', 'Channel membership is required.');

            return;
        }

        if (isset($data['search'])) {
            if (! is_array($data['search'])) {
                $this->sendSearchError($from, 'The search field must be an object.', $requestId);

                return;
            }

            $search = $this->validatedSearchFilters($data['search']);
            if (isset($search['error'])) {
                $this->sendSearchError($from, $search['error'], $requestId);

                return;
            }

            $result = $this->chatModel->searchMessages(
                $search['filters']['text'],
                $search['filters']['user'],
                $search['filters']['from'],
                $search['filters']['to'],
                is_int($page) ? max(1, $page) : 1,
                is_int($perPage) ? min(100, max(1, $perPage)) : 10,
                $channelId,
            );

            $from->send(json_encode([
                'action' => 'searchResults',
                'data' => [
                    'requestId' => $requestId,
                    'messages' => $result['messages'],
                    'pagination' => $result['pagination'],
                    'filters' => $search['filters'],
                    'channel_id' => $channelId,
                ],
            ]));

            return;
        }

        // Fetch messages from the database
        $result = $this->chatModel->getMsgPaginated(
            is_int($page) ? max(1, $page) : 1,
            is_int($perPage) ? min(100, max(1, $perPage)) : 10,
            $channelId,
        );
        $connectionData = $this->clients[$from] ?? [];
        $userId = $connectionData['user_id'] ?? 0;
        if (is_int($userId) && $userId > 0) {
            $this->channels->markRead($channelId, $userId);
        }

        // Send messages back to the client
        $from->send(json_encode([
            'action' => 'messages',
            'data' => [
                'messages'   => $result['messages'],
                'pagination' => $result['pagination'],
                'channel_id' => $channelId,
            ],
        ]));
    }

    /**
     * @param array<string, mixed> $search
     *
     * @return array{filters?: array{text: ?string, user: ?string, from: ?int, to: ?int}, error?: string}
     */
    private function validatedSearchFilters(array $search): array
    {
        $filters = ['text' => null, 'user' => null, 'from' => null, 'to' => null];

        foreach (['text' => 500, 'user' => 255] as $name => $maxLength) {
            if (! array_key_exists($name, $search)) {
                continue;
            }
            if (! is_string($search[$name]) || trim($search[$name]) === '') {
                return ['error' => "The {$name} filter must be a non-empty string."];
            }
            $value = trim($search[$name]);
            if (mb_strlen($value) > $maxLength) {
                return ['error' => "The {$name} filter is too long."];
            }
            $filters[$name] = $value;
        }

        foreach (['from', 'to'] as $name) {
            if (! array_key_exists($name, $search)) {
                continue;
            }
            if (! is_int($search[$name]) || $search[$name] < 0) {
                return ['error' => "The {$name} filter must be a non-negative Unix timestamp."];
            }
            $filters[$name] = $search[$name];
        }

        if ($filters['text'] === null && $filters['user'] === null && $filters['from'] === null && $filters['to'] === null) {
            return ['error' => 'Provide at least one search filter.'];
        }
        if ($filters['from'] !== null && $filters['to'] !== null && $filters['from'] > $filters['to']) {
            return ['error' => 'The from timestamp must be less than or equal to to.'];
        }

        return ['filters' => $filters];
    }

    private function sendSearchError(ConnectionInterface $from, string $message, ?int $requestId = null): void
    {
        $from->send(json_encode([
            'action' => 'error',
            'data' => [
                'requestId' => $requestId,
                'message' => $message,
                'code' => 'INVALID_SEARCH',
            ],
        ]));
    }

    /** @param array<string, mixed> $data */
    private function handleTypingEvent(ConnectionInterface $from, array $data, string $messageType): void
    {
        if (! $this->clients->contains($from)) {
            return;
        }

        $connectionData = $this->clients[$from];
        $authenticatedUserId = $connectionData['user_id'] ?? 0;
        $claimedUserId = $data['user_id'] ?? null;
        $username = $data['username'] ?? null;
        $channelId = $connectionData['channel_id'] ?? 0;

        if (
            ($connectionData['authenticated'] ?? false) !== true
            || ! is_int($authenticatedUserId)
            || $authenticatedUserId <= 0
            || ! is_int($claimedUserId)
            || $claimedUserId !== $authenticatedUserId
            || ! is_string($username)
            || trim($username) === ''
            || mb_strlen($username) > 255
            || ! is_int($channelId)
            || ! $this->channels->isMember($channelId, $authenticatedUserId)
        ) {
            return;
        }

        if ($messageType === 'typing_stop') {
            if (! isset($this->typingUsers[$authenticatedUserId])) {
                return;
            }

            unset($this->typingUsers[$authenticatedUserId]);
            $this->broadcastTypingState($from, $channelId);

            return;
        }

        $username = trim($username);
        $changed = ! isset($this->typingUsers[$authenticatedUserId])
            || $this->typingUsers[$authenticatedUserId]['username'] !== $username;
        $this->typingUsers[$authenticatedUserId] = [
            'user_id' => $authenticatedUserId,
            'username' => $username,
            'channel_id' => $channelId,
            'last_typed_at' => time(),
        ];

        if ($changed) {
            $this->broadcastTypingState($from, $channelId);
        }
    }

    private function broadcastTypingState(?ConnectionInterface $except = null, ?int $channelId = null): void
    {
        if ($channelId === null && $except !== null && $this->clients->contains($except)) {
            $channelId = $this->clients[$except]['channel_id'] ?? null;
        }
        $users = array_values(array_map(
            static fn (array $typingUser): array => [
                'user_id' => $typingUser['user_id'],
                'username' => $typingUser['username'],
            ],
            array_filter(
                $this->typingUsers,
                static fn (array $typingUser): bool => $channelId === null || $typingUser['channel_id'] === $channelId,
            ),
        ));

        usort($users, static fn (array $left, array $right): int => [$left['username'], $left['user_id']] <=> [$right['username'], $right['user_id']]);
        $payload = json_encode(['type' => 'typing_state', 'users' => $users]);

        foreach ($this->clients as $client) {
            if ($except !== null && $client === $except) {
                continue;
            }
            if ($channelId !== null && ($this->clients[$client]['channel_id'] ?? null) !== $channelId) {
                continue;
            }

            $client->send($payload);
        }
    }

    private function connectPresence(int $userId): void
    {
        $user = $this->users->findUserById($userId);
        if ($user === null) {
            return;
        }

        $presence = in_array($user['presence'] ?? null, ['away', 'busy'], true) ? $user['presence'] : 'online';
        $this->users->updatePresence($userId, $presence, null);
        $this->presenceUsers[$userId] = $this->presenceProfile($user, $presence, null);
        $this->broadcastPresenceState();
    }

    /** @param array<string, mixed> $data */
    private function handlePresenceUpdate(ConnectionInterface $from, array $data): void
    {
        if (! $this->clients->contains($from)) {
            return;
        }

        $connectionData = $this->clients[$from];
        $userId = $connectionData['user_id'] ?? 0;
        $presence = $data['presence'] ?? null;
        if (($connectionData['authenticated'] ?? false) !== true || ! is_int($userId) || ! in_array($presence, ['online', 'away', 'busy'], true)) {
            return;
        }

        $user = $this->users->findUserById($userId);
        if ($user === null) {
            return;
        }

        $this->users->updatePresence($userId, $presence, null);
        $this->presenceUsers[$userId] = $this->presenceProfile($user, $presence, null);
        $this->broadcastPresenceState();
    }

    private function broadcastPresenceState(): void
    {
        $users = array_values($this->presenceUsers);
        usort($users, static fn (array $left, array $right): int => [$left['display_name'], $left['user_id']] <=> [$right['display_name'], $right['user_id']]);
        $payload = json_encode(['type' => 'presence_state', 'users' => $users]);

        foreach ($this->clients as $client) {
            $client->send($payload);
        }
    }

    /** @param array<string, mixed> $user
     * @return array{user_id: int, username: string, display_name: string, avatar_url: ?string, presence: string, last_seen_at: ?string}
     */
    private function presenceProfile(array $user, string $presence, ?string $lastSeenAt): array
    {
        $userId = (int) $user['id'];

        return [
            'user_id' => $userId,
            'username' => (string) $user['username'],
            'display_name' => (string) ($user['display_name'] ?: $user['username']),
            'avatar_url' => $user['avatar_path'] ? "/profile/avatar/{$userId}" : null,
            'presence' => $presence,
            'last_seen_at' => $lastSeenAt,
        ];
    }

    /**
     * Handle the sendMessage action
     *
     * Validates the message, saves it to the database, and broadcasts
     * it to all connected clients.
     *
     * @param ConnectionInterface $from   The connection sending the message
     * @param array               $data   The message data containing username and message
     * @param int                 $userId The authenticated user's ID
     *
     * @return void
     */
    private function handleSendMessage(ConnectionInterface $from, array $data, int $userId): void
    {
        if (! $this->clients->contains($from)) {
            return;
        }

        $connectionData = $this->clients[$from];
        $channelId = $this->authorizedChannelId($from, $data['channel_id'] ?? null);
        $message = $data['message'] ?? null;
        $user = $this->users->findUserById($userId);
        if (
            ($connectionData['authenticated'] ?? false) !== true
            || $channelId === null
            || $user === null
            || ! is_string($message)
            || trim($message) === ''
            || mb_strlen($message) > 500
        ) {
            $this->sendChannelError($from, 'INVALID_MESSAGE', 'An authenticated channel member and a valid message are required.');

            return;
        }

        $username = (string) $user['username'];
        $message = trim($message);
        $timestamp = Time::now()->getTimestamp();

        $messageId = $this->chatModel->insertMsg($username, $message, $timestamp, $channelId);

        // Prepare the message data for broadcasting
        $messageData = [
            'action' => 'newMessage',
            'data' => [
                'id'        => $messageId,
                'user'      => $username,
                'msg'       => $message,
                'timestamp' => $timestamp,
                'channel_id' => $channelId,
            ],
        ];

        $memberIds = $this->channels->memberIds($channelId);
        $readBy = [];
        foreach ($this->clients as $client) {
            $clientData = $this->clients[$client];
            $clientUserId = $clientData['user_id'] ?? 0;
            if (! is_int($clientUserId) || ! in_array($clientUserId, $memberIds, true)) {
                continue;
            }
            if (($clientData['channel_id'] ?? null) === $channelId) {
                $client->send(json_encode($messageData));
                $readBy[$clientUserId] = true;
            } else {
                $client->send(json_encode(['type' => 'channel_unread', 'channel_id' => $channelId]));
            }
        }
        foreach (array_keys($readBy) as $readUserId) {
            $this->channels->markRead($channelId, $readUserId);
        }
    }

    /** @param array<string, mixed> $data */
    private function handleReaction(ConnectionInterface $from, array $data, string $messageType): void
    {
        if (! $this->clients->contains($from)) {
            return;
        }

        $connectionData = $this->clients[$from];
        $userId = $connectionData['user_id'] ?? 0;
        $messageId = $data['message_id'] ?? 0;
        $emoji = $this->validReactionEmoji($data['emoji'] ?? null);
        $channelId = is_int($messageId) ? $this->channels->messageChannelId($messageId) : null;
        if (
            ($connectionData['authenticated'] ?? false) !== true
            || ! is_int($userId)
            || $userId <= 0
            || ! is_int($messageId)
            || $messageId <= 0
            || $emoji === null
            || ! $this->reactions->messageExists($messageId)
            || $channelId === null
            || ! $this->channels->isMember($channelId, $userId)
        ) {
            $this->sendReactionError($from, 'INVALID_REACTION', 'The reaction request is invalid.');

            return;
        }

        if (! $this->allowReaction($userId)) {
            $this->sendReactionError($from, 'REACTION_RATE_LIMITED', 'Too many reactions. Please try again later.');

            return;
        }

        if ($messageType === 'reaction_add') {
            $this->reactions->add($messageId, $userId, $emoji);
        } else {
            $this->reactions->remove($messageId, $userId, $emoji);
        }

        $reaction = ['emoji' => $emoji, 'count' => 0, 'users' => []];
        foreach ($this->reactions->forMessage($messageId) as $state) {
            if ($state['emoji'] === $emoji) {
                $reaction = [
                    'emoji' => $state['emoji'],
                    'count' => $state['count'],
                    'users' => $state['users'],
                ];
                break;
            }
        }

        $payload = json_encode([
            'type' => 'reaction',
            'message_id' => $messageId,
            'channel_id' => $channelId,
            ...$reaction,
        ], JSON_UNESCAPED_UNICODE);
        foreach ($this->clients as $client) {
            $clientData = $this->clients[$client];
            if (($clientData['channel_id'] ?? null) === $channelId) {
                $client->send($payload);
            }
        }
    }

    /** @param array<string, mixed> $data */
    private function handleChannelSubscribe(ConnectionInterface $from, array $data): void
    {
        $channelId = $data['channel_id'] ?? null;
        if (! is_int($channelId) || ! $this->clients->contains($from)) {
            $this->sendChannelError($from, 'INVALID_CHANNEL', 'A numeric channel_id is required.');

            return;
        }

        $connectionData = $this->clients[$from];
        $userId = $connectionData['user_id'] ?? 0;
        if (
            ($connectionData['authenticated'] ?? false) !== true
            || ! is_int($userId)
            || ! $this->channels->isMember($channelId, $userId)
        ) {
            $this->sendChannelError($from, 'CHANNEL_ACCESS_DENIED', 'Channel membership is required.');

            return;
        }

        $connectionData['channel_id'] = $channelId;
        $this->clients[$from] = $connectionData;
        $this->channels->markRead($channelId, $userId);
        $from->send(json_encode(['type' => 'channel_subscribed', 'channel_id' => $channelId]));
    }

    private function authorizedChannelId(ConnectionInterface $from, mixed $requestedChannelId): ?int
    {
        if (! $this->clients->contains($from)) {
            return null;
        }

        $connectionData = $this->clients[$from];
        $channelId = is_int($requestedChannelId) ? $requestedChannelId : ($connectionData['channel_id'] ?? null);
        if (! is_int($channelId)) {
            return null;
        }

        $userId = $connectionData['user_id'] ?? 0;
        if (($connectionData['authenticated'] ?? false) === true) {
            return is_int($userId) && $this->channels->isMember($channelId, $userId) ? $channelId : null;
        }

        return $channelId === $this->channels->generalChannelId() ? $channelId : null;
    }

    private function sendChannelError(ConnectionInterface $connection, string $code, string $message): void
    {
        $connection->send(json_encode([
            'action' => 'error',
            'data' => ['code' => $code, 'message' => $message],
        ]));
    }

    private function allowReaction(int $userId): bool
    {
        $now = time();
        $history = array_values(array_filter(
            $this->reactionHistory[$userId] ?? [],
            static fn (int $timestamp): bool => $timestamp > $now - 60,
        ));
        if (count($history) >= 120) {
            $this->reactionHistory[$userId] = $history;

            return false;
        }

        $history[] = $now;
        $this->reactionHistory[$userId] = $history;

        return true;
    }

    private function validReactionEmoji(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        if (
            $value === ''
            || mb_strlen($value) > 10
            || strlen($value) > 64
            || preg_match('/[\p{C}\p{Z}]/u', $value) === 1
            || preg_match('/\p{Extended_Pictographic}/u', $value) !== 1
        ) {
            return null;
        }

        return $value;
    }

    private function sendReactionError(ConnectionInterface $connection, string $code, string $message): void
    {
        $connection->send(json_encode([
            'action' => 'error',
            'data' => ['code' => $code, 'message' => $message],
        ]));
    }
}
