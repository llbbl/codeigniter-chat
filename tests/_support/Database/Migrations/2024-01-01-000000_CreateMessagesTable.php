<?php

namespace Tests\Support\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration to create messages table for testing
 */
class CreateMessagesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 7,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'user' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'msg' => [
                'type' => 'TEXT',
            ],
            'time' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0,
            ],
        ]);
        
        $this->forge->addKey('id', true);
        $this->forge->createTable('messages');

        // Also create users table for other tests
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'username' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'unique' => true,
            ],
            'email' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'unique' => true,
            ],
            'password' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        
        $this->forge->addKey('id', true);
        $this->forge->createTable('users');
    }

    public function down()
    {
        $this->forge->dropTable('messages');
        $this->forge->dropTable('users');
    }
}