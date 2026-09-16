<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Migration;

final class CreateArchivedMessages extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 7],
            'channel_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'user' => ['type' => 'VARCHAR', 'constraint' => 255],
            'msg' => ['type' => 'TEXT'],
            'time' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'archived_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['channel_id', 'time', 'id']);
        $this->forge->addKey(['user', 'id']);
        $this->forge->addForeignKey('channel_id', 'channels', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('archived_messages');

        if ($this->database()->getPlatform() === 'MySQLi') {
            $table = $this->database()->prefixTable('archived_messages');
            $this->database()->query("ALTER TABLE {$table} MODIFY user VARCHAR(255) CHARACTER SET latin1 NOT NULL");
            $this->database()->query("ALTER TABLE {$table} MODIFY msg TEXT CHARACTER SET latin1 NOT NULL");
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('archived_messages');
    }

    private function database(): BaseConnection
    {
        if (! $this->db instanceof BaseConnection) {
            throw new \LogicException('Archive migrations require a CodeIgniter base database connection.');
        }

        return $this->db;
    }
}
