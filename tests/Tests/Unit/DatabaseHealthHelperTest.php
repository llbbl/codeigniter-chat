<?php

namespace Tests\Unit;

use App\Helpers\DatabaseHealthHelper;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Test database health monitoring functionality
 */
class DatabaseHealthHelperTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $migrateOnce = false;
    protected $namespace = 'Tests\Support';

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testCheckDatabaseHealth(): void
    {
        $health = DatabaseHealthHelper::checkDatabaseHealth(false); // Don't use cache
        
        $this->assertArrayHasKey('status', $health);
        $this->assertArrayHasKey('checks', $health);
        $this->assertArrayHasKey('timestamp', $health);
        $this->assertArrayHasKey('response_time_ms', $health);
        
        // Status should be one of the expected values
        $this->assertContains($health['status'], ['healthy', 'degraded', 'unhealthy']);
        
        // Should have all required checks
        $this->assertArrayHasKey('connection', $health['checks']);
        $this->assertArrayHasKey('query_performance', $health['checks']);
        $this->assertArrayHasKey('connection_pool', $health['checks']);
        $this->assertArrayHasKey('storage', $health['checks']);
        $this->assertArrayHasKey('tables', $health['checks']);
        
        // Response time should be reasonable
        $this->assertGreaterThanOrEqual(0, $health['response_time_ms']);
        $this->assertLessThan(5000, $health['response_time_ms']); // Should be under 5 seconds
    }

    public function testGetConnectionStats(): void
    {
        $stats = DatabaseHealthHelper::getConnectionStats();
        
        $this->assertArrayHasKey('driver', $stats);
        $this->assertArrayHasKey('database', $stats);
        $this->assertArrayHasKey('hostname', $stats);
        $this->assertArrayHasKey('port', $stats);
        $this->assertArrayHasKey('charset', $stats);
        
        // For test environment, should be SQLite3
        $this->assertNotEmpty($stats['driver']);
    }

    public function testMonitorConnectionPoolExhaustion(): void
    {
        $poolStatus = DatabaseHealthHelper::monitorConnectionPoolExhaustion();
        
        $this->assertArrayHasKey('current_connections', $poolStatus);
        $this->assertArrayHasKey('max_connections', $poolStatus);
        $this->assertArrayHasKey('usage_percent', $poolStatus);
        $this->assertArrayHasKey('warnings', $poolStatus);
        $this->assertArrayHasKey('alerts', $poolStatus);
        $this->assertArrayHasKey('status', $poolStatus);
        
        $this->assertIsArray($poolStatus['warnings']);
        $this->assertIsArray($poolStatus['alerts']);
        $this->assertContains($poolStatus['status'], ['healthy', 'warning', 'critical']);
        
        // Usage percent should be between 0 and 100
        $this->assertGreaterThanOrEqual(0, $poolStatus['usage_percent']);
        $this->assertLessThanOrEqual(100, $poolStatus['usage_percent']);
    }

    public function testGetHealthEndpointData(): void
    {
        $data = DatabaseHealthHelper::getHealthEndpointData();
        
        $this->assertArrayHasKey('service', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('response_time_ms', $data);
        $this->assertArrayHasKey('details', $data);
        
        $this->assertEquals('database', $data['service']);
        
        $this->assertArrayHasKey('health_checks', $data['details']);
        $this->assertArrayHasKey('connection_stats', $data['details']);
        $this->assertArrayHasKey('pool_status', $data['details']);
    }

    public function testHealthCheckWithData(): void
    {
        // Add some test data first
        $db = \Config\Database::connect();
        $db->table('messages')->insert([
            'user' => 'testuser',
            'msg' => 'Health check test message',
            'time' => time()
        ]);
        
        $health = DatabaseHealthHelper::checkDatabaseHealth(false);
        
        // Should still be healthy with data
        $this->assertNotEquals('unhealthy', $health['status']);
        
        // Check that query performance check is working (may or may not have recent messages)
        $this->assertArrayHasKey('query_performance', $health['checks']);
        $queryCheck = $health['checks']['query_performance'];
        $this->assertArrayHasKey('healthy', $queryCheck);
    }

    public function testBasicConnectionCheck(): void
    {
        $health = DatabaseHealthHelper::checkDatabaseHealth(false);
        
        // Connection check should be healthy
        $connectionCheck = $health['checks']['connection'];
        $this->assertTrue($connectionCheck['healthy']);
        $this->assertArrayHasKey('response_time_ms', $connectionCheck);
        $this->assertArrayHasKey('message', $connectionCheck);
        $this->assertEquals('Database connection successful', $connectionCheck['message']);
    }

    public function testQueryPerformanceCheck(): void
    {
        $health = DatabaseHealthHelper::checkDatabaseHealth(false);
        
        // Query performance check should pass
        $queryCheck = $health['checks']['query_performance'];
        $this->assertArrayHasKey('healthy', $queryCheck);
        $this->assertArrayHasKey('response_time_ms', $queryCheck);
        $this->assertArrayHasKey('message', $queryCheck);
        
        // Response time should be reasonable
        $this->assertGreaterThanOrEqual(0, $queryCheck['response_time_ms']);
    }

    public function testStorageHealthCheck(): void
    {
        $health = DatabaseHealthHelper::checkDatabaseHealth(false);
        
        // Storage check should work
        $storageCheck = $health['checks']['storage'];
        $this->assertArrayHasKey('healthy', $storageCheck);
        $this->assertArrayHasKey('message', $storageCheck);
        
        // May or may not have detailed counts depending on database setup
        $this->assertTrue(is_bool($storageCheck['healthy']));
    }

    public function testTableHealthCheck(): void
    {
        $health = DatabaseHealthHelper::checkDatabaseHealth(false);
        
        // Table check should work
        $tableCheck = $health['checks']['tables'];
        $this->assertArrayHasKey('healthy', $tableCheck);
        $this->assertArrayHasKey('message', $tableCheck);
        $this->assertTrue(is_bool($tableCheck['healthy']));
        
        // If tables exist, they should be in the details
        if (isset($tableCheck['tables'])) {
            $this->assertIsArray($tableCheck['tables']);
        }
    }

    public function testHealthCheckCaching(): void
    {
        // First call should be fresh
        $health1 = DatabaseHealthHelper::checkDatabaseHealth(true);
        $timestamp1 = $health1['timestamp'];
        
        // Second call should use cache (same timestamp)
        $health2 = DatabaseHealthHelper::checkDatabaseHealth(true);
        $timestamp2 = $health2['timestamp'];
        
        $this->assertEquals($timestamp1, $timestamp2);
        
        // Third call without cache should be fresh
        $health3 = DatabaseHealthHelper::checkDatabaseHealth(false);
        $timestamp3 = $health3['timestamp'];
        
        $this->assertGreaterThanOrEqual($timestamp1, $timestamp3);
    }
}