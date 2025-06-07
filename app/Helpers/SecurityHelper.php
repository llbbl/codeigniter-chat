<?php

namespace App\Helpers;

use Config\Services;

/**
 * Security Helper
 * 
 * Contains utility functions for security-related operations
 */
class SecurityHelper
{
    /**
     * Sanitize input data to prevent XSS attacks
     * 
     * @param mixed $input The input to sanitize
     * @return mixed The sanitized input
     */
    public static function sanitizeInput($input)
    {
        if (is_array($input)) {
            $sanitized = [];
            foreach ($input as $key => $value) {
                $sanitized[$key] = self::sanitizeInput($value);
            }
            return $sanitized;
        }
        
        if (is_string($input)) {
            return esc($input);
        }
        
        return $input;
    }
    
    /**
     * Generate a CSRF token
     * 
     * @return string The CSRF token
     */
    public static function generateCsrfToken(): string
    {
        return csrf_hash();
    }
    
    /**
     * Get the CSRF token name
     * 
     * @return string The CSRF token name
     */
    public static function getCsrfTokenName(): string
    {
        return csrf_token();
    }
    
    /**
     * Validate a CSRF token
     * 
     * @param string $token The token to validate
     * @return bool True if the token is valid
     */
    public static function validateCsrfToken(string $token): bool
    {
        $security = Services::security();
        return $security->validateCSRFToken($token);
    }
    
    /**
     * Generate a random string
     * 
     * @param int $length The length of the string
     * @param string $type The type of string (alpha, alnum, numeric, nozero, md5, sha1)
     * @return string The random string
     */
    public static function randomString(int $length = 16, string $type = 'alnum'): string
    {
        $security = Services::security();
        return $security->getRandomName($length, $type);
    }
    
    /**
     * Hash a password
     * 
     * @param string $password The password to hash
     * @return string The hashed password
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * Verify a password against a hash
     * 
     * @param string $password The password to verify
     * @param string $hash The hash to verify against
     * @return bool True if the password matches the hash
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
    
    /**
     * Sanitize a filename to prevent directory traversal attacks
     * 
     * @param string $filename The filename to sanitize
     * @return string The sanitized filename
     */
    public static function sanitizeFilename(string $filename): string
    {
        $security = Services::security();
        return $security->sanitizeFilename($filename);
    }
    
    /**
     * Encode HTML entities in a string
     * 
     * @param string $str The string to encode
     * @return string The encoded string
     */
    public static function encodeHtml(string $str): string
    {
        return htmlentities($str, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Decode HTML entities in a string
     * 
     * @param string $str The string to decode
     * @return string The decoded string
     */
    public static function decodeHtml(string $str): string
    {
        return html_entity_decode($str, ENT_QUOTES, 'UTF-8');
    }
}