<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateWebhooks extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'url' => ['type' => 'TEXT'],
            'url_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'secret' => ['type' => 'CHAR', 'constraint' => 64],
            'events' => ['type' => 'TEXT'],
            'active' => ['type' => 'BOOLEAN', 'default' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('user_id');
        $this->forge->addUniqueKey(['user_id', 'url_hash'], 'uq_webhooks_user_url');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('webhooks');

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'webhook_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'event' => ['type' => 'VARCHAR', 'constraint' => 64],
            'payload' => ['type' => 'TEXT'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'pending'],
            'attempts' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'response_status' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'response_body' => ['type' => 'TEXT', 'null' => true],
            'last_error' => ['type' => 'TEXT', 'null' => true],
            'next_attempt_at' => ['type' => 'DATETIME', 'null' => true],
            'delivered_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('webhook_id');
        $this->forge->addKey(['status', 'next_attempt_at'], false, false, 'idx_webhook_deliveries_due');
        $this->forge->addForeignKey('webhook_id', 'webhooks', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('webhook_deliveries');
    }

    public function down(): void
    {
        $this->forge->dropTable('webhook_deliveries');
        $this->forge->dropTable('webhooks');
    }
}
