# Testing

The test suite has three feedback tiers. Choose the narrowest tier that can prove the behavior.

## Unit and feature tests

Unit tests cover one class or function in isolation. Feature tests exercise the HTTP pipeline while replacing persistence or other boundaries with test doubles. Both belong in the fast default suite:

```shell
composer test
```

The default suite is expected to finish in under five seconds and runs on every push. You can target a smaller area with `composer test:unit`, `composer test:database`, or `composer test:feature`.

## Database integration tests

Integration tests belong in `tests/Tests/Integration/` and use `Tests\Support\IntegrationTestCase`. The base class applies the application migrations to a fresh SQLite `:memory:` database for every test and resets application services so controllers resolve the real repositories.

Write an integration test when the behavior depends on SQL, migrations, model validation, real password hashes, sessions, or response formatting from persisted rows. Run this tier with:

```shell
composer test:integration
```

Pull requests run both the fast and integration tiers. `composer test:all` runs both locally while excluding the WebSocket-specific test.

## WebSocket integration test

The WebSocket flow uses Ratchet in process: it authenticates connection objects with real issued tokens, writes through the real `ChatModel`, and verifies broadcasts without opening a network port. This avoids background-process and port-allocation failures in routine CI while retaining coverage of the application-level token-to-broadcast flow.

Run it explicitly with:

```shell
composer test:integration:websocket
```

Keep actual browser and network-server end-to-end coverage separate from this suite.

## Browser end-to-end tests

Playwright covers the XML, JSON, HTML, Vue, and Svelte chat implementations in Chromium. Each scenario signs in through the real login page, posts a message, verifies that the message appears, checks the frontend's network payload when applicable, fails on browser errors, and runs axe against serious and critical accessibility violations.

Install the PHP and JavaScript dependencies, then install the Playwright browser once:

```shell
composer install
pnpm install
pnpm exec playwright install chromium
```

Run the complete browser suite with:

```shell
pnpm e2e
```

The harness starts the CodeIgniter HTTP server, Vite development server, and WebSocket server automatically. It migrates and seeds an isolated SQLite database and uses a dedicated cache directory, then removes those test artifacts and stops every process during teardown. The fixed `e2euser` credentials exist only in this isolated test database.

Use `pnpm e2e:ui` for Playwright's interactive runner. `pnpm e2e:codegen` opens the application for locator exploration after the stack is running. Traces, screenshots, and video are retained for failures in `test-results/`; the HTML report is written to `playwright-report/` in CI and uploaded when a pull-request run fails.

Shared behavior belongs in `e2e/support/chat-scenarios.ts`; keep one small spec in `e2e/` for each frontend route. Pull requests run the suite through `.github/workflows/e2e.yml`.
