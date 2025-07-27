<?php

namespace App\Helpers;

use Config\Services;
use CodeIgniter\Cache\CacheInterface;

/**
 * Advanced Cache Helper
 * 
 * Provides intelligent cache management with tags for distributed cache invalidation
 */
class CacheHelper
{
    /**
     * Cache instance
     */
    private static ?CacheInterface $cache = null;

    /**
     * Cache tags registry key
     */
    private const TAGS_REGISTRY = 'cache_tags_registry';

    /**
     * Get cache instance
     */
    private static function getCache(): CacheInterface
    {
        if (self::$cache === null) {
            self::$cache = Services::cache();
        }
        return self::$cache;
    }

    /**
     * Store data in cache with tags
     * 
     * @param string $key Cache key
     * @param mixed $data Data to cache
     * @param int $ttl Time to live in seconds
     * @param array $tags Cache tags for invalidation
     * @return bool Success status
     */
    public static function remember(string $key, mixed $data, int $ttl = 300, array $tags = []): bool
    {
        $cache = self::getCache();
        
        // Store the data
        $success = $cache->save($key, $data, $ttl);
        
        if ($success && !empty($tags)) {
            // Register the key with its tags
            self::registerKeyWithTags($key, $tags);
        }
        
        return $success;
    }

    /**
     * Get data from cache
     * 
     * @param string $key Cache key
     * @return mixed Cached data or null if not found
     */
    public static function get(string $key): mixed
    {
        return self::getCache()->get($key);
    }

    /**
     * Delete cache by key
     * 
     * @param string $key Cache key
     * @return bool Success status
     */
    public static function delete(string $key): bool
    {
        $cache = self::getCache();
        
        // Remove from tags registry
        self::unregisterKeyFromTags($key);
        
        return $cache->delete($key);
    }

    /**
     * Invalidate cache by tags
     * 
     * @param array $tags Tags to invalidate
     * @return int Number of keys invalidated
     */
    public static function invalidateByTags(array $tags): int
    {
        $cache = self::getCache();
        $registry = $cache->get(self::TAGS_REGISTRY) ?? [];
        $keysToDelete = [];
        
        // Find all keys associated with the tags
        foreach ($tags as $tag) {
            if (isset($registry[$tag])) {
                $keysToDelete = array_merge($keysToDelete, $registry[$tag]);
            }
        }
        
        $keysToDelete = array_unique($keysToDelete);
        $deletedCount = 0;
        
        // Delete the keys
        foreach ($keysToDelete as $key) {
            if ($cache->delete($key)) {
                $deletedCount++;
            }
        }
        
        // Update the registry
        foreach ($tags as $tag) {
            unset($registry[$tag]);
        }
        $cache->save(self::TAGS_REGISTRY, $registry, 86400); // Store registry for 24 hours
        
        log_message('debug', "Invalidated {$deletedCount} cache keys for tags: " . implode(', ', $tags));
        
        return $deletedCount;
    }

    /**
     * Register a cache key with its tags
     * 
     * @param string $key Cache key
     * @param array $tags Tags to associate with the key
     */
    private static function registerKeyWithTags(string $key, array $tags): void
    {
        $cache = self::getCache();
        $registry = $cache->get(self::TAGS_REGISTRY) ?? [];
        
        foreach ($tags as $tag) {
            if (!isset($registry[$tag])) {
                $registry[$tag] = [];
            }
            if (!in_array($key, $registry[$tag])) {
                $registry[$tag][] = $key;
            }
        }
        
        $cache->save(self::TAGS_REGISTRY, $registry, 86400);
    }

    /**
     * Unregister a cache key from all tags
     * 
     * @param string $key Cache key to unregister
     */
    private static function unregisterKeyFromTags(string $key): void
    {
        $cache = self::getCache();
        $registry = $cache->get(self::TAGS_REGISTRY) ?? [];
        
        foreach ($registry as $tag => $keys) {
            $index = array_search($key, $keys);
            if ($index !== false) {
                unset($registry[$tag][$index]);
                $registry[$tag] = array_values($registry[$tag]); // Reindex array
                if (empty($registry[$tag])) {
                    unset($registry[$tag]);
                }
            }
        }
        
        $cache->save(self::TAGS_REGISTRY, $registry, 86400);
    }

    /**
     * Get cache statistics
     * 
     * @return array Cache statistics
     */
    public static function getStats(): array
    {
        $cache = self::getCache();
        $registry = $cache->get(self::TAGS_REGISTRY) ?? [];
        
        $totalTags = count($registry);
        $totalKeys = 0;
        
        foreach ($registry as $keys) {
            $totalKeys += count($keys);
        }
        
        return [
            'handler' => config('Cache')->handler,
            'backup_handler' => config('Cache')->backupHandler,
            'total_tags' => $totalTags,
            'total_cached_keys' => $totalKeys,
            'prefix' => config('Cache')->prefix,
        ];
    }

    /**
     * Clear all cache (use with caution)
     * 
     * @return bool Success status
     */
    public static function clearAll(): bool
    {
        $cache = self::getCache();
        
        // Clear the tags registry first
        $cache->delete(self::TAGS_REGISTRY);
        
        return $cache->clean();
    }

    /**
     * Check Redis connection for distributed cache health
     * 
     * @return array Connection status
     */
    public static function checkRedisHealth(): array
    {
        try {
            // Test Redis connection
            $redis = new \Redis();
            $config = config('Cache')->redis;
            
            $connected = $redis->connect($config['host'], $config['port'], $config['timeout']);
            
            if ($connected) {
                if ($config['password']) {
                    $redis->auth($config['password']);
                }
                
                $redis->select($config['database']);
                $info = $redis->info();
                $redis->close();
                
                return [
                    'status' => 'healthy',
                    'connected' => true,
                    'version' => $info['redis_version'] ?? 'unknown',
                    'memory_used' => $info['used_memory_human'] ?? 'unknown',
                    'connected_clients' => $info['connected_clients'] ?? 'unknown',
                ];
            } else {
                return [
                    'status' => 'unhealthy',
                    'connected' => false,
                    'error' => 'Failed to connect to Redis'
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'connected' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}