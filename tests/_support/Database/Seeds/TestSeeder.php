<?php

namespace Tests\Support\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Test Seeder for chat application tests
 */
class TestSeeder extends Seeder
{
    public function run()
    {
        // Create the messages table if it doesn't exist
        $this->db->table('messages')->truncate();
        
        // Insert some test messages for testing
        $data = [
            [
                'user' => 'testuser1',
                'msg' => 'Hello world!',
                'time' => time() - 3600
            ],
            [
                'user' => 'testuser2',
                'msg' => 'How are you?',
                'time' => time() - 1800
            ],
            [
                'user' => 'testuser1',
                'msg' => 'I am fine, thanks!',
                'time' => time() - 900
            ]
        ];
        
        $this->db->table('messages')->insertBatch($data);
        
        // Create users table data if needed (for other tests)
        $this->db->table('users')->truncate();
        
        $userData = [
            [
                'username' => 'testuser1',
                'email' => 'test1@example.com',
                'password' => password_hash('password123', PASSWORD_DEFAULT),
                'created_at' => date('Y-m-d H:i:s')
            ],
            [
                'username' => 'testuser2',
                'email' => 'test2@example.com',
                'password' => password_hash('password456', PASSWORD_DEFAULT),
                'created_at' => date('Y-m-d H:i:s')
            ]
        ];
        
        $this->db->table('users')->insertBatch($userData);
    }
}