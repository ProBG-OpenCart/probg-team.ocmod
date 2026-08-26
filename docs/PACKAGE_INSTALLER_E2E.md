# ProBG Team package installer E2E validation

Stage 17 adds a real installation-package release gate for ProBG Team. The goal is to prove that the canonical `.ocmod.zip` can be installed through OpenCart's own Extensions Installer instead of only validating the archive structure or overlaying source files directly in CI.

## Canonical package

The workflow builds and installs exactly:

```text
probg-team-1.0.02.ocmod.zip
```

The package is generated from the current source with:

```bash
bash tools/build-dist.sh 1.0.02
```

The source module version remains `1.0.2` for this test-only stage. Database schema version remains `1.0.0`.

## Supported runtime matrix

`.github/workflows/package-installer-e2e.yml` runs against clean official installations of:

- OpenCart 3.0.2.0;
- OpenCart 3.0.3.9.

Both jobs use PHP 7.4 and MariaDB 10.6, matching the existing edge-version runtime gates.

## What is different from the existing tests

The clean OpenCart tree does **not** receive a direct `upload/` overlay before the test.

Instead, `tests/runtime/package_installer_smoke.sh`:

1. logs into the real OpenCart administration;
2. opens the standard Extensions Installer;
3. uploads `probg-team-1.0.02.ocmod.zip` as a multipart file upload;
4. follows OpenCart's real installer step chain returned by the `marketplace/installer` and `marketplace/install` controllers;
5. verifies the installer-history row and installed-path records;
6. verifies that the `probg_team` OCMOD entry is linked to the same `extension_install_id`;
7. compares every installed Team file byte-for-byte against the package source;
8. refreshes modifications through the authenticated OpenCart administration route;
9. verifies the generated Team SEO modification cache;
10. installs ProBG Team through OpenCart's standard module installer;
11. verifies the Team schema and module registration;
12. opens the real Team settings page;
13. seeds a minimal Team section/category/member fixture after the package installation;
14. exercises the Team landing, category and member storefront routes;
15. rejects PHP fatal errors, uncaught exceptions and parse errors.

## Why this gate matters

Archive validation proves that the ZIP layout is structurally correct, but it cannot prove that OpenCart's own installer accepts every path, records the extension correctly, moves all files to permitted destinations or registers `install.xml` as expected.

This gate closes that gap and specifically protects the release package against:

- invalid `.ocmod.zip` naming;
- disallowed OpenCart installer paths;
- missing files after extraction/move;
- package/source byte mismatches;
- missing installer-history/path records;
- missing or incorrectly linked OCMOD records;
- modification-refresh failures after package installation;
- module-install failures caused by packaging differences.

## Release impact

Stage 17 changes only CI/tests/documentation. It does not introduce a new production feature or database migration, so it does not independently require a version bump.
