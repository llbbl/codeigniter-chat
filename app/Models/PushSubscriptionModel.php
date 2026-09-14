<?php

namespace App\Models;

use App\Contracts\PushSubscriptionRepository;
use CodeIgniter\Model;

final class PushSubscriptionModel extends Model implements PushSubscriptionRepository
{
    protected $table = 'push_subscriptions';
    protected $primaryKey = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = ['user_id', 'endpoint', 'endpoint_hash', 'p256dh', 'auth', 'content_encoding'];

    public function saveForUser(int $userId, array $subscription): bool
    {
        $existing = $this->select('id')->where('endpoint_hash', $subscription['endpoint_hash'])->first();
        $row = ['user_id' => $userId, ...$subscription];

        if (is_array($existing)) {
            return $this->update($existing['id'], $row);
        }

        return $this->insert($row) !== false;
    }

    public function deleteForUser(int $userId, string $endpointHash): bool
    {
        return $this->where('user_id', $userId)->where('endpoint_hash', $endpointHash)->delete();
    }
}
