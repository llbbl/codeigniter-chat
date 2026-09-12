<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class RateLimit extends BaseConfig
{
    /**
     * Request limits by profile and authentication state.
     *
     * Each tuple is [maximum requests, window in seconds].
     *
     * @var array<string, array{anonymous: array{int, int}, authenticated: array{int, int}}>
     */
    public array $profiles = [
        'default' => [
            'anonymous' => [60, 60],
            'authenticated' => [120, 60],
        ],
        'auth' => [
            'anonymous' => [5, 60],
            'authenticated' => [10, 60],
        ],
        'write' => [
            'anonymous' => [10, 60],
            'authenticated' => [30, 60],
        ],
    ];
}
