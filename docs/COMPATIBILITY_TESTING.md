# ProBG Team compatibility testing

This document describes the automated OpenCart 3 compatibility checks used by ProBG Team.

## Supported CI matrix

The repository validates the extension against clean official OpenCart source trees for:

- OpenCart 3.0.2.0
- OpenCart 3.0.3.7
- OpenCart 3.0.3.8
- OpenCart 3.0.3.9

The matrix is executed by `.github/workflows/validate.yml` on pull requests and pushes to `main`.

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

At the time this test layer was introduced, the current ProBG Team package matched 8 OpenCart core target files and 11 OCMOD operations on all supported matrix versions with zero compatibility warnings.

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

## What this proves

A green compatibility matrix provides automated evidence that:

- the current OCMOD integration points still exist in the tested OpenCart releases;
- the extension can be overlaid without replacing core files;
- the core Team install/upgrade/uninstall database lifecycle runs successfully against a real MariaDB server;
- source syntax and release packaging remain valid.

## What this does not replace

These tests are not a full browser end-to-end test suite. They do not prove visual compatibility with every third-party theme or extension combination.

Before publishing a production release, manual target-store verification should still cover at least:

- Extensions Installer and Modifications refresh;
- Team administration permissions and navigation;
- settings save and Diagnostics;
- category/member CRUD and image selection;
- SEO routes and canonical redirects;
- section, category and member frontend pages;
- category Layout inheritance and Team module instances;
- search and sitemap integration;
- Open Graph / JSON-LD output;
- active custom-theme overrides.

Custom themes that replace standard OpenCart Twig insertion points may require theme-specific OCMOD or template integration even when the core compatibility matrix is green.
