<?php

namespace Tests\Integration;

use App\Models\UserModel;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\IntegrationTestCase;

#[Group('integration')]
final class AuthIntegrationTest extends IntegrationTestCase
{
    public function testRegistrationPersistsAUserWithAHashedPassword(): void
    {
        $result = $this->withHeaders(['Origin' => 'http://localhost'])
            ->call('post', '/auth/processRegistration', [
                'username' => 'registereduser',
                'email' => 'registered@example.com',
                'password' => 'Password123!',
                'password_confirm' => 'Password123!',
                csrf_token() => csrf_hash(),
            ]);

        $result->assertRedirectTo('/auth/login');
        $result->assertSessionHas('success');

        $user = (new UserModel())->findUserByUsername('registereduser');
        $this->assertNotNull($user);
        $this->assertSame('registered@example.com', $user['email']);
        $this->assertTrue(password_verify('Password123!', $user['password']));
        $this->assertFalse(session()->has('logged_in'));
    }

    public function testRegistrationRejectsDuplicateDatabaseValues(): void
    {
        $model = new UserModel();
        $this->assertIsInt($model->createUser('existinguser', 'existing@example.com', 'Password123!'));

        $result = $this->withHeaders(['Origin' => 'http://localhost'])
            ->call('post', '/auth/processRegistration', [
                'username' => 'existinguser',
                'email' => 'another@example.com',
                'password' => 'Password123!',
                'password_confirm' => 'Password123!',
                csrf_token() => csrf_hash(),
            ]);

        $result->assertRedirect();
        $result->assertSessionHas('error');
        $this->assertSame(1, $model->where('username', 'existinguser')->countAllResults());
    }

    public function testLoginVerifiesARealPasswordHashAndCreatesTheSession(): void
    {
        $model = new UserModel();
        $userId = $model->createUser('loginuser', 'login@example.com', 'Password123!');
        $this->assertIsInt($userId);

        $result = $this->withHeaders(['Origin' => 'http://localhost'])
            ->call('post', '/auth/processLogin', [
                'username' => 'loginuser',
                'password' => 'Password123!',
                csrf_token() => csrf_hash(),
            ]);

        $result->assertRedirectTo('/chat');
        $this->assertTrue(session()->get('logged_in'));
        $this->assertSame($userId, session()->get('user_id'));
        $this->assertSame('loginuser', session()->get('username'));
        $this->assertNotEmpty(session()->get('websocket_token'));
    }

    public function testLoginRejectsAnIncorrectPasswordFromTheDatabase(): void
    {
        $model = new UserModel();
        $this->assertIsInt($model->createUser('loginuser', 'login@example.com', 'Password123!'));

        $result = $this->withHeaders(['Origin' => 'http://localhost'])
            ->call('post', '/auth/processLogin', [
                'username' => 'loginuser',
                'password' => 'WrongPassword!',
                csrf_token() => csrf_hash(),
            ]);

        $result->assertRedirect();
        $result->assertSessionHas('error');
        $this->assertFalse(session()->has('logged_in'));
    }
}
