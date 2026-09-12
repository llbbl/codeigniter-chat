<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCspReportsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'document_uri' => ['type' => 'TEXT', 'null' => true],
            'violated_directive' => ['type' => 'VARCHAR', 'constraint' => 255],
            'blocked_uri' => ['type' => 'TEXT', 'null' => true],
            'source_file' => ['type' => 'TEXT', 'null' => true],
            'line_number' => ['type' => 'INT', 'null' => true],
            'user_agent' => ['type' => 'TEXT', 'null' => true],
            'reported_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('violated_directive');
        $this->forge->addKey('reported_at');
        $this->forge->createTable('csp_reports');
    }

    public function down()
    {
        $this->forge->dropTable('csp_reports');
    }
}
