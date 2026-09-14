<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreatePushSubscriptionsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'endpoint' => ['type' => 'TEXT'],
            'endpoint_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'p256dh' => ['type' => 'VARCHAR', 'constraint' => 255],
            'auth' => ['type' => 'VARCHAR', 'constraint' => 255],
            'content_encoding' => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'aes128gcm'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('user_id');
        $this->forge->addUniqueKey('endpoint_hash');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('push_subscriptions');
    }

    public function down(): void
    {
        $this->forge->dropTable('push_subscriptions');
    }
}
