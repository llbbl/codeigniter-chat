<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Helpers\ResponseHelper;
use CodeIgniter\HTTP\Response;

/**
 * @internal
 */
final class ResponseHelperTest extends CIUnitTestCase
{
    public function testJson(): void
    {
        $data = ['test' => 'value'];
        $response = ResponseHelper::json($data);
        
        $this->assertInstanceOf(
            Response::class,
            $response,
            'Should return a Response instance'
        );
        
        $this->assertEquals(
            200,
            $response->getStatusCode(),
            'Default status code should be 200'
        );
        
        // Test with custom status code
        $response = ResponseHelper::json($data, 201);
        $this->assertEquals(
            201,
            $response->getStatusCode(),
            'Should use the provided status code'
        );
        
        // Test with custom headers
        $response = ResponseHelper::json($data, 200, ['X-Test' => 'test-value']);
        $this->assertEquals(
            'test-value',
            $response->getHeaderLine('X-Test'),
            'Should set custom headers'
        );
    }
    
    public function testXml(): void
    {
        $xml = '<?xml version="1.0"?><root><item>test</item></root>';
        $response = ResponseHelper::xml($xml);
        
        $this->assertInstanceOf(
            Response::class,
            $response,
            'Should return a Response instance'
        );
        
        $this->assertEquals(
            200,
            $response->getStatusCode(),
            'Default status code should be 200'
        );
        
        $this->assertEquals(
            'text/xml',
            $response->getHeaderLine('Content-Type'),
            'Content-Type should be text/xml'
        );
        
        $this->assertStringContainsString(
            'no-cache',
            $response->getHeaderLine('Cache-Control'),
            'Cache-Control should contain no-cache'
        );
        
        // Test with custom status code
        $response = ResponseHelper::xml($xml, 201);
        $this->assertEquals(
            201,
            $response->getStatusCode(),
            'Should use the provided status code'
        );
        
        // Test with custom headers
        $response = ResponseHelper::xml($xml, 200, ['X-Test' => 'test-value']);
        $this->assertEquals(
            'test-value',
            $response->getHeaderLine('X-Test'),
            'Should set custom headers'
        );
    }
    
    public function testSuccess(): void
    {
        $result = ResponseHelper::success();
        
        $this->assertIsArray($result, 'Should return an array');
        $this->assertEquals(200, $result['status'], 'Default status should be 200');
        $this->assertTrue($result['success'], 'Success flag should be true');
        $this->assertEquals('Success', $result['message'], 'Default message should be "Success"');
        $this->assertNull($result['data'], 'Default data should be null');
        
        // Test with custom data and message
        $data = ['test' => 'value'];
        $result = ResponseHelper::success($data, 'Custom message');
        
        $this->assertEquals($data, $result['data'], 'Should include the provided data');
        $this->assertEquals('Custom message', $result['message'], 'Should use the provided message');
    }
    
    public function testError(): void
    {
        $result = ResponseHelper::error();
        
        $this->assertIsArray($result, 'Should return an array');
        $this->assertEquals(400, $result['status'], 'Default status should be 400');
        $this->assertFalse($result['success'], 'Success flag should be false');
        $this->assertEquals('Error', $result['message'], 'Default message should be "Error"');
        $this->assertNull($result['errors'], 'Default errors should be null');
        
        // Test with custom message and errors
        $errors = ['field' => 'Error message'];
        $result = ResponseHelper::error('Custom error', $errors, 422);
        
        $this->assertEquals($errors, $result['errors'], 'Should include the provided errors');
        $this->assertEquals('Custom error', $result['message'], 'Should use the provided message');
        $this->assertEquals(422, $result['status'], 'Should use the provided status code');
    }
    
    public function testValidationError(): void
    {
        $errors = ['field' => 'Error message'];
        $result = ResponseHelper::validationError($errors);
        
        $this->assertIsArray($result, 'Should return an array');
        $this->assertEquals(400, $result['status'], 'Default status should be 400');
        $this->assertFalse($result['success'], 'Success flag should be false');
        $this->assertEquals('Validation failed', $result['message'], 'Default message should be "Validation failed"');
        $this->assertEquals($errors, $result['errors'], 'Should include the provided errors');
        
        // Test with custom message and status
        $result = ResponseHelper::validationError($errors, 'Custom validation error', 422);
        
        $this->assertEquals('Custom validation error', $result['message'], 'Should use the provided message');
        $this->assertEquals(422, $result['status'], 'Should use the provided status code');
    }
    
    public function testNotFound(): void
    {
        $result = ResponseHelper::notFound();
        
        $this->assertIsArray($result, 'Should return an array');
        $this->assertEquals(404, $result['status'], 'Status should be 404');
        $this->assertFalse($result['success'], 'Success flag should be false');
        $this->assertEquals('Resource not found', $result['message'], 'Default message should be "Resource not found"');
        
        // Test with custom message and errors
        $errors = ['id' => 'User not found'];
        $result = ResponseHelper::notFound('User not found', $errors);
        
        $this->assertEquals('User not found', $result['message'], 'Should use the provided message');
        $this->assertEquals($errors, $result['errors'], 'Should include the provided errors');
    }
    
    public function testUnauthorized(): void
    {
        $result = ResponseHelper::unauthorized();
        
        $this->assertIsArray($result, 'Should return an array');
        $this->assertEquals(401, $result['status'], 'Status should be 401');
        $this->assertFalse($result['success'], 'Success flag should be false');
        $this->assertEquals('Unauthorized', $result['message'], 'Default message should be "Unauthorized"');
    }
    
    public function testForbidden(): void
    {
        $result = ResponseHelper::forbidden();
        
        $this->assertIsArray($result, 'Should return an array');
        $this->assertEquals(403, $result['status'], 'Status should be 403');
        $this->assertFalse($result['success'], 'Success flag should be false');
        $this->assertEquals('Forbidden', $result['message'], 'Default message should be "Forbidden"');
    }
    
    public function testServerError(): void
    {
        $result = ResponseHelper::serverError();
        
        $this->assertIsArray($result, 'Should return an array');
        $this->assertEquals(500, $result['status'], 'Status should be 500');
        $this->assertFalse($result['success'], 'Success flag should be false');
        $this->assertEquals('Server error', $result['message'], 'Default message should be "Server error"');
    }
}