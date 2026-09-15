<?php

namespace App\Controllers;

use App\Contracts\UserRepository;
use CodeIgniter\HTTP\ResponseInterface;

final class Profile extends BaseController
{
    private const MAX_AVATAR_BYTES = 2 * 1024 * 1024;
    private const MAX_AVATAR_DIMENSION = 1024;
    private const AVATAR_SIZE = 256;
    private const THEMES = ['system', 'light', 'dark'];
    private const PRESENCES = ['online', 'away', 'busy'];

    public function __construct(private readonly UserRepository $users)
    {
    }

    public function index(): string
    {
        return $this->respondWithView('profile/index', [
            'profile' => $this->currentProfile(),
        ]);
    }

    public function show(): ResponseInterface
    {
        $profile = $this->currentProfile();
        if ($profile === null) {
            return $this->profileResponse(['error' => ['type' => 'not_found', 'message' => 'Profile not found.']], 404);
        }

        return $this->profileResponse(['profile' => $profile]);
    }

    public function update(): ResponseInterface
    {
        $input = $this->request->getJSON(true);
        if (! is_array($input)) {
            $input = $this->request->getPost();
        }

        $profile = $this->normalizeProfile($input);
        if (isset($profile['error'])) {
            return $this->profileResponse(['error' => ['type' => 'validation', 'message' => $profile['error']]], 422);
        }

        $userId = (int) $this->session->get('user_id');
        if (! $this->users->updateProfile($userId, $profile)) {
            return $this->profileResponse(['error' => ['type' => 'storage', 'message' => 'Profile could not be saved.']], 500);
        }

        $this->session->set($profile);

        return $this->show();
    }

    public function uploadAvatar(): ResponseInterface
    {
        $avatar = $this->request->getFile('avatar');
        if ($avatar === null || ! $avatar->isValid() || $avatar->hasMoved()) {
            return $this->avatarError('Choose a valid image to upload.');
        }

        if ($avatar->getSize() > self::MAX_AVATAR_BYTES) {
            return $this->avatarError('Avatar images must be 2 MB or smaller.');
        }

        $temporaryPath = $avatar->getTempName();
        $imageInfo = @getimagesize($temporaryPath);
        if ($imageInfo === false || $imageInfo[0] > self::MAX_AVATAR_DIMENSION || $imageInfo[1] > self::MAX_AVATAR_DIMENSION) {
            return $this->avatarError('Avatar images must be no larger than 1024 by 1024 pixels.');
        }

        $source = $this->createImage($temporaryPath, $imageInfo['mime']);
        if ($source === null) {
            return $this->avatarError('Avatar images must be JPEG, PNG, or WebP files.');
        }

        $userId = (int) $this->session->get('user_id');
        $relativePath = "avatars/{$userId}.webp";
        $directory = WRITEPATH . 'uploads/avatars';
        if (! is_dir($directory) && ! mkdir($directory, 0o755, true) && ! is_dir($directory)) {
            imagedestroy($source);
            return $this->profileResponse(['error' => ['type' => 'storage', 'message' => 'Avatar could not be saved.']], 500);
        }

        $destination = imagecreatetruecolor(self::AVATAR_SIZE, self::AVATAR_SIZE);
        $side = min(imagesx($source), imagesy($source));
        $sourceX = intdiv(imagesx($source) - $side, 2);
        $sourceY = intdiv(imagesy($source) - $side, 2);
        imagecopyresampled($destination, $source, 0, 0, $sourceX, $sourceY, self::AVATAR_SIZE, self::AVATAR_SIZE, $side, $side);

        $path = $directory . "/{$userId}.webp";
        $saved = imagewebp($destination, $path, 85);
        imagedestroy($source);
        imagedestroy($destination);

        if (! $saved || ! $this->users->updateProfile($userId, ['avatar_path' => $relativePath])) {
            return $this->profileResponse(['error' => ['type' => 'storage', 'message' => 'Avatar could not be saved.']], 500);
        }

        $this->session->set('avatar_path', $relativePath);

        return $this->profileResponse(['avatar_url' => site_url("profile/avatar/{$userId}")], 201);
    }

    public function avatar(int $userId): ResponseInterface
    {
        $user = $this->users->findUserById($userId);
        $path = is_array($user) ? (string) ($user['avatar_path'] ?? '') : '';
        $expectedPath = "avatars/{$userId}.webp";
        $absolutePath = WRITEPATH . 'uploads/' . $expectedPath;

        if ($path !== $expectedPath || ! is_file($absolutePath)) {
            return $this->response->setStatusCode(404);
        }

        return $this->response
            ->setHeader('Content-Type', 'image/webp')
            ->setHeader('Cache-Control', 'private, max-age=3600')
            ->setBody((string) file_get_contents($absolutePath));
    }

    public function profiles(): ResponseInterface
    {
        $input = $this->request->getGet('usernames');
        $usernames = is_string($input) ? array_slice(explode(',', $input), 0, 50) : [];

        return $this->profileResponse([
            'profiles' => array_map(fn (array $profile): array => $this->publicProfile($profile), $this->users->findPublicProfilesByUsernames($usernames)),
        ]);
    }

    /** @return array<string, mixed>|null */
    private function currentProfile(): ?array
    {
        $user = $this->users->findUserById((int) $this->session->get('user_id'));

        return is_array($user) ? $this->privateProfile($user) : null;
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function normalizeProfile(array $input): array
    {
        $displayName = trim((string) ($input['display_name'] ?? ''));
        $theme = (string) ($input['theme'] ?? 'system');
        $presence = (string) ($input['presence'] ?? 'online');
        $notifications = $input['notification_prefs'] ?? [];

        if (mb_strlen($displayName) > 100) {
            return ['error' => 'Display name must be 100 characters or fewer.'];
        }
        if (! in_array($theme, self::THEMES, true)) {
            return ['error' => 'Choose a valid theme.'];
        }
        if (! in_array($presence, self::PRESENCES, true)) {
            return ['error' => 'Choose a valid presence status.'];
        }
        if (! is_array($notifications)) {
            return ['error' => 'Notification preferences must be an object.'];
        }

        return [
            'display_name' => $displayName !== '' ? $displayName : null,
            'theme' => $theme,
            'presence' => $presence,
            'notification_prefs' => json_encode([
                'desktop' => (bool) ($notifications['desktop'] ?? true),
                'sound' => (bool) ($notifications['sound'] ?? true),
            ], JSON_THROW_ON_ERROR),
        ];
    }

    /** @return \GdImage|null */
    private function createImage(string $path, string $mime): ?\GdImage
    {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => false,
        };

        return $image instanceof \GdImage ? $image : null;
    }

    private function avatarError(string $message): ResponseInterface
    {
        return $this->profileResponse(['error' => ['type' => 'validation', 'message' => $message]], 422);
    }

    /** @param array<string, mixed> $data */
    private function profileResponse(array $data, int $status = 200): ResponseInterface
    {
        $data['csrf_token'] = csrf_hash();

        return $this->respondWithJson($data, $status);
    }

    /** @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function privateProfile(array $user): array
    {
        return array_merge($this->publicProfile($user), [
            'email' => $user['email'],
            'theme' => $user['theme'] ?? 'system',
            'notification_prefs' => json_decode((string) ($user['notification_prefs'] ?? ''), true) ?: ['desktop' => true, 'sound' => true],
        ]);
    }

    /** @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function publicProfile(array $user): array
    {
        $userId = (int) $user['id'];

        return [
            'id' => $userId,
            'username' => $user['username'],
            'display_name' => $user['display_name'] ?: $user['username'],
            'avatar_url' => $user['avatar_path'] ? site_url("profile/avatar/{$userId}") : null,
            'presence' => $user['presence'] ?? 'offline',
            'last_seen_at' => $user['last_seen_at'] ?? null,
        ];
    }
}
