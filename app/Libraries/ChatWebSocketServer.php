<?php

namespace App\Libraries;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use App\Models\ChatModel;
use CodeIgniter\I18n\Time;

/**
 * Chat WebSocket Server
 * 
 * Handles WebSocket connections and messages for the chat application
 */
class ChatWebSocketServer implements MessageComponentInterface
{
    /**
     * Connected clients
     * 
     * @var \SplObjectStorage
     */
    protected $clients;

    /**
     * Chat model instance
     * 
     * @var ChatModel
     */
    protected $chatModel;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->chatModel = new ChatModel();
        
        echo "Chat WebSocket Server started\n";
    }

    /**
     * When a new connection is opened
     * 
     * @param ConnectionInterface $conn
     * @return void
     */
    public function onOpen(ConnectionInterface $conn)
    {
        // Store the new connection
        $this->clients->attach($conn);
        
        echo "New connection! ({$conn->resourceId})\n";
    }

    /**
     * When a message is received from a client
     * 
     * @param ConnectionInterface $from
     * @param string $msg
     * @return void
     */
    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true);
        
        if (!$data || !isset($data['action'])) {
            return;
        }
        
        switch ($data['action']) {
            case 'getMessages':
                // Send existing messages to the client
                $page = $data['page'] ?? 1;
                $perPage = $data['perPage'] ?? 10;
                $result = $this->chatModel->getMsgPaginated($page, $perPage);
                
                $from->send(json_encode([
                    'action' => 'messages',
                    'data' => [
                        'messages' => $result['messages'],
                        'pagination' => $result['pagination']
                    ]
                ]));
                break;
                
            case 'sendMessage':
                // Validate and save the message
                if (!isset($data['message']) || !isset($data['username'])) {
                    return;
                }
                
                $username = $data['username'];
                $message = $data['message'];
                $timestamp = Time::now()->getTimestamp();
                
                // Insert message into database
                $this->chatModel->insertMsg($username, $message, $timestamp);
                
                // Broadcast the message to all clients
                $messageData = [
                    'action' => 'newMessage',
                    'data' => [
                        'user' => $username,
                        'msg' => $message,
                        'timestamp' => $timestamp
                    ]
                ];
                
                foreach ($this->clients as $client) {
                    $client->send(json_encode($messageData));
                }
                break;
        }
    }

    /**
     * When a connection is closed
     * 
     * @param ConnectionInterface $conn
     * @return void
     */
    public function onClose(ConnectionInterface $conn)
    {
        // Remove the connection
        $this->clients->detach($conn);
        
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    /**
     * When an error occurs
     * 
     * @param ConnectionInterface $conn
     * @param \Exception $e
     * @return void
     */
    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error has occurred: {$e->getMessage()}\n";
        
        $conn->close();
    }
}