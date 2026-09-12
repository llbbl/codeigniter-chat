# Security headers

`App\\Filters\\SecurityHeadersFilter` adds response-hardening headers after every request. It only sets a header when the response does not already provide one, so controllers and framework filters can make a deliberate exception without being overwritten.

## Environment policies

Production responses include HSTS, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Opener-Policy`, and `Cross-Origin-Resource-Policy`.

Development and testing use the same non-HSTS protections. HSTS is intentionally omitted so a browser cannot cache an HTTPS-only rule for a local HTTP hostname.

The policies are configured in `app/Config/SecurityHeaders.php`.

## Route exceptions

The named `frame-relaxed` override changes `X-Frame-Options` to `SAMEORIGIN` for the rare route that must be embedded by a same-origin page. Attach it with the `securityHeaders:frame-relaxed` filter alias; do not use it for cross-origin embedding.

## CSP reporting

`SecurityHeaders::$reportTo` is `null` by default. Set it to a Reporting API object with an absolute HTTPS endpoint when the CSP reporting endpoint is ready. The filter serializes it into the legacy-compatible `Report-To` header without hard-coding deployment-specific URLs.
