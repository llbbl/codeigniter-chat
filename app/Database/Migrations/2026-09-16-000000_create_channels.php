<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Migration;

final class CreateChannels extends Migration
{
    private const MESSAGE_CHANNEL_FK = 'fk_messages_channel';

    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 80],
            'slug' => ['type' => 'VARCHAR', 'constraint' => 50],
            'channel_type' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'public'],
            'topic' => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'created_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'archived_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('slug');
        $this->forge->addKey(['channel_type', 'archived_at']);
        $this->forge->addForeignKey('created_by', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('channels');

        $now = date('Y-m-d H:i:s');
        $this->database()->table('channels')->insert([
            'name' => 'General',
            'slug' => 'general',
            'channel_type' => 'public',
            'topic' => 'General conversation',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $generalId = (int) $this->database()->insertID();

        $this->forge->addField([
            'channel_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'user_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'last_read_message_id' => ['type' => 'INT', 'constraint' => 7, 'null' => true],
            'joined_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey(['channel_id', 'user_id'], true);
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('channel_id', 'channels', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('channel_members');

        $users = $this->database()->prefixTable('users');
        $members = $this->database()->prefixTable('channel_members');
        $this->database()->query(
            "INSERT INTO {$members} (channel_id, user_id, joined_at) SELECT ?, id, ? FROM {$users}",
            [$generalId, $now],
        );

        $this->addMessageChannel($generalId);

        if ($this->database()->getPlatform() === 'MySQLi') {
            $channels = $this->database()->prefixTable('channels');
            $this->database()->query("ALTER TABLE {$channels} CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }
    }

    public function down(): void
    {
        $messages = $this->database()->prefixTable('messages');

        if ($this->database()->getPlatform() === 'MySQLi') {
            $this->database()->query("ALTER TABLE {$messages} DROP FOREIGN KEY " . self::MESSAGE_CHANNEL_FK);
            $this->database()->query("ALTER TABLE {$messages} DROP INDEX idx_messages_channel_time");
            $this->database()->query("ALTER TABLE {$messages} DROP COLUMN channel_id");
        } else {
            $this->database()->query('DROP INDEX IF EXISTS idx_messages_channel_time');
            $this->database()->query("ALTER TABLE {$messages} DROP COLUMN channel_id");
        }

        $this->forge->dropTable('channel_members');
        $this->forge->dropTable('channels');
    }

    private function addMessageChannel(int $generalId): void
    {
        $messages = $this->database()->prefixTable('messages');

        if ($this->database()->getPlatform() === 'MySQLi') {
            $channels = $this->database()->prefixTable('channels');
            $this->database()->query("ALTER TABLE {$messages} ADD channel_id INT(11) UNSIGNED NULL AFTER id");
            $this->database()->query("UPDATE {$messages} SET channel_id = ? WHERE channel_id IS NULL", [$generalId]);
            $this->database()->query("ALTER TABLE {$messages} MODIFY channel_id INT(11) UNSIGNED NOT NULL DEFAULT {$generalId}");
            $this->database()->query("ALTER TABLE {$messages} ADD INDEX idx_messages_channel_time (channel_id, time, id)");
            $this->database()->query(
                "ALTER TABLE {$messages} ADD CONSTRAINT " . self::MESSAGE_CHANNEL_FK . " FOREIGN KEY (channel_id) REFERENCES {$channels}(id) ON DELETE RESTRICT ON UPDATE CASCADE",
            );

            return;
        }

        $this->database()->query("ALTER TABLE {$messages} ADD COLUMN channel_id INTEGER NOT NULL DEFAULT {$generalId}");
        $this->database()->query("CREATE INDEX idx_messages_channel_time ON {$messages} (channel_id, time, id)");
    }

    private function database(): BaseConnection
    {
        if (! $this->db instanceof BaseConnection) {
            throw new \LogicException('Channel migrations require a CodeIgniter base database connection.');
        }

        return $this->db;
    }
}
