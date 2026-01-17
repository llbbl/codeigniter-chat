<?php

namespace App\Database\Migrations;

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
        if ($this->db->DBDriver === 'MySQLi') {
            $this->db->query('ALTER TABLE messages MODIFY user VARCHAR(255) CHARACTER SET latin1 NOT NULL');
            $this->db->query('ALTER TABLE messages MODIFY msg TEXT CHARACTER SET latin1 NOT NULL');
        }
    }

    public function down()
    {
        $this->forge->dropTable('messages');
    }
}
