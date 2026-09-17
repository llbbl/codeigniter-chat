# Operations

## Data retention

The `retention:apply` Spark command is the normal entry point for scheduled
cleanup. It reads the policies in `app/Config/Retention.php` and applies every
configured lifecycle rule:

| Policy | Age | Action |
| --- | ---: | --- |
| `messages` | 90 days | Move live rows to `archived_messages` |
| `archived_messages` | 730 days | Permanently delete archive rows |
| `csp_reports` | 30 days | Permanently delete CSP reports |
| `audit_log` | 365 days | Permanently delete audit events |

Start with a dry run. It counts eligible rows without changing the database:

```bash
php spark retention:apply --dry-run
```

Run every policy, or select one policy for a one-off cleanup:

```bash
php spark retention:apply
php spark retention:apply --policy=csp_reports
```

Delete actions select primary keys and remove at most 5,000 rows per
transaction. Archive actions delegate to `messages:archive`, which performs the
live-to-archive move transactionally. Use `--batch-size` to reduce lock and
transaction-log pressure on busy MySQL installations; valid values are 1 to
10,000. Completed batches no longer match their policy, so interrupted and
completed runs are both safe to repeat.

`archived_messages` is a hot-cold tier, not a permanent record. Channel history
and exports can read it, but rows older than two years are deleted because the
database backup is the recovery layer. Take and verify a backup before enabling
hard-delete policies, and make sure the backup retention window matches the
organization's recovery expectations.

### Scheduling retention

Run from the application directory so CodeIgniter loads the intended `.env`.
For example, apply all policies every day at 03:00:

```cron
0 3 * * * cd /srv/codeigniter-chat && /usr/bin/php spark retention:apply >> writable/logs/retention.log 2>&1
```

Daily execution is appropriate for messages and CSP reports; daily or weekly
execution is sufficient for the longer audit and archive policies. Deploy the
cron entry with `--dry-run` first, inspect several runs, then remove that flag
to enable writes. Do not overlap invocations; schedule the next run after the
largest observed cleanup window.

Hard deletes do not necessarily return filesystem space immediately. After a
large first cleanup, use the database vendor's manual maintenance procedure
(`OPTIMIZE TABLE` for MySQL or `VACUUM` for SQLite) during a maintenance window.
The application command deliberately does not run storage maintenance.

## Archiving old messages

The `messages:archive` Spark command moves old rows from `messages` to
`archived_messages`. Original message and channel IDs are preserved, so channel
cursor reads and exports continue across the live/archive boundary.

Preview the scope with a database count, then archive every channel older than
90 days:

```bash
php spark messages:archive --older-than 90d --channel all
```

Limit the run to one channel or tune the transaction size:

```bash
php spark messages:archive --older-than 30d --channel 12 --batch-size 500
```

Accepted age suffixes are `m` (minutes), `h` (hours), and `d` (days). Each
batch is inserted into the archive and removed from the live table in one
transaction. A completed batch is therefore safe to re-run; a later invocation
finds only remaining live rows. The default batch size is 1,000 and the maximum
is 10,000.

Archived messages are read-only. Because reactions belong to live messages,
their rows are removed by the existing foreign-key cascade when a message is
archived. Search also remains live-message-only.

### Scheduling archive-only runs

Run from the application directory so CodeIgniter loads the intended `.env`.
The unified retention command above is recommended for routine scheduling. If
an archive-only schedule is needed, archive messages older than 90 days every
day at 02:15:

```cron
15 2 * * * cd /srv/codeigniter-chat && /usr/bin/php spark messages:archive --older-than 90d --channel all >> writable/logs/archive.log 2>&1
```

On MySQL, each batch briefly writes both tables and deletes from the live table.
Start with a smaller batch on busy installations, monitor transaction-log and
replication growth, and increase only after measuring. Back up the database
before the first production archive run. Restoring archived rows is deliberately
outside the command's scope and requires a manual SQL recovery.

## Export storage

Channel and current-user exports write to the application's `writable/cache`
directory and stream the completed file to the client in bounded chunks. Make
sure this directory is writable and has enough temporary space for the largest
expected export. Temporary files are removed at request shutdown.
