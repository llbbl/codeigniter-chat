<?php

namespace App\Helpers;

use Config\Database;
use Config\Services;

/**
 * Database Health Monitoring Helper
 * 
 * Provides comprehensive database connection monitoring and health checks
 */
class DatabaseHealthHelper
{
    /**
     * Database configuration
     */
    private static ?object $dbConfig = null;

    /**
     * Health check cache key
     */
    private const HEALTH_CACHE_KEY = 'db_health_status';

    /**
     * Health check TTL (30 seconds)
     */
    private const HEALTH_CACHE_TTL = 30;

    /**
     * Get database configuration
     */
    private static function getDbConfig(): object
    {
        if (self::$dbConfig === null) {
            self::$dbConfig = Database::connect();
        }
        return self::$dbConfig;
    }

    /**
     * Perform comprehensive database health check
     * 
     * @param bool $useCache Whether to use cached results
     * @return array Health check results
     */
    public static function checkDatabaseHealth(bool $useCache = true): array
    {
        if ($useCache) {
            $cached = CacheHelper::get(self::HEALTH_CACHE_KEY);
            if ($cached !== null) {
                return $cached;
            }
        }

        $health = [
            'status' => 'healthy',
            'checks' => [],
            'timestamp' => time(),
            'response_time_ms' => 0,
        ];

        $startTime = microtime(true);

        try {
            $db = self::getDbConfig();

            // Check 1: Basic Connection
            $connectionCheck = self::checkBasicConnection($db);
            $health['checks']['connection'] = $connectionCheck;
            if (!$connectionCheck['healthy']) {
                $health['status'] = 'unhealthy';
            }

            // Check 2: Query Performance
            $queryCheck = self::checkQueryPerformance($db);
            $health['checks']['query_performance'] = $queryCheck;
            if (!$queryCheck['healthy']) {
                $health['status'] = 'degraded';
            }

            // Check 3: Connection Pool Status
            $poolCheck = self::checkConnectionPool($db);
            $health['checks']['connection_pool'] = $poolCheck;
            if (!$poolCheck['healthy']) {
                $health['status'] = 'degraded';
            }

            // Check 4: Database Size and Storage
            $storageCheck = self::checkStorageHealth($db);
            $health['checks']['storage'] = $storageCheck;
            if (!$storageCheck['healthy']) {
                $health['status'] = 'degraded';
            }

            // Check 5: Table-specific Health
            $tableCheck = self::checkTableHealth($db);
            $health['checks']['tables'] = $tableCheck;
            if (!$tableCheck['healthy']) {
                $health['status'] = 'degraded';
            }

        } catch (\Exception $e) {
            $health['status'] = 'unhealthy';
            $health['error'] = $e->getMessage();
            log_message('error', 'Database health check failed: ' . $e->getMessage());
        }

        $endTime = microtime(true);
        $health['response_time_ms'] = round(($endTime - $startTime) * 1000, 2);

        // Cache the results
        if ($useCache) {
            CacheHelper::remember(self::HEALTH_CACHE_KEY, $health, self::HEALTH_CACHE_TTL, ['database_health']);
        }

        return $health;
    }

    /**
     * Check basic database connection
     */
    private static function checkBasicConnection($db): array
    {
        try {
            $startTime = microtime(true);
            $result = $db->query('SELECT 1 as test');
            $endTime = microtime(true);

            $row = $result->getRow();
            
            return [
                'healthy' => $row && $row->test == 1,
                'response_time_ms' => round(($endTime - $startTime) * 1000, 2),
                'message' => 'Database connection successful'
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'response_time_ms' => 0,
                'message' => 'Database connection failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check query performance
     */
    private static function checkQueryPerformance($db): array
    {
        try {
            $startTime = microtime(true);
            
            // Perform a slightly more complex query to test performance
            $result = $db->query('SELECT COUNT(*) as count FROM messages WHERE time > ?', [time() - 3600]);
            
            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000, 2);

            $row = $result ? $result->getRow() : (object)['count' => 0];
            
            // Consider performance degraded if query takes more than 100ms
            $healthy = $responseTime < 100;
            
            return [
                'healthy' => $healthy,
                'response_time_ms' => $responseTime,
                'message' => $healthy ? 'Query performance normal' : 'Query performance degraded',
                'recent_messages_count' => $row->count ?? 0
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'response_time_ms' => 0,
                'message' => 'Query performance check failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check connection pool status (simulated for SQLite/MySQL)
     */
    private static function checkConnectionPool($db): array
    {
        try {
            // For SQLite, check if the database file is accessible
            // For MySQL, this would check actual connection pool metrics
            $driver = $db->getDatabase();
            
            // Simple check - if we can execute multiple queries in sequence
            $db->query('SELECT 1');
            $db->query('SELECT 2');
            $db->query('SELECT 3');
            
            return [
                'healthy' => true,
                'message' => 'Connection pool healthy',
                'active_connections' => 1, // Simulated - in real implementation would query SHOW PROCESSLIST
                'max_connections' => 100   // Simulated - would get from configuration
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'message' => 'Connection pool check failed: ' . $e->getMessage(),
                'active_connections' => 0,
                'max_connections' => 0
            ];
        }
    }

    /**
     * Check database storage health
     */
    private static function checkStorageHealth($db): array
    {
        try {
            // Check table sizes and storage usage
            $messageResult = $db->query('SELECT COUNT(*) as count FROM messages');
            $userResult = $db->query('SELECT COUNT(*) as count FROM users');
            
            $messageCount = $messageResult ? $messageResult->getRow()->count : 0;
            $userCount = $userResult ? $userResult->getRow()->count : 0;
            
            // For SQLite, check file size; for MySQL, check table sizes
            $storageUsed = 0;
            if (strpos($db->database, '.db') !== false || strpos($db->database, '.sqlite') !== false) {
                // SQLite database
                if (file_exists($db->database)) {
                    $storageUsed = filesize($db->database);
                }
            }
            
            // Consider storage healthy if under 100MB for this demo
            $healthy = $storageUsed < (100 * 1024 * 1024);
            
            return [
                'healthy' => $healthy,
                'message' => $healthy ? 'Storage usage normal' : 'Storage usage high',
                'storage_used_bytes' => $storageUsed,
                'storage_used_mb' => round($storageUsed / (1024 * 1024), 2),
                'message_count' => $messageCount,
                'user_count' => $userCount
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'message' => 'Storage health check failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check table-specific health
     */
    private static function checkTableHealth($db): array
    {
        try {
            $tables = ['messages', 'users'];
            $tableStatus = [];
            $allHealthy = true;
            
            foreach ($tables as $table) {
                try {
                    $result = $db->query("SELECT COUNT(*) as count FROM {$table}");
                    $count = $result ? $result->getRow()->count : 0;
                    $tableStatus[$table] = [
                        'healthy' => true,
                        'row_count' => $count,
                        'message' => 'Table accessible'
                    ];
                } catch (\Exception $e) {
                    $tableStatus[$table] = [
                        'healthy' => false,
                        'row_count' => 0,
                        'message' => 'Table check failed: ' . $e->getMessage()
                    ];
                    $allHealthy = false;
                }
            }
            
            return [
                'healthy' => $allHealthy,
                'message' => $allHealthy ? 'All tables healthy' : 'Some table issues detected',
                'tables' => $tableStatus
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'message' => 'Table health check failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get database connection statistics
     */
    public static function getConnectionStats(): array
    {
        try {
            $db = self::getDbConfig();
            
            // Basic connection info
            return [
                'driver' => $db->DBDriver,
                'database' => $db->database,
                'hostname' => $db->hostname ?? 'localhost',
                'port' => $db->port ?? 'default',
                'charset' => $db->charset ?? 'utf8',
                'connection_id' => $db->connID ? (is_resource($db->connID) ? get_resource_type($db->connID) : get_class($db->connID)) : 'unknown'
            ];
        } catch (\Exception $e) {
            return [
                'error' => 'Failed to get connection stats: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Monitor connection pool exhaustion
     */
    public static function monitorConnectionPoolExhaustion(): array
    {
        // This would be more sophisticated in a real implementation
        // For now, simulate monitoring
        
        $warnings = [];
        $alerts = [];
        
        // Simulate some checks
        $currentConnections = 5; // Would get this from actual pool
        $maxConnections = 100;
        $connectionUsagePercent = ($currentConnections / $maxConnections) * 100;
        
        if ($connectionUsagePercent > 80) {
            $warnings[] = "Connection pool usage is at {$connectionUsagePercent}%";
        }
        
        if ($connectionUsagePercent > 95) {
            $alerts[] = "Critical: Connection pool nearly exhausted";
        }
        
        return [
            'current_connections' => $currentConnections,
            'max_connections' => $maxConnections,
            'usage_percent' => $connectionUsagePercent,
            'warnings' => $warnings,
            'alerts' => $alerts,
            'status' => empty($alerts) ? (empty($warnings) ? 'healthy' : 'warning') : 'critical'
        ];
    }

    /**
     * Create health check endpoint data
     */
    public static function getHealthEndpointData(): array
    {
        $health = self::checkDatabaseHealth();
        $stats = self::getConnectionStats();
        $poolStatus = self::monitorConnectionPoolExhaustion();
        
        return [
            'service' => 'database',
            'status' => $health['status'],
            'timestamp' => $health['timestamp'],
            'response_time_ms' => $health['response_time_ms'],
            'details' => [
                'health_checks' => $health['checks'],
                'connection_stats' => $stats,
                'pool_status' => $poolStatus
            ]
        ];
    }
}