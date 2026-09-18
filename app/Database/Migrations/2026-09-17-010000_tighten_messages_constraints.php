<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Migration;
use LogicException;

final class TightenMessagesConstraints extends Migration
{
    private const LIVE_USER_FK = 'fk_messages_user';
    private const ARCHIVE_USER_FK = 'fk_archived_messages_user';
    private const LIVE_MESSAGE_CHECK = 'chk_messages_msg_length';
    private const LIVE_TIME_CHECK = 'chk_messages_time_positive';
    private const ARCHIVE_MESSAGE_CHECK = 'chk_archived_messages_msg_length';
    private const ARCHIVE_TIME_CHECK = 'chk_archived_messages_time_positive';

    public function up(): void
    {
        $this->assertLegacyMessagesAreValid();

        if ($this->database()->getPlatform() === 'MySQLi') {
            $this->migrateMysqlUp();

            return;
        }

        $this->rebuildSqliteMessages(true);
    }

    public function down(): void
    {
        if ($this->database()->getPlatform() === 'MySQLi') {
            $this->migrateMysqlDown();

            return;
        }

        $this->rebuildSqliteMessages(false);
    }

    private function migrateMysqlUp(): void
    {
        $db = $this->database();
        $messages = $db->prefixTable('messages');
        $archived = $db->prefixTable('archived_messages');
        $users = $db->prefixTable('users');

        $db->query("ALTER TABLE {$messages} DROP INDEX idx_messages_fulltext");
        $db->query("ALTER TABLE {$messages} DROP INDEX idx_messages_time_user");
        $db->query("ALTER TABLE {$messages} ADD user_id INT(11) UNSIGNED NULL AFTER channel_id");
        $db->query("UPDATE {$messages} AS message LEFT JOIN {$users} AS user ON user.username = message.user SET message.user_id = user.id");
        $db->query("ALTER TABLE {$messages} DROP COLUMN user");
        $db->query(
            "ALTER TABLE {$messages}
                ADD INDEX idx_messages_user_id_time (user_id, time, id),
                ADD FULLTEXT INDEX idx_messages_fulltext (msg),
                ADD CONSTRAINT " . self::LIVE_USER_FK . " FOREIGN KEY (user_id) REFERENCES {$users}(id) ON DELETE SET NULL ON UPDATE CASCADE,
                ADD CONSTRAINT " . self::LIVE_MESSAGE_CHECK . ' CHECK (CHAR_LENGTH(msg) BETWEEN 1 AND 500),
                ADD CONSTRAINT ' . self::LIVE_TIME_CHECK . ' CHECK (time > 0)',
        );

        $db->query("ALTER TABLE {$archived} DROP INDEX user_id");
        $db->query("ALTER TABLE {$archived} ADD user_id INT(11) UNSIGNED NULL AFTER channel_id");
        $db->query("UPDATE {$archived} AS message LEFT JOIN {$users} AS user ON user.username = message.user SET message.user_id = user.id");
        $db->query("ALTER TABLE {$archived} DROP COLUMN user");
        $db->query(
            "ALTER TABLE {$archived}
                ADD INDEX idx_archived_messages_user_id (user_id, id),
                ADD CONSTRAINT " . self::ARCHIVE_USER_FK . " FOREIGN KEY (user_id) REFERENCES {$users}(id) ON DELETE SET NULL ON UPDATE CASCADE,
                ADD CONSTRAINT " . self::ARCHIVE_MESSAGE_CHECK . ' CHECK (CHAR_LENGTH(msg) BETWEEN 1 AND 500),
                ADD CONSTRAINT ' . self::ARCHIVE_TIME_CHECK . ' CHECK (time > 0)',
        );
    }

    private function migrateMysqlDown(): void
    {
        $db = $this->database();
        $messages = $db->prefixTable('messages');
        $archived = $db->prefixTable('archived_messages');
        $users = $db->prefixTable('users');

        $db->query("ALTER TABLE {$messages} DROP INDEX idx_messages_fulltext");
        $db->query("ALTER TABLE {$messages} ADD user VARCHAR(255) CHARACTER SET latin1 NULL AFTER channel_id");
        $db->query("UPDATE {$messages} AS message LEFT JOIN {$users} AS user ON user.id = message.user_id SET message.user = COALESCE(user.username, '[deleted]')");
        $db->query(
            "ALTER TABLE {$messages}
                DROP FOREIGN KEY " . self::LIVE_USER_FK . ',
                DROP INDEX idx_messages_user_id_time,
                DROP CHECK ' . self::LIVE_MESSAGE_CHECK . ',
                DROP CHECK ' . self::LIVE_TIME_CHECK . ',
                DROP COLUMN user_id,
                MODIFY user VARCHAR(255) CHARACTER SET latin1 NOT NULL,
                ADD INDEX user (user),
                ADD INDEX idx_messages_time_user (time, user),
                ADD FULLTEXT INDEX idx_messages_fulltext (user, msg)',
        );

        $db->query("ALTER TABLE {$archived} ADD user VARCHAR(255) CHARACTER SET latin1 NULL AFTER channel_id");
        $db->query("UPDATE {$archived} AS message LEFT JOIN {$users} AS user ON user.id = message.user_id SET message.user = COALESCE(user.username, '[deleted]')");
        $db->query(
            "ALTER TABLE {$archived}
                DROP FOREIGN KEY " . self::ARCHIVE_USER_FK . ',
                DROP INDEX idx_archived_messages_user_id,
                DROP CHECK ' . self::ARCHIVE_MESSAGE_CHECK . ',
                DROP CHECK ' . self::ARCHIVE_TIME_CHECK . ',
                DROP COLUMN user_id,
                MODIFY user VARCHAR(255) CHARACTER SET latin1 NOT NULL,
                ADD INDEX user_id (user, id)',
        );
    }

    private function rebuildSqliteMessages(bool $withConstraints): void
    {
        $db = $this->database();
        $messages = $db->prefixTable('messages');
        $archived = $db->prefixTable('archived_messages');
        $users = $db->prefixTable('users');
        $liveReplacement = $db->prefixTable('messages_constraint_rebuild');
        $archiveReplacement = $db->prefixTable('archived_messages_constraint_rebuild');

        $this->dropSqliteSearch();
        $db->disableForeignKeyChecks();

        try {
            $db->query("DROP TABLE IF EXISTS {$liveReplacement}");
            $db->query("DROP TABLE IF EXISTS {$archiveReplacement}");

            if ($withConstraints) {
                $this->createSqliteNormalizedTables($liveReplacement, $archiveReplacement);
                $db->query(
                    "INSERT INTO {$liveReplacement} (id, channel_id, user_id, msg, time)
                    SELECT message.id, message.channel_id, user.id, message.msg, message.time
                    FROM {$messages} AS message
                    LEFT JOIN {$users} AS user ON user.username = message.user",
                );
                $db->query(
                    "INSERT INTO {$archiveReplacement} (id, channel_id, user_id, msg, time, archived_at)
                    SELECT message.id, message.channel_id, user.id, message.msg, message.time, message.archived_at
                    FROM {$archived} AS message
                    LEFT JOIN {$users} AS user ON user.username = message.user",
                );
            } else {
                $this->createSqliteLegacyTables($liveReplacement, $archiveReplacement);
                $db->query(
                    "INSERT INTO {$liveReplacement} (id, channel_id, user, msg, time)
                    SELECT message.id, message.channel_id, COALESCE(user.username, '[deleted]'), message.msg, message.time
                    FROM {$messages} AS message
                    LEFT JOIN {$users} AS user ON user.id = message.user_id",
                );
                $db->query(
                    "INSERT INTO {$archiveReplacement} (id, channel_id, user, msg, time, archived_at)
                    SELECT message.id, message.channel_id, COALESCE(user.username, '[deleted]'), message.msg, message.time, message.archived_at
                    FROM {$archived} AS message
                    LEFT JOIN {$users} AS user ON user.id = message.user_id",
                );
            }

            $db->query("DROP TABLE {$archived}");
            $db->query("DROP TABLE {$messages}");
            $db->query("ALTER TABLE {$liveReplacement} RENAME TO {$messages}");
            $db->query("ALTER TABLE {$archiveReplacement} RENAME TO {$archived}");
            $this->createSqliteIndexes($withConstraints);
            $this->createSqliteSearch($withConstraints);
        } finally {
            $db->enableForeignKeyChecks();
        }

        $violations = $db->query('PRAGMA foreign_key_check')->getResultArray();
        if ($violations !== []) {
            throw new LogicException('Message constraint migration left invalid foreign keys.');
        }
    }

    private function createSqliteNormalizedTables(string $messages, string $archived): void
    {
        $channels = $this->database()->prefixTable('channels');
        $users = $this->database()->prefixTable('users');
        $generalId = $this->generalChannelId();
        $this->database()->query(
            "CREATE TABLE {$messages} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                channel_id INTEGER NOT NULL DEFAULT {$generalId},
                user_id INTEGER NULL,
                msg TEXT NOT NULL CONSTRAINT " . self::LIVE_MESSAGE_CHECK . ' CHECK (length(msg) BETWEEN 1 AND 500),
                time INTEGER NOT NULL CONSTRAINT ' . self::LIVE_TIME_CHECK . " CHECK (time > 0),
                CONSTRAINT fk_messages_channel FOREIGN KEY (channel_id) REFERENCES {$channels}(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT " . self::LIVE_USER_FK . " FOREIGN KEY (user_id) REFERENCES {$users}(id) ON DELETE SET NULL ON UPDATE CASCADE
            )",
        );
        $this->database()->query(
            "CREATE TABLE {$archived} (
                id INTEGER PRIMARY KEY,
                channel_id INTEGER NOT NULL,
                user_id INTEGER NULL,
                msg TEXT NOT NULL CONSTRAINT " . self::ARCHIVE_MESSAGE_CHECK . ' CHECK (length(msg) BETWEEN 1 AND 500),
                time INTEGER NOT NULL CONSTRAINT ' . self::ARCHIVE_TIME_CHECK . " CHECK (time > 0),
                archived_at DATETIME NOT NULL,
                CONSTRAINT fk_archived_messages_channel FOREIGN KEY (channel_id) REFERENCES {$channels}(id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT " . self::ARCHIVE_USER_FK . " FOREIGN KEY (user_id) REFERENCES {$users}(id) ON DELETE SET NULL ON UPDATE CASCADE
            )",
        );
    }

    private function createSqliteLegacyTables(string $messages, string $archived): void
    {
        $channels = $this->database()->prefixTable('channels');
        $generalId = $this->generalChannelId();
        $this->database()->query(
            "CREATE TABLE {$messages} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                channel_id INTEGER NOT NULL DEFAULT {$generalId},
                user VARCHAR(255) NOT NULL,
                msg TEXT NOT NULL,
                time INTEGER NOT NULL DEFAULT 0
            )",
        );
        $this->database()->query(
            "CREATE TABLE {$archived} (
                id INTEGER PRIMARY KEY,
                channel_id INTEGER NOT NULL,
                user VARCHAR(255) NOT NULL,
                msg TEXT NOT NULL,
                time INTEGER NOT NULL DEFAULT 0,
                archived_at DATETIME NOT NULL,
                CONSTRAINT fk_archived_messages_channel FOREIGN KEY (channel_id) REFERENCES {$channels}(id) ON DELETE CASCADE ON UPDATE CASCADE
            )",
        );
    }

    private function createSqliteIndexes(bool $normalized): void
    {
        $db = $this->database();
        $messages = $db->prefixTable('messages');
        $archived = $db->prefixTable('archived_messages');
        $db->query("CREATE INDEX idx_messages_time ON {$messages} (time)");
        $db->query("CREATE INDEX idx_messages_channel_time ON {$messages} (channel_id, time, id)");
        $db->query("CREATE INDEX idx_archived_messages_channel_time ON {$archived} (channel_id, time, id)");

        if ($normalized) {
            $db->query("CREATE INDEX idx_messages_user_id ON {$messages} (user_id)");
            $db->query("CREATE INDEX idx_messages_time_user_id ON {$messages} (time, user_id)");
            $db->query("CREATE INDEX idx_archived_messages_user_id ON {$archived} (user_id, id)");
        } else {
            $db->query("CREATE INDEX idx_messages_user ON {$messages} (user)");
            $db->query("CREATE INDEX idx_messages_time_user ON {$messages} (time, user)");
            $db->query("CREATE INDEX idx_archived_messages_user ON {$archived} (user, id)");
        }
    }

    private function dropSqliteSearch(): void
    {
        $db = $this->database();
        $search = $db->prefixTable('messages_fts');
        $triggerPrefix = $db->getPrefix() . 'messages_fts';

        foreach (['update', 'delete', 'insert'] as $suffix) {
            $db->query("DROP TRIGGER IF EXISTS {$triggerPrefix}_{$suffix}");
        }
        $db->query("DROP TABLE IF EXISTS {$search}");
    }

    private function createSqliteSearch(bool $normalized): void
    {
        $db = $this->database();
        $messages = $db->prefixTable('messages');
        $search = $db->prefixTable('messages_fts');
        $triggerPrefix = $db->getPrefix() . 'messages_fts';

        if ($normalized) {
            $db->query("CREATE VIRTUAL TABLE {$search} USING fts5(msg, content='{$messages}', content_rowid='id')");
            $db->query("INSERT INTO {$search}(rowid, msg) SELECT id, msg FROM {$messages}");
            $db->query("CREATE TRIGGER {$triggerPrefix}_insert AFTER INSERT ON {$messages} BEGIN INSERT INTO {$search}(rowid, msg) VALUES (new.id, new.msg); END");
            $db->query("CREATE TRIGGER {$triggerPrefix}_delete AFTER DELETE ON {$messages} BEGIN INSERT INTO {$search}({$search}, rowid, msg) VALUES ('delete', old.id, old.msg); END");
            $db->query("CREATE TRIGGER {$triggerPrefix}_update AFTER UPDATE ON {$messages} BEGIN INSERT INTO {$search}({$search}, rowid, msg) VALUES ('delete', old.id, old.msg); INSERT INTO {$search}(rowid, msg) VALUES (new.id, new.msg); END");

            return;
        }

        $db->query("CREATE VIRTUAL TABLE {$search} USING fts5(user, msg, content='{$messages}', content_rowid='id')");
        $db->query("INSERT INTO {$search}(rowid, user, msg) SELECT id, user, msg FROM {$messages}");
        $db->query("CREATE TRIGGER {$triggerPrefix}_insert AFTER INSERT ON {$messages} BEGIN INSERT INTO {$search}(rowid, user, msg) VALUES (new.id, new.user, new.msg); END");
        $db->query("CREATE TRIGGER {$triggerPrefix}_delete AFTER DELETE ON {$messages} BEGIN INSERT INTO {$search}({$search}, rowid, user, msg) VALUES ('delete', old.id, old.user, old.msg); END");
        $db->query("CREATE TRIGGER {$triggerPrefix}_update AFTER UPDATE ON {$messages} BEGIN INSERT INTO {$search}({$search}, rowid, user, msg) VALUES ('delete', old.id, old.user, old.msg); INSERT INTO {$search}(rowid, user, msg) VALUES (new.id, new.user, new.msg); END");
    }

    private function database(): BaseConnection
    {
        if (! $this->db instanceof BaseConnection) {
            throw new LogicException('Message constraint migrations require a database connection.');
        }

        return $this->db;
    }

    private function assertLegacyMessagesAreValid(): void
    {
        $db = $this->database();
        $length = $db->getPlatform() === 'MySQLi' ? 'CHAR_LENGTH(msg)' : 'length(msg)';

        foreach (['messages', 'archived_messages'] as $table) {
            $invalid = $db->table($table)
                ->groupStart()
                ->where("{$length} <", 1, false)
                ->orWhere("{$length} >", 500, false)
                ->orWhere('time <=', 0)
                ->groupEnd()
                ->countAllResults();

            if ($invalid > 0) {
                throw new LogicException(
                    "Cannot tighten {$table}: {$invalid} row(s) have an empty or over-500-character message, or a non-positive timestamp.",
                );
            }
        }
    }

    private function generalChannelId(): int
    {
        $row = $this->database()->table('channels')->select('id')->where('slug', 'general')->get()->getRowArray();
        if (! is_array($row)) {
            throw new LogicException('The #general channel is required for the message constraint migration.');
        }

        return (int) $row['id'];
    }
}
