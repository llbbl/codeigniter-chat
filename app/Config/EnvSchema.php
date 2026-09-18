<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

final class EnvSchema extends BaseConfig
{
    /**
     * @var array<string, array<string, mixed>>
     */
    public array $schema = [
        'CI_ENVIRONMENT' => [
            'group' => 'Application',
            'type' => 'enum',
            'values' => ['development', 'testing', 'production'],
            'required' => true,
            'example' => 'development',
            'description' => 'Active CodeIgniter runtime environment.',
        ],
        'APP_URL' => [
            'group' => 'Application',
            'type' => 'url',
            'required' => true,
            'example' => 'http://localhost:8000',
            'description' => 'Public application URL, including its scheme.',
        ],
        'APP_PORT' => [
            'group' => 'Application',
            'type' => 'int',
            'min' => 1,
            'max' => 65535,
            'default' => 8000,
            'description' => 'Host port exposed by the web container.',
        ],
        'DB_DRIVER' => [
            'group' => 'Database',
            'type' => 'enum',
            'values' => ['MySQLi', 'SQLite3'],
            'required' => true,
            'example' => 'MySQLi',
            'description' => 'Database driver used by the application.',
        ],
        'DB_DATABASE' => [
            'group' => 'Database',
            'type' => 'string',
            'required_when' => ['DB_DRIVER' => 'MySQLi'],
            'example' => 'ci4_chat',
            'description' => 'MySQL database name.',
        ],
        'DB_USERNAME' => [
            'group' => 'Database',
            'type' => 'string',
            'required_when' => ['DB_DRIVER' => 'MySQLi'],
            'example' => 'ci4_user',
            'description' => 'MySQL application account.',
        ],
        'DB_PASSWORD' => [
            'group' => 'Database',
            'type' => 'string',
            'required_when' => ['DB_DRIVER' => 'MySQLi'],
            'secret' => true,
            'min_length' => 12,
            'forbidden_values' => ['change-me', 'changeme', 'ci4_password', 'password', 'your_mysql_password'],
            'description' => 'Password for the MySQL application account.',
        ],
        'DB_ROOT_PASSWORD' => [
            'group' => 'Database',
            'type' => 'string',
            'secret' => true,
            'min_length' => 12,
            'forbidden_values' => ['change-me', 'changeme', 'rootpassword', 'password'],
            'example' => 'CHANGE-ME',
            'description' => 'MySQL root password used only by Docker Compose.',
        ],
        'DB_PORT' => [
            'group' => 'Database',
            'type' => 'int',
            'min' => 1,
            'max' => 65535,
            'default' => 3307,
            'description' => 'Host port exposed by the MySQL container.',
        ],
        'WEBSOCKET_PORT' => [
            'group' => 'WebSocket',
            'type' => 'int',
            'min' => 1,
            'max' => 65535,
            'default' => 8080,
            'description' => 'Host port exposed by the WebSocket server.',
        ],
        'WEBSOCKET_URL' => [
            'group' => 'WebSocket',
            'type' => 'websocket_url',
            'required' => true,
            'example' => 'ws://localhost:8080',
            'description' => 'Browser-accessible WebSocket URL using ws or wss.',
        ],
        'WEBSOCKET_TOKEN_SECRET' => [
            'group' => 'WebSocket',
            'type' => 'string',
            'required' => true,
            'secret' => true,
            'min_length' => 32,
            'forbidden_values' => ['change-me', 'changeme', 'your-secret-here', 'websocket-secret'],
            'description' => 'Secret reserved for signing WebSocket authentication material.',
        ],
        'BENCHMARK_MODE' => [
            'group' => 'Development',
            'type' => 'bool',
            'default' => false,
            'description' => 'Allows destructive benchmark data seeding outside production.',
        ],
        'push.vapidPublicKey' => [
            'group' => 'Push notifications',
            'type' => 'string',
            'description' => 'Public VAPID key exposed to browser push clients.',
        ],
        'database.default.hostname' => [
            'group' => 'CodeIgniter overrides',
            'type' => 'string',
            'description' => 'Direct override for the default database host.',
        ],
        'database.default.database' => [
            'group' => 'CodeIgniter overrides',
            'type' => 'string',
            'description' => 'Direct override for the default database name.',
        ],
        'database.default.username' => [
            'group' => 'CodeIgniter overrides',
            'type' => 'string',
            'description' => 'Direct override for the default database user.',
        ],
        'database.default.password' => [
            'group' => 'CodeIgniter overrides',
            'type' => 'string',
            'secret' => true,
            'forbidden_values' => ['change-me', 'changeme', 'ci4_password', 'password', 'your_mysql_password'],
            'description' => 'Direct override for the default database password.',
        ],
        'database.default.DBDriver' => [
            'group' => 'CodeIgniter overrides',
            'type' => 'enum',
            'values' => ['MySQLi', 'SQLite3'],
            'description' => 'Direct override for the default database driver.',
        ],
        'database.default.port' => [
            'group' => 'CodeIgniter overrides',
            'type' => 'int',
            'min' => 1,
            'max' => 65535,
            'description' => 'Direct override for the default database port.',
        ],
        'encryption.key' => [
            'group' => 'CodeIgniter overrides',
            'type' => 'string',
            'secret' => true,
            'forbidden_values' => ['change-me', 'changeme', 'your-secret-here'],
            'description' => 'CodeIgniter encryption key.',
        ],
        'email.protocol' => [
            'group' => 'Email',
            'type' => 'enum',
            'values' => ['mail', 'sendmail', 'smtp'],
            'description' => 'Email delivery protocol.',
        ],
        'email.SMTPHost' => [
            'group' => 'Email',
            'type' => 'string',
            'description' => 'SMTP server hostname.',
        ],
        'email.SMTPUser' => [
            'group' => 'Email',
            'type' => 'string',
            'description' => 'SMTP account username.',
        ],
        'email.SMTPPass' => [
            'group' => 'Email',
            'type' => 'string',
            'secret' => true,
            'forbidden_values' => ['change-me', 'changeme', 'password'],
            'description' => 'SMTP account password.',
        ],
        'email.SMTPPort' => [
            'group' => 'Email',
            'type' => 'int',
            'min' => 1,
            'max' => 65535,
            'description' => 'SMTP server port.',
        ],
        'email.SMTPCrypto' => [
            'group' => 'Email',
            'type' => 'enum',
            'values' => ['tls', 'ssl'],
            'description' => 'SMTP transport encryption mode.',
        ],
        'session.driver' => [
            'group' => 'Session',
            'type' => 'string',
            'description' => 'Fully-qualified CodeIgniter session handler class.',
        ],
        'session.savePath' => [
            'group' => 'Session',
            'type' => 'string',
            'description' => 'Session handler storage location.',
        ],
    ];
}
