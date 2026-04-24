# UpdaWa — Update Watchdog

**Tags:** updates, monitoring, rest api, security, maintenance  
**Requires at least:** 6.0  
**Tested up to:** 6.9  
**Requires PHP:** 7.0  
**Stable tag:** 1.0.5  
**License:** GPL-2.0-or-later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

Monitors WordPress core, plugin, theme, and SSL certificate status via a clean admin dashboard and a Bearer-token-secured REST API.

## Description

UpdaWa gives you a single place to see everything that needs attention on a WordPress site: pending core, plugin, and theme updates plus the SSL certificate expiry — all visible in the admin panel and exposed through a secure REST API endpoint you can poll from any monitoring tool.

### Features

- **REST API** — `GET /wp-json/updawa/v1/status` returns a full JSON snapshot protected by a per-site Bearer token.
- **Status dashboard** — stat cards showing pending update counts, plugin/theme totals, and SSL days remaining at a glance.
- **WordPress Core, SSL, Plugins & Themes** — each section in its own card with colour-coded badges (up to date / update available / expiring / expired).
- **SSL certificate monitoring** — connects to your site's HTTPS endpoint and reports the certificate expiry date and days remaining.
- **Bearer token management** — generate, copy, or revoke the 256-bit cryptographically random token from the Token API tab.
- **QR code** — encodes site name, API URL, and token for instant import into a mobile monitoring app.
- **Android app** *(coming soon)* — a dedicated mobile app for monitoring update and SSL status across multiple WordPress sites, with push notifications when updates are available or certificates are about to expire.
- **JSON view** — pretty-printed full API payload with a one-click Copy button.
- **Zero external dependencies** — the QR code library is bundled; no data is sent to external servers.

### REST API

The endpoint is read-only and requires a valid Bearer token:

    curl -H "Authorization: Bearer YOUR_TOKEN" \
         https://example.com/wp-json/updawa/v1/status

Example response:

    {
      "generated_at": "2026-04-14T09:10:41+00:00",
      "wordpress": {
        "current_version": "6.9.4",
        "update_available": false,
        "new_version": null,
        "package_url": null
      },
      "plugins": [ ... ],
      "themes":  [ ... ],
      "ssl_expires_at": "2026-07-05T01:48:00+00:00"
    }

## Screenshots

1. Manage your Bearer token, regenerate it, copy the example curl command, and scan the QR code for mobile access.
2. An overview of WordPress core, SSL certificate, plugins, and themes — with stat cards at the top and colour-coded badges in each section.
3. The full API payload displayed as formatted text, with a one-click Copy button.

## Installation

1. Upload the `updawa` folder to `/wp-content/plugins/`, or install it directly from the WordPress admin.
2. Activate the plugin through **Plugins → Installed Plugins**.
3. Go to **UpdaWa** in the admin sidebar.
4. Open the **Token API** tab to copy your API token or scan the QR code.

---

## Frequently Asked Questions

**Can I use the REST API from a remote monitoring system?**

Yes. Copy the Bearer token from the **Token API** tab and include it as the `Authorization: Bearer {TOKEN}` header in your HTTP requests. The endpoint returns a JSON snapshot of the current update and SSL status.

**Does the plugin monitor SSL certificates?**

Yes. If your site runs on HTTPS, UpdaWa connects to your domain on port 443 and reads the certificate expiry date. The SSL card on the Status tab shows the expiry date, days remaining, and a warning badge when fewer than 30 days remain.

**Does the plugin send any data to external servers?**

No. All update checks use WordPress's built-in functions (`wp_update_plugins`, `wp_update_themes`, `wp_version_check`). The SSL check connects to your own site. The bundled QR code library runs entirely in your browser.

**How is the API token stored?**

The token is stored as a WordPress option (`updawa_token`) in your site's database. It is never transmitted or logged by the plugin.

**What happens to my data when I delete the plugin?**

The plugin's uninstall routine removes the `updawa_token` option from the database when the plugin is deleted through the WordPress admin.

**How do I regenerate the token?**

Open the **Token API** tab and click **Regenerate token**. The old token becomes invalid immediately.

**Is there a mobile app?**

An Android app for consuming UpdaWa data is under development. It will let you monitor update and SSL status across multiple WordPress sites from your phone, with push notifications when updates are available or certificates are about to expire. Scan the QR code in the Token API tab now so you're ready the moment it launches.

---

## Changelog

### 1.0.5
- Fixed false positive update notifications for plugins and themes.

### 1.0.4
- Fixed empty "More Info" modal in the WordPress plugin directory.

### 1.0.3
- New modern admin UI with stat cards, colour-coded badges, and card-based layout.
- SSL certificate monitoring added to the Status tab.
- Copy button added to the Example API Call card.

### 1.0.2
- Plugin renamed to UpdaWa.

### 1.0.1
- Fixed regulatory compliance issues.

### 1.0.0
- Initial release.
