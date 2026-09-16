# Channels and direct messages

Authenticated users can organize conversations into public channels and private
two-person direct messages. Existing HTML, XML, and JSON clients remain pinned
to `#general`; they do not need to send a channel identifier.

## Data model

- `channels` stores public channels and direct-message conversations. Public
  slugs match `^[a-z0-9-]{3,50}$`; topics are optional and limited to 200
  characters.
- `channel_members` stores membership and the last-read message identifier used
  for unread counts.
- `messages.channel_id` is required and indexed with message time and ID.
- `archived_messages` preserves original message IDs after old rows leave the
  live table. Cursor reads combine both tables; archived rows are read-only.
- The migration creates `#general`, enrolls existing users, and assigns every
  existing message to it. New users are enrolled when they first use a channel
  endpoint or open an authenticated WebSocket connection.

`#general` cannot be left. A public channel's creator may rename it, change its
topic or slug, archive it, and restore it. This first version intentionally has
no granular roles or moderation permissions.

## HTTP API

All routes require session authentication. Mutating requests also require the
normal CSRF token and write-rate limit.

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/api/v1/channels` | List active public channels plus the current user's DMs, memberships, and unread counts |
| `POST` | `/api/v1/channels` | Create a public channel and join its creator |
| `PATCH` | `/api/v1/channels/{id}` | Creator-only name, slug, topic, and archive changes |
| `POST` | `/api/v1/channels/{id}/join` | Join an active public channel |
| `POST` | `/api/v1/channels/{id}/leave` | Leave a public channel other than `#general` |
| `POST` | `/api/v1/dms` | Find or create an idempotent two-person DM by user ID |
| `GET` | `/api/v1/channels/{id}/messages?limit=25&before={id}` | Read cursor-paginated live and archived messages and mark the channel read |
| `POST` | `/api/v1/channels/{id}/messages` | Post a member-scoped message |
| `GET` | `/api/v1/channels/{id}/messages/search` | Search messages within one member-scoped channel |
| `GET` | `/api/v1/channels/{id}/export?format=json|csv` | Download a member-scoped channel history |
| `GET` | `/api/v1/users/me/export?format=json|csv` | Download all messages authored by the current user |

Create a public channel:

```json
{
  "name": "Project Room",
  "slug": "project-room",
  "topic": "Planning and delivery"
}
```

Start or reopen a direct message (the username fallback is also accepted by the
built-in clients):

```json
{
  "user_id": 42
}
```

DM slugs are canonicalized from the two user IDs, so reversing the participants
returns the same channel. Non-members receive `403` for channel messages,
search, and direct-message content.

## WebSocket protocol

Authenticated connections begin subscribed to `#general`. Switch conversations
before reading or sending:

```json
{ "type": "channel_subscribe", "channel_id": 12 }
```

The server confirms with `channel_subscribed`. `getMessages`, `sendMessage`,
typing, and reaction events are restricted to channel members. A new message is
delivered in full only to member connections currently subscribed to that
channel. Other connected members receive:

```json
{ "type": "channel_unread", "channel_id": 12 }
```

Clients should include `channel_id` in `getMessages` and `sendMessage`. The
server derives the sender's username from the authenticated user and ignores a
client-supplied identity.

## Deploying the migration

The SQLite migration adds the required column with the new `#general` ID as its
default. MySQL uses an in-place sequence: add the column as nullable, backfill
existing rows, make it required with the `#general` default, then add the index
and foreign key.

For a MySQL `messages` table with roughly 100,000 rows or more, schedule a
maintenance window or test an online-schema-change process on a production-sized
copy first. `ALTER TABLE` may rebuild the table or hold a metadata lock, while
the backfill creates write load and transaction-log growth. Before deployment:

1. Verify a current backup and available disk space.
2. Measure the backfill and both `ALTER TABLE` operations on representative
   hardware.
3. Stop or drain message writers if the selected MySQL version cannot perform
   the changes online.
4. Monitor metadata-lock waits, replication lag, transaction logs, and free
   space throughout the migration.
5. Confirm that all old messages point to `#general` and that
   `idx_messages_channel_time` exists before restoring normal traffic.

Rollback removes the message foreign key and index before dropping
`messages.channel_id`, `channel_members`, and `channels`. Rolling back discards
all channel assignments and should only be used with an explicit recovery plan.
