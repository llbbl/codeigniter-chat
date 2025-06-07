<?php

namespace App\Helpers;

/**
 * Text Helper
 * 
 * Contains utility functions for text manipulation
 */
class TextHelper
{
    /**
     * Truncate a string to a specified length and append an ellipsis if needed
     * 
     * @param string $str The string to truncate
     * @param int $length The maximum length of the string
     * @param string $ellipsis The string to append if truncated
     * @return string The truncated string
     */
    public static function truncate(string $str, int $length = 100, string $ellipsis = '...'): string
    {
        if (mb_strlen($str) <= $length) {
            return $str;
        }
        
        return mb_substr($str, 0, $length) . $ellipsis;
    }
    
    /**
     * Convert a string to title case
     * 
     * @param string $str The string to convert
     * @return string The title-cased string
     */
    public static function titleCase(string $str): string
    {
        return mb_convert_case($str, MB_CASE_TITLE, 'UTF-8');
    }
}