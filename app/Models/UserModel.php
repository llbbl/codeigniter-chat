<?php

namespace App\Models;

use App\Contracts\UserRepository;
use CodeIgniter\Model;

class UserModel extends Model implements UserRepository
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'username',
        'email',
        'password',
        'display_name',
        'avatar_path',
        'theme',
        'notification_prefs',
        'presence',
        'last_seen_at',
        'created_at',
        'updated_at',
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    /**
     * Find a user by their username
     *
     * @param string $username The username to search for
     *
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
     *
     * @return array|null The user data or null if not found
     */
    public function findUserByEmail(string $email): ?array
    {
        return $this->where('email', $email)->first();
    }

    /** @return array<string, mixed>|null */
    public function findUserById(int $userId): ?array
    {
        return $this->find($userId);
    }

    /**
     * Create a new user
     *
     * @param string $username The username
     * @param string $email    The email address
     * @param string $password The password (will be hashed)
     *
     * @return int|false The inserted ID or false on failure
     */
    public function createUser(string $username, string $email, string $password): int|false
    {
        return $this->insert([
            'username' => $username,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
        ]);
    }

    /**
     * Verify a user's credentials
     *
     * @param string $username The username
     * @param string $password The password to verify
     *
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

    /** @param array<string, mixed> $profile */
    public function updateProfile(int $userId, array $profile): bool
    {
        return $this->update($userId, $profile);
    }

    /** @return list<array<string, mixed>> */
    public function findPublicProfilesByUsernames(array $usernames): array
    {
        $usernames = array_values(array_unique(array_filter(
            array_map(static fn (mixed $username): mixed => is_string($username) ? trim($username) : $username, $usernames),
            static fn (mixed $username): bool => is_string($username) && $username !== '',
        )));

        if ($usernames === []) {
            return [];
        }

        return $this->select('id, username, display_name, avatar_path, presence, last_seen_at')
            ->whereIn('username', $usernames)
            ->findAll();
    }

    public function updatePresence(int $userId, string $presence, ?string $lastSeenAt): bool
    {
        return $this->update($userId, [
            'presence' => $presence,
            'last_seen_at' => $lastSeenAt,
        ]);
    }
}
