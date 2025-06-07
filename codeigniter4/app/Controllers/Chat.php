<?php

namespace App\Controllers;

use App\Models\ChatModel;
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
        $name = $this->request->getPost('name');
        $message = $this->request->getPost('message');
        $html_redirect = $this->request->getPost('html_redirect');

        $current = Time::now();
        $this->chatModel->insertMsg($name, $message, $current->getTimestamp());
        
        if ($html_redirect === "true") {
            return redirect()->to('/chat');
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
        $query = $this->chatModel->getMsg();
        
        // If empty change the status
        if (count($query) == 0) {
            $status_code = 2;
        } else {
            $status_code = 1;
        }
        
        // XML headers
        $output = "<?xml version=\"1.0\"?>\n";
        $output .= "<response>\n";
        $output .= "\t<status>$status_code</status>\n";
        $output .= "\t<time>" . time() . "</time>\n";
        
        // Loop through all the data
        if (count($query) > 0) {
            foreach ($query as $row) {
                // Sanitize so XML is valid
                $escmsg = htmlspecialchars(stripslashes($row['msg']));
                $output .= "\t<message>\n";
                $output .= "\t\t<id>{$row['id']}</id>\n";
                $output .= "\t\t<author>{$row['user']}</author>\n";
                $output .= "\t\t<text>$escmsg</text>\n";
                $output .= "\t</message>\n";
            }
        }
        $output .= "</response>";
        
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
    public function json_backend()
    {
        // Headers for the JSON
        $this->response->setHeader('Cache-Control', 'no-cache, must-revalidate');
        $this->response->setHeader('Expires', 'Mon, 26 Jul 1997 05:00:00 GMT');
        $this->response->setHeader('Content-Type', 'application/json');
        
        // Get the data
        $data = $this->chatModel->getMsg();
        
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
            'html' => $this->html_backend()
        ];
        
        return view('chat/htmlView', $data);
    }

    /** 
     * Function to display the data in HTML
     * 
     * @return string
     */
    public function html_backend()
    {
        $data = [
            'query' => $this->chatModel->getMsg()
        ];
        
        return view('chat/htmlBackView', $data, ['saveData' => true]);
    }
}