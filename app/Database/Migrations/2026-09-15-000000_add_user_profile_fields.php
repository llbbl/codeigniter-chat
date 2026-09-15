<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Migration;
use RuntimeException;

final class AddUserProfileFields extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('users', [
            'display_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'avatar_path' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'theme' => [
                'type'       => 'VARCHAR',
                'constraint' => 16,
                'default'    => 'system',
            ],
            'notification_prefs' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'presence' => [
                'type'       => 'VARCHAR',
                'constraint' => 16,
                'default'    => 'offline',
            ],
            'last_seen_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
    }

    public function down(): void
    {
        if (! $this->db instanceof BaseConnection) {
            throw new RuntimeException('A database connection is required to remove user profile fields.');
        }

        $db = $this->db;
        $db->resetDataCache();

        if (! $db->tableExists('users')) {
            return;
        }

        $columns = [
            'display_name',
            'avatar_path',
            'theme',
            'notification_prefs',
            'presence',
            'last_seen_at',
        ];

        if ($db->DBDriver === 'SQLite3') {
            $table = $db->protectIdentifiers('users', true, false);

            foreach ($columns as $column) {
                $db->query("ALTER TABLE {$table} DROP COLUMN {$column}");
            }

            return;
        }

        $this->forge->dropColumn('users', $columns);
    }
}
