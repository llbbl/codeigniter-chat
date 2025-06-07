<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class OptimizeMessagesTable extends Migration
{
    public function up()
    {
        // Add index on time column for time-based queries and sorting
        $this->forge->addKey('time', false);
        
        // Add index on user column for user-based filtering
        $this->forge->addKey('user', false);
        
        // Update character set of user and msg columns to UTF-8
        $this->db->query('ALTER TABLE messages MODIFY user VARCHAR(255) CHARACTER SET utf8 NOT NULL');
        $this->db->query('ALTER TABLE messages MODIFY msg TEXT CHARACTER SET utf8 NOT NULL');
        
        // Add a composite index for queries that might filter by both time and user
        $this->db->query('CREATE INDEX idx_messages_time_user ON messages(time, user)');
    }

    public function down()
    {
        // Remove the indexes
        $this->db->query('DROP INDEX idx_messages_time_user ON messages');
        $this->db->query('DROP INDEX user ON messages');
        $this->db->query('DROP INDEX time ON messages');
        
        // Revert character set changes
        $this->db->query('ALTER TABLE messages MODIFY user VARCHAR(255) CHARACTER SET latin1 NOT NULL');
        $this->db->query('ALTER TABLE messages MODIFY msg TEXT CHARACTER SET latin1 NOT NULL');
    }
}