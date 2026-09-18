<?php

namespace App\Models;

use App\Contracts\ReactionRepository;
use App\Contracts\WebhookDispatcher;
use App\Services\NullWebhookDispatcher;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Model;
use CodeIgniter\Validation\ValidationInterface;

final class MessageReactionModel extends Model implements ReactionRepository
{
    protected $table = 'message_reactions';
    protected $primaryKey = 'id';
    protected $useTimestamps = true;
    protected $updatedField = '';
    protected $allowedFields = ['message_id', 'user_id', 'emoji'];

    private readonly WebhookDispatcher $webhooks;

    public function __construct(
        ?ConnectionInterface $db = null,
        ?ValidationInterface $validation = null,
        ?WebhookDispatcher $webhooks = null,
    ) {
        parent::__construct($db, $validation);
        $this->webhooks = $webhooks ?? new NullWebhookDispatcher();
    }

    public function messageExists(int $messageId): bool
    {
        return $this->db->table('messages')->where('id', $messageId)->countAllResults() === 1;
    }

    public function add(int $messageId, int $userId, string $emoji): bool
    {
        if ($this->hasReaction($messageId, $userId, $emoji)) {
            return true;
        }
        $saved = $this->db->table($this->table)->ignore(true)->insert([
            'message_id' => $messageId,
            'user_id' => $userId,
            'emoji' => $emoji,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        if ($saved) {
            $message = $this->db->table('messages')->select('channel_id')->where('id', $messageId)->get()->getRowArray();
            $channelId = is_array($message) ? (int) $message['channel_id'] : null;
            $this->webhooks->dispatch('reaction.added', [
                'reaction' => [
                    'message_id' => $messageId,
                    'user_id' => $userId,
                    'emoji' => $emoji,
                ],
            ], $channelId);
        }

        return $saved;
    }

    public function remove(int $messageId, int $userId, string $emoji): bool
    {
        return $this->where('message_id', $messageId)
            ->where('user_id', $userId)
            ->where('emoji', $emoji)
            ->delete();
    }

    public function forMessage(int $messageId, ?int $currentUserId = null): array
    {
        $rows = $this->db->table('message_reactions AS reactions')
            ->select('reactions.emoji, reactions.user_id, users.username')
            ->join('users', 'users.id = reactions.user_id')
            ->where('reactions.message_id', $messageId)
            ->orderBy('reactions.emoji', 'ASC')
            ->orderBy('users.username', 'ASC')
            ->get()
            ->getResultArray();
        $grouped = [];

        foreach ($rows as $row) {
            $emoji = (string) $row['emoji'];
            $grouped[$emoji] ??= [
                'emoji' => $emoji,
                'count' => 0,
                'users' => [],
                'reacted_by_current_user' => false,
            ];
            ++$grouped[$emoji]['count'];
            $grouped[$emoji]['users'][] = (string) $row['username'];
            if ($currentUserId !== null && (int) $row['user_id'] === $currentUserId) {
                $grouped[$emoji]['reacted_by_current_user'] = true;
            }
        }

        return array_values($grouped);
    }

    private function hasReaction(int $messageId, int $userId, string $emoji): bool
    {
        return $this->where('message_id', $messageId)
            ->where('user_id', $userId)
            ->where('emoji', $emoji)
            ->countAllResults() === 1;
    }
}
