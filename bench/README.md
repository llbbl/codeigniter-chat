# Local performance benchmarks

These k6 scenarios exercise the application through its public HTTP and WebSocket interfaces. They are intentionally local-only and never run in CI.

## Prerequisites

- PHP and project dependencies from `composer install`
- [k6](https://grafana.com/docs/k6/latest/set-up/install-k6/)
- SQLite support, or a configured MySQL database
- The application migrations applied to the benchmark database

Prepare a reproducible dataset. This replaces messages owned by the reserved `k6-user-*` accounts and creates 100 local benchmark users with password `Benchmark123!`:

```shell
DB_DRIVER=SQLite3 php spark migrate --all
BENCHMARK_MODE=true DB_DRIVER=SQLite3 php spark seed:messages 10000
```

The seed command refuses to run in production or without `BENCHMARK_MODE=true` because it creates known local-only credentials and replaces messages owned by the reserved accounts.

Start the HTTP server with multiple workers and the local-only benchmark rate-limit profile. `BENCHMARK_MODE` is ignored in production:

```shell
CI_ENVIRONMENT=development PHP_CLI_SERVER_WORKERS=8 BENCHMARK_MODE=true DB_DRIVER=SQLite3 app_baseURL=http://127.0.0.1:8080/ app_CSPEnabled=false cookie_secure=false php spark serve --host 127.0.0.1 --port 8080
```

For the WebSocket fanout scenario, start the WebSocket server in another terminal:

```shell
DB_DRIVER=SQLite3 php spark chat:websocket --port 8081
```

Keep the WebSocket server stopped while measuring message posts. Post requests attempt a best-effort broadcast, so running the standalone server would mix WebSocket handshake behavior into the HTTP persistence measurement. The dedicated fanout scenario measures WebSocket delivery separately.

## Run scenarios

From the project root:

```shell
pnpm bench:post
pnpm bench:read
pnpm bench:ws
```

Override endpoints or the deterministic password when needed:

```shell
BASE_URL=http://127.0.0.1:8080 WS_URL=ws://127.0.0.1:8081 BENCH_PASSWORD='Benchmark123!' pnpm bench:ws
```

The post scenario runs 10 virtual users for 30 seconds and expects p95 message-post latency below 200 ms. The read scenario runs 50 polling users for 30 seconds and expects p99 read latency below 200 ms. The fanout scenario connects 100 users, lets every client broadcast once, and expects p95 delivery below 500 ms.

Treat thresholds as local smoke targets, not universal service-level objectives. Compare results only on a similar machine, database, dataset, and server configuration. A failed check usually indicates authentication, CSRF, rate-limit, or server setup trouble; a failed duration threshold indicates a performance result worth investigating.
