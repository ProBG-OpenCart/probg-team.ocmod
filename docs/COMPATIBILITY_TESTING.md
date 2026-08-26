# ProBG Team compatibility testing

This document describes the automated OpenCart 3 compatibility and release-gate checks used by ProBG Team.

## Supported CI matrix

The repository validates the extension against clean official OpenCart source trees for:

- OpenCart 3.0.2.0;
- OpenCart 3.0.3.7;
- OpenCart 3.0.3.8;
- OpenCart 3.0.3.9.

The full static/database matrix runs on all four versions. The slow authenticated HTTP, browser and package-installer gates run on the oldest and newest supported versions: OpenCart 3.0.2.0 and 3.0.3.9.

## Validation layers

ProBG Team uses several independent CI layers instead of relying on one broad smoke test:

- `.github/workflows/validate.yml` — source/package validation, PHP compatibility, deterministic builds, OpenCart core compatibility and database lifecycle;
- `.github/workflows/ocmod-runtime.yml` — real authenticated OCMOD refresh and SEO/search/sitemap/header integration;
- `.github/workflows/admin-runtime.yml` — real authenticated administration controllers, permissions, settings, Diagnostics and CRUD;
- `.github/workflows/browser-e2e.yml` — real Chromium administration UI including JavaScript-only controls;
- `.github/workflows/storefront-browser.yml` — responsive storefront, Layout integration and theme fallback in real Chromium;
- `.github/workflows/package-installer-e2e.yml` — real `.ocmod.zip` upload and installation through OpenCart Extensions Installer.

## Static source and package validation

Every primary validation run includes:

- PHP syntax checks on PHP 7.4 and PHP 8.3;
- `install.xml` XML parsing;
- JavaScript syntax validation;
- module-version consistency checks;
- committed `.ocmod.zip` integrity and layout validation;
- SHA-256 verification for committed release packages;
- deterministic package generation by building the same source twice and comparing SHA-256 hashes;
- rejection of root-level `.ocmod.zip` archives and package wrapper directories.

The canonical v1.0.2 installation filename is:

```text
probg-team-1.0.02.ocmod.zip
```

## OpenCart core compatibility validation

`tools/check-opencart-compatibility.py` validates the module against clean official OpenCart source trees supplied by CI.

For every supported version the harness checks:

1. every `<file>` target in `install.xml` resolves to the expected OpenCart core file(s);
2. every required OCMOD `<search>` anchor exists in the target core file;
3. optional `error="skip"` operations are reported without hiding required-anchor failures;
4. module files under `upload/` do not replace existing OpenCart core files;
5. a clean package overlay preserves every module file byte-for-byte.

The current integration targets the standard OpenCart SEO URL, Layout, common header, product search, Google Sitemap and administration sidebar paths.

## Database/runtime smoke validation

Each OpenCart compatibility job starts a clean MariaDB 10.6 service and imports the stock OpenCart schema for that version.

The database session mirrors the OpenCart 3 MySQLi runtime SQL mode:

```sql
SET SESSION sql_mode = 'NO_ZERO_IN_DATE,NO_ENGINE_SUBSTITUTION';
```

`tests/runtime/install_model_smoke.php` exercises the real ProBG Team administration model and verifies:

- fresh `install()` creates all required Team tables;
- module and schema version settings are written;
- repeated `install()` is idempotent;
- legacy schema upgrades are repaired safely;
- category/member store relations remain valid;
- `uninstall()` removes Team-owned tables;
- uninstall rotates the Team cache namespace.

## Real storefront HTTP validation

The edge versions are exercised through the actual OpenCart storefront on PHP 7.4 and MariaDB 10.6.

`tests/runtime/http_storefront_smoke.sh` verifies:

- stock OpenCart boot;
- Team landing page;
- category page;
- member profile;
- working hours and structured data;
- standalone Team sitemap;
- expected 404 behavior for invalid hierarchy requests;
- absence of fatal PHP errors, uncaught exceptions and parse errors.

## Real OCMOD refresh and integration validation

`.github/workflows/ocmod-runtime.yml` executes the real OpenCart modification-refresh flow on OpenCart 3.0.2.0 and 3.0.3.9.

CI logs into the administration, refreshes modifications through `marketplace/modification/refresh`, verifies generated files under `system/storage/modification/`, and then exercises the modified storefront.

The runtime verifies:

- three-level Team SEO hierarchy;
- canonical redirects;
- Open Graph and Twitter metadata;
- Team results in standard OpenCart search;
- Team URLs in standard Google Sitemap;
- Team category Layout inheritance through the modified Layout model;
- no OCMOD failure markers or fatal PHP runtime errors.

## Real administration HTTP validation

`.github/workflows/admin-runtime.yml` exercises authenticated Team administration on OpenCart 3.0.2.0 and 3.0.3.9.

It covers:

- installation through OpenCart's standard module-extension route;
- permission grants for Team custom routes;
- Team sidebar access;
- global settings and multilingual section data;
- typed Members block and Team Menu module instances;
- Diagnostics repair;
- manual cache refresh;
- category create/filter/edit/delete lifecycle;
- member create/filter/edit/delete lifecycle;
- store relations, working hours, contact data and SEO URL persistence;
- category delete protection while members exist.

This layer focuses on controller/model/permission behavior and intentionally remains browserless.

## Real administration browser E2E

`.github/workflows/browser-e2e.yml` uses Playwright with headless Chromium on OpenCart 3.0.2.0 and 3.0.3.9.

It covers JavaScript-only behavior that cannot be proved by HTTP controller tests:

- authenticated OpenCart administration login;
- OCMOD refresh and module installation;
- Team sidebar/settings UI;
- Summernote initialization and persistence;
- category creation and non-default Layout selection;
- real JavaScript add buttons for Members block and Team Menu instances;
- Image Manager selection for main member image;
- additional image-row creation and Image Manager selection;
- persistence after reopening forms.

The harness contains narrowly scoped compatibility handling for confirmed stock OpenCart 3.0.2.0/3.0.3.x JavaScript differences while still failing on unrelated browser errors.

## Responsive storefront and theme browser E2E

`.github/workflows/storefront-browser.yml` validates the Team storefront in Chromium on:

- OpenCart 3.0.2.0 / default theme;
- OpenCart 3.0.2.0 / synthetic custom theme;
- OpenCart 3.0.3.9 / default theme;
- OpenCart 3.0.3.9 / synthetic custom theme.

Every matrix job checks desktop, tablet and mobile viewports.

The browser scenario verifies:

- Team landing/category/member pages;
- Members block and Team Menu as real OpenCart Layout module instances;
- category Layout inheritance on member profiles;
- default-theme Team template fallback while a non-default theme is active;
- OCMOD metadata injection into a custom theme header;
- responsive member/gallery images;
- Magnific Popup;
- protection against page-level horizontal overflow from long URLs, unbroken text, `<pre>` and wide rich HTML;
- local scrolling for intentionally wide rich content instead of widening the whole page.

## Real Extensions Installer package E2E

`.github/workflows/package-installer-e2e.yml` closes the gap between archive validation and a real OpenCart installation.

The job builds exactly:

```text
probg-team-1.0.02.ocmod.zip
```

It then installs a clean OpenCart instance **without** overlaying ProBG Team source files and uploads the ZIP through OpenCart's real `marketplace/installer/upload` route.

`tests/runtime/package_installer_smoke.sh` verifies:

1. the standard Extensions Installer accepts the `.ocmod.zip` filename;
2. the real multi-step `marketplace/install` chain completes without an error;
3. `extension_install` history contains the package filename;
4. `extension_path` contains the files moved by OpenCart;
5. the `probg_team` OCMOD row is linked to the same `extension_install_id`;
6. the installed OCMOD version is `1.0.2`;
7. every installed Team file matches the packaged source byte-for-byte;
8. authenticated OCMOD refresh produces the expected Team modification cache;
9. standard OpenCart module installation creates the Team schema and module registration;
10. the Team settings page boots after package installation;
11. a seeded Team landing/category/member storefront boots from the package-installed files;
12. no fatal PHP error, uncaught exception or parse error occurs during the flow.

This test runs on OpenCart 3.0.2.0 and 3.0.3.9 with PHP 7.4 and MariaDB 10.6.

## What the automated suite proves

A fully green suite provides automated evidence that:

- the OCMOD insertion points remain compatible with supported OpenCart 3 versions;
- the source and canonical package are syntactically and structurally valid;
- package generation is reproducible;
- the module database lifecycle works against a real MariaDB server;
- real OpenCart admin permissions, settings and CRUD controllers work;
- JavaScript-only admin controls work in real Chromium;
- SEO, search, sitemap, canonical and metadata integrations work from generated OCMOD cache;
- Team Layout modules and category Layout inheritance work on the storefront;
- responsive Team rendering does not introduce page-level horizontal overflow in the tested viewports;
- default-theme fallback works while a non-default theme is active;
- the canonical `.ocmod.zip` is accepted and installed by the real OpenCart Extensions Installer on the supported edge versions.

## What this does not replace

Automated tests cannot prove compatibility with every hosting environment, third-party theme or modification combination.

Before a production deployment to a specific store, targeted verification may still be appropriate for:

- hosting-specific upload limits, filesystem ownership/permissions and security rules;
- heavily customized themes that replace standard OpenCart Twig structures rather than using normal fallback behavior;
- third-party OCMOD/VQMod extensions that modify the same core insertion points;
- store-specific SEO rewrites, caching layers, CDN/proxy behavior and custom admin security middleware.

Custom themes or extensions that replace standard OpenCart insertion points may require theme- or extension-specific integration even when the core compatibility matrix is fully green.
