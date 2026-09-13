<?php

namespace App\Controllers;

use App\Contracts\ChatFormatter;
use App\Contracts\ChatRepository;
use App\Libraries\WebSocketClient;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\I18n\Time;

/**
 * Chat Controller
 *
 * Handles all chat-related functionality including displaying and updating messages
 * in various formats (XML, JSON, HTML).
 *
 * ============================================================================
 * DEPENDENCY INJECTION IN THIS CONTROLLER (for beginners)
 * ============================================================================
 *
 * This controller depends on the ChatRepository contract rather than a concrete
 * model. Config\Services constructs it for HTTP requests, while tests can pass a
 * small interface stub directly.
 *
 * ============================================================================
 */
class Chat extends BaseController
{
    /**
     * Chat repository for message persistence and retrieval.
     *
     * The controller only knows this contract; Config\Services selects the
     * concrete model used by the application.
     *
     * @var ChatRepository
     */
    protected ChatRepository $chatRepository;

    /**
     * Constructor - receives dependencies via injection.
     *
     * Config\Services is the composition root, so the controller never reaches
     * back into the service locator to obtain its own repository.
     */
    public function __construct(ChatRepository $chatRepository, private readonly ChatFormatter $chatFormatter)
    {
        $this->chatRepository = $chatRepository;
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
            // The route's validate:message filter has already validated this input.
            $data = [
                'message' => $this->request->getPost('message'),
            ];

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
                $messageId = $this->chatRepository->insertMsg($name, $message, $current->getTimestamp());
            } catch (\Exception $e) {
                return $this->handleDatabaseError('Failed to save message', [
                    'error' => $e->getMessage(),
                ]);
            }

            // Log successful message
            $this->logMessage('info', 'New message posted', [
                'user' => $name,
                'message_length' => strlen($message),
            ]);

            // Broadcast the message to all connected WebSocket clients
            try {
                $webSocketClient = new WebSocketClient();
                $webSocketClient->send([
                    'action' => 'sendMessage',
                    'username' => $name,
                    'message' => $message,
                ]);
            } catch (\Exception $e) {
                // Log the error but don't fail the request
                $this->logMessage('error', 'Failed to broadcast message to WebSocket', [
                    'error' => $e->getMessage(),
                ]);
            }

            if ($html_redirect === 'true') {
                return redirect()->to('/chat/html');
            }

            // Machine-consumed routes always return JSON, with or without the
            // legacy X-Requested-With header.
            if (
                $this->request->isAJAX()
                || str_contains($this->request->getHeaderLine('Accept'), 'application/json')
            ) {
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
     * @return \CodeIgniter\HTTP\ResponseInterface XML-formatted chat messages with pagination information
     */
    public function backend(): \CodeIgniter\HTTP\ResponseInterface
    {
        // Get page from request or default to 1
        $page = $this->request->getGet('page') ?? 1;

        // Get per_page from request or default to 10
        $perPage = $this->request->getGet('per_page') ?? 10;

        // Get the data with pagination
        $result = $this->chatRepository->getMsgPaginated($page, $perPage);

        // Format messages as XML using ChatHelper
        $output = $this->chatFormatter->asXml($result['messages'], $result['pagination']);

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
        $result = $this->chatRepository->getMsgPaginated($page, $perPage);

        // Format messages as JSON using ChatHelper
        $data = $this->chatFormatter->asJson($result['messages'], $result['pagination']);

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
            'html' => $this->htmlBackend(),
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
        $result = $this->chatRepository->getMsgPaginated($page, $perPage);

        $data = [
            'query' => $result['messages'],
            'pagination' => $result['pagination'],
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
    public function vue(): string|RedirectResponse
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

    /**
     * Loads the Svelte version of the chat
     *
     * This method renders the chat view that uses Svelte for a reactive UI.
     * It's the entry point for the Svelte-based chat interface.
     *
     * SVELTE vs VUE COMPARISON:
     * -------------------------
     * Both Svelte and Vue are component-based frameworks, but they differ in approach:
     *
     * - Vue uses a virtual DOM and runs in the browser
     * - Svelte compiles components at build time, resulting in smaller bundles
     * - Svelte 5 uses "runes" ($state, $derived, $effect) for reactivity
     * - Vue 3 uses ref(), reactive(), computed(), and watch()
     *
     * This method is structurally identical to vue() - the differences are all
     * in the frontend implementation (src/svelte/ vs src/vue/).
     *
     * This method requires authentication and will redirect to the login page
     * if the user is not logged in.
     *
     * @return string|RedirectResponse The rendered view with the Svelte-based chat interface
     *                                 or a redirect response to the login page if not authenticated
     */
    public function svelte(): string|RedirectResponse
    {
        // Check if user is logged in
        if (!session()->get('logged_in')) {
            return redirect()->to('auth/login');
        }

        return $this->respondWithView('chat/svelteView');
    }

    /**
     * API endpoint for the Svelte version
     *
     * This method serves as the API endpoint for the Svelte chat interface.
     * It reuses the existing jsonBackend method to retrieve and format chat messages.
     * This endpoint is called by the Svelte frontend to fetch messages via AJAX.
     *
     * Note: This is functionally identical to vueApi() - both frontends use
     * the same JSON format for chat messages. We create a separate endpoint
     * to maintain consistency with the route naming pattern (chat/svelteApi)
     * and to allow for future customization if needed.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface JSON response containing chat messages and pagination information
     */
    public function svelteApi(): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->jsonBackend();
    }
}
