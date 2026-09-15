<?php

namespace Tests\Integration;

use App\Controllers\Profile;
use App\Models\UserModel;
use CodeIgniter\HTTP\Files\FileCollection;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Test\TestResponse;
use PHPUnit\Framework\Attributes\Group;
use ReflectionProperty;
use Tests\Support\IntegrationTestCase;
use Config\Services;

#[Group('integration')]
final class ProfileIntegrationTest extends IntegrationTestCase
{
    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    public function testProfileApiRequiresAuthentication(): void
    {
        $result = $this->withHeaders(['Origin' => 'http://localhost'])
            ->call('get', '/api/v1/profile');

        $this->assertSame(401, $result->response()->getStatusCode());
    }

    public function testAuthenticatedUserCanReadAndUpdateProfile(): void
    {
        $model = new UserModel();
        $userId = $model->createUser('profile-user', 'profile@example.com', 'Password123!');
        $this->assertIsInt($userId);

        $getResult = $this->loginAs([
            'id' => $userId,
            'username' => 'profile-user',
            'email' => 'profile@example.com',
        ])->withHeaders(['Origin' => 'http://localhost'])
            ->call('get', '/api/v1/profile');

        $this->assertSame(200, $getResult->response()->getStatusCode());
        $profile = json_decode($getResult->getJSON(), true, flags: JSON_THROW_ON_ERROR)['profile'];
        $this->assertSame('profile-user', $profile['display_name']);
        $this->assertSame('system', $profile['theme']);

        $updateResult = $this->withHeaders([
            'Origin' => 'http://localhost',
            'Content-Type' => 'application/json',
            'X-CSRF-TOKEN' => csrf_hash(),
        ])->withBody(json_encode([
            'display_name' => 'Profile User',
            'theme' => 'dark',
            'presence' => 'away',
            'notification_prefs' => ['desktop' => false, 'sound' => true],
        ], JSON_THROW_ON_ERROR))->call('post', '/api/v1/profile');

        $this->assertSame(200, $updateResult->response()->getStatusCode());
        $updated = json_decode($updateResult->getJSON(), true, flags: JSON_THROW_ON_ERROR)['profile'];
        $this->assertSame('Profile User', $updated['display_name']);
        $this->assertSame('dark', $updated['theme']);
        $this->assertFalse($updated['notification_prefs']['desktop']);
    }

    public function testProfileUpdateRejectsInvalidPreferences(): void
    {
        $model = new UserModel();
        $userId = $model->createUser('profile-user', 'profile@example.com', 'Password123!');
        $this->assertIsInt($userId);

        $result = $this->loginAs([
            'id' => $userId,
            'username' => 'profile-user',
            'email' => 'profile@example.com',
        ])->withHeaders([
            'Origin' => 'http://localhost',
            'Content-Type' => 'application/json',
            'X-CSRF-TOKEN' => csrf_hash(),
        ])->withBody(json_encode([
            'theme' => 'ultraviolet',
            'presence' => 'invisible',
        ], JSON_THROW_ON_ERROR))->call('post', '/api/v1/profile');

        $this->assertSame(422, $result->response()->getStatusCode());
    }

    public function testPublicProfileLookupDoesNotExposePrivateFields(): void
    {
        $model = new UserModel();
        $userId = $model->createUser('profile-user', 'profile@example.com', 'Password123!');
        $this->assertIsInt($userId);

        $result = $this->loginAs([
            'id' => $userId,
            'username' => 'profile-user',
            'email' => 'profile@example.com',
        ])->withHeaders(['Origin' => 'http://localhost'])
            ->call('get', '/api/v1/profiles?usernames=profile-user');

        $this->assertSame(200, $result->response()->getStatusCode());
        $profile = json_decode($result->getJSON(), true, flags: JSON_THROW_ON_ERROR)['profiles'][0];
        $this->assertSame('profile-user', $profile['username']);
        $this->assertArrayNotHasKey('email', $profile);
        $this->assertArrayNotHasKey('theme', $profile);
    }

    public function testAvatarUploadIsCroppedToA256PixelWebp(): void
    {
        $model = new UserModel();
        $userId = $model->createUser('avatar-user', 'avatar@example.com', 'Password123!');
        $this->assertIsInt($userId);
        $source = $this->createPng(400, 200);
        $avatarPath = WRITEPATH . "uploads/avatars/{$userId}.webp";
        $this->temporaryFiles[] = $avatarPath;

        $result = $this->uploadAvatar($source, $userId);

        $this->assertSame(201, $result->response()->getStatusCode());
        $this->assertFileExists($avatarPath);
        $image = getimagesize($avatarPath);
        $this->assertIsArray($image);
        $this->assertSame([256, 256, IMAGETYPE_WEBP], [$image[0], $image[1], $image[2]]);
        $user = $model->findUserById($userId);
        $this->assertNotNull($user);
        $this->assertSame("avatars/{$userId}.webp", $user['avatar_path']);
    }

    public function testAvatarUploadRejectsImagesLargerThan1024Pixels(): void
    {
        $model = new UserModel();
        $userId = $model->createUser('avatar-user', 'avatar@example.com', 'Password123!');
        $this->assertIsInt($userId);

        $result = $this->uploadAvatar($this->createPng(1025, 20), $userId);

        $this->assertSame(422, $result->response()->getStatusCode());
        $payload = json_decode($result->getJSON(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('1024', $payload['error']['message']);
    }

    public function testThemeIsReappliedAfterLogoutAndLogin(): void
    {
        $model = new UserModel();
        $userId = $model->createUser('theme-user', 'theme@example.com', 'Password123!');
        $this->assertIsInt($userId);
        $model->updateProfile($userId, ['theme' => 'dark']);

        $login = fn () => $this->withHeaders(['Origin' => 'http://localhost'])
            ->call('post', '/auth/processLogin', [
                'username' => 'theme-user',
                'password' => 'Password123!',
                csrf_token() => csrf_hash(),
            ]);

        $this->assertSame(302, $login()->response()->getStatusCode());
        $this->assertSame('dark', session()->get('theme'));

        $this->withHeaders(['Origin' => 'http://localhost'])->call('get', '/auth/logout');
        $this->assertFalse(session()->has('theme'));

        $this->assertSame(302, $login()->response()->getStatusCode());
        $this->assertSame('dark', session()->get('theme'));
    }

    private function createPng(int $width, int $height): string
    {
        $path = tempnam(sys_get_temp_dir(), 'profile-avatar-');
        $this->assertNotFalse($path);
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 40, 100, 160));
        imagepng($image, $path);
        imagedestroy($image);
        $this->temporaryFiles[] = $path;

        return $path;
    }

    private function uploadAvatar(string $source, int $userId): TestResponse
    {
        session()->set(['logged_in' => true, 'user_id' => $userId, 'username' => 'avatar-user']);
        $request = service('incomingrequest', config(\Config\App::class), false);
        $files = new FileCollection();
        (new ReflectionProperty(FileCollection::class, 'files'))->setValue($files, [
            'avatar' => new ProfileTestUploadedFile($source, 'avatar.png', 'image/png', filesize($source), UPLOAD_ERR_OK),
        ]);
        (new ReflectionProperty($request, 'files'))->setValue($request, $files);

        $controller = new Profile(new UserModel());
        $controller->initController($request, service('response', null, false), Services::logger());

        return new TestResponse($controller->uploadAvatar());
    }
}

final class ProfileTestUploadedFile extends UploadedFile
{
    public function isValid(): bool
    {
        return true;
    }
}
