<?php

namespace App\Helpers;

/**
 * Chat Helper
 *
 * Contains utility functions for chat operations
 */
class ChatHelper
{
    /**
     * Format messages as XML
     *
     * @param array      $messages   Array of message data
     * @param array|null $pagination Pagination data
     *
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
                $author = self::escapeForXml($row['user']);
                $message = self::escapeForXml($row['msg']);
                $output .= "\t\t<message>\n";
                $output .= "\t\t\t<id>{$row['id']}</id>\n";
                $output .= "\t\t\t<author>$author</author>\n";
                $output .= "\t\t\t<text>$message</text>\n";
                $output .= "\t\t</message>\n";
            }
            $output .= "\t</messages>\n";
        }
        $output .= '</response>';

        return $output;
    }

    /**
     * Format messages as JSON
     *
     * @param array      $messages   Array of message data
     * @param array|null $pagination Pagination data
     *
     * @return array JSON-ready array
     */
    public static function formatAsJson(array $messages, ?array $pagination = null): array
    {
        // For JSON, we can structure the data with messages and pagination
        $result = [
            'messages' => array_map(static function (array $message): array {
                if (array_key_exists('user', $message)) {
                    $message['user'] = self::escapeForJson($message['user']);
                }

                if (array_key_exists('msg', $message)) {
                    $message['msg'] = self::escapeForJson($message['msg']);
                }

                return $message;
            }, $messages),
            'status' => (count($messages) == 0) ? 2 : 1,
            'time' => time(),
        ];

        // Add pagination data if available
        if ($pagination !== null) {
            $result['pagination'] = $pagination;
        }

        // CodeIgniter's response class will handle the conversion to JSON
        return $result;
    }

    /**
     * Escape a user-controlled value before it is encoded in a JSON API response.
     */
    public static function escapeForJson(mixed $value): string
    {
        return esc((string) $value);
    }

    /**
     * Escape a user-controlled value before it is inserted into XML.
     */
    public static function escapeForXml(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, 'UTF-8');
    }
}
