# Security

The authoritative security model is in `ARCHITECTURE.md`. Acceptance tests cover tenant isolation, RBAC, token/log redaction, refresh reuse, webhook signature/deduplication, queue/send idempotency, opt-out and template/sender gates, schedule uniqueness, upload validation, injection and export authorization. Never commit environments, logs, imports, exports, media, backups or credentials.

## Dependency advisory register

On 2026-08-01, npm reports GHSA-qwww-vcr4-c8h2 against React Router 7.18.2 with no patched registry release available. The affected behavior is React Server Components/server-action processing; WhatstheUp is a client-only Vite SPA and does not enable RSC, framework mode, loaders/actions, or a React Router server. This removes the vulnerable execution path, but the advisory remains tracked and the dependency must be upgraded immediately when an upstream patched release is published.
