<?php

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use App\Models\UserModel;
use Config\Services;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @internal
 */
final class AuthTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    
    private MockObject $mockUserModel;
    private array $sampleUser;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a mock for the UserModel
        $this->mockUserModel = $this->createMock(UserModel::class);
        
        // Sample user data for testing
        $this->sampleUser = [
            'id' => 1,
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'last_login' => null
        ];
        
        // Replace the service with our mock
        Services::injectMock('usermodel', $this->mockUserModel);
    }
    
    public function testRegisterDisplaysForm(): void
    {
        $result = $this->call('get', '/auth/register');
        
        $result->assertOK();
        $result->assertSee('Register');
        $result->assertSee('Username');
        $result->assertSee('Email');
        $result->assertSee('Password');
    }
    
    public function testProcessRegistrationValidatesInput(): void
    {
        // Test with missing data
        $result = $this->call('post', '/auth/processRegistration', [
            'username' => '',
            'email' => '',
            'password' => '',
            'password_confirm' => ''
        ]);
        
        // Should redirect back to the form with errors
        $result->assertRedirect();
        $result->assertSessionHas('error');
        
        // Test with invalid email
        $result = $this->call('post', '/auth/processRegistration', [
            'username' => 'testuser',
            'email' => 'invalid-email',
            'password' => 'password123',
            'password_confirm' => 'password123'
        ]);
        
        $result->assertRedirect();
        $result->assertSessionHas('error');
        
        // Test with mismatched passwords
        $result = $this->call('post', '/auth/processRegistration', [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirm' => 'different'
        ]);
        
        $result->assertRedirect();
        $result->assertSessionHas('error');
    }
    
    public function testProcessRegistrationCreatesUser(): void
    {
        // Set up the mock to return a user ID
        $this->mockUserModel->method('createUser')
            ->willReturn(1);
        
        // Test with valid data
        $result = $this->call('post', '/auth/processRegistration', [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirm' => 'password123'
        ]);
        
        // Should redirect to login with success message
        $result->assertRedirectTo('/auth/login');
        $result->assertSessionHas('success');
    }
    
    public function testLoginDisplaysForm(): void
    {
        $result = $this->call('get', '/auth/login');
        
        $result->assertOK();
        $result->assertSee('Login');
        $result->assertSee('Username');
        $result->assertSee('Password');
    }
    
    public function testProcessLoginValidatesInput(): void
    {
        // Test with missing data
        $result = $this->call('post', '/auth/processLogin', [
            'username' => '',
            'password' => ''
        ]);
        
        // Should redirect back to the form with errors
        $result->assertRedirect();
        $result->assertSessionHas('error');
    }
    
    public function testProcessLoginAuthenticatesUser(): void
    {
        // Set up the mock to return a user
        $this->mockUserModel->method('verifyCredentials')
            ->willReturn($this->sampleUser);
        
        // Test with valid credentials
        $result = $this->call('post', '/auth/processLogin', [
            'username' => 'testuser',
            'password' => 'password123'
        ]);
        
        // Should redirect to chat
        $result->assertRedirectTo('/chat');
        
        // Session should have user data
        $this->assertTrue(session()->has('logged_in'));
        $this->assertEquals('testuser', session()->get('username'));
    }
    
    public function testProcessLoginRejectsInvalidCredentials(): void
    {
        // Set up the mock to return false (invalid credentials)
        $this->mockUserModel->method('verifyCredentials')
            ->willReturn(false);
        
        // Test with invalid credentials
        $result = $this->call('post', '/auth/processLogin', [
            'username' => 'testuser',
            'password' => 'wrongpassword'
        ]);
        
        // Should redirect back to the form with errors
        $result->assertRedirect();
        $result->assertSessionHas('error');
        
        // Session should not have user data
        $this->assertFalse(session()->has('logged_in'));
    }
    
    public function testLogoutClearsSession(): void
    {
        // Simulate a logged-in user
        $session = Services::session();
        $session->set('logged_in', true);
        $session->set('username', 'testuser');
        
        // Call logout
        $result = $this->call('get', '/auth/logout');
        
        // Should redirect to login with success message
        $result->assertRedirectTo('/auth/login');
        $result->assertSessionHas('success');
        
        // Session should not have user data
        $this->assertFalse(session()->has('logged_in'));
        $this->assertFalse(session()->has('username'));
    }
    
    protected function tearDown(): void
    {
        Services::resetSingle('usermodel');
        parent::tearDown();
    }
}