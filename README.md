# Amplisio AIO for WooCommerce

Amplisio AIO is a modular, production-ready toolkit for WooCommerce merchants. It provides actionable intelligence, lifecycle automation, and growth tools built on modern WordPress architecture with full HPOS compatibility.

## Requirements

- PHP 8.1 or higher
- WordPress 6.3 or higher
- WooCommerce 8.0 or higher (HPOS enabled or legacy)

## Installation

1. Clone or download this repository into your WordPress `wp-content/plugins` directory.
2. Install PHP dependencies:

   ```bash
   composer install
   ```

3. (Optional) Install Node dependencies for asset builds:

   ```bash
   npm install
   ```

4. Activate **Amplisio AIO for WooCommerce** from the WordPress Plugins screen.

On activation, the plugin seeds default settings and declares HPOS compatibility via `Automattic\WooCommerce\Utilities\FeaturesUtil`.

## Building assets

The dashboard UI is compiled with [esbuild](https://esbuild.github.io/). Run:

```bash
npm run build
```

This bundles `assets/js/admin/dashboard.js` and outputs to `build/admin-dashboard.js`. Use `npm run watch` during development for incremental builds.

## HPOS notes

- The plugin declares compatibility with the HPOS custom order tables feature and avoids deprecated CRUD methods when working with orders and coupons.
- The Abandoned Cart module relies on HPOS-aware APIs (such as `Automattic\WooCommerce\Utilities\OrderUtil` and `WC_Order` data objects) to associate carts with orders, and safely falls back to email matching when HPOS is disabled.
- Custom tables (`amplisio_carts`, `amplisio_recovered_carts`, `amplisio_back_in_stock`) are provisioned with indexed columns for performant lookups and cleanup jobs.

## Modules

Each module registers through the service container and module manager. Modules are disabled by default unless specified. Administrators can toggle modules from **Amplisio → Dashboard**.

| Module | Description |
| ------ | ----------- |
| `analytics` | Aggregates store performance metrics, exposes REST data, and populates dashboard cards. |
| `abandoned_cart` | Captures open carts (guest and customer), automates multi-step recovery sequences with optional coupons, and surfaces REST stats with GDPR tooling. |
| `recovered_carts` | Tracks recovered checkout sessions, stores summaries in an indexed table, and surfaces KPIs. |
| `back_in_stock` | Collects back-in-stock signups and provides REST endpoints for public opt-ins and admin metrics. |

## Dashboard & settings

The Amplisio dashboard is a single-page experience that exposes:

- KPI cards (AOV, conversion rate, customer lifetime value, recovered carts, recovered revenue, back-in-stock signups)
- Theme settings (font family, primary & accent colors, border radius)
- Module toggles with accessibility-friendly switch controls
- Abandoned cart sequence designer with preview and test utilities when the module is enabled

Settings persist via the REST API (`amplisio/v1/settings`) with nonce and capability checks. Modules only bootstrap when enabled.

## Security

- All REST endpoints require capability checks and WP REST nonces.
- Input is sanitized via helper utilities before persisting.
- Database access uses `$wpdb->prepare()` or parameterized inserts.

## Performance

- Background maintenance runs via Action Scheduler hook `amplisio_aio_scheduled_run`.
- Module services perform batched cleanups of custom tables.
- Metrics are cached in transients to avoid repeated heavy queries.

## Internationalization

Text strings load from `languages/` using the `amplisio-aio` text domain.

## CLI commands

Two WP-CLI commands are registered when WP-CLI is available:

- `wp amplisio status` – List plugin version and module enablement.
- `wp amplisio module <enable|disable> <module>` – Toggle module state from the command line.

## Development tooling

- Composer autoloads PHP classes via PSR-4.
- `phpcs.xml` configures WordPress Coding Standards.
- `phpunit.xml.dist` and `tests/bootstrap.php` provide a PHPUnit skeleton.

## Hooks reference

| Hook | Type | Description |
| ---- | ---- | ----------- |
| `amplisio_aio_scheduled_run` | Action Scheduler | Fires daily to let enabled modules perform maintenance. |
| `amplisio_aio_abandoned_sequence` | Action Scheduler | Dispatched per abandoned cart sequence to deliver recovery emails. |
| `amplisio_aio_generate_coupon` | Filter | Override the default coupon generator for abandoned cart emails. |
| `amplisio_aio_abandoned_cart_email_headers` | Filter | Modify headers used for abandoned cart recovery emails and tests. |
| `woocommerce_order_status_completed` | Action | Intercepted by the Recovered Carts module to log recoveries when the `_amplisio_recovered_cart` flag is set on an order. |

## Support & contributions

Contributions are welcome via pull requests. Please run `composer install` and `npm run build` before submitting changes, and follow the WordPress Coding Standards enforced by PHPCS.
