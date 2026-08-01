# WhatstheUp first-release architecture

## Product boundary

WhatstheUp is a modular monolith with a React SPA, a PHP 8.3 REST API, MySQL as the system of record, protected local storage, and short-lived cron workers. The first release completes authentication, onboarding, official Meta connection, contacts/import/consent, templates, campaign creation/testing/sending/scheduling, queue processing, webhooks, reporting, settings, focused Super Admin, audit and health.

Postponed: reseller UI/custom domains, automated billing, shared inbox, AI/chatbots, automation builder, public API, WhatsApp Flows builder, advanced analytics/CMS/roles, and WordPress/WooCommerce/Shopify/Odoo integrations. Ownership columns and interfaces prepare for these without fake screens.

## Tenancy and roles

Users are identities; `business_users` are memberships. Middleware resolves an active business membership after authentication and ignores browser-provided `business_id`. Every business aggregate, query, unique rule, index and storage path is tenant-scoped. A future reseller may own businesses. Platform-scope Super Admin access and impersonation are separate and always audited with a reason.

`user_platform_roles` assigns platform roles without a customer membership. A platform login receives a `platform` scope and no `business` object; a business login receives a `business` scope and one server-resolved workspace. The SPA routes these identities into separate protected shells.

| Capability | Super Admin | Owner | Admin | Campaign Manager | Viewer |
|---|---:|---:|---:|---:|---:|
| Platform administration | Yes | No | No | No | No |
| Dashboard/reports | Yes | Yes | Yes | Yes | View |
| Contacts/imports | Yes | Yes | Yes | Yes | View |
| Templates | Yes | Yes | Yes | Yes | View |
| Campaign actions | Yes | Yes | Yes | Yes | View |
| Settings/team/audit | Yes | Yes | Yes | No | No |

Permissions use the exact granular keys in the product brief and are enforced by PHP policies. Frontend checks are display-only.

## Data and relationships

Tables are grouped as follows:

- Core: users, sessions, verification/reset tokens, businesses/memberships, roles/permissions, audits/settings.
- Reseller foundation: resellers, users, branding and domains.
- Plans: plans, features, subscriptions and usage.
- Meta: connections, WABAs, phone numbers, encrypted tokens, subscriptions/events/API logs.
- Contacts: contacts, groups/tags, custom fields/values, consents, suppressions and import rows.
- Templates: library/versioning, customer templates/versions, variables/buttons/status history.
- Campaign/messaging: campaigns, audience, recipients, mappings/media/events/statistics, messages/status/errors.
- Operations: queue/failed/scheduled jobs, notifications and security logs.

A business owns all operational aggregates. Meta connection → WABA → phone number/template. Contact → consent history and many-to-many groups/tags. Campaign → approved template/sender → immutable expanded recipients → messages → status/error history → aggregate statistics. Webhook, queue and audit records retain external idempotency/correlation keys. Foreign keys, soft deletion, tenant/status/schedule indexes, unique Meta event/message IDs and append-only histories protect integrity.

## Routes

Frontend public routes: `/login`, `/verify-email`, `/forgot-password`, `/reset-password`. Business: `/dashboard`, `/campaigns`, `/campaigns/new`, `/campaigns/:id`, `/templates`, `/contacts`, `/reports`, `/settings`, `/meta`. Admin: `/admin` plus businesses, users, plans, Meta assets, templates, campaigns, messages, imports, webhooks, queue, audits, health and settings. Only working milestone routes are exposed.

API prefix is `/api/v1`. Auth exposes login, refresh, logout, logout-all, current user, sessions, verification and reset. Business resources expose dashboard, contacts/groups/tags/imports, templates, campaigns, reports, settings, connections and phones with explicit lifecycle actions. `/api/v1/admin/*` is platform-scoped. `GET|POST /webhooks/meta` uses separate verification/HMAC controls.

## Security

Passwords use Argon2id. Access JWTs expire quickly and live only in JS memory. Opaque refresh tokens are hashed, family-tracked and rotated in Secure HttpOnly cookies; reuse revokes the family. Authentication uses generic errors, IP/identifier throttling and lockout. Other controls include strict origin allowlists/preflight, server-derived tenancy, data-driven RBAC, prepared PDO, input validation, encrypted Meta credentials, log redaction, protected MIME/size-checked uploads, random filenames, webhook HMAC/deduplication, idempotent workers, immutable audits, security headers and least-privilege accounts.

## Meta dependencies and risks

Only the official WhatsApp Business Platform Cloud API is permitted. The August 2026 target is Graph API `v25.0`, always read from `META_GRAPH_API_VERSION` and revalidated in Meta's official changelog/dashboard before launch. Embedded Signup requires a Meta Business app/configuration, domains/redirect URI, business portfolio, WABA and eligible phone. Confirm `whatsapp_business_management`, `whatsapp_business_messaging`, and only flow-required `business_management`, business verification, App Review/advanced access, live webhook and production token strategy.

Template categories are Marketing, Utility and Authentication. Component support, messaging-window rules, number registration, pricing and throughput must be checked for the configured version/account. Business verification, permission review, Embedded Signup configuration, phone OTP/migration, display-name/template review, quality/limits and regional rules are external gates. UI actions remain disabled with real diagnostics until gates pass; no status or cost is simulated.

## Queue, scheduler and webhooks

`QueueDriverInterface` provides push/claim/ack/release/fail. The database driver atomically claims by conditional update/transaction, random lock token and lease expiry. Jobs carry tenant, queue/type/payload, trace and unique idempotency key. Retries use bounded exponential backoff with jitter; permanent failures dead-letter. A message reservation unique to campaign recipient prevents duplicate sends. Rate/batch controls apply per phone and tenant. Redis can later implement the same interface.

UTC scheduler runs every minute under a DB lock, atomically transitions due campaigns once, records an event, enqueues preparation and recovers stale leases. Workers are bounded and gracefully exit.

Webhook GET verifies the token/challenge. POST verifies `X-Hub-Signature-256` against the raw body, hashes and deduplicates before persistence/enqueue, then acknowledges quickly. Workers resolve tenancy from stored WABA/phone assets and idempotently update delivery/template/connection/opt-out state. Admin replay schedules another processing attempt, not another effect.

## Environments and deployment

Backend variables: `APP_*`, `DB_*`, strict `CORS_ALLOWED_ORIGINS`, `JWT_SECRET`, access/refresh TTLs, refresh cookie settings, login limits, storage/upload/queue limits, `META_GRAPH_API_VERSION`, Meta app/config/webhook secrets, encryption key, mail and optional Razorpay placeholders. Frontend receives only API URL/app name and public Meta app/config IDs.

DirectAdmin serves only `api/public` under HTTPS/PHP 8.3+, with application/storage outside webroot, restricted MySQL user, optimized Composer autoload, OPcache, explicit migrations, cron, log rotation, monitoring and off-host encrypted backups. Vercel root is `frontend`, command `npm run build`, output `dist`, SPA rewrite enabled, domain `app.whatstheup.in`; previews use separate API/origin settings.

## Milestones and onboarding

1. Foundation: monorepo, migrations, auth, tenant/RBAC/audit, shell and tests.
2. Meta: Embedded Signup, encryption, WABA/phone sync, diagnostics and verification webhook.
3. Contacts: CRUD, groups/tags, consent/suppression and queued imports.
4. Templates: draft library, validation, submission/sync/status.
5. Campaigns: builder, audience validation, tests, queue sending/scheduling/pause/cancel.
6. Reporting: delivery handlers, summary/recipient reports and exports.
7. Launch: production assets, controlled test and monitored first campaign.

First-client checklist: provision domains/TLS/DB/mail/backups; create admin/business/access/owner; verify identity/profile/security; finish Meta verification/review/configuration; connect and diagnose WABA/phone/webhook/token; import a small consent-backed set; obtain real template approval; send an internal test and see status webhooks; pass readiness review; launch a small campaign and monitor queue/webhook/errors before scaling.
