<?php

namespace App\Libraries;

/**
 * WebSocket Client
 * 
 * A simple WebSocket client for sending messages to the WebSocket server
 */
class WebSocketClient
{
    /**
     * WebSocket server host
     * 
     * @var string
     */
    protected $host;
    
    /**
     * WebSocket server port
     * 
     * @var int
     */
    protected $port;
    
    /**
     * Socket resource
     * 
     * @var resource|null
     */
    protected $socket = null;
    
    /**
     * Constructor
     * 
     * @param string $host WebSocket server host
     * @param int $port WebSocket server port
     */
    public function __construct(string $host = 'localhost', int $port = 8080)
    {
        $this->host = $host;
        $this->port = $port;
    }
    
    /**
     * Send a message to the WebSocket server
     * 
     * @param array $data Message data
     * @return bool True if the message was sent successfully, false otherwise
     */
    public function send(array $data): bool
    {
        try {
            // Create a TCP/IP socket
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if ($socket === false) {
                log_message('error', 'WebSocketClient: Failed to create socket: ' . socket_strerror(socket_last_error()));
                return false;
            }
            
            // Connect to the WebSocket server
            $result = socket_connect($socket, $this->host, $this->port);
            if ($result === false) {
                log_message('error', 'WebSocketClient: Failed to connect: ' . socket_strerror(socket_last_error($socket)));
                socket_close($socket);
                return false;
            }
            
            // Prepare the HTTP headers for the WebSocket handshake
            $key = base64_encode(openssl_random_pseudo_bytes(16));
            $headers = "GET / HTTP/1.1\r\n";
            $headers .= "Host: {$this->host}:{$this->port}\r\n";
            $headers .= "Upgrade: websocket\r\n";
            $headers .= "Connection: Upgrade\r\n";
            $headers .= "Sec-WebSocket-Key: {$key}\r\n";
            $headers .= "Sec-WebSocket-Version: 13\r\n";
            $headers .= "\r\n";
            
            // Send the headers
            socket_write($socket, $headers, strlen($headers));
            
            // Read the response
            $response = socket_read($socket, 2048);
            if ($response === false) {
                log_message('error', 'WebSocketClient: Failed to read response: ' . socket_strerror(socket_last_error($socket)));
                socket_close($socket);
                return false;
            }
            
            // Check if the handshake was successful
            if (strpos($response, '101 Switching Protocols') === false) {
                log_message('error', 'WebSocketClient: Handshake failed: ' . $response);
                socket_close($socket);
                return false;
            }
            
            // Encode the message according to the WebSocket protocol
            $message = json_encode($data);
            $encodedMessage = $this->encodeMessage($message);
            
            // Send the message
            $sent = socket_write($socket, $encodedMessage, strlen($encodedMessage));
            if ($sent === false) {
                log_message('error', 'WebSocketClient: Failed to send message: ' . socket_strerror(socket_last_error($socket)));
                socket_close($socket);
                return false;
            }
            
            // Close the socket
            socket_close($socket);
            
            return true;
        } catch (\Exception $e) {
            log_message('error', 'WebSocketClient: Exception: ' . $e->getMessage());
            if (isset($socket) && is_resource($socket)) {
                socket_close($socket);
            }
            return false;
        }
    }
    
    /**
     * Encode a message according to the WebSocket protocol
     * 
     * @param string $message Message to encode
     * @return string Encoded message
     */
    protected function encodeMessage(string $message): string
    {
        $length = strlen($message);
        $header = chr(129); // 0x81 (FIN + text frame)
        
        if ($length <= 125) {
            $header .= chr($length);
        } elseif ($length <= 65535) {
            $header .= chr(126) . chr(($length >> 8) & 255) . chr($length & 255);
        } else {
            $header .= chr(127) . chr(0) . chr(0) . chr(0) . chr(0) . chr(($length >> 24) & 255) . chr(($length >> 16) & 255) . chr(($length >> 8) & 255) . chr($length & 255);
        }
        
        return $header . $message;
    }
}