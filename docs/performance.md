# Performance baseline

The k6 harness in `bench/` provides local comparison points for performance-sensitive changes. These numbers are not production capacity claims or CI gates.

## Baseline environment

- Application revision: `12b314cfd871c2380a725398802e551ac21e26c8`
- Date: September 13, 2026
- Machine: Mac Studio, Apple M1 Ultra (20 cores), 64 GB memory
- Runtime: macOS 26.6.2, PHP 8.5.7, k6 2.2.0, SQLite 3.51.0
- Server: PHP development server with 8 workers; Ratchet WebSocket server used only for the fanout scenario
- Dataset: 10,000 deterministic messages and 100 benchmark users

The application revision identifies the endpoint and persistence code being measured. The benchmark harness itself was the issue #119 working tree based on that revision.

## Results

| Scenario | Load | Throughput | Latency | Failures |
| --- | --- | ---: | ---: | ---: |
| Message posts | 10 VUs for 30s | 17.94 iterations/s | p95 72.51 ms | 0/547 |
| Message reads | 50 VUs for 30s | 89.56 iterations/s | p99 94.15 ms | 0/2,752 |
| WebSocket fanout | 100 simultaneous clients | 100/100 deliveries | p95 95.05 ms | 0/100 |

The WebSocket run established all 100 connections, sent one marked message per client, and observed 5,050 total received frames while measuring each sender's own broadcast round trip. Connection p95 was 40.28 ms.

## Comparing later runs

Re-run the same scenario on comparable hardware with the same dataset and server settings. Compare the custom duration and failure metrics, not the login/setup HTTP totals. Record a new baseline only for intentional performance work, and explain material configuration or environment differences alongside the numbers.
