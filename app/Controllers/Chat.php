<?php

namespace App\Controllers;

use App\Models\ChatModel;
use App\Helpers\ChatHelper;
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
        // Get data for validation
        $data = [
            'message' => $this->request->getPost('message')
        ];

        // Validate message using ChatHelper
        $validation = ChatHelper::validateMessage($data);

        if ($validation !== true) {
            // If AJAX request, return JSON with errors
            if (!$this->request->getPost('html_redirect')) {
                return $this->respondWithJson([
                    'success' => false,
                    'errors' => $validation
                ]);
            }

            // For HTML form, redirect back with errors
            return redirect()->back()->withInput()->with('errors', $validation);
        }

        // Get username from session
        $name = $this->getCurrentUsername();

        // Get sanitized inputs
        $message = $this->sanitizeInput($data)['message'];
        $html_redirect = $this->request->getPost('html_redirect');

        $current = Time::now();
        $this->chatModel->insertMsg($name, $message, $current->getTimestamp());

        if ($html_redirect === "true") {
            return redirect()->to('/chat/html');
        }

        // For AJAX requests, return success JSON
        if ($this->request->isAJAX()) {
            return $this->respondWithJson(['success' => true]);
        }

        return '';
    }

    /**
     * XML Backend - returns chat messages in XML format
     * 
     * @return string
     */
    public function backend()
    {
        // Get the data
        $messages = $this->chatModel->getMsg();

        // Format messages as XML using ChatHelper
        $output = ChatHelper::formatAsXml($messages);

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
        // Get the data
        $messages = $this->chatModel->getMsg();

        // Format messages as JSON using ChatHelper
        $data = ChatHelper::formatAsJson($messages);

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
        $data = [
            'query' => $this->chatModel->getMsg()
        ];

        return $this->respondWithView('chat/htmlBackView', $data, ['saveData' => true]);
    }
}
