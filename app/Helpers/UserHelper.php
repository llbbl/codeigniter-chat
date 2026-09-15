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
            'display_name' => $user['display_name'] ?? null,
            'avatar_path' => $user['avatar_path'] ?? null,
            'theme' => $user['theme'] ?? 'system',
            'notification_prefs' => $user['notification_prefs'] ?? null,
            'presence' => $user['presence'] ?? 'offline',
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
        session()->remove([
            'user_id',
            'username',
            'email',
            'display_name',
            'avatar_path',
            'theme',
            'notification_prefs',
            'presence',
            'logged_in',
        ]);
    }
}
