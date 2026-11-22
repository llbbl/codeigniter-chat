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
            'user_id' => $userId,
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

    public function testPublicChannelsCannotClaimDirectMessageSlugs(): void
    {
        $creatorId = $this->createUser('creator');
        $channels = new ChannelModel();

        $this->assertFalse($channels->createPublic('Impersonated DM', 'dm-1-2', null, $creatorId));
        $this->assertFalse($channels->createPublic('Impersonated DM', ' DM-1-2 ', null, $creatorId));
        $channelId = $channels->createPublic('Team', 'team-room', null, $creatorId);
        $this->assertIsInt($channelId);
        $this->assertFalse($channels->updateChannel($channelId, ['slug' => 'dm-anything']));
        $this->seeInDatabase('channels', ['id' => $channelId, 'slug' => 'team-room']);
    }

    public function testLegacyPublicSlugCollisionCannotAcquireDirectMessageMembers(): void
    {
        $aliceId = $this->createUser('alice');
        $bobId = $this->createUser('bob');
        $malloryId = $this->createUser('mallory');
        $channels = new ChannelModel();
        $this->db->table('channels')->insert([
            'name' => 'Legacy public collision',
            'slug' => "dm-{$aliceId}-{$bobId}",
            'channel_type' => 'public',
            'created_by' => $malloryId,
        ]);
        $channelId = (int) $this->db->insertID();
        $channels->join($channelId, $malloryId);

        $this->assertFalse($channels->findOrCreateDm($aliceId, $bobId));
        $this->assertFalse($channels->findOrCreateDm($bobId, $aliceId));
        $this->assertSame([$malloryId], $channels->memberIds($channelId));
        $this->seeInDatabase('channels', ['id' => $channelId, 'channel_type' => 'public']);
    }

    public function testContaminatedDirectMessageCannotBeReusedOrRepaired(): void
    {
        $aliceId = $this->createUser('alice');
        $bobId = $this->createUser('bob');
        $malloryId = $this->createUser('mallory');
        $channels = new ChannelModel();
        $channelId = $channels->findOrCreateDm($aliceId, $bobId);
        $this->assertIsInt($channelId);
        $channels->join($channelId, $malloryId);

        $this->assertFalse($channels->findOrCreateDm($aliceId, $bobId));
        $this->assertEqualsCanonicalizing([$aliceId, $bobId, $malloryId], $channels->memberIds($channelId));

        $channels->leave($channelId, $bobId);
        $this->assertFalse($channels->findOrCreateDm($bobId, $aliceId));
        $this->assertEqualsCanonicalizing([$aliceId, $malloryId], $channels->memberIds($channelId));

        $channels->leave($channelId, $malloryId);
        $this->assertFalse($channels->findOrCreateDm($aliceId, $bobId));
        $this->assertSame([$aliceId], $channels->memberIds($channelId));
    }

    public function testIgnoredInsertRechecksChannelTypeAndMembers(): void
    {
        if ($this->db->getPlatform() !== 'SQLite3') {
            $this->markTestSkipped('The deterministic ignored-insert trigger uses SQLite syntax.');
        }

        $aliceId = $this->createUser('alice');
        $bobId = $this->createUser('bob');
        $malloryId = $this->createUser('mallory');
        $channels = new ChannelModel();
        $table = $this->db->prefixTable('channels');
        $members = $this->db->prefixTable('channel_members');
        $slug = "dm-{$aliceId}-{$bobId}";

        foreach (['public', 'polluted-dm', 'valid-dm'] as $collision) {
            $type = $collision === 'public' ? 'public' : 'dm';
            $outsider = $collision === 'valid-dm' ? '' : "INSERT INTO {$members} (channel_id, user_id, joined_at) SELECT id, {$malloryId}, CURRENT_TIMESTAMP FROM {$table} WHERE slug = '{$slug}';";
            // Simulate a competing insert after the first lookup but before INSERT OR IGNORE.
            $this->db->query("CREATE TRIGGER dm_insert_collision BEFORE INSERT ON {$table}
                WHEN NEW.slug = '{$slug}' BEGIN
                    INSERT INTO {$table} (name, slug, channel_type, created_by) VALUES ('Collision', '{$slug}', '{$type}', {$aliceId});
                    INSERT INTO {$members} (channel_id, user_id, joined_at) SELECT id, {$aliceId}, CURRENT_TIMESTAMP FROM {$table} WHERE slug = '{$slug}';
                    INSERT INTO {$members} (channel_id, user_id, joined_at) SELECT id, {$bobId}, CURRENT_TIMESTAMP FROM {$table} WHERE slug = '{$slug}';
                    {$outsider}
                    SELECT RAISE(IGNORE);
                END");

            try {
                $channelId = $channels->findOrCreateDm($bobId, $aliceId);
                if ($collision === 'valid-dm') {
                    $this->assertIsInt($channelId);
                    $this->assertEqualsCanonicalizing([$aliceId, $bobId], $channels->memberIds($channelId));
                    $this->assertSame($channelId, $channels->findOrCreateDm($aliceId, $bobId));
                } else {
                    $this->assertFalse($channelId, $collision);
                }
            } finally {
                $this->db->query('DROP TRIGGER dm_insert_collision');
                $this->db->table('channels')->where('slug', $slug)->delete();
            }
        }
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
