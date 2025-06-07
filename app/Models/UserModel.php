<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $allowedFields = ['username', 'email', 'password', 'created_at', 'updated_at'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    /**
     * Find a user by their username
     * 
     * @param string $username The username to search for
     * @return array|null The user data or null if not found
     */
    public function findUserByUsername(string $username): ?array
    {
        return $this->where('username', $username)->first();
    }

    /**
     * Find a user by their email
     * 
     * @param string $email The email to search for
     * @return array|null The user data or null if not found
     */
    public function findUserByEmail(string $email): ?array
    {
        return $this->where('email', $email)->first();
    }

    /**
     * Create a new user
     * 
     * @param string $username The username
     * @param string $email The email address
     * @param string $password The password (will be hashed)
     * @return int|false The inserted ID or false on failure
     */
    public function createUser(string $username, string $email, string $password): int|false
    {
        return $this->insert([
            'username' => $username,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT)
        ]);
    }

    /**
     * Verify a user's credentials
     * 
     * @param string $username The username
     * @param string $password The password to verify
     * @return array|null The user data if verified, null otherwise
     */
    public function verifyCredentials(string $username, string $password): ?array
    {
        $user = $this->findUserByUsername($username);

        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }

        return null;
    }
}
