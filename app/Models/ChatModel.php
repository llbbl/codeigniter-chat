<?php

namespace App\Models;

use App\Contracts\ChatRepository;
use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Model;
use CodeIgniter\Validation\ValidationInterface;
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
class ChatModel extends Model implements ChatRepository
{
    protected $table = 'messages';
    protected $primaryKey = 'id';
    protected $allowedFields = ['user', 'msg', 'time', 'channel_id'];

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

    private readonly CacheInterface $cache;

    public function __construct(
        ?ConnectionInterface $db = null,
        ?ValidationInterface $validation = null,
        ?CacheInterface $cache = null,
    ) {
        parent::__construct($db, $validation);
        $this->cache = $cache ?? Services::cache();
    }

    /**
     * Get messages from the database with caching and pagination
     *
     * @param int $page    Page number (1-based)
     * @param int $perPage Number of messages per page
     *
     * @return array
     */
    public function getMsgPaginated(int $page = 1, int $perPage = 10, ?int $channelId = null): array
    {
        // Ensure page is at least 1
        $page = max(1, (int)$page);

        // Calculate offset
        $offset = ($page - 1) * $perPage;

        // Create a unique cache key based on the pagination parameters
        $cacheChannel = $channelId ?? 'general';
        $cacheKey = $this->cacheKey . '_channel_' . $cacheChannel . '_page_' . $page . '_' . $perPage;

        // Get the cache service
        $cache = $this->cache;

        // Try to get data from the cache first
        $result = $cache->get($cacheKey);

        // If not in the cache or cache expired, get from the database and store in the cache
        if ($result === null) {
            $channelId ??= $this->generalChannelId();
            // Get total count for pagination
            $totalCount = $this->where('channel_id', $channelId)->countAllResults();

            // Use time index for ordering instead of id
            // This is more efficient for chat applications where time-based ordering is natural
            $messages = $this->where('channel_id', $channelId)
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
                    'hasPrev' => $page > 1,
                ],
            ];

            // Store in the cache
            $cache->save($cacheKey, $result, $this->cacheTTL);

            // Log cache miss
            log_message('debug', 'Chat messages cache miss. Fetched from the database with pagination.');
        } else {
            // Log cache hit
            log_message('debug', 'Chat messages with pagination retrieved from the cache.');
        }

        return $result;
    }

    /**
     * Get messages from the database with caching
     *
     * @param int $limit Number of messages to retrieve
     *
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
     * Read stable channel history across the live and archive tables.
     *
     * @return array{
     *     messages: list<array<string, mixed>>,
     *     pagination: array{limit: int, nextBefore: ?int, hasMore: bool}
     * }
     */
    public function getMessageHistory(int $channelId, ?int $before = null, int $limit = 25): array
    {
        $limit = max(1, min(100, $limit));
        $messages = $this->db->prefixTable('messages');
        $archived = $this->db->prefixTable('archived_messages');
        $cursor = $before === null ? '' : ' AND id < ?';
        $bindings = [$channelId];
        if ($before !== null) {
            $bindings[] = $before;
        }
        $bindings[] = $channelId;
        if ($before !== null) {
            $bindings[] = $before;
        }
        $bindings[] = $limit + 1;

        $rows = $this->db->query(
            "SELECT id, channel_id, user, msg, time, archived FROM (
                SELECT id, channel_id, user, msg, time, 0 AS archived FROM {$messages} WHERE channel_id = ?{$cursor}
                UNION ALL
                SELECT id, channel_id, user, msg, time, 1 AS archived FROM {$archived} WHERE channel_id = ?{$cursor}
            ) AS channel_history ORDER BY id DESC LIMIT ?",
            $bindings,
        )->getResultArray();

        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }

        return [
            'messages' => $rows,
            'pagination' => [
                'limit' => $limit,
                'nextBefore' => $hasMore && $rows !== [] ? (int) $rows[array_key_last($rows)]['id'] : null,
                'hasMore' => $hasMore,
            ],
        ];
    }

    /**
     * Search messages using each supported database's native full-text index.
     *
     * Exact-user and time filters are intentionally applied to the messages
     * table so its ordinary indexes remain useful alongside full-text search.
     *
     * @return array{
     *     messages: list<array<string, mixed>>,
     *     pagination: array<string, int|bool|float>
     * }
     */
    public function searchMessages(
        ?string $text = null,
        ?string $user = null,
        ?int $from = null,
        ?int $to = null,
        int $page = 1,
        int $perPage = 10,
        ?int $channelId = null,
    ): array {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;
        $text = $text !== null ? trim($text) : null;
        $user = $user !== null ? trim($user) : null;

        $messagesTable = $this->db->prefixTable($this->table);
        $bindings = [];
        $conditions = [];
        $channelId ??= $this->generalChannelId();
        $conditions[] = 'messages.channel_id = ?';
        $bindings[] = $channelId;

        if ($this->db->getPlatform() === 'SQLite3' && $text !== null && $text !== '') {
            $searchTable = $this->db->prefixTable('messages_fts');
            $fromClause = "{$messagesTable} AS messages INNER JOIN {$searchTable} ON {$searchTable}.rowid = messages.id";
            $conditions[] = "{$searchTable} MATCH ?";
            $bindings[] = $this->toSqliteFtsQuery($text);
        } else {
            $fromClause = "{$messagesTable} AS messages";

            if ($this->db->getPlatform() === 'MySQLi' && $text !== null && $text !== '') {
                $conditions[] = 'MATCH(messages.user, messages.msg) AGAINST (? IN NATURAL LANGUAGE MODE)';
                $bindings[] = $text;
            }
        }

        if ($user !== null && $user !== '') {
            $conditions[] = 'messages.user = ?';
            $bindings[] = $user;
        }
        if ($from !== null) {
            $conditions[] = 'messages.time >= ?';
            $bindings[] = $from;
        }
        if ($to !== null) {
            $conditions[] = 'messages.time <= ?';
            $bindings[] = $to;
        }

        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
        $countRow = $this->db->query("SELECT COUNT(*) AS total FROM {$fromClause}{$where}", $bindings)->getRowArray();
        $totalItems = (int) ($countRow['total'] ?? 0);

        $queryBindings = [...$bindings, $perPage, $offset];
        $messages = $this->db->query(
            "SELECT messages.* FROM {$fromClause}{$where} ORDER BY messages.time DESC, messages.id DESC LIMIT ? OFFSET ?",
            $queryBindings,
        )->getResultArray();
        $totalPages = (int) ceil($totalItems / $perPage);

        return [
            'messages' => $messages,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'totalItems' => $totalItems,
                'totalPages' => $totalPages,
                'hasNext' => $page < $totalPages,
                'hasPrev' => $page > 1,
            ],
        ];
    }

    /**
     * Insert a new message into the database and invalidate the cache
     *
     * This method inserts a new chat message into the database with the given
     * username, message text, and timestamp. If the insertion is successful,
     * it invalidates all related cache entries to ensure that later requests
     * will receive the updated data.
     *
     * @param string $name    The username of the message author
     * @param string $message The message text content
     * @param int    $current The Unix timestamp when the message was created
     *
     * @return int|bool The insert ID if the insert was successful, or false on failure
     */
    public function insertMsg(string $name, string $message, int $current, ?int $channelId = null): int|bool
    {
        $result = $this->insert([
            'user' => $name,
            'msg' => $message,
            'time' => $current,
            'channel_id' => $channelId ?? $this->generalChannelId(),
        ]);

        // If insert was successful, invalidate the cache
        if ($result) {
            $this->invalidateCache();
            log_message('debug', 'Chat messages cache invalidated after new message.');
        }

        return $result;
    }

    public function archiveMessagesBefore(int $timestamp, ?int $channelId = null, int $batchSize = 1_000): int
    {
        $batchSize = max(1, min(10_000, $batchSize));
        $archivedCount = 0;

        do {
            $builder = $this->db->table('messages')->where('time <', $timestamp)->orderBy('id')->limit($batchSize);
            if ($channelId !== null) {
                $builder->where('channel_id', $channelId);
            }
            $rows = $builder->get()->getResultArray();
            if ($rows === []) {
                break;
            }

            $ids = array_map(static fn (array $row): int => (int) $row['id'], $rows);
            $archivedAt = date('Y-m-d H:i:s');
            $archiveRows = array_map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'channel_id' => (int) $row['channel_id'],
                'user' => (string) $row['user'],
                'msg' => (string) $row['msg'],
                'time' => (int) $row['time'],
                'archived_at' => $archivedAt,
            ], $rows);

            $this->db->transException(true)->transStart();
            $this->db->table('archived_messages')->insertBatch($archiveRows);
            $this->db->table('messages')->whereIn('id', $ids)->delete();
            $this->db->transComplete();

            $archivedCount += count($rows);
            $this->invalidateCache();
        } while (count($rows) === $batchSize);

        return $archivedCount;
    }

    public function exportMessages(?int $channelId = null, ?string $username = null, int $batchSize = 500): iterable
    {
        $batchSize = max(1, min(5_000, $batchSize));
        $messages = $this->db->prefixTable('messages');
        $archived = $this->db->prefixTable('archived_messages');
        $after = 0;

        do {
            $where = ['id > ?'];
            $tableBindings = [$after];
            if ($channelId !== null) {
                $where[] = 'channel_id = ?';
                $tableBindings[] = $channelId;
            }
            if ($username !== null) {
                $where[] = 'user = ?';
                $tableBindings[] = $username;
            }
            $condition = implode(' AND ', $where);
            $bindings = [...$tableBindings, ...$tableBindings, $batchSize];
            $rows = $this->db->query(
                "SELECT id, channel_id, user, msg, time, archived FROM (
                    SELECT id, channel_id, user, msg, time, 0 AS archived FROM {$messages} WHERE {$condition}
                    UNION ALL
                    SELECT id, channel_id, user, msg, time, 1 AS archived FROM {$archived} WHERE {$condition}
                ) AS message_export ORDER BY id ASC LIMIT ?",
                $bindings,
            )->getResultArray();

            foreach ($rows as $row) {
                $after = (int) $row['id'];
                yield $row;
            }
        } while (count($rows) === $batchSize);
    }

    /**
     * Get messages by user with caching and pagination
     *
     * @param string $username Username to filter by
     * @param int    $page     Page number (1-based)
     * @param int    $perPage  Number of messages per page
     *
     * @return array
     */
    public function getMsgByUserPaginated(string $username, int $page = 1, int $perPage = 10): array
    {
        // Ensure page is at least 1
        $page = max(1, (int)$page);

        // Calculate offset
        $offset = ($page - 1) * $perPage;

        $channelId = $this->generalChannelId();

        // Create a unique cache key based on the username and pagination parameters
        $cacheKey = $this->cacheKey . '_channel_' . $channelId . '_user_' . md5($username) . '_page_' . $page . '_' . $perPage;

        // Get the cache service
        $cache = $this->cache;

        // Try to get data from the cache first
        $result = $cache->get($cacheKey);

        // If not in the cache or cache expired, get from the database and store in the cache
        if ($result === null) {
            // Get total count for pagination
            $totalCount = $this->where('channel_id', $channelId)->where('user', $username)->countAllResults();

            // Use user index for filtering and time index for ordering
            $messages = $this->where('channel_id', $channelId)
                            ->where('user', $username)
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
                    'hasPrev' => $page > 1,
                ],
            ];

            // Store in the cache
            $cache->save($cacheKey, $result, $this->cacheTTL);

            // Log cache miss
            log_message('debug', 'User chat messages cache miss. Fetched from the database with pagination.');
        } else {
            // Log cache hit
            log_message('debug', 'User chat messages with pagination retrieved from the cache.');
        }

        return $result;
    }

    /**
     * Get messages by user with caching
     *
     * @param string $username Username to filter by
     * @param int    $limit    Number of messages to retrieve
     *
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
     * @param int $endTime   End timestamp
     * @param int $page      Page number (1-based)
     * @param int $perPage   Number of messages per page
     *
     * @return array
     */
    public function getMsgByTimeRangePaginated(int $startTime, int $endTime, int $page = 1, int $perPage = 10): array
    {
        // Ensure page is at least 1
        $page = max(1, (int)$page);

        // Calculate offset
        $offset = ($page - 1) * $perPage;

        $channelId = $this->generalChannelId();

        // Create a unique cache key based on the time range and pagination parameters
        $cacheKey = $this->cacheKey . '_channel_' . $channelId . '_time_' . $startTime . '_' . $endTime . '_page_' . $page . '_' . $perPage;

        // Get the cache service
        $cache = $this->cache;

        // Try to get data from the cache first
        $result = $cache->get($cacheKey);

        // If not in the cache or cache expired, get from the database and store in the cache
        if ($result === null) {
            // Get total count for pagination
            $totalCount = $this->where('channel_id', $channelId)
                               ->where('time >=', $startTime)
                               ->where('time <=', $endTime)
                               ->countAllResults();

            // Use time index for filtering and ordering
            $messages = $this->where('channel_id', $channelId)
                            ->where('time >=', $startTime)
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
                    'hasPrev' => $page > 1,
                ],
            ];

            // Store in the cache
            $cache->save($cacheKey, $result, $this->cacheTTL);

            // Log cache miss
            log_message('debug', 'Time range chat messages cache miss. Fetched from the database with pagination.');
        } else {
            // Log cache hit
            log_message('debug', 'Time range chat messages with pagination retrieved from the cache.');
        }

        return $result;
    }

    /**
     * Get messages by time range with caching
     *
     * @param int $startTime Start timestamp
     * @param int $endTime   End timestamp
     * @param int $limit     Number of messages to retrieve
     *
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
     * Invalidate all message caches
     *
     * This method clears all cached chat messages by deleting cache entries
     * that match the base cache key pattern. It's called after a new message
     * is inserted to ensure that later requests will fetch fresh data
     * from the database instead of using outdated cached data.
     *
     * @return void
     */
    protected function invalidateCache(): void
    {
        $cache = $this->cache;

        // Delete all cache keys that start with the base cache key
        // This is a simple approach; for more complex scenarios,
        // you might want to track and delete specific keys
        $cache->deleteMatching($this->cacheKey . '_*');
    }

    private function toSqliteFtsQuery(string $text): string
    {
        // A quoted phrase treats user input as text rather than FTS5 syntax.
        return '"' . str_replace('"', '""', $text) . '"';
    }

    private function generalChannelId(): int
    {
        $row = $this->db->table('channels')->select('id')->where('slug', 'general')->get()->getRowArray();
        if (! is_array($row)) {
            throw new \LogicException('The #general channel is missing. Run database migrations.');
        }

        return (int) $row['id'];
    }
}
