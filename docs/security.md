# Security operations

## Tightening the Content Security Policy

The current policy permits `unsafe-inline` for scripts and styles because several views still contain inline assets. To remove it safely, first move those assets into bundled files. In a feature branch, set `reportOnly = true`, deploy for at least a week, and monitor `/admin/csp-reports` for legitimate violations. Fix those violations, then restore enforcing mode and remove `unsafe-inline`.

CSP reports are retained for 30 days; the report endpoint prunes older rows when new reports arrive.
