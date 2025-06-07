<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Helpers\SecurityHelper;

/**
 * @internal
 */
final class SecurityHelperTest extends CIUnitTestCase
{
    public function testSanitizeInput(): void
    {
        // Test with string
        $input = '<script>alert("XSS")</script>';
        $sanitized = SecurityHelper::sanitizeInput($input);
        
        $this->assertNotEquals(
            $input,
            $sanitized,
            'Should sanitize HTML in strings'
        );
        
        // Test with array
        $inputArray = [
            'safe' => 'Normal text',
            'unsafe' => '<script>alert("XSS")</script>',
            'nested' => [
                'unsafe' => '<script>alert("Nested XSS")</script>'
            ]
        ];
        
        $sanitizedArray = SecurityHelper::sanitizeInput($inputArray);
        
        $this->assertEquals(
            $inputArray['safe'],
            $sanitizedArray['safe'],
            'Should not modify safe strings'
        );
        
        $this->assertNotEquals(
            $inputArray['unsafe'],
            $sanitizedArray['unsafe'],
            'Should sanitize unsafe strings'
        );
        
        $this->assertNotEquals(
            $inputArray['nested']['unsafe'],
            $sanitizedArray['nested']['unsafe'],
            'Should sanitize nested unsafe strings'
        );
        
        // Test with non-string/non-array
        $inputInt = 123;
        $this->assertEquals(
            $inputInt,
            SecurityHelper::sanitizeInput($inputInt),
            'Should not modify non-string/non-array values'
        );
    }
    
    public function testGenerateCsrfToken(): void
    {
        $token = SecurityHelper::generateCsrfToken();
        
        $this->assertIsString(
            $token,
            'Should return a string'
        );
        
        $this->assertNotEmpty(
            $token,
            'Should not return an empty string'
        );
    }
    
    public function testGetCsrfTokenName(): void
    {
        $tokenName = SecurityHelper::getCsrfTokenName();
        
        $this->assertIsString(
            $tokenName,
            'Should return a string'
        );
        
        $this->assertNotEmpty(
            $tokenName,
            'Should not return an empty string'
        );
    }
    
    public function testHashPassword(): void
    {
        $password = 'test_password';
        $hash = SecurityHelper::hashPassword($password);
        
        $this->assertIsString(
            $hash,
            'Should return a string'
        );
        
        $this->assertNotEquals(
            $password,
            $hash,
            'Hash should be different from the original password'
        );
        
        // Verify that the hash can be verified
        $this->assertTrue(
            password_verify($password, $hash),
            'Should create a valid password hash'
        );
    }
    
    public function testVerifyPassword(): void
    {
        $password = 'test_password';
        $hash = password_hash($password, PASSWORD_DEFAULT);
        
        $this->assertTrue(
            SecurityHelper::verifyPassword($password, $hash),
            'Should verify correct password'
        );
        
        $this->assertFalse(
            SecurityHelper::verifyPassword('wrong_password', $hash),
            'Should not verify incorrect password'
        );
    }
    
    public function testSanitizeFilename(): void
    {
        $filename = '../../../etc/passwd';
        $sanitized = SecurityHelper::sanitizeFilename($filename);
        
        $this->assertNotEquals(
            $filename,
            $sanitized,
            'Should sanitize filenames with directory traversal'
        );
        
        $this->assertFalse(
            strpos($sanitized, '../') !== false,
            'Sanitized filename should not contain directory traversal sequences'
        );
    }
    
    public function testEncodeHtml(): void
    {
        $html = '<script>alert("XSS")</script>';
        $encoded = SecurityHelper::encodeHtml($html);
        
        $this->assertNotEquals(
            $html,
            $encoded,
            'Should encode HTML entities'
        );
        
        $this->assertStringContainsString(
            '&lt;script&gt;',
            $encoded,
            'Should encode < and > characters'
        );
    }
    
    public function testDecodeHtml(): void
    {
        $encoded = '&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;';
        $decoded = SecurityHelper::decodeHtml($encoded);
        
        $this->assertEquals(
            '<script>alert("XSS")</script>',
            $decoded,
            'Should decode HTML entities'
        );
    }
    
    public function testRandomString(): void
    {
        // Test default length
        $random = SecurityHelper::randomString();
        
        $this->assertIsString(
            $random,
            'Should return a string'
        );
        
        $this->assertEquals(
            16,
            strlen($random),
            'Default length should be 16'
        );
        
        // Test custom length
        $random = SecurityHelper::randomString(8);
        
        $this->assertEquals(
            8,
            strlen($random),
            'Should return a string of the specified length'
        );
        
        // Test different types
        $randomAlpha = SecurityHelper::randomString(8, 'alpha');
        $this->assertMatchesRegularExpression(
            '/^[a-zA-Z]+$/',
            $randomAlpha,
            'Alpha type should only contain letters'
        );
        
        $randomNumeric = SecurityHelper::randomString(8, 'numeric');
        $this->assertMatchesRegularExpression(
            '/^[0-9]+$/',
            $randomNumeric,
            'Numeric type should only contain numbers'
        );
    }
}