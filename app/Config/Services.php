<?php

namespace Config;

use App\Models\ChatModel;
use App\Models\UserModel;
use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 *
 * ============================================================================
 * DEPENDENCY INJECTION PATTERN EXPLANATION (for beginners)
 * ============================================================================
 *
 * What is Dependency Injection (DI)?
 * ----------------------------------
 * Dependency Injection is a design pattern where objects receive their
 * dependencies from external sources rather than creating them internally.
 *
 * Why use DI?
 * -----------
 * 1. TESTABILITY: You can easily swap real dependencies with mock objects
 *    during testing. For example, inject a mock ChatModel that doesn't
 *    actually hit the database.
 *
 * 2. LOOSE COUPLING: Controllers don't need to know HOW to create a model,
 *    they just receive one. This makes code more modular and flexible.
 *
 * 3. SINGLE RESPONSIBILITY: The controller's job is to handle requests,
 *    not to manage model creation.
 *
 * 4. CENTRALIZED CONFIGURATION: All dependency creation happens here in
 *    Services.php, making it easy to change implementations application-wide.
 *
 * How it works in CodeIgniter 4:
 * ------------------------------
 * 1. Define a service method here (e.g., chatModel())
 * 2. Use service('chatModel') anywhere in your app to get an instance
 * 3. The $getShared parameter controls singleton behavior:
 *    - true (default): Returns the same instance each time (singleton pattern)
 *    - false: Creates a new instance each time
 *
 * Example usage in a controller:
 *   // In constructor or initController
 *   $this->chatModel = service('chatModel');
 *
 * ============================================================================
 */
class Services extends BaseService
{
    /**
     * Returns the ChatModel service.
     *
     * This service provides access to the ChatModel for handling chat messages.
     * By default, it returns a shared (singleton) instance, which is efficient
     * because the same model instance can be reused across multiple requests
     * within the same process.
     *
     * Usage:
     *   $chatModel = service('chatModel');
     *   // or
     *   $chatModel = \Config\Services::chatModel();
     *
     * Why use this instead of `new ChatModel()`?
     * - Consistent instance management across your application
     * - Easy to swap with a mock during testing
     * - Centralized configuration for the model
     *
     * @param bool $getShared Whether to return a shared instance (singleton).
     *                        Set to false if you need a fresh instance.
     *
     * @return ChatModel The ChatModel instance
     */
    public static function chatModel(bool $getShared = true): ChatModel
    {
        // If requesting a shared instance, check if one already exists and return it
        // This implements the singleton pattern - only one instance is created
        if ($getShared) {
            return static::getSharedInstance('chatModel');
        }

        // Create and return a new instance
        // This is called when $getShared is false, or when creating the first shared instance
        return new ChatModel();
    }

    /**
     * Returns the UserModel service.
     *
     * This service provides access to the UserModel for handling user data
     * and authentication. By default, it returns a shared (singleton) instance.
     *
     * Usage:
     *   $userModel = service('userModel');
     *   // or
     *   $userModel = \Config\Services::userModel();
     *
     * @param bool $getShared Whether to return a shared instance (singleton).
     *                        Set to false if you need a fresh instance.
     *
     * @return UserModel The UserModel instance
     */
    public static function userModel(bool $getShared = true): UserModel
    {
        if ($getShared) {
            return static::getSharedInstance('userModel');
        }

        return new UserModel();
    }
}
