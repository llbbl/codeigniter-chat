<?php

/**
 * AI Chat Application API Client (PHP)
 * 
 * A comprehensive client library for interacting with the AI Chat Application API.
 * Supports authentication, conversation management, and real-time messaging.
 * 
 * @version 1.0.0
 * @author AI Chat Team
 */

namespace AiChat\ApiClient;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\MultipartStream;

/**
 * API Response wrapper
 */
class ApiResponse
{
    public bool $success;
    public mixed $data;
    public ?string $message;
    public ?array $errors;

    public function __construct(bool $success, mixed $data = null, ?string $message = null, ?array $errors = null)
    {
        $this->success = $success;
        $this->data = $data;
        $this->message = $message;
        $this->errors = $errors;
    }
}

/**
 * AI Chat Application API Client
 * 
 * A comprehensive client for interacting with the AI Chat Application API.
 * Provides methods for authentication, conversation management, messaging, and file uploads.
 */
class AiChatApiClient
{
    private string $baseUrl;
    private ?string $token;
    private Client $httpClient;
    private int $timeout;

    /**
     * Initialize the API client
     * 
     * @param string $baseUrl Base URL of the API
     * @param string|null $token JWT authentication token
     * @param int $timeout Request timeout in seconds
     */
    public function __construct(string $baseUrl = 'http://localhost:8080', ?string $token = null, int $timeout = 30)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->token = $token;
        $this->timeout = $timeout;
        
        $this->httpClient = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => $this->timeout,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]
        ]);
    }

    /**
     * Set the authentication token
     * 
     * @param string|null $token JWT token
     */
    public function setToken(?string $token): void
    {
        $this->token = $token;
    }

    /**
     * Get headers with authentication
     * 
     * @return array Headers array
     */
    private function getHeaders(): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if ($this->token) {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        }

        return $headers;
    }

    /**
     * Make HTTP request to the API
     * 
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array|null $data Request data
     * @param array|null $queryParams Query parameters
     * @param array|null $files Files to upload
     * @return ApiResponse API response
     */
    private function makeRequest(string $method, string $endpoint, ?array $data = null, ?array $queryParams = null, ?array $files = null): ApiResponse
    {
        try {
            $options = [
                'headers' => $this->getHeaders(),
            ];

            if ($queryParams) {
                $options['query'] = $queryParams;
            }

            if ($files) {
                // Handle file uploads
                $multipart = [];
                
                if ($data) {
                    foreach ($data as $key => $value) {
                        $multipart[] = [
                            'name' => $key,
                            'contents' => $value,
                        ];
                    }
                }

                foreach ($files as $key => $file) {
                    $multipart[] = [
                        'name' => $key,
                        'contents' => fopen($file, 'r'),
                        'filename' => basename($file),
                    ];
                }

                $options['multipart'] = $multipart;
                // Remove Content-Type header for multipart
                unset($options['headers']['Content-Type']);
            } elseif ($data) {
                $options['json'] = $data;
            }

            $response = $this->httpClient->request($method, $endpoint, $options);
            $responseData = json_decode($response->getBody()->getContents(), true);

            return new ApiResponse(
                success: $responseData['success'] ?? true,
                data: $responseData,
                message: $responseData['message'] ?? null,
                errors: $responseData['errors'] ?? null
            );

        } catch (RequestException $e) {
            $responseData = null;
            $message = $e->getMessage();

            if ($e->hasResponse()) {
                try {
                    $responseData = json_decode($e->getResponse()->getBody()->getContents(), true);
                    $message = $responseData['message'] ?? $message;
                } catch (Exception $jsonException) {
                    // Use original message if JSON parsing fails
                }
            }

            return new ApiResponse(
                success: false,
                data: $responseData,
                message: $message,
                errors: $responseData['errors'] ?? null
            );
        }
    }

    // Authentication Methods

    /**
     * Login user
     * 
     * @param string $email User email
     * @param string $password User password
     * @return ApiResponse Login response with token and user info
     */
    public function login(string $email, string $password): ApiResponse
    {
        $response = $this->makeRequest('POST', '/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);

        if ($response->success && isset($response->data['token'])) {
            $this->setToken($response->data['token']);
        }

        return $response;
    }

    /**
     * Register new user
     * 
     * @param string $name User name
     * @param string $email User email
     * @param string $password User password
     * @return ApiResponse Registration response
     */
    public function register(string $name, string $email, string $password): ApiResponse
    {
        return $this->makeRequest('POST', '/auth/register', [
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);
    }

    /**
     * Logout user
     * 
     * @return ApiResponse Logout response
     */
    public function logout(): ApiResponse
    {
        $response = $this->makeRequest('POST', '/auth/logout');
        if ($response->success) {
            $this->setToken(null);
        }
        return $response;
    }

    /**
     * Get current user information
     * 
     * @return ApiResponse User information
     */
    public function getCurrentUser(): ApiResponse
    {
        return $this->makeRequest('GET', '/auth/me');
    }

    // Conversation Methods

    /**
     * Get all conversations
     * 
     * @param int $page Page number
     * @param int $limit Items per page
     * @return ApiResponse Conversations list with pagination
     */
    public function getConversations(int $page = 1, int $limit = 20): ApiResponse
    {
        $queryParams = ['page' => $page, 'limit' => $limit];
        return $this->makeRequest('GET', '/chat/conversations', null, $queryParams);
    }

    /**
     * Create new conversation
     * 
     * @param string|null $title Conversation title
     * @param string|null $description Conversation description
     * @return ApiResponse Created conversation
     */
    public function createConversation(?string $title = null, ?string $description = null): ApiResponse
    {
        $data = [];
        if ($title !== null) {
            $data['title'] = $title;
        }
        if ($description !== null) {
            $data['description'] = $description;
        }

        return $this->makeRequest('POST', '/chat/conversations', $data);
    }

    /**
     * Get specific conversation with messages
     * 
     * @param int $conversationId Conversation ID
     * @return ApiResponse Conversation with messages
     */
    public function getConversation(int $conversationId): ApiResponse
    {
        return $this->makeRequest('GET', "/chat/conversations/{$conversationId}");
    }

    /**
     * Update conversation
     * 
     * @param int $conversationId Conversation ID
     * @param string|null $title New title
     * @param string|null $description New description
     * @return ApiResponse Updated conversation
     */
    public function updateConversation(int $conversationId, ?string $title = null, ?string $description = null): ApiResponse
    {
        $data = [];
        if ($title !== null) {
            $data['title'] = $title;
        }
        if ($description !== null) {
            $data['description'] = $description;
        }

        return $this->makeRequest('PUT', "/chat/conversations/{$conversationId}", $data);
    }

    /**
     * Delete conversation
     * 
     * @param int $conversationId Conversation ID
     * @return ApiResponse Deletion response
     */
    public function deleteConversation(int $conversationId): ApiResponse
    {
        return $this->makeRequest('DELETE', "/chat/conversations/{$conversationId}");
    }

    // Message Methods

    /**
     * Get messages for a conversation
     * 
     * @param int $conversationId Conversation ID
     * @param int $page Page number
     * @param int $limit Items per page
     * @return ApiResponse Messages list with pagination
     */
    public function getMessages(int $conversationId, int $page = 1, int $limit = 50): ApiResponse
    {
        $queryParams = ['page' => $page, 'limit' => $limit];
        return $this->makeRequest('GET', "/chat/conversations/{$conversationId}/messages", null, $queryParams);
    }

    /**
     * Send message in conversation
     * 
     * @param int $conversationId Conversation ID
     * @param string $content Message content
     * @param array|null $attachments File attachments
     * @return ApiResponse Sent message and AI response
     */
    public function sendMessage(int $conversationId, string $content, ?array $attachments = null): ApiResponse
    {
        $data = ['content' => $content];
        if ($attachments) {
            $data['attachments'] = $attachments;
        }

        return $this->makeRequest('POST', "/chat/conversations/{$conversationId}/messages", $data);
    }

    // File Upload Methods

    /**
     * Upload file
     * 
     * @param string $filePath Path to file to upload
     * @param int|null $conversationId Associated conversation ID
     * @return ApiResponse Upload response
     */
    public function uploadFile(string $filePath, ?int $conversationId = null): ApiResponse
    {
        if (!file_exists($filePath)) {
            return new ApiResponse(false, null, 'File does not exist: ' . $filePath);
        }

        $data = [];
        if ($conversationId !== null) {
            $data['conversation_id'] = $conversationId;
        }

        return $this->makeRequest('POST', '/chat/upload', $data, null, ['file' => $filePath]);
    }
}

/**
 * Example usage and helper functions
 */
class AiChatApiClientHelper
{
    /**
     * Create a client instance with common configuration
     * 
     * @param string $baseUrl API base URL
     * @param string|null $token JWT token
     * @return AiChatApiClient Configured client instance
     */
    public static function create(string $baseUrl = 'http://localhost:8080', ?string $token = null): AiChatApiClient
    {
        return new AiChatApiClient($baseUrl, $token);
    }

    /**
     * Handle API response and extract data or throw exception
     * 
     * @param ApiResponse $response API response
     * @return mixed Response data
     * @throws Exception If response indicates failure
     */
    public static function handleResponse(ApiResponse $response): mixed
    {
        if (!$response->success) {
            $message = $response->message ?: 'API request failed';
            if ($response->errors) {
                $message .= '. Errors: ' . json_encode($response->errors);
            }
            throw new Exception($message);
        }

        return $response->data;
    }
}

// Example usage
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'] ?? '')) {
    try {
        // Initialize client
        $client = new AiChatApiClient('https://api.example.com');

        // Login
        $loginResponse = $client->login('user@example.com', 'password123');
        if ($loginResponse->success) {
            echo "Logged in: " . json_encode($loginResponse->data['user']) . "\n";
        } else {
            echo "Login failed: " . $loginResponse->message . "\n";
        }

        // Create conversation
        $conversationResponse = $client->createConversation('My First Chat', 'Learning about AI');
        if ($conversationResponse->success) {
            $conversationId = $conversationResponse->data['conversation']['id'];

            // Send message
            $messageResponse = $client->sendMessage(
                $conversationId,
                'Hello, how can you help me today?'
            );

            if ($messageResponse->success) {
                echo "Message sent: " . json_encode($messageResponse->data) . "\n";
            }
        }

        // Get conversations
        $conversationsResponse = $client->getConversations(1, 10);
        if ($conversationsResponse->success) {
            echo "Found " . count($conversationsResponse->data['conversations']) . " conversations\n";
        }

    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}