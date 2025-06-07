<?php

namespace App\Helpers;

use Config\Services;

/**
 * User Helper
 * 
 * Contains utility functions for user operations
 */
class UserHelper
{
    /**
     * Validate registration data
     * 
     * @param array $data Registration data to validate
     * @return array|bool Validation errors or true if valid
     */
    public static function validateRegistration(array $data): array|bool
    {
        $rules = [
            'username' => [
                'rules' => 'required|min_length[3]|max_length[50]|alpha_numeric|is_unique[users.username]',
                'errors' => [
                    'required' => 'Username is required',
                    'min_length' => 'Username must be at least 3 characters long',
                    'max_length' => 'Username cannot exceed 50 characters',
                    'alpha_numeric' => 'Username can only contain alphanumeric characters',
                    'is_unique' => 'Username is already taken'
                ]
            ],
            'email' => [
                'rules' => 'required|valid_email|is_unique[users.email]',
                'errors' => [
                    'required' => 'Email is required',
                    'valid_email' => 'Please enter a valid email address',
                    'is_unique' => 'Email is already registered'
                ]
            ],
            'password' => [
                'rules' => 'required|min_length[8]',
                'errors' => [
                    'required' => 'Password is required',
                    'min_length' => 'Password must be at least 8 characters long'
                ]
            ],
            'password_confirm' => [
                'rules' => 'required|matches[password]',
                'errors' => [
                    'required' => 'Password confirmation is required',
                    'matches' => 'Passwords do not match'
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
     * Validate login data
     * 
     * @param array $data Login data to validate
     * @return array|bool Validation errors or true if valid
     */
    public static function validateLogin(array $data): array|bool
    {
        $rules = [
            'username' => [
                'rules' => 'required',
                'errors' => [
                    'required' => 'Username is required'
                ]
            ],
            'password' => [
                'rules' => 'required',
                'errors' => [
                    'required' => 'Password is required'
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
     * Set user session data
     * 
     * @param array $user User data
     * @return void
     */
    public static function setUserSession(array $user): void
    {
        $userData = [
            'user_id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'logged_in' => true
        ];

        session()->set($userData);
    }

    /**
     * Clear user session data
     * 
     * @return void
     */
    public static function clearUserSession(): void
    {
        session()->remove(['user_id', 'username', 'email', 'logged_in']);
    }
}