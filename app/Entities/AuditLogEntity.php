<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class AuditLogEntity extends Entity
{
    protected $casts = ['id' => 'integer', 'user_id' => '?integer', 'context' => 'json-array'];
}
