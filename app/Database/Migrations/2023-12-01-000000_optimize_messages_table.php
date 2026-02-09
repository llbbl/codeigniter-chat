<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class OptimizeMessagesTable extends Migration
{
    public function up()
    {
        // Add indexes for better query performance
        // Using raw SQL for cross-database compatibility with IF NOT EXISTS logic

        if ($this->db->DBDriver === 'MySQLi') {
            // MySQL: Add indexes and update character set
            $this->db->query('CREATE INDEX IF NOT EXISTS time ON messages(time)');
            $this->db->query('CREATE INDEX IF NOT EXISTS user ON messages(user)');
            $this->db->query('CREATE INDEX IF NOT EXISTS idx_messages_time_user ON messages(time, user)');

            // Update character set of user and msg columns to UTF-8 (MySQL-specific)
            $this->db->query('ALTER TABLE messages MODIFY user VARCHAR(255) CHARACTER SET utf8 NOT NULL');
            $this->db->query('ALTER TABLE messages MODIFY msg TEXT CHARACTER SET utf8 NOT NULL');
        } else {
            // SQLite: Add indexes (SQLite uses IF NOT EXISTS syntax)
            $this->db->query('CREATE INDEX IF NOT EXISTS idx_messages_time ON messages(time)');
            $this->db->query('CREATE INDEX IF NOT EXISTS idx_messages_user ON messages(user)');
            $this->db->query('CREATE INDEX IF NOT EXISTS idx_messages_time_user ON messages(time, user)');
            // Note: SQLite doesn't support column-level character sets; UTF-8 is the default
        }
    }

    public function down()
    {
        if ($this->db->DBDriver === 'MySQLi') {
            // MySQL: Remove the indexes using MySQL syntax
            $this->db->query('DROP INDEX idx_messages_time_user ON messages');
            $this->db->query('DROP INDEX user ON messages');
            $this->db->query('DROP INDEX time ON messages');

            // Revert character set changes (MySQL-specific)
            $this->db->query('ALTER TABLE messages MODIFY user VARCHAR(255) CHARACTER SET latin1 NOT NULL');
            $this->db->query('ALTER TABLE messages MODIFY msg TEXT CHARACTER SET latin1 NOT NULL');
        } else {
            // SQLite: Remove indexes using SQLite syntax
            $this->db->query('DROP INDEX IF EXISTS idx_messages_time_user');
            $this->db->query('DROP INDEX IF EXISTS idx_messages_user');
            $this->db->query('DROP INDEX IF EXISTS idx_messages_time');
        }
    }
}