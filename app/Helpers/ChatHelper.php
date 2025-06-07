<?php

namespace App\Helpers;

use Config\Services;

/**
 * Chat Helper
 * 
 * Contains utility functions for chat operations
 */
class ChatHelper
{
    /**
     * Validate message data
     * 
     * @param array $data Data to validate
     * @return array|bool Validation errors or true if valid
     */
    public static function validateMessage($data)
    {
        $rules = [
            'message' => [
                'rules' => 'required|min_length[1]|max_length[500]',
                'errors' => [
                    'required' => 'Message is required',
                    'min_length' => 'Message must be at least 1 character long',
                    'max_length' => 'Message cannot exceed 500 characters'
                ]
            ]
        ];

        $validation = Services::validation();
        $validation->setRules($rules);

        if (!$validation->run($data)) {
            return $validation->getErrors();
        }

        return true;
    }

    /**
     * Format messages as XML
     * 
     * @param array $messages Array of message data
     * @return string XML formatted string
     */
    public static function formatAsXml($messages)
    {
        // Determine status code
        $status_code = (count($messages) == 0) ? 2 : 1;

        // XML headers
        $output = "<?xml version=\"1.0\"?>\n";
        $output .= "<response>\n";
        $output .= "\t<status>$status_code</status>\n";
        $output .= "\t<time>" . time() . "</time>\n";

        // Loop through all the data
        if (count($messages) > 0) {
            foreach ($messages as $row) {
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
     * Format messages as JSON
     * 
     * @param array $messages Array of message data
     * @return array JSON-ready array
     */
    public static function formatAsJson($messages)
    {
        // For JSON, we can just return the array directly
        // as CodeIgniter's response class will handle the conversion
        return $messages;
    }
}