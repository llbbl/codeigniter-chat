# Database schema

The application supports MySQL 8 and SQLite. Migrations in
`app/Database/Migrations/` are the source of truth; this document highlights
the core account and message relationships and the constraints that protect
them.

## Core relationships

```text
users 1 ──── * messages          * ──── 1 channels
  │                │
  │                └─ nullable user_id preserves content after user deletion
  │
  ├──── * archived_messages      * ──── 1 channels
  ├──── * channel_members        * ──── 1 channels
  ├──── * message_reactions      * ──── 1 messages
  └──── * push_subscriptions
```

`messages` and `archived_messages` store the author's numeric `user_id`, not a
copy of the username. Repository reads join `users` and continue to expose a
`user` string in HTTP and WebSocket payloads. If an account is deleted, the
foreign key sets `user_id` to `NULL` and reads and exports show `[deleted]`.

## Relevant columns

### `users`

| Column | Constraints | Purpose |
| --- | --- | --- |
| `id` | primary key | Stable account identifier |
| `username` | unique, `chk_users_username_format` | Login and display handle |
| `email` | unique | Login and account email |
| `password` | `chk_users_password_hash` | Output of PHP `password_hash()` |
| profile columns | see `AddUserProfileFields` | Display name, avatar, theme, notifications, and presence |

The schema column is named `password`; it contains a password hash. The check
name uses “password hash” to make that invariant explicit.

### `messages`

| Column | Constraints | Purpose |
| --- | --- | --- |
| `id` | primary key | Stable message and cursor identifier |
| `channel_id` | not null, FK to `channels.id`, delete restricted | Owning channel |
| `user_id` | nullable, `fk_messages_user`, delete sets null | Author account |
| `msg` | not null, `chk_messages_msg_length` | 1–500 character body |
| `time` | not null, `chk_messages_time_positive` | Positive Unix timestamp |

### `archived_messages`

Archived messages preserve the original message ID, channel, author reference,
body, and timestamp and add `archived_at`. They use the equivalent named checks
`chk_archived_messages_msg_length` and
`chk_archived_messages_time_positive`, plus the nullable
`fk_archived_messages_user` foreign key. This keeps live and archived history
under the same data-integrity rules.

## Constraint rationale

| Constraint | Rule | Why it exists |
| --- | --- | --- |
| `chk_users_username_format` | 3–30 ASCII letters, digits, or underscores | Prevents invalid handles from direct SQL or missed validation |
| `chk_users_password_hash` | starts with `$2y$` or `$argon2` | Rejects accidental plain-text password writes |
| `chk_messages_msg_length` | character length is 1–500 | Enforces the public message contract in storage |
| `chk_messages_time_positive` | `time > 0` | Rejects zero and negative sentinel timestamps |
| `chk_archived_messages_msg_length` | character length is 1–500 | Gives archived rows parity with live rows |
| `chk_archived_messages_time_positive` | `time > 0` | Gives archived rows parity with live rows |
| `fk_messages_user` | nullable user FK, `ON DELETE SET NULL`, `ON UPDATE CASCADE` | Preserves messages without retaining a stale username |
| `fk_archived_messages_user` | nullable user FK, `ON DELETE SET NULL`, `ON UPDATE CASCADE` | Preserves archived history after account deletion |

Application validation deliberately repeats the username, message-length, and
password requirements. It provides useful client errors; the database checks
are the final safety net for direct SQL, migrations, and missed code paths.

## Migration behavior

`TightenMessagesConstraints` replaces the legacy string `user` columns in both
message tables with `user_id`. It backfills IDs by matching the old username to
`users.username`. A row whose username no longer exists receives `NULL` instead
of blocking the migration.

Run a pre-deployment audit for usernames outside the allowed format, password
values without a supported hash prefix, empty or over-500-character messages,
and non-positive timestamps. Existing rows that violate a new check cause the
migration to fail; the migration does not silently rewrite invalid account or
message data.

On rollback, `user_id` is converted back to a username. Rows with a deleted or
otherwise missing author become `[deleted]`, so a deleted username cannot be
recovered by rolling back.

### MySQL and MariaDB

MySQL must be 8.0.16 or newer for enforced `CHECK` constraints; CI runs these
migrations and direct-SQL constraint tests against MySQL 8.4. MariaDB supports
checks but differs across releases in named-check syntax and metadata. Confirm
the exact MariaDB target version in staging before deployment and use the
constraint names above when inspecting or removing checks.

### SQLite

SQLite cannot add these checks and foreign keys to an existing table in place,
so the migrations rebuild `users`, `messages`, and `archived_messages`, copy the
data, recreate indexes and FTS5 triggers, and run `PRAGMA foreign_key_check`.
Foreign-key enforcement still requires `PRAGMA foreign_keys=ON`; CodeIgniter
enables it for the application connections. If a migration is interrupted,
inspect both the original table and any `*_constraint_rebuild` table before
retrying.

Message full-text indexes contain only `msg` after normalization. Exact author
filtering joins `users.username`; deleted authors remain readable as
`[deleted]` but cannot match a former username.
