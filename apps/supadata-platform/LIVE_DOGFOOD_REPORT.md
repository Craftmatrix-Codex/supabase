# Live Alpha Studio Dogfood Report

## Target

- Public URL: `https://go-alpha.craftmatrix.org`
- Project route: `https://go-alpha.craftmatrix.org/project/default`
- Browser: headless Chromium via Playwright
- Authentication: runtime-only HTTP Basic Auth; credentials were not written to the repository or report

## Coverage

- Same-origin routes reached: **101**
- Unique base routes excluding query variants: **55**
- Routes with no recorded problem: **37**
- Routes with one or more recorded problems: **64**
- Safe visible controls clicked: **936**
- Control probes attempted: **941**
- Destructive/state-changing controls intentionally skipped: **5**
- Evidence screenshots: **945**

The crawl followed every same-origin link exposed by the live Studio UI, including project overview, SQL Editor, Table Editor, Database, Authentication, Storage, Edge Functions, Realtime, Advisors, Observability, Logs, Integrations, and Project Settings. It also followed query-driven log explorer routes discovered from the UI.

## Confirmed application defects

### 1. Native metadata / pg-meta contract is missing

`POST /api/platform/pg-meta/default/query` returned HTTP 500 **1,335 times** during route and control navigation.

This blocks or leaves loading:

- Database schema visualizer
- Tables
- Functions
- Triggers
- Extensions
- Indexes
- Publications
- Policies
- Roles
- SQL Editor metadata and definition loading

Evidence:

- `/tmp/live-alpha-e2e-parallel/screenshots/route-2-https_go-alpha.craftmatrix.org_project_default_database_schemas.png`
- Direct probe response: `{"error":{}}`

Root cause: the Go control plane currently exposes the project/Auth/REST/Storage/Realtime slices, but not the native Studio pg-meta query contract.

### 2. Native Studio content/snippet contract is missing

`GET /api/platform/projects/default/content` returned HTTP 500 **735 times**.

Related failures:

- `/content/count` → HTTP 500; response says `SNIPPETS_MANAGEMENT_FOLDER env var is not set`
- `/content/folders` → HTTP 500

This affects SQL examples/templates and query content loading.

### 3. Logs and analytics contracts are unavailable

- `GET /api/platform/projects/default/analytics/endpoints/logs.all` → HTTP 500 **170 times**
- `GET /api/platform/projects/default/analytics/log-drains` → HTTP 500 **12 times**

Direct response for log drains:

```json
{"error":{"message":"LOGFLARE_PRIVATE_ACCESS_TOKEN, LOGFLARE_URL env variables are not set"}}
```

Visible UI evidence:

- Log Drains displays “Failed to load log drains — Error: API error happened while trying to communicate with the server.”
- `/tmp/live-alpha-e2e-parallel/screenshots/route-35-https_go-alpha.craftmatrix.org_project_default_settings_log-drains.png`

### 4. Edge Functions backend contract is unavailable

`GET /api/v1/projects/default/functions` returned HTTP 500 **18 times**.

Direct response contains an assertion failure (`ERR_ASSERTION`). The page remains on skeleton loaders instead of showing a clean unavailable/empty state.

Evidence:

- `/tmp/live-alpha-e2e-parallel/screenshots/route-6-https_go-alpha.craftmatrix.org_project_default_functions.png`

### 5. Storage management contracts are unavailable

- `GET /api/platform/storage/default/buckets` → HTTP 500 **19 times**
- `GET /api/platform/storage/default/vector-buckets` → HTTP 500 **5 times**

Direct responses were generic `{"error":{}}`. This prevents native Storage bucket management from loading even though the Supabase-compatible Storage API slice exists separately.

### 6. Client-side runtime failures occur on unsupported/partial routes

The browser recorded **431 page errors**, including:

```text
TypeError: Cannot read properties of undefined (reading 'component')
```

This occurred while the Studio route loader was resolving unsupported/partial route data. The browser also recorded React minified error #306 and a controlled/uncontrolled Tabs warning.

### 7. Monaco editor asset is incomplete

The browser recorded failures for:

```text
/monaco-editor/vs/editor/editor.main.css
```

Native Monaco reports:

```text
Loading "vs/css!vs/editor/editor.main" failed
Could not find /monaco-editor/vs/editor/editor.main.css.
```

This affects SQL/log/query editor surfaces.

## Events not classified as product defects

The parallel crawler also observed `ERR_ABORTED`, `ERR_CONNECTION_CLOSED`, `ERR_QUIC_PROTOCOL_ERROR`, and `ERR_FAILED` events. These were heavily concentrated in requests cancelled when an isolated probe closed its page or when many probes loaded the same large Studio chunks concurrently. They remain in the raw JSON for auditability but are not counted as confirmed backend defects above.

## Intentionally skipped controls

The harness did not mutate the live alpha project. It skipped:

- `New table`
- `Create a new query` on SQL routes

The raw report contains the exact route/control list and all screenshots:

- Raw report: `/tmp/live-alpha-e2e-parallel/report.json`
- Screenshots: `/tmp/live-alpha-e2e-parallel/screenshots/`
- Reusable harness: `e2e/studio/live-exhaustive-parallel.cjs`

## Acceptance result

**Failed.** The public native Studio shell and navigation are reachable, but the live alpha is not yet a clean native Supabase Studio deployment. The main blockers are missing pg-meta, content/snippets, logs/analytics, Storage management, Edge Functions, and Monaco compatibility contracts.
