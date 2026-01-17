<?php

namespace Config;

use CodeIgniter\Database\Config;

/**
 * Database Configuration
 *
 * This application supports both MySQL and SQLite databases.
 * Set the DB_DRIVER environment variable to switch between them:
 *   - DB_DRIVER=MySQLi (default) - Use MySQL/MariaDB
 *   - DB_DRIVER=SQLite3 - Use SQLite (easier setup, great for development)
 *
 * For SQLite, the database file is stored in writable/database/
 */
class Database extends Config
{
    /**
     * The directory that holds the Migrations and Seeds directories.
     */
    public string $filesPath = APPPATH . 'Database' . DIRECTORY_SEPARATOR;

    /**
     * Lets you choose which connection group to use if no other is specified.
     */
    public string $defaultGroup = 'default';

    /**
     * The default database connection (MySQL).
     * This can be overridden by setting DB_DRIVER=SQLite3 in your .env file.
     *
     * @var array<string, mixed>
     */
    public array $default = [
        'DSN'          => '',
        'hostname'     => 'localhost',
        'username'     => 'your_mysql_username', // Change this to your MySQL username
        'password'     => 'your_mysql_password', // Change this to your MySQL password
        'database'     => 'your_database_name',  // Change this to your database name
        'DBDriver'     => 'MySQLi',
        'DBPrefix'     => '',
        'pConnect'     => true, // Enabled persistent connections for database connection pooling
        'DBDebug'      => true,
        'charset'      => 'utf8mb4',
        'DBCollat'     => 'utf8mb4_general_ci',
        'swapPre'      => '',
        'encrypt'      => false,
        'compress'     => false,
        'strictOn'     => false,
        'failover'     => [],
        'port'         => 3306,
        'numberNative' => false,
        'foundRows'    => false,
        'dateFormat'   => [
            'date'     => 'Y-m-d',
            'datetime' => 'Y-m-d H:i:s',
            'time'     => 'H:i:s',
        ],
    ];

    /**
     * SQLite database connection.
     * Enable by setting DB_DRIVER=SQLite3 in your .env file.
     * The database file will be created at writable/database/chat.db
     *
     * @var array<string, mixed>
     */
    public array $sqlite = [
        'database'    => WRITEPATH . 'database/chat.db',
        'DBDriver'    => 'SQLite3',
        'DBPrefix'    => '',
        'DBDebug'     => true,
        'swapPre'     => '',
        'failover'    => [],
        'foreignKeys' => true,
        'busyTimeout' => 1000,
        'synchronous' => null,
        'dateFormat'  => [
            'date'     => 'Y-m-d',
            'datetime' => 'Y-m-d H:i:s',
            'time'     => 'H:i:s',
        ],
    ];

    /**
     * This database connection is used when running PHPUnit database tests.
     *
     * @var array<string, mixed>
     */
    public array $tests = [
        'DSN'         => '',
        'hostname'    => '127.0.0.1',
        'username'    => '',
        'password'    => '',
        'database'    => ':memory:',
        'DBDriver'    => 'SQLite3',
        'DBPrefix'    => 'db_',  // Needed to ensure we're working correctly with prefixes live. DO NOT REMOVE FOR CI DEVS
        'pConnect'    => false, // SQLite3 doesn't support persistent connections
        'DBDebug'     => true,
        'charset'     => 'utf8',
        'DBCollat'    => '',
        'swapPre'     => '',
        'encrypt'     => false,
        'compress'    => false,
        'strictOn'    => false,
        'failover'    => [],
        'port'        => 3306,
        'foreignKeys' => true,
        'busyTimeout' => 1000,
        'dateFormat'  => [
            'date'     => 'Y-m-d',
            'datetime' => 'Y-m-d H:i:s',
            'time'     => 'H:i:s',
        ],
    ];

    public function __construct()
    {
        parent::__construct();

        // Ensure that we always set the database group to 'tests' if
        // we are currently running an automated test suite, so that
        // we don't overwrite live data on accident.
        if (ENVIRONMENT === 'testing') {
            $this->defaultGroup = 'tests';

            return;
        }

        // Check if SQLite is requested via environment variable
        // Set DB_DRIVER=SQLite3 in your .env file to use SQLite
        $driver = env('DB_DRIVER', 'MySQLi');

        if ($driver === 'SQLite3') {
            // Switch to SQLite configuration
            $this->defaultGroup = 'sqlite';

            // Ensure the database directory exists
            $dbDir = WRITEPATH . 'database';
            if (! is_dir($dbDir)) {
                mkdir($dbDir, 0755, true);
            }
        }
    }
}
