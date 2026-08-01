# Meta setup and compliance gate

Use only the official Cloud API—no QR/WhatsApp Web automation, browser automation, scraping, device emulation or unofficial libraries. Set `META_GRAPH_API_VERSION=v25.0` for the August 2026 target and re-check Meta's official changelog immediately before deployment.

Configure a Meta Business app, WhatsApp product, Embedded Signup configuration, allowed domain/redirect URI, business portfolio, WABA, eligible verified phone, HTTPS webhook, verification token, app secret and production token strategy. Confirm required WhatsApp permissions, business verification and App Review/advanced access in Meta's dashboard.

Exchange signup codes server-side and encrypt credentials at rest; never send tokens to React. Sending stays blocked until connection, WABA, registered phone, webhook, approved template and consent-backed contacts are healthy.
