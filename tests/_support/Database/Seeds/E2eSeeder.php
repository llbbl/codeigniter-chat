<?php

namespace Tests\Support\Database\Seeds;

use CodeIgniter\Database\Seeder;

final class E2eSeeder extends Seeder
{
    public function run(): void
    {
        $this->db->table('users')->insert([
            'username' => 'e2euser',
            'email' => 'e2e@example.com',
            'password' => password_hash('Playwright123!', PASSWORD_DEFAULT),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
