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
    public static function validateMessage(array $data): array|bool
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
     * @param array|null $pagination Pagination data
     * @return string XML formatted string
     */
    public static function formatAsXml(array $messages, ?array $pagination = null): string
    {
        // Determine status code
        $status_code = (count($messages) == 0) ? 2 : 1;

        // XML headers
        $output = "<?xml version=\"1.0\"?>\n";
        $output .= "<response>\n";
        $output .= "\t<status>$status_code</status>\n";
        $output .= "\t<time>" . time() . "</time>\n";

        // Add pagination data if available
        if ($pagination !== null) {
            $output .= "\t<pagination>\n";
            $output .= "\t\t<page>{$pagination['page']}</page>\n";
            $output .= "\t\t<perPage>{$pagination['perPage']}</perPage>\n";
            $output .= "\t\t<totalItems>{$pagination['totalItems']}</totalItems>\n";
            $output .= "\t\t<totalPages>{$pagination['totalPages']}</totalPages>\n";
            $output .= "\t\t<hasNext>" . ($pagination['hasNext'] ? 'true' : 'false') . "</hasNext>\n";
            $output .= "\t\t<hasPrev>" . ($pagination['hasPrev'] ? 'true' : 'false') . "</hasPrev>\n";
            $output .= "\t</pagination>\n";
        }

        // Loop through all the data
        if (count($messages) > 0) {
            $output .= "\t<messages>\n";
            foreach ($messages as $row) {
                // Sanitize so XML is valid
                $escmsg = htmlspecialchars(stripslashes($row['msg']));
                $output .= "\t\t<message>\n";
                $output .= "\t\t\t<id>{$row['id']}</id>\n";
                $output .= "\t\t\t<author>{$row['user']}</author>\n";
                $output .= "\t\t\t<text>$escmsg</text>\n";
                $output .= "\t\t</message>\n";
            }
            $output .= "\t</messages>\n";
        }
        $output .= "</response>";

        return $output;
    }

    /**
     * Format messages as JSON
     * 
     * @param array $messages Array of message data
     * @param array|null $pagination Pagination data
     * @return array JSON-ready array
     */
    public static function formatAsJson(array $messages, ?array $pagination = null): array
    {
        // For JSON, we can structure the data with messages and pagination
        $result = [
            'messages' => $messages,
            'status' => (count($messages) == 0) ? 2 : 1,
            'time' => time()
        ];

        // Add pagination data if available
        if ($pagination !== null) {
            $result['pagination'] = $pagination;
        }

        // CodeIgniter's response class will handle the conversion to JSON
        return $result;
    }
}
