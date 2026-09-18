<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Migration;
use LogicException;

final class TightenUsersConstraints extends Migration
{
    private const USERNAME_CHECK = 'chk_users_username_format';
    private const PASSWORD_CHECK = 'chk_users_password_hash';

    public function up(): void
    {
        $db = $this->database();

        if ($db->getPlatform() === 'MySQLi') {
            $users = $db->prefixTable('users');
            $db->query(
                "ALTER TABLE {$users}
                    ADD CONSTRAINT " . self::USERNAME_CHECK . " CHECK (
                        CHAR_LENGTH(username) BETWEEN 3 AND 30
                        AND username REGEXP '^[A-Za-z0-9_]+$'
                    ),
                    ADD CONSTRAINT " . self::PASSWORD_CHECK . " CHECK (
                        password LIKE '\$2y\$%' OR password LIKE '\$argon2%'
                    )",
            );

            return;
        }

        $this->rebuildSqliteUsers(true);
    }

    public function down(): void
    {
        $db = $this->database();

        if ($db->getPlatform() === 'MySQLi') {
            $users = $db->prefixTable('users');
            $db->query("ALTER TABLE {$users} DROP CHECK " . self::PASSWORD_CHECK);
            $db->query("ALTER TABLE {$users} DROP CHECK " . self::USERNAME_CHECK);

            return;
        }

        $this->rebuildSqliteUsers(false);
    }

    private function rebuildSqliteUsers(bool $withChecks): void
    {
        $db = $this->database();
        $users = $db->prefixTable('users');
        $replacement = $db->prefixTable('users_constraint_rebuild');
        $usernameCheck = $withChecks
            ? ' CONSTRAINT ' . self::USERNAME_CHECK . " CHECK (
                length(username) BETWEEN 3 AND 30
                AND username NOT GLOB '*[^A-Za-z0-9_]*'
            )"
            : '';
        $passwordCheck = $withChecks
            ? ' CONSTRAINT ' . self::PASSWORD_CHECK . " CHECK (
                password LIKE '\$2y\$%' OR password LIKE '\$argon2%'
            )"
            : '';

        $db->disableForeignKeyChecks();

        try {
            $db->query("DROP TABLE IF EXISTS {$replacement}");
            $db->query(
                "CREATE TABLE {$replacement} (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username VARCHAR(50) NOT NULL UNIQUE{$usernameCheck},
                    email VARCHAR(100) NOT NULL UNIQUE,
                    password VARCHAR(255) NOT NULL{$passwordCheck},
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    display_name VARCHAR(100) NULL,
                    avatar_path VARCHAR(255) NULL,
                    theme VARCHAR(16) NOT NULL DEFAULT 'system',
                    notification_prefs TEXT NULL,
                    presence VARCHAR(16) NOT NULL DEFAULT 'offline',
                    last_seen_at DATETIME NULL
                )",
            );
            $db->query(
                "INSERT INTO {$replacement} (
                    id, username, email, password, created_at, updated_at,
                    display_name, avatar_path, theme, notification_prefs,
                    presence, last_seen_at
                )
                SELECT
                    id, username, email, password, created_at, updated_at,
                    display_name, avatar_path, theme, notification_prefs,
                    presence, last_seen_at
                FROM {$users}",
            );
            $db->query("DROP TABLE {$users}");
            $db->query("ALTER TABLE {$replacement} RENAME TO {$users}");
        } finally {
            $db->enableForeignKeyChecks();
        }

        $violations = $db->query('PRAGMA foreign_key_check')->getResultArray();
        if ($violations !== []) {
            throw new LogicException('User constraint migration left invalid foreign keys.');
        }
    }

    private function database(): BaseConnection
    {
        if (! $this->db instanceof BaseConnection) {
            throw new LogicException('User constraint migrations require a database connection.');
        }

        return $this->db;
    }
}
