<?php

namespace Tests\Integration;

use App\Models\UserModel;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\IntegrationTestCase;

#[Group('integration')]
final class UserProfileRepositoryIntegrationTest extends IntegrationTestCase
{
    public function testNewUsersReceiveProfileDefaults(): void
    {
        $model = new UserModel();
        $userId = $model->createUser('profile_user', 'profile@example.com', 'Password123!');

        $this->assertIsInt($userId);
        $user = $model->findUserById($userId);

        $this->assertNotNull($user);
        $this->assertNull($user['display_name']);
        $this->assertNull($user['avatar_path']);
        $this->assertSame('system', $user['theme']);
        $this->assertNull($user['notification_prefs']);
        $this->assertSame('offline', $user['presence']);
        $this->assertNull($user['last_seen_at']);
    }

    public function testProfilesCanBeUpdatedAndReadPublicly(): void
    {
        $model = new UserModel();
        $userId = $model->createUser('profile_user', 'profile@example.com', 'Password123!');
        $this->assertIsInt($userId);

        $this->assertTrue($model->updateProfile($userId, [
            'display_name' => 'Profile User',
            'avatar_path' => 'avatars/1.webp',
            'theme' => 'dark',
            'notification_prefs' => '{"desktop":true}',
        ]));
        $this->assertTrue($model->updatePresence($userId, 'away', '2026-09-15 12:00:00'));

        $user = $model->findUserById($userId);
        $this->assertNotNull($user);
        $this->assertSame('Profile User', $user['display_name']);
        $this->assertSame('dark', $user['theme']);
        $this->assertSame('away', $user['presence']);

        $profiles = $model->findPublicProfilesByUsernames([' profile_user ', '', 'profile_user']);
        $this->assertCount(1, $profiles);
        $this->assertSame('profile_user', $profiles[0]['username']);
        $this->assertArrayNotHasKey('email', $profiles[0]);
        $this->assertArrayNotHasKey('password', $profiles[0]);
    }

    public function testPublicProfileLookupHandlesAnEmptyUsernameList(): void
    {
        $this->assertSame([], (new UserModel())->findPublicProfilesByUsernames([]));
    }
}
