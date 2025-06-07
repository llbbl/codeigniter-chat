<?php

namespace App\Models;

use CodeIgniter\Model;
use Config\Services;

/**
 * Chat Model
 * 
 * This model handles all database operations related to chat messages.
 * It provides methods for retrieving, inserting, and filtering messages,
 * with support for caching and pagination to improve performance.
 * 
 * @package App\Models
 */
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
     * Get messages from the database with caching and pagination
     * 
     * @param int $page Page number (1-based)
     * @param int $perPage Number of messages per page
     * @return array
     */
    public function getMsgPaginated($page = 1, $perPage = 10)
    {
        // Ensure page is at least 1
        $page = max(1, (int)$page);

        // Calculate offset
        $offset = ($page - 1) * $perPage;

        // Create a unique cache key based on the pagination parameters
        $cacheKey = $this->cacheKey . '_page_' . $page . '_' . $perPage;

        // Get the cache service
        $cache = Services::cache();

        // Try to get data from cache first
        $result = $cache->get($cacheKey);

        // If not in cache or cache expired, get from database and store in cache
        if ($result === null) {
            // Get total count for pagination
            $totalCount = $this->countAllResults();

            // Use time index for ordering instead of id
            // This is more efficient for chat applications where time-based ordering is natural
            $messages = $this->orderBy('time', 'DESC')
                            ->limit($perPage, $offset)
                            ->get()
                            ->getResultArray();

            // Calculate total pages
            $totalPages = ceil($totalCount / $perPage);

            // Create result with pagination data
            $result = [
                'messages' => $messages,
                'pagination' => [
                    'page' => $page,
                    'perPage' => $perPage,
                    'totalItems' => $totalCount,
                    'totalPages' => $totalPages,
                    'hasNext' => $page < $totalPages,
                    'hasPrev' => $page > 1
                ]
            ];

            // Store in cache
            $cache->save($cacheKey, $result, $this->cacheTTL);

            // Log cache miss
            log_message('debug', 'Chat messages cache miss. Fetched from database with pagination.');
        } else {
            // Log cache hit
            log_message('debug', 'Chat messages with pagination retrieved from cache.');
        }

        return $result;
    }

    /**
     * Get messages from the database with caching
     * 
     * @param int $limit Number of messages to retrieve
     * @return array
     */
    public function getMsg($limit = 10)
    {
        // For backward compatibility, get the first page with the specified limit
        $result = $this->getMsgPaginated(1, $limit);

        // Return just the messages for backward compatibility
        return $result['messages'];
    }

    /**
     * Insert a new message into the database and invalidate cache
     * 
     * This method inserts a new chat message into the database with the given
     * user name, message text, and timestamp. If the insertion is successful,
     * it invalidates all related cache entries to ensure that subsequent requests
     * will receive the updated data.
     * 
     * @param string $name     User name of the message author
     * @param string $message  Message text content
     * @param int    $current  Unix timestamp when the message was created
     * 
     * @return int|bool The insert ID if the insert was successful, or false on failure
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
     * This method clears all cached chat messages by deleting cache entries
     * that match the base cache key pattern. It's called after a new message
     * is inserted to ensure that subsequent requests will fetch fresh data
     * from the database instead of using outdated cached data.
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

    /**
     * Get messages by user with caching and pagination
     * 
     * @param string $username Username to filter by
     * @param int $page Page number (1-based)
     * @param int $perPage Number of messages per page
     * @return array
     */
    public function getMsgByUserPaginated($username, $page = 1, $perPage = 10)
    {
        // Ensure page is at least 1
        $page = max(1, (int)$page);

        // Calculate offset
        $offset = ($page - 1) * $perPage;

        // Create a unique cache key based on the username and pagination parameters
        $cacheKey = $this->cacheKey . '_user_' . md5($username) . '_page_' . $page . '_' . $perPage;

        // Get the cache service
        $cache = Services::cache();

        // Try to get data from cache first
        $result = $cache->get($cacheKey);

        // If not in cache or cache expired, get from database and store in cache
        if ($result === null) {
            // Get total count for pagination
            $totalCount = $this->where('user', $username)->countAllResults();

            // Use user index for filtering and time index for ordering
            $messages = $this->where('user', $username)
                            ->orderBy('time', 'DESC')
                            ->limit($perPage, $offset)
                            ->get()
                            ->getResultArray();

            // Calculate total pages
            $totalPages = ceil($totalCount / $perPage);

            // Create result with pagination data
            $result = [
                'messages' => $messages,
                'pagination' => [
                    'page' => $page,
                    'perPage' => $perPage,
                    'totalItems' => $totalCount,
                    'totalPages' => $totalPages,
                    'hasNext' => $page < $totalPages,
                    'hasPrev' => $page > 1
                ]
            ];

            // Store in cache
            $cache->save($cacheKey, $result, $this->cacheTTL);

            // Log cache miss
            log_message('debug', 'User chat messages cache miss. Fetched from database with pagination.');
        } else {
            // Log cache hit
            log_message('debug', 'User chat messages with pagination retrieved from cache.');
        }

        return $result;
    }

    /**
     * Get messages by user with caching
     * 
     * @param string $username Username to filter by
     * @param int $limit Number of messages to retrieve
     * @return array
     */
    public function getMsgByUser($username, $limit = 10)
    {
        // For backward compatibility, get the first page with the specified limit
        $result = $this->getMsgByUserPaginated($username, 1, $limit);

        // Return just the messages for backward compatibility
        return $result['messages'];
    }

    /**
     * Get messages by time range with caching and pagination
     * 
     * @param int $startTime Start timestamp
     * @param int $endTime End timestamp
     * @param int $page Page number (1-based)
     * @param int $perPage Number of messages per page
     * @return array
     */
    public function getMsgByTimeRangePaginated($startTime, $endTime, $page = 1, $perPage = 10)
    {
        // Ensure page is at least 1
        $page = max(1, (int)$page);

        // Calculate offset
        $offset = ($page - 1) * $perPage;

        // Create a unique cache key based on the time range and pagination parameters
        $cacheKey = $this->cacheKey . '_time_' . $startTime . '_' . $endTime . '_page_' . $page . '_' . $perPage;

        // Get the cache service
        $cache = Services::cache();

        // Try to get data from cache first
        $result = $cache->get($cacheKey);

        // If not in cache or cache expired, get from database and store in cache
        if ($result === null) {
            // Get total count for pagination
            $totalCount = $this->where('time >=', $startTime)
                               ->where('time <=', $endTime)
                               ->countAllResults();

            // Use time index for filtering and ordering
            $messages = $this->where('time >=', $startTime)
                            ->where('time <=', $endTime)
                            ->orderBy('time', 'DESC')
                            ->limit($perPage, $offset)
                            ->get()
                            ->getResultArray();

            // Calculate total pages
            $totalPages = ceil($totalCount / $perPage);

            // Create result with pagination data
            $result = [
                'messages' => $messages,
                'pagination' => [
                    'page' => $page,
                    'perPage' => $perPage,
                    'totalItems' => $totalCount,
                    'totalPages' => $totalPages,
                    'hasNext' => $page < $totalPages,
                    'hasPrev' => $page > 1
                ]
            ];

            // Store in cache
            $cache->save($cacheKey, $result, $this->cacheTTL);

            // Log cache miss
            log_message('debug', 'Time range chat messages cache miss. Fetched from database with pagination.');
        } else {
            // Log cache hit
            log_message('debug', 'Time range chat messages with pagination retrieved from cache.');
        }

        return $result;
    }

    /**
     * Get messages by time range with caching
     * 
     * @param int $startTime Start timestamp
     * @param int $endTime End timestamp
     * @param int $limit Number of messages to retrieve
     * @return array
     */
    public function getMsgByTimeRange($startTime, $endTime, $limit = 10)
    {
        // For backward compatibility, get the first page with the specified limit
        $result = $this->getMsgByTimeRangePaginated($startTime, $endTime, 1, $limit);

        // Return just the messages for backward compatibility
        return $result['messages'];
    }
}
