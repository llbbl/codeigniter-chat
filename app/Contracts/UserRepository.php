<?php

namespace App\Contracts;

interface UserRepository
{
    /** @return array<string, mixed>|null */
    public function findUserByUsername(string $username): ?array;

    /** @return array<string, mixed>|null */
    public function findUserByEmail(string $email): ?array;

    /** @return array<string, mixed>|null */
    public function findUserById(int $userId): ?array;

    public function createUser(string $username, string $email, string $password): int|false;

    /** @return array<string, mixed>|null */
    public function verifyCredentials(string $username, string $password): ?array;

    /** @param array<string, mixed> $profile */
    public function updateProfile(int $userId, array $profile): bool;

    /** @return list<array<string, mixed>> */
    public function findPublicProfilesByUsernames(array $usernames): array;

    public function updatePresence(int $userId, string $presence, ?string $lastSeenAt): bool;
}
