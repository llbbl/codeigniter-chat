<?php

namespace App\Helpers;

use CodeIgniter\HTTP\Response;
use Config\Services;

/**
 * Response Helper
 * 
 * Contains utility functions for standardizing API responses
 */
class ResponseHelper
{
    /**
     * Return a JSON response with the specified data
     * 
     * @param mixed $data The data to be converted to JSON
     * @param int $status The HTTP status code
     * @param array $headers Additional headers to include
     * @return Response
     */
    public static function json(mixed $data, int $status = 200, array $headers = []): Response
    {
        $response = Services::response();
        
        foreach ($headers as $name => $value) {
            $response->setHeader($name, $value);
        }
        
        return $response->setStatusCode($status)
                       ->setJSON($data);
    }
    
    /**
     * Return an XML response with the specified data
     * 
     * @param string $xml The XML string
     * @param int $status The HTTP status code
     * @param array $headers Additional headers to include
     * @return Response
     */
    public static function xml(string $xml, int $status = 200, array $headers = []): Response
    {
        $response = Services::response();
        
        $defaultHeaders = [
            'Content-Type' => 'text/xml',
            'Cache-Control' => 'no-cache'
        ];
        
        $headers = array_merge($defaultHeaders, $headers);
        
        foreach ($headers as $name => $value) {
            $response->setHeader($name, $value);
        }
        
        return $response->setStatusCode($status)
                       ->setBody($xml);
    }
    
    /**
     * Create a standardized success response
     * 
     * @param mixed $data The data to include in the response
     * @param string $message The success message
     * @param int $status The HTTP status code
     * @return array
     */
    public static function success(mixed $data = null, string $message = 'Success', int $status = 200): array
    {
        return [
            'status' => $status,
            'success' => true,
            'message' => $message,
            'data' => $data
        ];
    }
    
    /**
     * Create a standardized error response
     * 
     * @param string $message The error message
     * @param mixed $errors Additional error details
     * @param int $status The HTTP status code
     * @return array
     */
    public static function error(string $message = 'Error', mixed $errors = null, int $status = 400): array
    {
        return [
            'status' => $status,
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ];
    }
    
    /**
     * Create a standardized validation error response
     * 
     * @param array $errors The validation errors
     * @param string $message The error message
     * @param int $status The HTTP status code
     * @return array
     */
    public static function validationError(array $errors, string $message = 'Validation failed', int $status = 400): array
    {
        return self::error($message, $errors, $status);
    }
    
    /**
     * Create a standardized not found response
     * 
     * @param string $message The error message
     * @param mixed $errors Additional error details
     * @return array
     */
    public static function notFound(string $message = 'Resource not found', $errors = null): array
    {
        return self::error($message, $errors, 404);
    }
    
    /**
     * Create a standardized unauthorized response
     * 
     * @param string $message The error message
     * @param mixed $errors Additional error details
     * @return array
     */
    public static function unauthorized(string $message = 'Unauthorized', $errors = null): array
    {
        return self::error($message, $errors, 401);
    }
    
    /**
     * Create a standardized forbidden response
     * 
     * @param string $message The error message
     * @param mixed $errors Additional error details
     * @return array
     */
    public static function forbidden(string $message = 'Forbidden', $errors = null): array
    {
        return self::error($message, $errors, 403);
    }
    
    /**
     * Create a standardized server error response
     * 
     * @param string $message The error message
     * @param mixed $errors Additional error details
     * @return array
     */
    public static function serverError(string $message = 'Server error', $errors = null): array
    {
        return self::error($message, $errors, 500);
    }
}