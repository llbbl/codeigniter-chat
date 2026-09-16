<?php

namespace Tests\Integration;

use App\Models\ChannelModel;
use App\Models\ChatModel;
use App\Models\UserModel;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\IntegrationTestCase;

#[Group('integration')]
final class ChannelModelIntegrationTest extends IntegrationTestCase
{
    public function testMigrationCreatesGeneralMembershipAndMessageDefault(): void
    {
        $userId = $this->createUser('alice');
        $channels = new ChannelModel();
        $generalId = $channels->ensureGeneralMembership($userId);

        $messageId = $this->db->table('messages')->insert([
            'user' => 'alice',
            'msg' => 'A legacy client message',
            'time' => 1_700_000_000,
        ], true);

        $this->assertGreaterThan(0, $messageId);
        $this->seeInDatabase('channels', ['id' => $generalId, 'slug' => 'general']);
        $this->seeInDatabase('channel_members', ['channel_id' => $generalId, 'user_id' => $userId]);
        $this->seeInDatabase('messages', ['id' => $messageId, 'channel_id' => $generalId]);
        $this->assertMessageChannelColumnIsRequiredAndIndexed();
    }

    public function testPublicChannelMembershipAndUnreadCounts(): void
    {
        $creatorId = $this->createUser('creator');
        $memberId = $this->createUser('member');
        $channels = new ChannelModel();

        $channelId = $channels->createPublic('Project Room', 'project-room', 'Planning', $creatorId);
        $this->assertIsInt($channelId);
        $this->assertTrue($channels->isCreator($channelId, $creatorId));
        $discovered = $channels->listForUser($memberId);
        $project = array_values(array_filter($discovered, static fn (array $channel): bool => $channel['id'] === $channelId))[0];
        $this->assertFalse($project['is_member']);
        $this->assertTrue($channels->join($channelId, $memberId));

        $messageId = (new ChatModel())->insertMsg('creator', 'Welcome', 1_700_000_001, $channelId);
        $this->assertIsInt($messageId);

        $listed = $channels->listForUser($memberId);
        $project = array_values(array_filter($listed, static fn (array $channel): bool => $channel['id'] === $channelId))[0];
        $this->assertSame(1, $project['unread_count']);
        $this->assertSame(['creator', 'member'], $project['members']);

        $this->assertTrue($channels->markRead($channelId, $memberId));
        $listed = $channels->listForUser($memberId);
        $project = array_values(array_filter($listed, static fn (array $channel): bool => $channel['id'] === $channelId))[0];
        $this->assertSame(0, $project['unread_count']);

        $this->assertTrue($channels->leave($channelId, $memberId));
        $this->assertFalse($channels->isMember($channelId, $memberId));
        $this->assertFalse($channels->leave($channels->generalChannelId(), $creatorId));
    }

    public function testDirectMessagesAreIdempotentAndHaveExactlyTwoMembers(): void
    {
        $aliceId = $this->createUser('alice');
        $bobId = $this->createUser('bob');
        $channels = new ChannelModel();

        $first = $channels->findOrCreateDm($aliceId, $bobId);
        $second = $channels->findOrCreateDm($bobId, $aliceId);

        $this->assertIsInt($first);
        $this->assertSame($first, $second);
        $this->assertSame([$aliceId, $bobId], $channels->memberIds($first));
        $this->seeInDatabase('channels', ['id' => $first, 'channel_type' => 'dm']);
    }

    private function createUser(string $username): int
    {
        $id = (new UserModel())->createUser($username, "{$username}@example.com", 'Password123!');
        $this->assertIsInt($id);

        return $id;
    }

    private function assertMessageChannelColumnIsRequiredAndIndexed(): void
    {
        $messages = $this->db->prefixTable('messages');
        if ($this->db->getPlatform() === 'SQLite3') {
            $columns = $this->db->query("PRAGMA table_info('{$messages}')")->getResultArray();
            $channel = array_values(array_filter($columns, static fn (array $column): bool => $column['name'] === 'channel_id'))[0];
            $this->assertSame(1, (int) $channel['notnull']);

            $indexes = $this->db->query("PRAGMA index_list('{$messages}')")->getResultArray();
            $this->assertContains('idx_messages_channel_time', array_column($indexes, 'name'));

            return;
        }

        $column = $this->db->query("SHOW COLUMNS FROM {$messages} LIKE 'channel_id'")->getRowArray();
        $this->assertSame('NO', $column['Null'] ?? null);
        $indexes = $this->db->query("SHOW INDEX FROM {$messages} WHERE Key_name = 'idx_messages_channel_time'")->getResultArray();
        $this->assertCount(3, $indexes);
    }
}
