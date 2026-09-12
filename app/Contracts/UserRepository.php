<?php

namespace App\Contracts;

interface UserRepository
{
    /** @return array<string, mixed>|null */
    public function findUserByUsername(string $username): ?array;

    /** @return array<string, mixed>|null */
    public function findUserByEmail(string $email): ?array;

    public function createUser(string $username, string $email, string $password): int|false;

    /** @return array<string, mixed>|null */
    public function verifyCredentials(string $username, string $password): ?array;
}
