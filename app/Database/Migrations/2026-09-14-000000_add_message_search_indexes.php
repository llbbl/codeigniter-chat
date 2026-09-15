<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Migration;

class AddMessageSearchIndexes extends Migration
{
    public function up(): void
    {
        $db = $this->database();
        $messages = $db->prefixTable('messages');

        if ($db->getPlatform() === 'SQLite3') {
            $search = $db->prefixTable('messages_fts');
            $triggerPrefix = $db->getPrefix() . 'messages_fts';

            $this->db->query("CREATE VIRTUAL TABLE {$search} USING fts5(user, msg, content='{$messages}', content_rowid='id')");
            $this->db->query("INSERT INTO {$search}(rowid, user, msg) SELECT id, user, msg FROM {$messages}");
            $this->db->query("CREATE TRIGGER {$triggerPrefix}_insert AFTER INSERT ON {$messages} BEGIN INSERT INTO {$search}(rowid, user, msg) VALUES (new.id, new.user, new.msg); END");
            $this->db->query("CREATE TRIGGER {$triggerPrefix}_delete AFTER DELETE ON {$messages} BEGIN INSERT INTO {$search}({$search}, rowid, user, msg) VALUES ('delete', old.id, old.user, old.msg); END");
            $this->db->query("CREATE TRIGGER {$triggerPrefix}_update AFTER UPDATE ON {$messages} BEGIN INSERT INTO {$search}({$search}, rowid, user, msg) VALUES ('delete', old.id, old.user, old.msg); INSERT INTO {$search}(rowid, user, msg) VALUES (new.id, new.user, new.msg); END");

            return;
        }

        if ($this->db->getPlatform() === 'MySQLi') {
            $this->db->query("ALTER TABLE {$messages} ADD FULLTEXT INDEX idx_messages_fulltext (user, msg)");
        }
    }

    public function down(): void
    {
        $db = $this->database();
        $messages = $db->prefixTable('messages');

        if ($db->getPlatform() === 'SQLite3') {
            $search = $db->prefixTable('messages_fts');
            $triggerPrefix = $db->getPrefix() . 'messages_fts';

            foreach (['update', 'delete', 'insert'] as $suffix) {
                $this->db->query("DROP TRIGGER IF EXISTS {$triggerPrefix}_{$suffix}");
            }
            $this->db->query("DROP TABLE IF EXISTS {$search}");

            return;
        }

        if ($this->db->getPlatform() === 'MySQLi') {
            $this->db->query("ALTER TABLE {$messages} DROP INDEX idx_messages_fulltext");
        }
    }

    private function database(): BaseConnection
    {
        if (! $this->db instanceof BaseConnection) {
            throw new \LogicException('Message search migrations require a CodeIgniter base database connection.');
        }

        return $this->db;
    }
}
