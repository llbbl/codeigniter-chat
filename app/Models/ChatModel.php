<?php

namespace App\Models;

use CodeIgniter\Model;
use Config\Services;

class ChatModel extends Model
{
    protected $table = 'messages';
    protected $primaryKey = 'id';
    protected $allowedFields = ['user', 'msg', 'time'];

    /**
     * Cache key for messages
     * 
     * @var string
     */
    protected $cacheKey = 'chat_messages';

    /**
     * Cache TTL in seconds
     * 
     * @var int
     */
    protected $cacheTTL = 300; // 5 minutes

    /**
     * Get messages from the database with caching
     * 
     * @param int $limit Number of messages to retrieve
     * @return array
     */
    public function getMsg($limit = 10)
    {
        // Create a unique cache key based on the limit
        $cacheKey = $this->cacheKey . '_' . $limit;

        // Get the cache service
        $cache = Services::cache();

        // Try to get data from cache first
        $messages = $cache->get($cacheKey);

        // If not in cache or cache expired, get from database and store in cache
        if ($messages === null) {
            $messages = $this->orderBy('id', 'DESC')
                            ->limit($limit)
                            ->get()
                            ->getResultArray();

            // Store in cache
            $cache->save($cacheKey, $messages, $this->cacheTTL);

            // Log cache miss
            log_message('debug', 'Chat messages cache miss. Fetched from database.');
        } else {
            // Log cache hit
            log_message('debug', 'Chat messages retrieved from cache.');
        }

        return $messages;
    }

    /**
     * Insert a new message into the database and invalidate cache
     * 
     * @param string $name User name
     * @param string $message Message text
     * @param int $current Timestamp
     * @return bool
     */
    public function insertMsg($name, $message, $current)
    {
        $result = $this->insert([
            'user' => $name,
            'msg' => $message,
            'time' => $current
        ]);

        // If insert was successful, invalidate the cache
        if ($result) {
            $this->invalidateCache();
            log_message('debug', 'Chat messages cache invalidated after new message.');
        }

        return $result;
    }

    /**
     * Invalidate all message caches
     * 
     * @return void
     */
    protected function invalidateCache()
    {
        $cache = Services::cache();

        // Delete all cache keys that start with the base cache key
        // This is a simple approach; for more complex scenarios, 
        // you might want to track and delete specific keys
        $cache->deleteMatching($this->cacheKey . '_*');
    }
}
