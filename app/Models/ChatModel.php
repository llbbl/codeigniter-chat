<?php

namespace App\Models;

use CodeIgniter\Model;

class ChatModel extends Model
{
    protected $table = 'messages';
    protected $primaryKey = 'id';
    protected $allowedFields = ['user', 'msg', 'time'];

    /**
     * Get messages from the database
     * 
     * @param int $limit Number of messages to retrieve
     * @return array
     */
    public function getMsg($limit = 10)
    {
        return $this->orderBy('id', 'DESC')
                    ->limit($limit)
                    ->get()
                    ->getResultArray();
    }

    /**
     * Insert a new message into the database
     * 
     * @param string $name User name
     * @param string $message Message text
     * @param int $current Timestamp
     * @return bool
     */
    public function insertMsg($name, $message, $current)
    {
        return $this->insert([
            'user' => $name,
            'msg' => $message,
            'time' => $current
        ]);
    }
}
