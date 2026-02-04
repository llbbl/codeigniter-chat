<?php

namespace App\Controllers;

use App\Helpers\DatabaseHealthHelper;
use App\Helpers\CacheHelper;

/**
 * Health Check Controller
 * 
 * Provides health monitoring endpoints for database connectivity and performance
 */
class HealthController extends BaseController
{
    /**
     * Overall system health check
     * 
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            $dbHealth = DatabaseHealthHelper::getHealthEndpointData();
            $cacheHealth = CacheHelper::checkRedisHealth();
            
            $overallStatus = 'healthy';
            if ($dbHealth['status'] === 'unhealthy' || $cacheHealth['status'] === 'unhealthy') {
                $overallStatus = 'unhealthy';
            } elseif ($dbHealth['status'] === 'degraded' || $cacheHealth['status'] === 'degraded') {
                $overallStatus = 'degraded';
            }
            
            $response = [
                'status' => $overallStatus,
                'timestamp' => time(),
                'services' => [
                    'database' => $dbHealth,
                    'cache' => [
                        'service' => 'redis',
                        'status' => $cacheHealth['status'],
                        'connected' => $cacheHealth['connected'],
                        'details' => $cacheHealth
                    ]
                ]
            ];
            
            $httpStatus = match($overallStatus) {
                'healthy' => 200,
                'degraded' => 200, // Still functional
                'unhealthy' => 503,
                default => 500
            };
            
            return $this->respondWithJson($response, $httpStatus);
            
        } catch (\Throwable $e) {
            return $this->handleException($e, 'server', 500);
        }
    }

    /**
     * Database-specific health check
     * 
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function database(): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            $health = DatabaseHealthHelper::getHealthEndpointData();
            
            $httpStatus = match($health['status']) {
                'healthy' => 200,
                'degraded' => 200,
                'unhealthy' => 503,
                default => 500
            };
            
            return $this->respondWithJson($health, $httpStatus);
            
        } catch (\Throwable $e) {
            return $this->handleException($e, 'server', 500);
        }
    }

    /**
     * Cache-specific health check
     * 
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function cache(): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            $health = CacheHelper::checkRedisHealth();
            $stats = CacheHelper::getStats();
            
            $response = [
                'service' => 'cache',
                'status' => $health['status'],
                'timestamp' => time(),
                'details' => array_merge($health, $stats)
            ];
            
            $httpStatus = match($health['status']) {
                'healthy' => 200,
                'degraded' => 200,
                'unhealthy' => 503,
                default => 500
            };
            
            return $this->respondWithJson($response, $httpStatus);
            
        } catch (\Throwable $e) {
            return $this->handleException($e, 'server', 500);
        }
    }

    /**
     * Database connection statistics
     * 
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function dbStats(): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            $stats = DatabaseHealthHelper::getConnectionStats();
            $poolStatus = DatabaseHealthHelper::monitorConnectionPoolExhaustion();
            
            $response = [
                'connection_stats' => $stats,
                'pool_status' => $poolStatus,
                'timestamp' => time()
            ];
            
            return $this->respondWithJson($response);
            
        } catch (\Throwable $e) {
            return $this->handleException($e, 'server', 500);
        }
    }

    /**
     * Database performance check (for monitoring systems)
     * 
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function dbPerformance(): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            // Force fresh health check without cache
            $health = DatabaseHealthHelper::checkDatabaseHealth(false);
            
            $response = [
                'status' => $health['status'],
                'response_time_ms' => $health['response_time_ms'],
                'checks' => $health['checks'],
                'timestamp' => $health['timestamp']
            ];
            
            return $this->respondWithJson($response);
            
        } catch (\Throwable $e) {
            return $this->handleException($e, 'server', 500);
        }
    }

    /**
     * Readiness probe (for Kubernetes/Docker)
     * 
     * Returns 200 if the service is ready to receive traffic
     * 
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function ready(): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            $dbHealth = DatabaseHealthHelper::checkDatabaseHealth();
            $cacheHealth = CacheHelper::checkRedisHealth();
            
            $ready = $dbHealth['status'] !== 'unhealthy' && 
                     $cacheHealth['status'] !== 'unhealthy';
            
            $response = [
                'ready' => $ready,
                'timestamp' => time(),
                'checks' => [
                    'database' => $dbHealth['status'],
                    'cache' => $cacheHealth['status']
                ]
            ];
            
            return $this->respondWithJson($response, $ready ? 200 : 503);
            
        } catch (\Throwable $e) {
            return $this->respondWithJson(['ready' => false, 'error' => $e->getMessage()], 503);
        }
    }

    /**
     * Liveness probe (for Kubernetes/Docker)
     * 
     * Returns 200 if the service is alive (basic functionality check)
     * 
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function live(): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            // Very basic check - can we respond?
            $response = [
                'alive' => true,
                'timestamp' => time(),
                'uptime' => 'unknown' // Could calculate if we tracked start time
            ];
            
            return $this->respondWithJson($response);
            
        } catch (\Throwable $e) {
            return $this->respondWithJson(['alive' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Connection pool monitoring endpoint
     * 
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function connectionPool(): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            $poolStatus = DatabaseHealthHelper::monitorConnectionPoolExhaustion();
            
            $httpStatus = match($poolStatus['status']) {
                'healthy' => 200,
                'warning' => 200,
                'critical' => 503,
                default => 500
            };
            
            return $this->respondWithJson($poolStatus, $httpStatus);
            
        } catch (\Throwable $e) {
            return $this->handleException($e, 'server', 500);
        }
    }
}