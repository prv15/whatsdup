# Restricted Meta test connection repair

This is a repair for an existing single-phone workspace, not production onboarding.
No database migration is needed. It does not register or delete phone numbers or send messages.

## Deployment

Back up the backend and database first. Deploy the changed API files to CAISC,
and deploy the frontend build through the existing Vercel project.
Keep the following settings in the backend environment only (never VITE variables):

```dotenv
META_TEST_CONNECTION_ENABLED=true
META_TEST_BUSINESS_ID=<internal WhatsdUp business UUID, NOT the Meta business ID>
META_TEST_WABA_ID=1376690200553798
META_TEST_PHONE_NUMBER_ID=1349858381536228
```

The workspace must already have exactly one active connection/phone. The logged-in
user needs the existing settings.manage permission. Obtain the internal workspace
UUID from the administrator's business listing. Do not guess it from the company name.
APP_URL must be HTTPS; META_APP_ID, graph version and token encryption key must be configured.
Keep the site in maintenance during deployment. Ensure request bodies are not logged
by the reverse proxy/APM; the token is submitted to the API over HTTPS, encrypted at
rest and never deliberately included in audit entries or responses.

## Test

1. Log into that workspace and open Meta Connection → Developer test connection.
2. Generate a token in the correct Meta app's test setup. Paste it only into this
   password field, not chat or source files. Confirm replacement and connect.
3. Confirm displayed WABA and sender match the developer dashboard and webhook is active.
   A successful connection does not prove phone registration or message delivery.
4. Open Templates → Sync from Meta. Old templates lose approval when WABA changes;
   historical campaigns and contacts remain. Do not manually approve an old template.
5. Verify your own recipient in Meta's test setup, add it as opted in inside WhatsdUp,
   and create a NEW campaign using a synced approved text template without variables.
6. Send only to that recipient. Verify the message arrives and delivery status updates.
   If 133010 recurs, inspect the current sender registration in Meta; do not retry bulk campaigns.

The dashboard token can expire. Refresh it through this same form. The ordinary
business-access check calls /me/businesses and is not the test-token health check.
Turn META_TEST_CONNECTION_ENABLED=false when testing is finished to hide/disable
replacement; this does not disconnect the saved token or prevent sending.

## Limitations and rollback

Local unit tests do not replace a live Meta/MySQL test. Test with a database backup.
The repair blocks queued/scheduled/processing/paused campaigns and serializes new
launches. It leaves prior Meta subscriptions untouched to avoid affecting other apps.
The previous token is overwritten, not retained for rollback; restore the encrypted
database backup and previous API/frontend build together if rollback is necessary.
Do not change TOKEN_ENCRYPTION_KEY. Live activation still requires administrator
deployment access and the user's freshly generated token.
