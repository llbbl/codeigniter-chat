<?php

namespace App\Models;

use App\Contracts\ChannelRepository;
use CodeIgniter\Model;

final class ChannelModel extends Model implements ChannelRepository
{
    protected $table = 'channels';
    protected $primaryKey = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = ['name', 'slug', 'channel_type', 'topic', 'created_by', 'archived_at'];

    public function generalChannelId(): int
    {
        $row = $this->select('id')->where('slug', 'general')->first();
        if (! is_array($row)) {
            throw new \LogicException('The #general channel is missing. Run database migrations.');
        }

        return (int) $row['id'];
    }

    public function ensureGeneralMembership(int $userId): int
    {
        $channelId = $this->generalChannelId();
        $this->db->table('channel_members')->ignore(true)->insert([
            'channel_id' => $channelId,
            'user_id' => $userId,
            'joined_at' => date('Y-m-d H:i:s'),
        ]);

        return $channelId;
    }

    public function listForUser(int $userId): array
    {
        $this->ensureGeneralMembership($userId);
        $channels = $this->db->table('channels AS channels')
            ->select('channels.*, membership.last_read_message_id, membership.user_id AS membership_user_id')
            ->select('(CASE WHEN membership.user_id IS NULL THEN 0 ELSE (SELECT COUNT(*) FROM ' . $this->db->prefixTable('messages') . ' AS unread_messages WHERE unread_messages.channel_id = channels.id AND unread_messages.id > COALESCE(membership.last_read_message_id, 0)) END) AS unread_count', false)
            ->join('channel_members AS membership', 'membership.channel_id = channels.id AND membership.user_id = ' . $userId, 'left')
            ->groupStart()
            ->where('channels.channel_type', 'public')
            ->orWhere('membership.user_id', $userId)
            ->groupEnd()
            ->where('channels.archived_at', null)
            ->orderBy("CASE WHEN channels.slug = 'general' THEN 0 ELSE 1 END", '', false)
            ->orderBy('channels.channel_type', 'ASC')
            ->orderBy('channels.name', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($channels as &$channel) {
            $channel['id'] = (int) $channel['id'];
            $channel['created_by'] = $channel['created_by'] === null ? null : (int) $channel['created_by'];
            $channel['unread_count'] = (int) $channel['unread_count'];
            $channel['is_member'] = $channel['membership_user_id'] !== null;
            unset($channel['membership_user_id']);
            $channel['members'] = $this->memberUsernames((int) $channel['id']);
        }
        unset($channel);

        return $channels;
    }

    public function findChannel(int $channelId): ?array
    {
        $channel = $this->find($channelId);

        return is_array($channel) ? $channel : null;
    }

    public function isMember(int $channelId, int $userId): bool
    {
        return $this->db->table('channel_members')
            ->where('channel_id', $channelId)
            ->where('user_id', $userId)
            ->countAllResults() === 1;
    }

    public function isCreator(int $channelId, int $userId): bool
    {
        return $this->where('id', $channelId)->where('created_by', $userId)->countAllResults() === 1;
    }

    public function createPublic(string $name, string $slug, ?string $topic, int $creatorId): int|false
    {
        $this->db->transStart();
        $channelId = $this->insert([
            'name' => $name,
            'slug' => $slug,
            'channel_type' => 'public',
            'topic' => $topic,
            'created_by' => $creatorId,
        ]);
        if ($channelId !== false) {
            $this->join((int) $channelId, $creatorId);
        }
        $this->db->transComplete();

        return $this->db->transStatus() && $channelId !== false ? (int) $channelId : false;
    }

    public function join(int $channelId, int $userId): bool
    {
        $saved = $this->db->table('channel_members')->ignore(true)->insert([
            'channel_id' => $channelId,
            'user_id' => $userId,
            'joined_at' => date('Y-m-d H:i:s'),
        ]);

        return $saved && $this->isMember($channelId, $userId);
    }

    public function leave(int $channelId, int $userId): bool
    {
        if ($channelId === $this->generalChannelId()) {
            return false;
        }

        return $this->db->table('channel_members')
            ->where('channel_id', $channelId)
            ->where('user_id', $userId)
            ->delete();
    }

    public function updateChannel(int $channelId, array $changes): bool
    {
        return $this->update($channelId, $changes);
    }

    public function findOrCreateDm(int $firstUserId, int $secondUserId): int|false
    {
        [$firstUserId, $secondUserId] = [min($firstUserId, $secondUserId), max($firstUserId, $secondUserId)];
        $slug = "dm-{$firstUserId}-{$secondUserId}";
        $existing = $this->select('id')->where('slug', $slug)->first();
        if (is_array($existing)) {
            $channelId = (int) $existing['id'];
            $this->join($channelId, $firstUserId);
            $this->join($channelId, $secondUserId);

            return $channelId;
        }

        $this->db->transStart();
        $this->db->table('channels')->ignore(true)->insert([
            'name' => 'Direct message',
            'slug' => $slug,
            'channel_type' => 'dm',
            'created_by' => $firstUserId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $channel = $this->select('id')->where('slug', $slug)->first();
        if (! is_array($channel)) {
            $this->db->transRollback();

            return false;
        }
        $channelId = (int) $channel['id'];
        $this->join($channelId, $firstUserId);
        $this->join($channelId, $secondUserId);
        $this->db->transComplete();

        return $this->db->transStatus() ? $channelId : false;
    }

    public function memberIds(int $channelId): array
    {
        return array_map(
            static fn (array $row): int => (int) $row['user_id'],
            $this->db->table('channel_members')->select('user_id')->where('channel_id', $channelId)->get()->getResultArray(),
        );
    }

    public function markRead(int $channelId, int $userId): bool
    {
        $row = $this->db->table('messages')->selectMax('id', 'latest_id')->where('channel_id', $channelId)->get()->getRowArray();
        $latestId = isset($row['latest_id']) ? (int) $row['latest_id'] : null;

        return $this->db->table('channel_members')
            ->where('channel_id', $channelId)
            ->where('user_id', $userId)
            ->update(['last_read_message_id' => $latestId]);
    }

    public function messageChannelId(int $messageId): ?int
    {
        $row = $this->db->table('messages')->select('channel_id')->where('id', $messageId)->get()->getRowArray();

        return is_array($row) ? (int) $row['channel_id'] : null;
    }

    /** @return list<string> */
    private function memberUsernames(int $channelId): array
    {
        return array_column(
            $this->db->table('channel_members')
                ->select('users.username')
                ->join('users', 'users.id = channel_members.user_id')
                ->where('channel_members.channel_id', $channelId)
                ->orderBy('users.username', 'ASC')
                ->get()
                ->getResultArray(),
            'username',
        );
    }
}
