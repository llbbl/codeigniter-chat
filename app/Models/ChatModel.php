<?php

namespace App\Models;

use CodeIgniter\Model;
use Config\Services;
use App\Helpers\CacheHelper;

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
    protected string $cacheKey = 'chat_messages';

    /**
     * Cache TTL in seconds
     * 
     * @var int
     */
    protected int $cacheTTL = 300; // 5 minutes

    /**
     * Whether to enable query performance monitoring
     * 
     * @var bool
     */
    protected bool $enableQueryMonitoring = true;

    /**
     * Get messages from the database with caching and pagination
     * 
     * @param int $page Page number (1-based)
     * @param int $perPage Number of messages per page
     * @return array
     */
    public function getMsgPaginated(int $page = 1, int $perPage = 10): array
    {
        // Ensure page is at least 1
        $page = max(1, (int)$page);

        // Calculate offset
        $offset = ($page - 1) * $perPage;

        // Create a unique cache key based on the pagination parameters
        $cacheKey = $this->cacheKey . '_page_' . $page . '_' . $perPage;

        // Try to get data from cache first using intelligent caching
        $result = CacheHelper::get($cacheKey);

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

            // Store in cache with tags for intelligent invalidation
            CacheHelper::remember($cacheKey, $result, $this->cacheTTL, ['messages', 'pagination']);

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
    public function getMsg(int $limit = 10): array
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
    public function insertMsg(string $name, string $message, int $current): int|bool
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
     * Invalidate all message caches using intelligent cache tags
     * 
     * This method clears all cached chat messages by invalidating cache entries
     * using tags. It's called after a new message is inserted to ensure that 
     * subsequent requests will fetch fresh data from the database instead of 
     * using outdated cached data.
     * 
     * @return void
     */
    protected function invalidateCache(): void
    {
        // Use intelligent cache invalidation with tags
        $invalidated = CacheHelper::invalidateByTags(['messages', 'pagination', 'user_messages', 'time_range']);
        
        log_message('debug', "Chat cache invalidation completed. Invalidated {$invalidated} cache entries.");
    }

    /**
     * Get messages by user with caching and pagination
     * 
     * @param string $username Username to filter by
     * @param int $page Page number (1-based)
     * @param int $perPage Number of messages per page
     * @return array
     */
    public function getMsgByUserPaginated(string $username, int $page = 1, int $perPage = 10): array
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
    public function getMsgByUser(string $username, int $limit = 10): array
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
    public function getMsgByTimeRangePaginated(int $startTime, int $endTime, int $page = 1, int $perPage = 10): array
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
    public function getMsgByTimeRange(int $startTime, int $endTime, int $limit = 10): array
    {
        // For backward compatibility, get the first page with the specified limit
        $result = $this->getMsgByTimeRangePaginated($startTime, $endTime, 1, $limit);

        // Return just the messages for backward compatibility
        return $result['messages'];
    }

    /**
     * Monitor query performance with EXPLAIN statement
     * 
     * @param string $query The SQL query to analyze
     * @param array $binds Query bindings
     * @return void
     */
    protected function monitorQueryPerformance(string $query, array $binds = []): void
    {
        if (!$this->enableQueryMonitoring || ENVIRONMENT !== 'development') {
            return;
        }

        try {
            // Execute EXPLAIN query
            $explainQuery = "EXPLAIN " . $query;
            $result = $this->db->query($explainQuery, $binds);
            
            if ($result) {
                $explainData = $result->getResultArray();
                
                // Log the performance data
                log_message('debug', 'Query Performance Analysis:', [
                    'query' => $query,
                    'explain' => $explainData,
                    'timestamp' => time()
                ]);

                // Check for potential performance issues
                foreach ($explainData as $row) {
                    // Alert if no index is being used
                    if (isset($row['key']) && $row['key'] === null) {
                        log_message('warning', 'Query not using index: ' . $query);
                    }
                    
                    // Alert if too many rows are being examined
                    if (isset($row['rows']) && $row['rows'] > 1000) {
                        log_message('warning', 'Query examining many rows (' . $row['rows'] . '): ' . $query);
                    }
                    
                    // Alert if using filesort
                    if (isset($row['Extra']) && strpos($row['Extra'], 'Using filesort') !== false) {
                        log_message('info', 'Query using filesort: ' . $query);
                    }
                }
            }
        } catch (\Exception $e) {
            log_message('error', 'Failed to analyze query performance: ' . $e->getMessage());
        }
    }

    /**
     * Get performance optimized messages with monitoring
     * 
     * @param int $page Page number (1-based)
     * @param int $perPage Number of messages per page
     * @return array
     */
    public function getMsgPaginatedOptimized(int $page = 1, int $perPage = 10): array
    {
        // Monitor the query performance using query builder directly
        $builder = $this->db->table($this->table);
        $query = $builder->select('*')
                         ->orderBy('time', 'DESC')
                         ->limit($perPage, ($page - 1) * $perPage)
                         ->getCompiledSelect();
        
        $this->monitorQueryPerformance($query);

        // Use the regular method for actual data retrieval
        return $this->getMsgPaginated($page, $perPage);
    }
}
