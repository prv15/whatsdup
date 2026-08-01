# DirectAdmin deployment

Point `api.whatstheup.in` only to `api/public`; keep all other API paths outside the document root. Enable HTTPS/PHP 8.3+, install optimized production dependencies, set environment outside version control, run migrations explicitly, grant writes only to storage, add cron from `infrastructure/cron.example`, and configure restricted MySQL, OPcache, log rotation, health alerts and encrypted off-host backups.
