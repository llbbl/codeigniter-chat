<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAuditLogTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'event_type' => ['type' => 'VARCHAR', 'constraint' => 100],
            'user_id' => ['type' => 'INT', 'null' => true],
            'username_attempted' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45],
            'user_agent' => ['type' => 'TEXT', 'null' => true],
            'context' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('event_type');
        $this->forge->addKey('user_id');
        $this->forge->addKey('ip_address');
        $this->forge->addKey('created_at');
        $this->forge->createTable('audit_log');
    }
    public function down()
    {
        $this->forge->dropTable('audit_log');
    }
}
