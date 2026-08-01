# WhatstheUp

Production foundation for a multi-tenant SaaS using the official WhatsApp Business Platform Cloud API.

- `frontend/`: React, Vite, TypeScript and Tailwind SPA
- `website/`: public marketing, contact and Meta compliance website
- `api/`: PHP 8.3+ REST API, MySQL migrations and CLI workers
- `docs/`: architecture, Meta, security and deployment guidance
- `infrastructure/`: cron/deployment examples

## Milestone 1

The foundation includes secure authentication/session rotation, tenant isolation, RBAC, auditing, schema migrations, a responsive application shell, and deployment-safe configuration. Meta actions remain disabled until real credentials and external approvals are configured.

```bash
cp api/.env.example api/.env
cp frontend/.env.example frontend/.env.local
composer install --working-dir=api
npm install --prefix frontend
npm install --prefix website
php api/bin/console migrate
php api/bin/console seed:foundation
```

Run the public website locally with `npm run dev --prefix website`. Copy
`website/.env.example` to `website/.env.local` when the marketing and app URLs
need to differ from their production defaults.

See `docs/ARCHITECTURE.md` and `docs/INSTALLATION.md`.
