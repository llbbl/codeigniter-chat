# Message Reaction API

All reaction endpoints require an authenticated session. Write requests require
the normal CSRF token and use the `react` rate-limit profile (120 requests per
minute for authenticated users).

## Endpoints

### `GET /api/v1/messages/{messageId}/reactions`

Returns a JSON array grouped by emoji:

```json
[{"emoji":"👍","count":2,"users":["alice","bob"],"reacted_by_current_user":true}]
```

### `POST /api/v1/messages/{messageId}/reactions`

Send JSON such as `{"emoji":"👍"}`. The emoji must contain an emoji code
point, contain no whitespace or control characters, and be at most 10 Unicode
characters (64 UTF-8 bytes). The response is `201 Created` with the current
aggregate state. Repeating the same request is idempotent because storage has a
unique `(message_id, user_id, emoji)` constraint.

### `DELETE /api/v1/messages/{messageId}/reactions/{emoji}`

URL-encode the emoji path segment. Only the authenticated user's matching
reaction is removed. The response is `204 No Content`, including when that
reaction was already absent.

Unknown message IDs return `404`; invalid emoji return `422`.

## WebSocket events

Authenticated clients add or remove their own reaction with:

```json
{"type":"reaction_add","message_id":42,"emoji":"👍"}
```

```json
{"type":"reaction_remove","message_id":42,"emoji":"👍"}
```

The server derives the user from the authenticated connection, applies the same
120-per-minute limit, persists the change, and broadcasts the complete current
state for that emoji:

```json
{"type":"reaction","message_id":42,"emoji":"👍","count":2,"users":["alice","bob"]}
```

The Vue and Svelte clients use WebSockets while connected and the HTTP API as a
fallback. Both provide the same eight-emoji accessible picker and togglable
reaction badges.
