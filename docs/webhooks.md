# Outgoing webhooks

Outgoing webhooks let an authenticated user send selected chat events to an
HTTPS endpoint. Manage them in the Vue chat's **Webhooks** panel or through the
`/api/v1/webhooks` API. HTTP loopback URLs are accepted only outside production
so local integrations can be tested safely. Production HTTPS hostnames must
resolve only to public addresses when saved and again immediately before each
delivery; private, loopback, link-local, and reserved destinations are rejected.

## Events

Every request body uses this envelope:

```json
{
  "id": "32-character-delivery-event-id",
  "event": "message.created",
  "created_at": "2026-09-18T13:00:00+00:00",
  "data": {}
}
```

Supported events and their `data` objects are:

- `message.created`: `message` contains `id`, `channel_id`, `user`, `msg`, and
  Unix `time`.
- `reaction.added`: `reaction` contains `message_id`, `user_id`, and `emoji`.
- `channel.created`: `channel` contains `id`, `name`, `slug`, `channel_type`,
  nullable `topic`, and `created_by`.

Payloads larger than 100 KB are not queued. Event creation only writes a
delivery row; it never waits for the remote endpoint. Message and reaction
events are queued only for webhook owners who belong to their source channel;
this applies to both public channels and direct messages.

## Verify signatures

The creation response returns a 64-character signing secret once. Store it
securely. It is omitted from later list and update responses.

Requests include these headers:

- `X-Webhook-Delivery`: delivery row ID.
- `X-Webhook-Event`: event name.
- `X-Webhook-Timestamp`: Unix timestamp used in the signature.
- `X-Webhook-Signature`: `v1=` followed by a SHA-256 HMAC in hexadecimal.

The signed value is the timestamp, a literal period, and the exact request
body. Compare signatures with a timing-safe function and reject stale
timestamps. PHP verification looks like this:

```php
$timestamp = $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ?? '';
$provided = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
$body = file_get_contents('php://input');
$expected = 'v1=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret);

if (! hash_equals($expected, $provided) || abs(time() - (int) $timestamp) > 300) {
    http_response_code(401);
    exit;
}
```

## Deliveries and retries

Run the cron-friendly worker regularly:

```bash
php spark webhooks:deliver --limit=25
```

Any `2xx` response marks a delivery `delivered`. Network errors and other HTTP
statuses retry after 60, 120, 240, and 480 seconds. The fifth failed attempt
marks the delivery `dropped`. The worker disables redirects and uses five-second
connect and ten-second total timeouts.

Delivery history is available at
`GET /api/v1/webhooks/{webhookId}/deliveries`. A delivered or dropped row can be
reset through the Vue panel or
`POST /api/v1/webhook-deliveries/{deliveryId}/redeliver`; the next worker run
will attempt it again.

For cron, a once-per-minute entry is sufficient:

```cron
* * * * * cd /path/to/codeigniter-chat && php spark webhooks:deliver --limit=100
```

Workers claim deliveries with a five-minute lease before sending. Overlapping
cron invocations skip rows already claimed by another worker; a claim left by a
stopped worker becomes eligible again after the lease expires.
