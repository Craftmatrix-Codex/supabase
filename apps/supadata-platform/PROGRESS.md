# Progress

## Current execution state

The Go rewrite remains the Supabase-compatible data plane. The Laravel application at `apps/studio-laravel` now replaces the Studio server/control-plane runtime while reusing the existing React/TanStack UI build. The previous Node Studio deployment remains the rollback path until the Laravel control-plane compatibility matrix is complete.

## Verified

- Go 1.22 module at `apps/supadata-platform`.
- Core configuration loading.
- Constant-time Bearer authentication for the control-plane API.
- Atomic durable project registry.
- Project list/current/create/select HTTP contract.
- Graceful HTTP shutdown.
- `go test ./...` passes.
- `go test -race ./...` passes.
- `go vet ./...` passes.
- Stripped Go binary builds.
- Local binary smoke test verified health, unauthorized access, project creation, selection, and current-project persistence.
- Auth password hashing and verification use bcrypt; plaintext is never stored.
- Auth signup, password token, refresh-token rotation, logout/session revocation, admin user list/delete, API-key gating, disable-signup, GoTrue health/settings response shapes, and persisted-user lookup are covered by executed Go tests.
- PostgreSQL Auth repository round-trip and refresh-token rotation passed against disposable PostgreSQL 16.
- REST has parameterized SELECT/INSERT/UPSERT/PATCH/DELETE builders, `in`/`not`/`or` filter slices, and API-key/JWT gating; handler tests passed with sqlmock and live PostgreSQL SELECT integration.
- Storage has API-key authorization, service/user mutation checks, safe bucket/object paths, bounded uploads, list/download/delete handlers, and an S3-compatible backend. A disposable SeaweedFS 3.80 put/get/list/delete round-trip passed.
- Realtime has API-key/JWT-gated `/realtime/v1/websocket` handling, Phoenix join/leave/heartbeat replies, public-topic authorization, WebSocket tests, and NGINX upgrade routing. Broadcast, presence, database changes, and exact protocol parity remain.
- Two-project isolation coverage now verifies hostname-routed Auth scopes, separate REST/RPC database connections, RLS project claims, Storage positive/cross-project reads, Realtime project topic/token boundaries, and rejection of project-bound JWTs on the wrong project.
- A real Playwright run against `https://go-alpha.craftmatrix.org/project/default` passed after the control-route and public-URL fixes: zero failed responses, zero console/page errors, visible native Studio project content, public endpoint `https://go-alpha.craftmatrix.org` (no localhost fallback), and `Advisor found no issues`.
- The unified runtime entrypoint pins native Studio to port 8082 even when a platform injects `PORT=8080`; the production image includes `curl` because Coolify's generated Docker healthcheck requires it.
- A clean BuildKit single-image build passed: native Studio Vite/TanStack build, Go test/race/vet/build stage, NGINX, and runtime entrypoint. Image size reported by Docker is 433,475,516 bytes.
- The Laravel Studio server/control-plane rewrite now lives under `apps/studio-laravel`; it uses PHP-FPM and NGINX, contains no source Blade views, and serves the existing Supabase React/TanStack client artifact built by Vite.
- Laravel feature/control-plane contracts cover authenticated SPA fallback, project/profile/bootstrap metadata, project `ref` identifiers, pg-meta query empty-state rows, content/count, Storage/vector buckets, Auth users, Edge Functions, analytics/logs, Log Drains, settings, lints, API keys, GitHub authorization, UTC time, PostgREST config/readiness, and JSON API 404s.
- Laravel regression suite passed with 16 tests and 97 assertions under alpha-style `SUPADATA_STUDIO_AUTH_USERNAME/PASSWORD` variables; the unified image build passed Go test/race/vet, React/Vite build, Composer production discovery, NGINX, and runtime health gates.
- Container Playwright smoke through NGINX/PHP-FPM passed with zero failed responses, zero console errors, zero page errors, native `Default Project | Supabase` title, and authenticated `/project/default` rendering.
- Live alpha `https://go-alpha.craftmatrix.org` was redeployed serially through Coolify; final deployment `tggljpaqxh98baormbxmnq0u` finished and application read-back was `running:healthy`.
- Final live serial Playwright matrix covered 14 key Studio routes: all returned HTTP 200, all had zero page errors, and all had zero console errors after the project-list and pg-meta response-shape fixes. Five `ERR_ABORTED` entries were requests cancelled as the test intentionally navigated away from Users/Functions; no HTTP 4xx/5xx remained.

## Active work

Continuing from the verified Auth/REST/Storage/Realtime slices into full PostgREST semantics, RPC, metadata/admin APIs, Functions/jobs, client compatibility, and production-safe deployment gates. The unified image is locally buildable and smoke-tested, but it is not yet a complete Supabase replacement.

## Required next sequence

1. Expand REST to full PostgREST semantics and request-scoped PostgreSQL roles/RLS.
2. Implement RPC and database metadata/admin contracts.
3. Expand Storage/SeaweedFS and implement Realtime/WebSocket, Functions, and jobs.
4. Run differential supabase-js/Laravel/PDO compatibility suites.
5. Run security, fuzz, failure, stress, soak, profiling, optimization, and regression suites.
6. Deploy only an isolated verified canary; preserve Stable and require public NGINX/Studio proof before any cutover.

## Resume commands

```bash
cd /root/supabase/apps/supadata-platform
go test ./...
go test -race ./...
go vet ./...
go build -trimpath -ldflags='-s -w' -o /tmp/supadata-go ./cmd/server
```

## Completion rule

Do not mark the platform complete while any required compatibility matrix entry is `NOT STARTED`, `PARTIAL`, `UNKNOWN`, `UNWIRED`, or `UNTESTED`.
