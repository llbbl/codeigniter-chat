<?php

namespace App\Controllers;

use App\Models\ChatModel;
use App\Helpers\ChatHelper;
use CodeIgniter\I18n\Time;

class Chat extends BaseController
{
    protected $chatModel;

    /**
     * Constructor
     * - loads the model
     */
    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->chatModel = new ChatModel();
    }

    /**
     * Loads the default page for the XML example
     */
    public function index()
    {
        return view('chat/chatView');
    }

    /**
     * UPDATES the DB
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
                return $this->response->setJSON([
                    'success' => false,
                    'errors' => $validation
                ]);
            }

            // For HTML form, redirect back with errors
            return redirect()->back()->withInput()->with('errors', $validation);
        }

        // Get username from session
        $name = session()->get('username');

        // Get sanitized inputs
        $message = esc($data['message']);
        $html_redirect = $this->request->getPost('html_redirect');

        $current = Time::now();
        $this->chatModel->insertMsg($name, $message, $current->getTimestamp());

        if ($html_redirect === "true") {
            return redirect()->to('/chat/html');
        }

        // For AJAX requests, return success JSON
        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['success' => true]);
        }

        return '';
    }

    /**
     * XML Backend
     * 
     * @return string
     */
    public function backend()
    {
        // HTTP headers for XML
        $this->response->setHeader('Content-Type', 'text/xml');
        $this->response->setHeader('Cache-Control', 'no-cache');

        // Get the data
        $messages = $this->chatModel->getMsg();

        // Format messages as XML using ChatHelper
        $output = ChatHelper::formatAsXml($messages);

        return $output;
    }

    /**
     * Loads the default view for the JSON example
     * 
     * @return string
     */
    public function json()
    {
        return view('chat/jsonView');
    }

    /**
     * Displays the JSON formatted data
     * 
     * @return string
     */
    public function jsonBackend()
    {
        // Headers for the JSON
        $this->response->setHeader('Cache-Control', 'no-cache, must-revalidate');
        $this->response->setHeader('Expires', 'Mon, 26 Jul 1997 05:00:00 GMT');
        $this->response->setHeader('Content-Type', 'application/json');

        // Get the data
        $messages = $this->chatModel->getMsg();

        // Format messages as JSON using ChatHelper
        $data = ChatHelper::formatAsJson($messages);

        // JSON sized dump to STDOUT
        return $this->response->setJSON($data);
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

        return view('chat/htmlView', $data);
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

        return view('chat/htmlBackView', $data, ['saveData' => true]);
    }
}
