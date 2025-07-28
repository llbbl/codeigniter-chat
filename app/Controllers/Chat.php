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
    protected ChatModel $chatModel;

    /**
     * Constructor - initializes the controller and loads the model
     * 
     * This method is called by the framework when the controller is instantiated.
     * It initializes the parent controller and creates a new instance of the ChatModel.
     * 
     * @param \CodeIgniter\HTTP\RequestInterface  $request  The HTTP request object
     * @param \CodeIgniter\HTTP\ResponseInterface $response The HTTP response object
     * @param \Psr\Log\LoggerInterface            $logger   The logger object
     * 
     * @return void
     */
    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);
        $this->chatModel = new ChatModel();
    }

    /**
     * Loads the default page for the XML example
     * 
     * This method renders the main chat view that uses XML for data exchange.
     * It's the entry point for the XML-based chat interface.
     * 
     * @return string The rendered view with the chat interface
     */
    public function index(): string
    {
        return $this->respondWithView('chat/chatView');
    }

    /**
     * Updates the database with a new chat message
     * 
     * This method processes a chat message submission. It validates the message,
     * checks user authentication, sanitizes the input, saves the message to the database,
     * and broadcasts it to all connected WebSocket clients.
     * 
     * @return mixed Returns one of the following:
     *               - Redirect response (for HTML form submissions)
     *               - JSON response (for AJAX requests)
     *               - Empty string (for other requests)
     *               - Error response (for validation, authentication, or database errors)
     * 
     * @throws \Exception If there's an error saving the message to the database
     */
    public function update(): \CodeIgniter\HTTP\ResponseInterface
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
     * This method retrieves chat messages from the database with pagination
     * and formats them as XML. It's used by the XML-based chat interface
     * to fetch messages via AJAX.
     * 
     * @return string XML-formatted chat messages with pagination information
     */
    public function backend(): string
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
     * This method renders the chat view that uses JSON for data exchange.
     * It's the entry point for the JSON-based chat interface.
     * 
     * @return string The rendered view with the JSON-based chat interface
     */
    public function json(): string
    {
        return $this->respondWithView('chat/jsonView');
    }

    /**
     * Displays the JSON formatted data
     * 
     * This method retrieves chat messages from the database with pagination
     * and formats them as JSON. It's used by the JSON-based chat interface
     * to fetch messages via AJAX.
     * 
     * @return \CodeIgniter\HTTP\ResponseInterface JSON response containing chat messages and pagination information
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
     * This method renders the chat view that uses traditional HTML form submission.
     * It's the entry point for the HTML-based chat interface, which works without JavaScript.
     * It calls htmlBackend() to get the HTML content for the messages.
     * 
     * @return string The rendered view with the HTML-based chat interface
     */
    public function html(): string
    {
        $data = [
            'html' => $this->htmlBackend()
        ];

        return $this->respondWithView('chat/htmlView', $data);
    }

    /** 
     * Function to display the data in HTML
     * 
     * This method retrieves chat messages from the database with pagination
     * and renders them as HTML. It's used by the HTML-based chat interface
     * to display messages without requiring JavaScript.
     * 
     * @return string The rendered HTML view containing chat messages and pagination controls
     */
    public function htmlBackend(): string
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
     * This method renders the chat view that uses Vue.js for a reactive UI.
     * It's the entry point for the Vue.js-based chat interface.
     * This method requires authentication and will redirect to the login page
     * if the user is not logged in.
     * 
     * @return string|RedirectResponse The rendered view with the Vue.js-based chat interface
     *                                 or a redirect response to the login page if not authenticated
     */
    public function vue(): string|\CodeIgniter\HTTP\RedirectResponse
    {
        // Check if user is logged in
        if (!session()->get('logged_in')) {
            return redirect()->to('auth/login');
        }

        return $this->respondWithView('chat/vueView');
    }

    /**
     * API endpoint for the Vue.js version
     * 
     * This method serves as the API endpoint for the Vue.js chat interface.
     * It reuses the existing jsonBackend method to retrieve and format chat messages.
     * This endpoint is called by the Vue.js frontend to fetch messages via AJAX.
     * 
     * @return \CodeIgniter\HTTP\ResponseInterface JSON response containing chat messages and pagination information
     */
    public function vueApi(): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->jsonBackend();
    }
}
