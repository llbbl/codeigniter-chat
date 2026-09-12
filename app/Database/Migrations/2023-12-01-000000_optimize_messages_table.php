<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class OptimizeMessagesTable extends Migration
{
    public function up()
    {
        if ($this->db->getPlatform() === 'MySQLi') {
            $this->forge
                ->addKey('time', false, false, 'time')
                ->addKey('user', false, false, 'user')
                ->addKey(['time', 'user'], false, false, 'idx_messages_time_user')
                ->processIndexes('messages');
        } else {
            $this->forge
                ->addKey('time', false, false, 'idx_messages_time')
                ->addKey('user', false, false, 'idx_messages_user')
                ->addKey(['time', 'user'], false, false, 'idx_messages_time_user')
                ->processIndexes('messages');
        }
    }

    public function down()
    {
        if ($this->db->getPlatform() === 'MySQLi') {
            $this->forge->dropKey('messages', 'idx_messages_time_user', false);
            $this->forge->dropKey('messages', 'user', false);
            $this->forge->dropKey('messages', 'time', false);
        } else {
            $this->forge->dropKey('messages', 'idx_messages_time_user', false);
            $this->forge->dropKey('messages', 'idx_messages_user', false);
            $this->forge->dropKey('messages', 'idx_messages_time', false);
        }
    }
}
