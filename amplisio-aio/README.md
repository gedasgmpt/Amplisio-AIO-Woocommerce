# Amplisio AIO for WooCommerce

Amplisio AIO delivers a modular auto-styling foundation for WooCommerce that is ready for production deployments. It embraces modern tooling, HPOS compatibility, and an extensible service container architecture to keep features decoupled and testable.

## Requirements

- PHP 8.1 or newer
- WordPress 6.3 or newer
- WooCommerce 8.0 or newer (HPOS compatible)
- Node.js 18+ (for asset builds)
- Composer 2+

## Installation

1. Clone this repository into the `wp-content/plugins` directory or download the packaged ZIP and upload it via **Plugins → Add New**.
2. Install PHP dependencies:

   ```bash
   composer install
   ```

3. Install Node dependencies and build the production assets:

   ```bash
   npm ci
   npm run build
   ```

4. Activate **Amplisio AIO for WooCommerce** from the WordPress plugins screen.
5. Find the settings dashboard under **WooCommerce → Amplisio AIO**.

## Development

- Watch assets during development:

  ```bash
  npm run dev
  ```

- Run the automated tests:

  ```bash
  ./vendor/bin/phpunit
  ```

- Check coding standards:

  ```bash
  ./vendor/bin/phpcs
  ```

- Build production assets:

  ```bash
  npm run build
  ```

## WP-CLI Commands

After loading WordPress with WP-CLI, the plugin exposes helpers for module management:

```bash
wp amplisio module list
wp amplisio module enable <slug>
wp amplisio module disable <slug>
```

## Continuous Integration

Example GitHub Actions workflows are provided in [`.github/workflows`](.github/workflows) to validate Composer dependencies, enforce coding standards, run unit tests, and compile assets.

## BUILD CHECKLIST

- [ ] `composer install`
- [ ] `npm ci`
- [ ] `npm run build`
- [ ] `./vendor/bin/phpunit`
- [ ] `./vendor/bin/phpcs`
- [ ] Activate plugin via WordPress admin
- [ ] Configure settings at **WooCommerce → Amplisio AIO**
- [ ] Verify WP-CLI commands (`wp amplisio module ...`)

## ZIP CONTENTS

```
amplisio-aio/
├── .github/
│   └── workflows/
├── assets/
│   ├── admin/
│   └── front/
├── bin/
├── languages/
├── resources/
│   ├── admin/
│   └── front/
├── src/
│   ├── AdminUI/
│   ├── Core/
│   ├── Modules/
│   │   └── _Example/
│   └── REST/
├── tests/
├── amplisio-aio.php
├── build.mjs
├── composer.json
├── package.json
├── phpcs.xml.dist
├── phpunit.xml
└── README.md
```
