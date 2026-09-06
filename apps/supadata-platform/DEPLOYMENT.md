# Deployment

The current Node control plane and native Studio deployment remain the rollback path. The Go platform will first run as an isolated build and local integration target.

The production target is one reproducible multi-stage Docker build containing:

- Studio frontend build artifacts.
- A statically linked Go platform binary.
- Minimal NGINX runtime configuration.
- No Node or Go toolchain in the final runtime image.

Deployment is allowed only after the compatibility, security, Docker, and live verification gates are recorded.

## Required project database configuration

The Go binary fails closed when a registry contains projects without database bindings.

- `SUPADATA_REQUIRE_PROJECT_SCOPE=true` is the secure default and should remain enabled.
- `SUPADATA_DATABASE_MODE=per-project` selects isolated database connections.
- `SUPADATA_PROJECT_DATABASE_URLS` is a JSON object keyed by project ID, for example `{ "alpha": "[REDACTED]", "beta": "[REDACTED]" }`. Values must be supplied through the deployment secret manager and must not be committed.
- `SUPADATA_DATABASE_MODE=shared` is supported for controlled testing and maps every registered project to `DATABASE_URL`; it does not provide database-level isolation.
- The current runtime verifies and pings every configured project database at startup, registers it in the request router, and refuses startup when a binding is missing or unreachable.

Dynamic PostgreSQL database/role creation is not enabled. New per-project resources require an external provisioning workflow that can issue runtime credentials through a secret manager; the server intentionally does not generate or persist those credentials in the registry.