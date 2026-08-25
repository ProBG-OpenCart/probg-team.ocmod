# ProBG Team — validation and release engineering

This document describes the permanent validation and packaging workflow introduced after v1.0.1.

## Goals

- Keep OpenCart installation archives limited to `install.xml` and `upload/`.
- Prevent wrapper directories and root-level `.ocmod.zip` files.
- Validate PHP, XML and JavaScript before merging changes.
- Verify module-version consistency between `install.xml` and the administration controller/model.
- Generate reproducible ZIP archives with stable ordering and timestamps.
- Keep `dist/SHA256SUMS` synchronized with the canonical package.
- Make tag/release publication repeatable and guarded by the version declared in source.

## Local validation

Run from the repository root:

```bash
bash tools/validate.sh
```

The validator checks:

- `install.xml` XML syntax and module version;
- PHP syntax for every PHP file under `upload/`;
- JavaScript syntax for every JavaScript file under `upload/`;
- version constants in the main administration controller/model;
- absence of root-level `.ocmod.zip` files;
- archive integrity and allowed top-level package paths;
- `dist/SHA256SUMS`, when present.

## Build an installation package

```bash
bash tools/build-dist.sh 1.0.01
```

The argument controls only the version fragment in the package filename. The actual module version always comes from `install.xml`.

The builder removes old `.ocmod.zip` files from `dist/`, creates:

```text
dist/probg-team-<package-version>.ocmod.zip
dist/SHA256SUMS
```

and then runs the full validator.

ZIP entries are sorted and receive a fixed archive timestamp. Running the builder twice against identical source produces the same SHA-256 digest.

## Pull-request CI

`.github/workflows/validate.yml` runs for pull requests and pushes to `main`.

It validates source on PHP 7.4 and PHP 8.3, then builds the package twice and verifies that both builds have the same SHA-256. A short-lived validation artifact is uploaded for inspection.

## Publishing a release

Use the **Publish ProBG Team release** workflow from GitHub Actions. It requires:

- `version` — must exactly match `<version>` in `install.xml`;
- `package_version` — controls the `dist` filename (for example `1.0.02`);
- `prerelease` — controls GitHub prerelease status.

The workflow refuses to publish an already existing tag. It validates the source, builds the canonical package, commits changed `dist` files to `main`, creates annotated tag `v<version>`, and publishes the GitHub Release with the installation ZIP and `SHA256SUMS` attached.

## Recommended release sequence

1. Change source and version through a normal feature/release PR.
2. Merge only after the validation workflow is green.
3. Run **Publish ProBG Team release** with the merged version.
4. Verify the resulting GitHub tag, Release assets and checksum.
