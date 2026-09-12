<?php

namespace App\Helpers;

/**
 * User Helper
 *
 * Contains utility functions for user operations
 */
class UserHelper
{
    /**
     * Set user session data
     *
     * @param array $user User data
     *
     * @return void
     */
    public static function setUserSession(array $user): void
    {
        $userData = [
            'user_id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'logged_in' => true,
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
