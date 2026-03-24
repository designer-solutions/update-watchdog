# UpdaWa - the update watchdog

**Tags:** updates, monitoring, rest api, security, maintenance
**Requires at least:** 6.0
**Tested up to:** 6.9
**Requires PHP:** 7.0
**Stable tag:** 1.0.2
**License:** GPL-2.0-or-later
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

Monitors the availability of updates for WordPress plugins, themes, and core via an admin panel and a Bearer-token-secured REST API.

---

## Description

UpdaWa keeps track of pending updates for your WordPress installation and makes that information available in two ways:

- **Admin dashboard** – a dedicated UpdaWa menu item shows the current update status as a formatted JSON view or an HTML table (plugins, themes, WordPress core).
- **REST API endpoint** – `GET /wp-json/updawa/v1/status` returns the same data as JSON, protected by a per-site Bearer token that you generate and manage from the admin panel.

### Features

- One-click status refresh for plugins, themes, and WordPress core.
- JSON view with pretty-printed output.
- Table view with colour-coded update indicators.
- Secure, per-site API token (64-character hex string, cryptographically random).
- Token regeneration with a single click.
- QR code in the Token API tab encodes the site name, site URL, and token for easy mobile access.
- Zero external dependencies — the QR code library is bundled with the plugin.

### REST API

The endpoint is read-only and requires a valid Bearer token:

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" https://example.com/wp-json/updawa/v1/status
```

The response is a JSON object with keys `generated_at`, `wordpress`, `plugins`, and `themes`.

---

## Installation

1. Upload the `updawa` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Go to **UpdaWa** in the admin sidebar.
4. Open the **Token API** tab to find or regenerate your API token.

---

## Frequently Asked Questions

**Does the plugin send any data to external servers?**

No. All update checks use WordPress's built-in functions (`wp_update_plugins`, `wp_update_themes`, `wp_version_check`), which are the same calls WordPress itself makes. The bundled QR code library runs entirely in your browser and sends no data anywhere.

**How is the API token stored?**

The token is stored as a WordPress option (`updawa_token`) in your site's database. It is never transmitted or logged by the plugin itself.

**What happens to my data when I delete the plugin?**

The plugin registers an uninstall routine that removes the `updawa_token` option from the database when the plugin is deleted through the WordPress admin.

**Can I use the REST API from a remote monitoring system?**

Yes. Copy the Bearer token from the **Token API** tab and include it as the `Authorization` header in your HTTP requests. The endpoint returns a JSON snapshot of the current update status.

**How do I regenerate the token?**

Open the **Token API** tab and click **Regenerate token**. The old token becomes invalid immediately.

---

## Screenshots

1. Status tab – update status for core, plugins, and themes in a clear grid.
2. JSON tab – pretty-printed update status output.
3. Token API tab – token field, QR code, and example curl command.

---

## Changelog

### 1.0.2
- Change plugin name to UpdaWa because Update Watchdog is too similar to existing ones

### 1.0.1
- Fixed regulatory compliance issues.
### 1.0.0
- Initial release.
