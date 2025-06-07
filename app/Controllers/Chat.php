<?php

namespace App\Controllers;

use App\Models\ChatModel;
use App\Helpers\ChatHelper;
use App\Libraries\WebSocketClient;
use CodeIgniter\I18n\Time;

/**
 * Chat Controller
 * 
 * Handles all chat-related functionality including displaying and updating messages
 * in various formats (XML, JSON, HTML)
 */
class Chat extends BaseController
{
    /**
     * Chat model instance
     * 
     * @var ChatModel
     */
    protected $chatModel;

    /**
     * Constructor - loads the model
     * 
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @param LoggerInterface   $logger
     * 
     * @return void
     */
    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->chatModel = new ChatModel();
    }

    /**
     * Loads the default page for the XML example
     * 
     * @return string
     */
    public function index()
    {
        return $this->respondWithView('chat/chatView');
    }

    /**
     * Updates the database with a new chat message
     * 
     * @return mixed
     */
    public function update()
    {
        try {
            // Get data for validation
            $data = [
                'message' => $this->request->getPost('message')
            ];

            // Validate message using ChatHelper
            $validation = ChatHelper::validateMessage($data);

            if ($validation !== true) {
                // Use the error handler for validation errors
                return $this->handleValidationError($validation, 'Message validation failed');
            }

            // Get username from session
            $name = $this->getCurrentUsername();

            if (!$name) {
                return $this->handleAuthenticationError('You must be logged in to post messages');
            }

            // Get sanitized inputs
            $message = $this->sanitizeInput($data)['message'];
            $html_redirect = $this->request->getPost('html_redirect');

            $current = Time::now();

            // Insert message and handle potential database errors
            try {
                $messageId = $this->chatModel->insertMsg($name, $message, $current->getTimestamp());
            } catch (\Exception $e) {
                return $this->handleDatabaseError('Failed to save message', [
                    'error' => $e->getMessage()
                ]);
            }

            // Log successful message
            $this->logMessage('info', 'New message posted', [
                'user' => $name,
                'message_length' => strlen($message)
            ]);

            // Broadcast the message to all connected WebSocket clients
            try {
                $webSocketClient = new WebSocketClient();
                $webSocketClient->send([
                    'action' => 'sendMessage',
                    'username' => $name,
                    'message' => $message
                ]);
            } catch (\Exception $e) {
                // Log the error but don't fail the request
                $this->logMessage('error', 'Failed to broadcast message to WebSocket', [
                    'error' => $e->getMessage()
                ]);
            }

            if ($html_redirect === "true") {
                return redirect()->to('/chat/html');
            }

            // For AJAX requests, return success JSON
            if ($this->request->isAJAX()) {
                return $this->respondWithJson(['success' => true]);
            }

            return '';
        } catch (\Throwable $e) {
            // Catch any unexpected exceptions
            return $this->handleException($e);
        }
    }

    /**
     * XML Backend - returns chat messages in XML format
     * 
     * @return string
     */
    public function backend()
    {
        // Get page from request or default to 1
        $page = $this->request->getGet('page') ?? 1;

        // Get per_page from request or default to 10
        $perPage = $this->request->getGet('per_page') ?? 10;

        // Get the data with pagination
        $result = $this->chatModel->getMsgPaginated($page, $perPage);

        // Format messages as XML using ChatHelper
        $output = ChatHelper::formatAsXml($result['messages'], $result['pagination']);

        return $this->respondWithXml($output);
    }

    /**
     * Loads the default view for the JSON example
     * 
     * @return string
     */
    public function json()
    {
        return $this->respondWithView('chat/jsonView');
    }

    /**
     * Displays the JSON formatted data
     * 
     * @return ResponseInterface
     */
    public function jsonBackend()
    {
        // Get page from request or default to 1
        $page = $this->request->getGet('page') ?? 1;

        // Get per_page from request or default to 10
        $perPage = $this->request->getGet('per_page') ?? 10;

        // Get the data with pagination
        $result = $this->chatModel->getMsgPaginated($page, $perPage);

        // Format messages as JSON using ChatHelper
        $data = ChatHelper::formatAsJson($result['messages'], $result['pagination']);

        // Return JSON response
        return $this->respondWithJson($data);
    }

    /**
     * Main for the HTML example
     * 
     * @return string
     */
    public function html()
    {
        $data = [
            'html' => $this->htmlBackend()
        ];

        return $this->respondWithView('chat/htmlView', $data);
    }

    /** 
     * Function to display the data in HTML
     * 
     * @return string
     */
    public function htmlBackend()
    {
        // Get page from request or default to 1
        $page = $this->request->getGet('page') ?? 1;

        // Get per_page from request or default to 10
        $perPage = $this->request->getGet('per_page') ?? 10;

        // Get the data with pagination
        $result = $this->chatModel->getMsgPaginated($page, $perPage);

        $data = [
            'query' => $result['messages'],
            'pagination' => $result['pagination']
        ];

        return $this->respondWithView('chat/htmlBackView', $data, ['saveData' => true]);
    }

    /**
     * Loads the Vue.js version of the chat
     * 
     * @return string
     */
    public function vue()
    {
        // Check if user is logged in
        if (!session()->get('logged_in')) {
            return redirect()->to('auth/login');
        }

        return $this->respondWithView('chat/vueView');
    }

    /**
     * API endpoint for the Vue.js version
     * Reuses the existing jsonBackend method
     * 
     * @return ResponseInterface
     */
    public function vueApi()
    {
        return $this->jsonBackend();
    }
}
