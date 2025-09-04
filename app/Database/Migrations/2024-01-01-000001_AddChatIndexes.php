<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration to add database indexes for chat messages table
 * 
 * This migration adds various indexes to optimize query performance
 * for the messages table based on common usage patterns.
 */
class AddChatIndexes extends Migration
{
    public function up()
    {
        // Add composite index on (time, user) for efficient user filtering with time-based ordering
        // This index optimizes queries that filter by user and order by time
        $this->forge->addKey(['time', 'user'], false, false, 'idx_messages_time_user');

        // Add index on time column alone for pagination optimization
        // This index optimizes general message retrieval ordered by time
        $this->forge->addKey('time', false, false, 'idx_messages_time');

        // Add index on user column for user-specific message queries
        // This index optimizes queries that filter messages by specific users
        $this->forge->addKey('user', false, false, 'idx_messages_user');

        // Add composite index on (user, time) for user message history
        // This index optimizes user-specific queries ordered by time
        $this->forge->addKey(['user', 'time'], false, false, 'idx_messages_user_time');

        // Process the indexes on the messages table
        $this->forge->processIndexes('messages');

        // Log the migration
        log_message('info', 'Chat message indexes added successfully');
    }

    public function down()
    {
        // Remove the indexes in reverse order
        if ($this->db->indexExists('messages', 'idx_messages_user_time')) {
            $this->forge->dropKey('messages', 'idx_messages_user_time');
        }

        if ($this->db->indexExists('messages', 'idx_messages_user')) {
            $this->forge->dropKey('messages', 'idx_messages_user');
        }

        if ($this->db->indexExists('messages', 'idx_messages_time')) {
            $this->forge->dropKey('messages', 'idx_messages_time');
        }

        if ($this->db->indexExists('messages', 'idx_messages_time_user')) {
            $this->forge->dropKey('messages', 'idx_messages_time_user');
        }

        // Log the rollback
        log_message('info', 'Chat message indexes removed successfully');
    }
}