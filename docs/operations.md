# Operations

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

### Scheduling

Run from the application directory so CodeIgniter loads the intended `.env`.
For example, archive messages older than 90 days every day at 02:15:

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
