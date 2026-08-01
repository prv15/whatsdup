# Meta setup and compliance gate

Use only the official Cloud API—no QR/WhatsApp Web automation, browser automation, scraping, device emulation or unofficial libraries. Set `META_GRAPH_API_VERSION=v25.0` for the August 2026 target and re-check Meta's official changelog immediately before deployment.

Configure a Meta Business app, WhatsApp product, Embedded Signup configuration, allowed domain/redirect URI, business portfolio, WABA, eligible verified phone, HTTPS webhook, verification token, app secret and production token strategy. Confirm required WhatsApp permissions, business verification and App Review/advanced access in Meta's dashboard.

The current integration loads Meta's official Facebook JavaScript SDK only when all required environment values are present and the application uses HTTPS. The authorization code and returned WABA/phone IDs are sent to the PHP API; the API exchanges the code, independently fetches both assets, encrypts the token with sodium secretbox, persists the verified assets, and subscribes the app to the WABA. A failed webhook subscription produces `webhook_error` instead of a false connected state.

Meta-owned references:

- https://www.postman.com/meta/whatsapp-business-platform/collection/du6gzjv/embedded-signup
- https://www.postman.com/meta/whatsapp-business-platform/folder/gumbt4j/waba-subscriptions
- https://www.postman.com/meta/whatsapp-business-platform/request/o84xigu/phone-numbers

Exchange signup codes server-side and encrypt credentials at rest; never send tokens to React. Sending stays blocked until connection, WABA, registered phone, webhook, approved template and consent-backed contacts are healthy.
