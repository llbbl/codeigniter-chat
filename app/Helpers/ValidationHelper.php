<?php

namespace App\Helpers;

use Config\Services;

/**
 * Validation Helper
 * 
 * Contains utility functions for data validation
 */
class ValidationHelper
{
    /**
     * Validate data against rules
     * 
     * @param array $data Data to validate
     * @param array $rules Validation rules
     * @return array|bool Validation errors or true if valid
     */
    public static function validate(array $data, array $rules)
    {
        $validation = Services::validation();
        $validation->setRules($rules);

        if (!$validation->run($data)) {
            return $validation->getErrors();
        }

        return true;
    }

    /**
     * Get common validation rules for email fields
     * 
     * @param bool $required Whether the field is required
     * @param bool $unique Whether the email should be unique in the users table
     * @return array Validation rules
     */
    public static function getEmailRules(bool $required = true, bool $unique = true): array
    {
        $rules = [
            'rules' => 'valid_email',
            'errors' => [
                'valid_email' => 'Please enter a valid email address'
            ]
        ];

        if ($required) {
            $rules['rules'] = 'required|' . $rules['rules'];
            $rules['errors']['required'] = 'Email is required';
        }

        if ($unique) {
            $rules['rules'] .= '|is_unique[users.email]';
            $rules['errors']['is_unique'] = 'Email is already registered';
        }

        return $rules;
    }

    /**
     * Get common validation rules for username fields
     * 
     * @param bool $required Whether the field is required
     * @param bool $unique Whether the username should be unique in the users table
     * @param int $minLength Minimum length of the username
     * @param int $maxLength Maximum length of the username
     * @return array Validation rules
     */
    public static function getUsernameRules(
        bool $required = true, 
        bool $unique = true, 
        int $minLength = 3, 
        int $maxLength = 50
    ): array {
        $rules = [
            'rules' => 'alpha_numeric',
            'errors' => [
                'alpha_numeric' => 'Username can only contain alphanumeric characters'
            ]
        ];

        if ($required) {
            $rules['rules'] = 'required|' . $rules['rules'];
            $rules['errors']['required'] = 'Username is required';
        }

        if ($minLength > 0) {
            $rules['rules'] .= '|min_length[' . $minLength . ']';
            $rules['errors']['min_length'] = 'Username must be at least ' . $minLength . ' characters long';
        }

        if ($maxLength > 0) {
            $rules['rules'] .= '|max_length[' . $maxLength . ']';
            $rules['errors']['max_length'] = 'Username cannot exceed ' . $maxLength . ' characters';
        }

        if ($unique) {
            $rules['rules'] .= '|is_unique[users.username]';
            $rules['errors']['is_unique'] = 'Username is already taken';
        }

        return $rules;
    }

    /**
     * Get common validation rules for password fields
     * 
     * @param bool $required Whether the field is required
     * @param int $minLength Minimum length of the password
     * @return array Validation rules
     */
    public static function getPasswordRules(bool $required = true, int $minLength = 8): array
    {
        $rules = [
            'rules' => '',
            'errors' => []
        ];

        if ($required) {
            $rules['rules'] = 'required';
            $rules['errors']['required'] = 'Password is required';
        }

        if ($minLength > 0) {
            $rules['rules'] .= ($rules['rules'] ? '|' : '') . 'min_length[' . $minLength . ']';
            $rules['errors']['min_length'] = 'Password must be at least ' . $minLength . ' characters long';
        }

        return $rules;
    }

    /**
     * Get validation rules for password confirmation
     * 
     * @param string $matchField The field to match (usually 'password')
     * @param bool $required Whether the field is required
     * @return array Validation rules
     */
    public static function getPasswordConfirmRules(string $matchField = 'password', bool $required = true): array
    {
        $rules = [
            'rules' => 'matches[' . $matchField . ']',
            'errors' => [
                'matches' => 'Passwords do not match'
            ]
        ];

        if ($required) {
            $rules['rules'] = 'required|' . $rules['rules'];
            $rules['errors']['required'] = 'Password confirmation is required';
        }

        return $rules;
    }

    /**
     * Get common validation rules for message content
     * 
     * @param bool $required Whether the field is required
     * @param int $minLength Minimum length of the message
     * @param int $maxLength Maximum length of the message
     * @return array Validation rules
     */
    public static function getMessageRules(
        bool $required = true, 
        int $minLength = 1, 
        int $maxLength = 500
    ): array {
        $rules = [
            'rules' => '',
            'errors' => []
        ];

        if ($required) {
            $rules['rules'] = 'required';
            $rules['errors']['required'] = 'Message is required';
        }

        if ($minLength > 0) {
            $rules['rules'] .= ($rules['rules'] ? '|' : '') . 'min_length[' . $minLength . ']';
            $rules['errors']['min_length'] = 'Message must be at least ' . $minLength . ' character long';
        }

        if ($maxLength > 0) {
            $rules['rules'] .= ($rules['rules'] ? '|' : '') . 'max_length[' . $maxLength . ']';
            $rules['errors']['max_length'] = 'Message cannot exceed ' . $maxLength . ' characters';
        }

        return $rules;
    }
}