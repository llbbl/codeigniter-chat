<?php

namespace App\Libraries;

use Exception;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use App\Models\ChatModel;
use App\Helpers\WebSocketTokenHelper;
use CodeIgniter\I18n\Time;
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

    /**
     * Constructor
     *
     * Initializes the WebSocket server with its dependencies.
     * Uses dependency injection to allow for easier testing.
     *
     * @param SplObjectStorage|null $clients    Optional: Pre-configured client storage
     * @param ChatModel|null        $chatModel  Optional: Pre-configured chat model
     * @param bool                  $requireAuth Whether to require authentication (default: true)
     */
    public function __construct(
        ?SplObjectStorage $clients = null,
        ?ChatModel $chatModel = null,
        bool $requireAuth = true
    ) {
        $this->clients = $clients ?? new SplObjectStorage();
        $this->chatModel = $chatModel ?? new ChatModel();
        $this->requireAuth = $requireAuth;

        $this->logServerStart();
    }

    /**
     * Log server startup message
     *
     * @return void
     */
    private function logServerStart(): void
    {
        echo "Chat WebSocket Server started\n";
        echo "Authentication: " . ($this->requireAuth ? "ENABLED" : "DISABLED") . "\n";
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
        $this->clients->attach($conn, [
            'user_id'      => $userId,
            'connected_at' => time(),
            'authenticated' => $this->requireAuth ? true : ($userId > 0)
        ]);

        // Log successful connection
        $authStatus = $this->requireAuth ? "(authenticated, user_id: {$userId})" : "(auth disabled)";
        echo "New connection! (" . spl_object_id($conn) . ") {$authStatus}\n";
    }

    /**
     * Parse query parameters from the WebSocket connection URL
     *
     * Ratchet provides access to the HTTP request that initiated the WebSocket
     * handshake. We use this to extract query parameters.
     *
     * @param ConnectionInterface $conn The connection to parse
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
                'code'    => 'AUTH_FAILED'
            ]
        ]));

        // Close the connection
        $conn->close();
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
     * @return void
     */
    public function onMessage(ConnectionInterface $from, $msg)
    {
        // Parse the JSON message
        $data = json_decode($msg, true);

        // Validate message format
        if (!$data || !isset($data['action'])) {
            return;
        }

        // Get the user info stored with this connection
        $connectionData = $this->clients[$from] ?? [];
        $userId = $connectionData['user_id'] ?? 0;

        // Handle different actions
        switch ($data['action']) {
            case 'getMessages':
                $this->handleGetMessages($from, $data);
                break;

            case 'sendMessage':
                $this->handleSendMessage($from, $data, $userId);
                break;
        }
    }

    /**
     * Handle the getMessages action
     *
     * Retrieves paginated chat messages and sends them to the requesting client.
     *
     * @param ConnectionInterface $from The connection requesting messages
     * @param array               $data The request data containing page and perPage
     * @return void
     */
    private function handleGetMessages(ConnectionInterface $from, array $data): void
    {
        $page = $data['page'] ?? 1;
        $perPage = $data['perPage'] ?? 10;

        // Fetch messages from the database
        $result = $this->chatModel->getMsgPaginated($page, $perPage);

        // Send messages back to the client
        $from->send(json_encode([
            'action' => 'messages',
            'data' => [
                'messages'   => $result['messages'],
                'pagination' => $result['pagination']
            ]
        ]));
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
     * @return void
     */
    private function handleSendMessage(ConnectionInterface $from, array $data, int $userId): void
    {
        // Validate required fields
        if (!isset($data['message']) || !isset($data['username'])) {
            return;
        }

        $username = $data['username'];
        $message = $data['message'];
        $timestamp = Time::now()->getTimestamp();

        // Insert message into database
        $this->chatModel->insertMsg($username, $message, $timestamp);

        // Prepare the message data for broadcasting
        $messageData = [
            'action' => 'newMessage',
            'data' => [
                'user'      => $username,
                'msg'       => $message,
                'timestamp' => $timestamp
            ]
        ];

        // Broadcast the message to ALL connected clients
        // This is what makes the chat "real-time" - everyone sees new messages instantly
        foreach ($this->clients as $client) {
            $client->send(json_encode($messageData));
        }
    }

    /**
     * Handle connection close
     *
     * Called when a client disconnects. We clean up by removing
     * the connection from our storage.
     *
     * @param ConnectionInterface $conn The connection that closed
     * @return void
     */
    public function onClose(ConnectionInterface $conn): void
    {
        // Get user info before removing
        $connectionData = $this->clients[$conn] ?? [];
        $userId = $connectionData['user_id'] ?? 'unknown';

        // Remove the connection from our storage
        $this->clients->detach($conn);

        echo "Connection " . spl_object_id($conn) . " (user_id: {$userId}) has disconnected\n";
    }

    /**
     * Handle connection errors
     *
     * Called when an error occurs on a connection. We log the error
     * and close the connection.
     *
     * @param ConnectionInterface $conn The connection that errored
     * @param Exception           $e    The exception that occurred
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
}
