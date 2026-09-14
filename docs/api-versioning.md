# API Versioning Policy

Machine-consumed HTTP endpoints live under `/api/v{major}/`. Version 1 starts
with the message endpoints at `/api/v1/messages`.

## Compatibility rules

- Breaking response, request, authentication, or behavior changes require a new
  major path such as `/api/v2/`.
- Additive changes, including new optional fields, remain within the current
  path version.
- A superseded endpoint remains available for at least six months. During that
  window it returns `Deprecation`, `Sunset`, and a `Link` with
  `rel="successor-version"`.
- HTML page routes are not versioned. Only stable machine contracts belong
  under `/api/`.

## Version 1 messages

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/api/v1/messages` | Paginated JSON messages |
| `POST` | `/api/v1/messages` | Create a message |
| `GET` | `/api/v1/messages/search` | Search messages by text, exact user, or Unix time range |
| `GET` | `/api/v1/messages/xml` | Paginated XML messages |

All endpoints use the existing session authentication and CSRF rules. The
canonical payload and error envelopes are documented in
[`public-api.md`](public-api.md).

## Current deprecations

`/chat/jsonBackend`, `/chat/vueApi`, and `/chat/svelteApi` are compatibility
aliases for `GET /api/v1/messages`. They sunset on July 1, 2027 and include:

```http
Deprecation: true
Sunset: Thu, 01 Jul 2027 00:00:00 GMT
Link: </api/v1/messages>; rel="successor-version"
```

The HTML routes `/chat/json`, `/chat/vue`, and `/chat/svelte` remain supported.
