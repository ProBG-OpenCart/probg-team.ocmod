# ProBG Team compatibility testing

This document describes the automated OpenCart 3 compatibility checks used by ProBG Team.

## Supported CI matrix

The repository validates the extension against clean official OpenCart source trees for:

- OpenCart 3.0.2.0
- OpenCart 3.0.3.7
- OpenCart 3.0.3.8
- OpenCart 3.0.3.9

The primary matrix is executed by `.github/workflows/validate.yml` on pull requests and pushes to `main`. The real generated-modification integration test is executed separately by `.github/workflows/ocmod-runtime.yml` on the oldest and newest supported OpenCart 3 lines. The authenticated administration lifecycle is exercised by `.github/workflows/admin-runtime.yml` on the same edge versions.

## Static source and package validation

Every CI run includes:

- PHP syntax checks on PHP 7.4 and PHP 8.3;
- `install.xml` XML parsing;
- JavaScript syntax validation;
- module-version consistency checks;
- committed `.ocmod.zip` integrity and layout validation;
- SHA-256 verification for committed release packages;
- deterministic package generation by building the same source twice and comparing SHA-256 hashes.

## OpenCart core compatibility validation

`tools/check-opencart-compatibility.py` downloads no code itself; CI supplies a clean official OpenCart `upload/` tree for each matrix version.

For every version the harness checks:

1. every `<file>` target in `install.xml` resolves to the expected OpenCart core file(s);
2. every required OCMOD `<search>` anchor exists in the target core file;
3. `error="skip"` operations are reported as warnings instead of hard failures when an optional anchor is unavailable;
4. module files under `upload/` do not replace existing OpenCart core files;
5. a clean package overlay preserves every module file byte-for-byte.

The current ProBG Team package matches 8 OpenCart core target files and 11 OCMOD operations on all supported matrix versions with zero compatibility warnings.

## Database/runtime smoke validation

Each OpenCart compatibility job starts a clean MariaDB 10.6 service and imports that OpenCart version's stock `install/opencart.sql`.

The database session mirrors the OpenCart 3 MySQLi runtime SQL mode:

```sql
SET SESSION sql_mode = 'NO_ZERO_IN_DATE,NO_ENGINE_SUBSTITUTION';
```

The module source is then overlaid into the clean OpenCart tree and `tests/runtime/install_model_smoke.php` exercises the actual ProBG Team administration model.

The smoke test verifies:

- fresh module `install()` creates all required Team tables;
- module and schema version settings are written;
- a second `install()` is idempotent and does not duplicate settings;
- a simulated legacy schema without `working_hours` is upgraded correctly;
- the restored `working_hours` column is `NOT NULL`;
- categories and members without store assignments are repaired for the default store;
- member store assignments remain constrained by category store assignments;
- `uninstall()` removes all Team-owned tables;
- uninstall rotates the Team cache namespace.

## Real storefront HTTP smoke validation

The edge versions of the supported OpenCart 3 range are exercised through a real HTTP storefront runtime:

- OpenCart 3.0.2.0 on PHP 7.4;
- OpenCart 3.0.3.9 on PHP 7.4.

For each job CI:

1. starts a fresh MariaDB 10.6 service;
2. downloads the official OpenCart source tag;
3. overlays the ProBG Team `upload/` files;
4. installs OpenCart with the official `install/cli_install.php` installer;
5. installs the Team schema and seeds a minimal active section, category and member fixture;
6. starts the actual storefront through PHP's built-in HTTP server;
7. issues real `curl` requests against OpenCart routes.

`tests/runtime/http_storefront_smoke.sh` verifies:

- the stock OpenCart home route boots successfully;
- the Team section route returns the seeded section and category;
- the category route returns the visible member and city;
- the member profile returns its category, working hours and `ProfilePage` JSON-LD;
- the standalone Team sitemap returns section, category and member URLs;
- an unknown category returns HTTP 404;
- a member requested through the wrong category hierarchy returns HTTP 404;
- the PHP server log contains no fatal error, uncaught exception or parse error.

The intermediate OpenCart 3.0.3.7 and 3.0.3.8 lines continue to receive the full OCMOD, package-overlay and MariaDB lifecycle tests. Testing the oldest and newest supported lines through HTTP gives direct coverage of the compatibility range edges without duplicating the slowest browserless runtime job four times.

## Real OCMOD refresh and integration validation

`.github/workflows/ocmod-runtime.yml` goes beyond anchor checks and executes the actual OpenCart modification refresh path on OpenCart 3.0.2.0 and 3.0.3.9.

For each edge-version job CI:

1. installs a clean OpenCart store with the official CLI installer;
2. removes the installer directory as a production store would;
3. installs and seeds the Team fixture;
4. registers the real `install.xml` in OpenCart's standard `modification` table;
5. logs into the real OpenCart administration with the installed admin account;
6. calls `marketplace/modification/refresh` with the authenticated `user_token`;
7. verifies the generated files in `system/storage/modification/`;
8. rejects OCMOD logs containing failure markers;
9. starts the modified storefront and exercises the integrations over HTTP.

The generated modification cache is required to contain the Team changes for:

- `catalog/controller/startup/seo_url.php`;
- `catalog/model/design/layout.php`;
- `catalog/controller/common/header.php`;
- `catalog/view/theme/default/template/common/header.twig`;
- `catalog/controller/product/search.php`;
- `catalog/view/theme/default/template/product/search.twig`;
- `catalog/controller/extension/feed/google_sitemap.php`;
- `admin/controller/common/column_left.php`.

`tests/runtime/ocmod_http_smoke.sh` then verifies through real HTTP requests:

- the three-level Team SEO hierarchy for section, category and member;
- canonical HTTP 301 from the query-string member route to its Team SEO URL;
- Open Graph and Twitter metadata injected through the modified common header;
- Team member results injected into the standard OpenCart product search page;
- Team section, category and member URLs appended to the standard Google Sitemap;
- no fatal PHP error, uncaught exception or parse error during the integration flow.

The PHP rewrite router used by CI explicitly emulates the `_route_` value normally supplied by OpenCart's Apache `.htaccess`, allowing the built-in PHP server to exercise the real `startup/seo_url.php` modification path.

## Real administration runtime validation

`.github/workflows/admin-runtime.yml` exercises the authenticated ProBG Team administration lifecycle on OpenCart 3.0.2.0 and 3.0.3.9 with PHP 7.4 and MariaDB 10.6.

For each edge-version job CI:

1. installs a clean OpenCart store with the official CLI installer;
2. registers and refreshes the real Team OCMOD through the authenticated admin route;
3. installs ProBG Team through OpenCart's standard `extension/extension/module/install` route;
4. verifies that the Team sidebar and Settings, Categories and Members pages are accessible with the permissions granted by the module itself;
5. saves global settings, multilingual section content and section SEO data through the real settings controller;
6. creates and persists a typed Members block and Team Menu as standard OpenCart module instances;
7. executes the Diagnostics repair and manual cache-refresh routes;
8. creates, filters and edits a Team category through the real administration controller;
9. creates, filters and edits a Team member, including category, store, city, working hours, contact data and SEO URL;
10. verifies that a category owning a member cannot be deleted;
11. deletes the member and then successfully deletes the empty category;
12. rejects fatal PHP errors, uncaught exceptions and parse errors from the administration HTTP flow.

This runtime layer also guards the OpenCart 3 custom-route permission behavior: `startup/permission` resolves `extension/probg_team/category` and `extension/probg_team/member` to the parent access route `extension/probg_team`, while the Team controllers retain their dedicated `modify` permissions for write operations.

The administration smoke test intentionally remains browserless. It validates HTTP/controller/model persistence and permissions, but it does not automate JavaScript-only UI interactions such as Image Manager dialogs, Summernote editing behavior, drag-and-drop interactions or visual Layout placement.

## What this proves

A green compatibility, OCMOD-runtime and admin-runtime matrix provides automated evidence that:

- the current OCMOD integration points exist in the tested OpenCart releases;
- the extension can be overlaid without replacing core files;
- the Team install/upgrade/uninstall database lifecycle runs successfully against a real MariaDB server;
- the actual OpenCart storefront can bootstrap and render the primary Team routes on the oldest and newest supported OpenCart 3 lines;
- OpenCart can generate the Team modification cache through the real authenticated admin refresh route;
- Team SEO, canonical, search, Google Sitemap and common-header metadata integrations execute successfully from that generated cache;
- the module can be installed through the standard OpenCart module installer and can grant the administration permissions required by its custom routes;
- settings, Diagnostics, module instances and category/member CRUD execute successfully through authenticated OpenCart administration HTTP routes;
- source syntax and release packaging remain valid.

## What this does not replace

These tests are not a full browser end-to-end or visual-regression suite. They do not prove compatibility with every third-party theme or extension combination.

Before publishing a production release, manual target-store verification should still cover at least:

- Extensions Installer upload behavior in the target hosting environment;
- Image Manager selection and additional-image management;
- Summernote editing behavior;
- category Layout inheritance and visual Team module-instance placement;
- active custom-theme overrides and responsive presentation;
- conflicts with third-party modifications touching the same core insertion points.

Custom themes that replace standard OpenCart Twig insertion points may require theme-specific OCMOD or template integration even when the core compatibility matrix is green.
