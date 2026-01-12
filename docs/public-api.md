# Public API Reference (Backend + WebSocket + Frontend)

This document describes the **public surface area** of this repository:

- **HTTP endpoints** (CodeIgniter routes/controllers)
- **WebSocket protocol** used for realtime chat
- **Frontend entrypoints/components** (jQuery XML/JSON/HTML + Vue)
- **Reusable PHP APIs** (Helpers, Libraries, Models) intended to be called by app code

If you are looking for project setup, see `README.md` and `docs/setup-guide.md`.

---

## Base URL

All HTTP endpoints are relative to your configured base URL (see `.env` / `app/Config/App.php`).

Examples below assume:

- `BASE_URL=http://localhost:8080`
- The WebSocket server defaults to **port 8080**. If your HTTP app is also running on 8080 (common in dev), you must run the WebSocket server on a different port (e.g. 8081) and update the frontend to match.

---

## Authentication & Sessions

### Session cookie

Authentication is session-based. After successful login, the app sets a session cookie:

- **Cookie name**: `ci_chat_session` (see `app/Config/Session.php`)

Most `chat/*` routes are protected by the `auth` filter:

- **Protected paths**: `chat`, `chat/*` (see `app/Config/Filters.php`)

### CSRF protection (required for POST)

CSRF is enabled globally (see `app/Config/Filters.php`) and uses **cookie-based CSRF**:

- **CSRF token name**: `csrf_test_name`
- **CSRF header**: `X-CSRF-TOKEN`
- **CSRF cookie name**: `csrf_cookie_name`
- **Regeneration**: enabled (token rotates after successful submissions)

See `app/Config/Security.php`.

#### Programmatic clients (curl) recipe

1) **Fetch a page** to obtain CSRF cookie + token (pages include `<meta name="csrf-token" ...>`):

```bash
BASE_URL="http://localhost:8080"

# Store cookies in a jar
curl -sS -c cookies.txt "$BASE_URL/auth/login" > /tmp/login.html

# Extract token from the HTML meta tag
CSRF="$(python - <<'PY'
import re
html=open("/tmp/login.html","r",encoding="utf-8").read()
m=re.search(r'<meta name="csrf-token" content="([^"]+)"', html)
print(m.group(1) if m else "")
PY
)"
echo "CSRF=$CSRF"
```

2) **POST with cookies + CSRF**:

```bash
curl -sS -b cookies.txt -c cookies.txt \
  -H "X-CSRF-TOKEN: $CSRF" \
  -d "csrf_test_name=$CSRF" \
  -d "username=demo" \
  -d "password=secret" \
  "$BASE_URL/auth/processLogin" \
  -i
```

Notes:

- Include CSRF either as the `X-CSRF-TOKEN` header or as form field `csrf_test_name`. Including both is fine.
- Because CSRF regenerates on POST, you may need to re-fetch a fresh token before the next POST.

---

## HTTP API (Routes)

Routes are defined in `app/Config/Routes.php`.

### `GET /`

- **Controller**: `Home::index`
- **Auth**: redirects to `/auth/login` if not logged in
- **Response**: HTML landing page with links to implementations

### Authentication

#### `GET /auth/register`

- **Controller**: `Auth::register`
- **Response**: HTML registration form

#### `POST /auth/processRegistration`

- **Controller**: `Auth::processRegistration`
- **Consumes**: `application/x-www-form-urlencoded` (HTML form)
- **CSRF**: required
- **Body fields**:
  - `username` (required, 3..50, alpha_numeric, unique)
  - `email` (required, valid_email, unique)
  - `password` (required, min 8)
  - `password_confirm` (required, matches password)
- **Success**: redirect to `/auth/login` with flash message
- **Failure**:
  - HTML: redirect back with errors
  - AJAX/JSON Accept: JSON error object (see **Error format** below)

Example:

```bash
curl -sS -b cookies.txt -c cookies.txt \
  -H "X-CSRF-TOKEN: $CSRF" \
  -d "csrf_test_name=$CSRF" \
  -d "username=demo" \
  -d "email=demo@example.com" \
  -d "password=secret123" \
  -d "password_confirm=secret123" \
  "$BASE_URL/auth/processRegistration" \
  -i
```

#### `GET /auth/login`

- **Controller**: `Auth::login`
- **Response**: HTML login form

#### `POST /auth/processLogin`

- **Controller**: `Auth::processLogin`
- **Consumes**: `application/x-www-form-urlencoded`
- **CSRF**: required
- **Body fields**:
  - `username` (required)
  - `password` (required)
- **Success**: sets session (`ci_chat_session`) and redirects to `/chat`
- **Failure**: redirect back with flash error, or JSON error for AJAX/JSON Accept

#### `GET /auth/logout`

- **Controller**: `Auth::logout`
- **Auth**: required (practically)
- **Success**: clears session and redirects to `/auth/login`

### Chat (UI Pages)

These endpoints return HTML pages that load the appropriate frontend bundle.

#### `GET /chat`

- **Controller**: `Chat::index`
- **Auth**: required (filter)
- **Response**: HTML page for the **XML (jQuery)** implementation

#### `GET /chat/json`

- **Controller**: `Chat::json`
- **Auth**: required (filter)
- **Response**: HTML page for the **JSON (jQuery)** implementation

#### `GET /chat/html`

- **Controller**: `Chat::html`
- **Auth**: required (filter)
- **Response**: HTML page for the **traditional HTML form** implementation

#### `GET /chat/vue`

- **Controller**: `Chat::vue`
- **Auth**: required (filter + explicit redirect in controller)
- **Response**: HTML page for the **Vue** implementation

### Chat (Backend Data Endpoints)

#### Pagination parameters (common)

All message-list endpoints accept:

- `page` (optional, default 1)
- `per_page` (optional, default 10)

Returned pagination object uses:

- `page`, `perPage`, `totalItems`, `totalPages`, `hasNext`, `hasPrev`

#### `GET /chat/backend` (XML)

- **Controller**: `Chat::backend`
- **Auth**: required (filter)
- **Response**: `text/xml`

Response shape is generated by `App\Helpers\ChatHelper::formatAsXml()`.

Example:

```bash
curl -sS -b cookies.txt "$BASE_URL/chat/backend?page=1&per_page=10"
```

#### `GET /chat/jsonBackend` (JSON)

- **Controller**: `Chat::jsonBackend`
- **Auth**: required (filter)
- **Response**: JSON

Response shape is generated by `App\Helpers\ChatHelper::formatAsJson()`:

```json
{
  "messages": [
    { "id": 123, "user": "alice", "msg": "Hello", "time": 1700000000 }
  ],
  "status": 1,
  "time": 1700001234,
  "pagination": {
    "page": 1,
    "perPage": 10,
    "totalItems": 42,
    "totalPages": 5,
    "hasNext": true,
    "hasPrev": false
  }
}
```

Example:

```bash
curl -sS -b cookies.txt "$BASE_URL/chat/jsonBackend?page=1&per_page=10" | python -m json.tool
```

#### `GET /chat/htmlBackend` (HTML snippet)

- **Controller**: `Chat::htmlBackend`
- **Auth**: required (filter)
- **Response**: HTML snippet (server-rendered)
- **Intended use**: rendered inside `/chat/html`

Example:

```bash
curl -sS -b cookies.txt "$BASE_URL/chat/htmlBackend?page=1&per_page=10"
```

#### `GET /chat/vueApi` (JSON)

- **Controller**: `Chat::vueApi`
- **Auth**: required (filter)
- **Response**: JSON (delegates to `jsonBackend`)

Example:

```bash
curl -sS -b cookies.txt "$BASE_URL/chat/vueApi?page=1&per_page=10" | python -m json.tool
```

#### `POST /chat/update` (post a message)

- **Controller**: `Chat::update`
- **Auth**: required (filter + controller checks session username)
- **CSRF**: required
- **Body fields**:
  - `message` (required, 1..500 chars)
  - `action` (historical; the UI sends `postmsg`)
  - `html_redirect` (optional): if `"true"`, redirects to `/chat/html` after posting
- **Response**:
  - AJAX (`X-Requested-With: XMLHttpRequest`): JSON `{ "success": true }` on success, or JSON error object
  - HTML form: redirect (if `html_redirect=true`), or empty body otherwise

Important notes:

- The server uses the **logged-in session username** (`session()->get('username')`) as the message author.
- The message list is stored with fields `user`, `msg`, `time` in the DB.

Example (AJAX-style):

```bash
curl -sS -b cookies.txt -c cookies.txt \
  -H "X-Requested-With: XMLHttpRequest" \
  -H "X-CSRF-TOKEN: $CSRF" \
  -d "csrf_test_name=$CSRF" \
  -d "action=postmsg" \
  -d "message=Hello from curl" \
  "$BASE_URL/chat/update" | python -m json.tool
```

### CSP violation reporting

#### `POST /csp-report`

- **Controller**: `CspReport::index`
- **Consumes**: JSON
- **Response**: `204 No Content` on valid report, `400` JSON error otherwise

Request shape:

```json
{
  "csp-report": {
    "document-uri": "https://example.test/",
    "violated-directive": "script-src",
    "blocked-uri": "https://evil.example/"
  }
}
```

Example:

```bash
curl -sS -b cookies.txt -c cookies.txt \
  -H "Content-Type: application/json" \
  -d '{"csp-report":{"document-uri":"https://example.test/","violated-directive":"script-src","blocked-uri":"https://evil.example/"}}' \
  "$BASE_URL/csp-report" -i
```

---

## Error format (JSON)

Most controllers use `App\Libraries\ErrorHandler` via `BaseController`.

For AJAX requests or clients sending `Accept: application/json`, errors are returned as:

```json
{
  "success": false,
  "type": "validation|database|authentication|authorization|not_found|server",
  "message": "Human-readable message",
  "errors": { "field": "details" }
}
```

For normal HTML form submissions, the app redirects back and sets flashdata instead.

---

## Rate limiting

`RateLimitFilter` applies to:

- `chat/update`
- `chat/backend`
- `chat/jsonBackend`
- `chat/htmlBackend`

Default settings:

- **10 requests per 60 seconds** per identifier (logged-in `user_id` if present, otherwise IP)

When exceeded:

- **HTTP 429** with plain-text body: `Too many requests. Please try again later.`

See `app/Filters/RateLimitFilter.php`.

---

## WebSocket API (Realtime Chat)

### Start the server

The WebSocket server is started via a CodeIgniter command:

```bash
php spark chat:websocket --port 8080
```

See `app/Commands/ChatWebSocketServer.php`.

### Connect

Default URL (if you run it locally on port 8080):

- `ws://localhost:8080`

The Vue client chooses `ws:` vs `wss:` based on the page protocol and uses port `8080` (see `src/vue/App.vue`).

### Protocol (messages)

All WebSocket messages are JSON with an `action` field.

#### Client → server: `getMessages`

```json
{ "action": "getMessages", "page": 1, "perPage": 10 }
```

#### Server → client: `messages`

```json
{
  "action": "messages",
  "data": {
    "messages": [{ "id": 1, "user": "alice", "msg": "Hello", "time": 1700000000 }],
    "pagination": { "page": 1, "perPage": 10, "totalItems": 42, "totalPages": 5, "hasNext": true, "hasPrev": false }
  }
}
```

#### Client → server: `sendMessage`

```json
{ "action": "sendMessage", "username": "alice", "message": "Hello" }
```

#### Server → client: `newMessage`

```json
{
  "action": "newMessage",
  "data": { "user": "alice", "msg": "Hello", "timestamp": 1700000000 }
}
```

### Browser example

```js
const ws = new WebSocket("ws://localhost:8080");
ws.onmessage = (e) => console.log("recv", JSON.parse(e.data));
ws.onopen = () => {
  ws.send(JSON.stringify({ action: "getMessages", page: 1, perPage: 10 }));
  ws.send(JSON.stringify({ action: "sendMessage", username: "alice", message: "Hello via WS" }));
};
```

---

## Frontend entrypoints (Vite)

Vite entrypoints are defined in `vite.config.js` and built into `public/dist/` with a manifest:

```bash
npm install
npm run build
```

The views load bundles using `vite_tags()` from `app/Helpers/vite_helper.php`.

### XML (jQuery) — `src/js/chat.js`

- **Page**: `/chat` (`app/Views/chat/chatView.php`)
- **Globals provided by the page**:
  - `CHAT_ROUTES.update`
  - `CHAT_ROUTES.backend`
  - `CSRF_TOKEN_NAME`
  - `CURRENT_USERNAME`
  - `<meta name="csrf-token" content="...">`
- **Main behaviors**:
  - Loads messages from `/chat/backend?page=...`
  - Posts to `/chat/update` with CSRF and `action=postmsg`
  - Adds a “Load More Messages” button (pagination)

### JSON (jQuery) — `src/js/chat-json.js`

- **Page**: `/chat/json` (`app/Views/chat/jsonView.php`)
- **Globals**:
  - `CHAT_ROUTES.update`
  - `CHAT_ROUTES.jsonBackend`
  - `CSRF_TOKEN_NAME`
  - `<meta name="csrf-token" content="...">`
- **Main behaviors**:
  - Loads messages from `/chat/jsonBackend?page=...`
  - Posts to `/chat/update`

### HTML (no AJAX) — `src/js/chat-html.js`

- **Page**: `/chat/html` (`app/Views/chat/htmlView.php`)
- **Behavior**: client-side form validation only; server posting is plain form POST to `/chat/update` with `html_redirect=true`.

### Vue — `src/vue/main.js` + `src/vue/App.vue`

- **Page**: `/chat/vue` (`app/Views/chat/vueView.php`)
- **Globals**:
  - `window.CHAT_ROUTES.update`
  - `window.CHAT_ROUTES.vueApi`
  - `window.CSRF_TOKEN_NAME`
  - `window.CURRENT_USERNAME`
  - `<meta name="csrf-token" content="...">`
- **Realtime**:
  - Connects to WebSocket on port 8080 for realtime updates
  - Falls back to HTTP (`/chat/vueApi`) if WebSocket is not connected

---

## PHP APIs (Helpers, Libraries, Models)

These classes are part of the application’s reusable API surface for app code.

### `App\Helpers\ChatHelper`

- `validateMessage(array $data): array|bool`
- `formatAsXml(array $messages, ?array $pagination = null): string`
- `formatAsJson(array $messages, ?array $pagination = null): array`

### `App\Models\ChatModel`

- `getMsgPaginated(int $page = 1, int $perPage = 10): array`
- `getMsg(int $limit = 10): array` (compat)
- `insertMsg(string $name, string $message, int $current): int|bool`
- `getMsgByUserPaginated(string $username, int $page = 1, int $perPage = 10): array`
- `getMsgByUser(string $username, int $limit = 10): array` (compat)
- `getMsgByTimeRangePaginated(int $startTime, int $endTime, int $page = 1, int $perPage = 10): array`
- `getMsgByTimeRange(int $startTime, int $endTime, int $limit = 10): array` (compat)

### `App\Models\UserModel`

- `findUserByUsername(string $username): ?array`
- `findUserByEmail(string $email): ?array`
- `createUser(string $username, string $email, string $password): int|false`
- `verifyCredentials(string $username, string $password): ?array`

### `App\Libraries\ErrorHandler`

- `handleError(string $type, string $message, array $errors = [], int $statusCode = 400, string $logLevel = ErrorHandler::LOG_LEVEL_ERROR, bool $logError = true)`
- `handleException(Throwable $exception, string $type = ErrorHandler::ERROR_TYPE_SERVER, int $statusCode = 500, string $logLevel = ErrorHandler::LOG_LEVEL_ERROR, bool $logError = true)`

### `App\Libraries\ChatWebSocketServer`

Ratchet message component implementing:

- `onOpen(ConnectionInterface $conn)`
- `onMessage(ConnectionInterface $from, string $msg): void`
- `onClose(ConnectionInterface $conn)`
- `onError(ConnectionInterface $conn, \Exception $e)`

### `App\Libraries\WebSocketClient`

- `__construct(string $host = 'localhost', int $port = 8080)`
- `send(array $data): bool`

### Other helpers

- `App\Helpers\DateTimeHelper` (timestamp formatting utilities)
- `App\Helpers\FileHelper` (filesystem utilities)
- `App\Helpers\ResponseHelper` (standard response payloads)
- `App\Helpers\SecurityHelper` (CSRF/token/password utilities)
- `App\Helpers\TextHelper` (truncate/title-case)
- `App\Helpers\UserHelper` (auth validation + session helpers)
- `App\Helpers\ValidationHelper` (common validation rules)
- `vite_*()` helper functions in `app/Helpers/vite_helper.php`

