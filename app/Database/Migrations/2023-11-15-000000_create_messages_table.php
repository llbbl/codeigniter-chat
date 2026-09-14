<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Migration;

class CreateMessagesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 7,
                'unsigned'       => false,
                'auto_increment' => true,
            ],
            'user' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'msg' => [
                'type'       => 'TEXT',
                'null'       => false,
            ],
            'time' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => false,
                'default'    => 0,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('messages');

        // Set character set to latin1 for user and msg columns to match original table
        // This is MySQL-specific - SQLite doesn't support character sets at column level
        if ($this->db->getPlatform() === 'MySQLi') {
            $db = $this->database();
            $messages = $db->prefixTable('messages');
            $db->query("ALTER TABLE {$messages} MODIFY user VARCHAR(255) CHARACTER SET latin1 NOT NULL");
            $db->query("ALTER TABLE {$messages} MODIFY msg TEXT CHARACTER SET latin1 NOT NULL");
        }
    }

    public function down()
    {
        $this->forge->dropTable('messages');
    }

    private function database(): BaseConnection
    {
        if (! $this->db instanceof BaseConnection) {
            throw new \LogicException('Message migrations require a CodeIgniter base database connection.');
        }

        return $this->db;
    }
}
