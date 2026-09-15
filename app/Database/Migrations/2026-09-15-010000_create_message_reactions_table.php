<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Migration;

final class CreateMessageReactionsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'message_id' => ['type' => 'INT', 'constraint' => 7],
            'user_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'emoji' => ['type' => 'VARCHAR', 'constraint' => 64],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('message_id');
        $this->forge->addKey('user_id');
        $this->forge->addUniqueKey(['message_id', 'user_id', 'emoji'], 'uq_message_user_emoji');
        $this->forge->addForeignKey('message_id', 'messages', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('message_reactions');

        if ($this->db->getPlatform() === 'MySQLi') {
            $table = $this->database()->prefixTable('message_reactions');
            $this->database()->query("ALTER TABLE {$table} MODIFY emoji VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL");
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('message_reactions');
    }

    private function database(): BaseConnection
    {
        if (! $this->db instanceof BaseConnection) {
            throw new \LogicException('Reaction migrations require a CodeIgniter base database connection.');
        }

        return $this->db;
    }
}
