# Installation

Requires PHP 8.3+ with PDO MySQL, sodium, mbstring and fileinfo; Composer; MySQL 8+; Node 20+ and npm.

```bash
cp api/.env.example api/.env
composer install --working-dir=api
php api/bin/console migrate
php api/bin/console seed:foundation
php api/bin/console user:create-super-admin --name="Platform Admin" --email="admin@example.com" --password="use-a-unique-admin-password"
php api/bin/console user:create-owner --business="Client" --name="Owner" --email="owner@example.com" --password="use-a-unique-password"
cp frontend/.env.example frontend/.env.local
npm install --prefix frontend
npm run build --prefix frontend
```

For development: `php -S 127.0.0.1:8080 -t api/public api/public/router.php` and `npm run dev --prefix frontend`. Never use the example owner/password in production; pass a unique secret locally and clear shell history where appropriate.

Platform users sign in through the same `/login` page and are routed to `/admin`. Business users are routed to `/dashboard`. Platform roles are stored separately from business memberships so a Super Admin never needs a fake customer workspace.

## Client onboarding and campaigns

1. Sign in as the Super Admin and create the customer from **Admin → Businesses**. This creates the client workspace and Business Owner login together.
2. The Business Owner signs in at the same app URL, then completes **Meta Connection** using Meta Embedded Signup.
3. In **Templates**, use **Sync from Meta** to bring in templates approved in Meta Business Manager. Only approved templates can launch.
4. In **Contacts**, import a CSV using `phone`, `name`, `email`, `tags`, and `consent` columns. Phone values must be E.164, such as `+919876543210`; campaigns only include `opted_in` contacts.
5. In **Campaigns**, create a draft and launch it. Add the `queue:work` entry from `infrastructure/cron.example` to the server crontab so queued and scheduled campaigns are delivered through the connected Meta phone number.
