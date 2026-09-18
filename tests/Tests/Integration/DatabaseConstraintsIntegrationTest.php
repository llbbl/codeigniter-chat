<?php

namespace Tests\Integration;

use App\Database\Migrations\TightenMessagesConstraints;
use App\Models\ChannelModel;
use App\Models\ChatModel;
use App\Models\UserModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use Config\Database;
use LogicException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\IntegrationTestCase;

#[Group('integration')]
#[Group('database-constraints')]
final class DatabaseConstraintsIntegrationTest extends IntegrationTestCase
{
    public function testMessageMigrationBackfillsLiveAndArchivedUserIds(): void
    {
        $userId = $this->createUser('backfill_user');
        $channelId = (new ChannelModel())->generalChannelId();
        $migration = new TightenMessagesConstraints(Database::forge($this->db));
        $migration->down();

        $this->db->table('messages')->insert([
            'channel_id' => $channelId,
            'user' => 'backfill_user',
            'msg' => 'Live legacy row',
            'time' => 1_700_000_000,
        ]);
        $this->db->table('archived_messages')->insert([
            'id' => 900,
            'channel_id' => $channelId,
            'user' => 'backfill_user',
            'msg' => 'Archived legacy row',
            'time' => 1_600_000_000,
            'archived_at' => '2026-01-01 00:00:00',
        ]);

        $migration->up();

        $this->seeInDatabase('messages', ['user_id' => $userId, 'msg' => 'Live legacy row']);
        $this->seeInDatabase('archived_messages', ['user_id' => $userId, 'msg' => 'Archived legacy row']);
        $this->assertTrue($this->db->fieldExists('user_id', 'messages'));
        $this->assertFalse($this->db->fieldExists('user', 'messages'));
    }

    public function testMessageMigrationPreflightLeavesLegacySchemaIntact(): void
    {
        $migration = new TightenMessagesConstraints(Database::forge($this->db));
        $migration->down();
        $this->db->table('messages')->insert([
            'channel_id' => (new ChannelModel())->generalChannelId(),
            'user' => '[deleted]',
            'msg' => '',
            'time' => 0,
        ]);

        try {
            $migration->up();
            $this->fail('Expected invalid legacy message data to stop the migration.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('Cannot tighten messages', $exception->getMessage());
        }

        $this->assertTrue($this->db->fieldExists('user', 'messages'));
        $this->assertFalse($this->db->fieldExists('user_id', 'messages'));
        if ($this->db->getPlatform() === 'SQLite3') {
            $this->assertTrue($this->db->tableExists('messages_fts'));
        }

        $this->db->table('messages')->where('msg', '')->delete();
        $migration->up();
    }

    public function testDatabaseRejectsMessagesOutsideLengthLimit(): void
    {
        $this->expectException(DatabaseException::class);

        $this->db->table('messages')->insert([
            'msg' => str_repeat('x', 501),
            'time' => 1_700_000_000,
        ]);
    }

    public function testDatabaseRejectsNonPositiveMessageTime(): void
    {
        $this->expectException(DatabaseException::class);

        $this->db->table('messages')->insert([
            'msg' => 'Invalid timestamp',
            'time' => 0,
        ]);
    }

    public function testDatabaseRejectsArchivedMessagesOutsideLengthLimit(): void
    {
        $this->expectException(DatabaseException::class);

        $this->db->table('archived_messages')->insert([
            'id' => 901,
            'channel_id' => (new ChannelModel())->generalChannelId(),
            'msg' => str_repeat('x', 501),
            'time' => 1_700_000_000,
            'archived_at' => '2026-01-01 00:00:00',
        ]);
    }

    public function testDatabaseRejectsNonPositiveArchivedMessageTime(): void
    {
        $this->expectException(DatabaseException::class);

        $this->db->table('archived_messages')->insert([
            'id' => 902,
            'channel_id' => (new ChannelModel())->generalChannelId(),
            'msg' => 'Invalid archived timestamp',
            'time' => 0,
            'archived_at' => '2026-01-01 00:00:00',
        ]);
    }

    public function testDatabaseRejectsInvalidUsernameCharacters(): void
    {
        $this->expectException(DatabaseException::class);

        $this->db->table('users')->insert([
            'username' => 'bad<>name',
            'email' => 'bad-name@example.com',
            'password' => password_hash('Password123!', PASSWORD_DEFAULT),
        ]);
    }

    public function testDatabaseRejectsPlainTextPasswordStorage(): void
    {
        $this->expectException(DatabaseException::class);

        $this->db->table('users')->insert([
            'username' => 'plain_text_user',
            'email' => 'plain-text@example.com',
            'password' => 'Password123!',
        ]);
    }

    public function testDeletedUsersRemainReadableAcrossLiveAndArchivedHistory(): void
    {
        $userId = $this->createUser('departed_user');
        $channelId = (new ChannelModel())->generalChannelId();
        $messages = new ChatModel();
        $archivedId = $messages->insertMsg('departed_user', 'Archived authorship', 100, $channelId);
        $liveId = $messages->insertMsg('departed_user', 'Live authorship', 200, $channelId);
        $this->assertIsInt($archivedId);
        $this->assertIsInt($liveId);
        $this->assertSame(1, $messages->archiveMessagesBefore(150, $channelId));

        $this->assertTrue((new UserModel())->delete($userId));

        $this->seeInDatabase('messages', ['id' => $liveId, 'user_id' => null]);
        $this->seeInDatabase('archived_messages', ['id' => $archivedId, 'user_id' => null]);
        $history = $messages->getMessageHistory($channelId, limit: 10);
        $this->assertSame(['[deleted]', '[deleted]'], array_column($history['messages'], 'user'));
        $this->assertSame(
            ['[deleted]', '[deleted]'],
            array_column(iterator_to_array($messages->exportMessages(channelId: $channelId)), 'user'),
        );
    }

    private function createUser(string $username): int
    {
        $id = (new UserModel())->createUser($username, "{$username}@example.com", 'Password123!');
        $this->assertIsInt($id);

        return $id;
    }
}
