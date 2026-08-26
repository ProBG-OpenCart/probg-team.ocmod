# ProBG Team browser E2E validation

This document describes the browser-level administration validation added after the OpenCart HTTP/controller runtime suite.

## Scope

The browser test is implemented with Playwright Chromium and runs against clean official OpenCart installations for the supported compatibility edges:

- OpenCart 3.0.2.0 on PHP 7.4 and MariaDB 10.6;
- OpenCart 3.0.3.9 on PHP 7.4 and MariaDB 10.6.

The workflow is `.github/workflows/browser-e2e.yml` and the executable scenario is `tests/browser/admin-ui-e2e.js`.

## Covered UI behavior

The scenario uses the real OpenCart administration interface and validates:

- admin login and authenticated `user_token` handling;
- real OCMOD refresh and ProBG Team module installation;
- Team sidebar and settings-page rendering;
- Summernote initialization and persistence in global Team content;
- category creation through the browser UI;
- category Summernote editing;
- category store assignment;
- selection and persistence of a non-default OpenCart Layout;
- JavaScript creation of a typed Members block instance;
- JavaScript creation of a typed Team Menu instance;
- persistence of both standard OpenCart module instances;
- member creation through the browser UI;
- Summernote initialization for short and full member descriptions;
- OpenCart Image Manager selection of a deterministic fixture image;
- JavaScript creation of an additional-image row;
- Image Manager selection for the additional image;
- persistence of main and additional member images;
- member category and store selection.

## Failure artifacts

When the browser scenario fails, CI uploads:

- a full-page screenshot;
- the current HTML document;
- uncaught browser errors and console warnings.

The PHP built-in server log is also checked for fatal errors, uncaught exceptions and parse errors.

## Relationship to the existing runtime suite

The browser E2E workflow complements, rather than replaces:

- `validate.yml` for syntax, package and compatibility-matrix checks;
- `opencart-http-runtime.yml` for real storefront HTTP routes;
- `ocmod-runtime.yml` for generated modification-cache integrations;
- `admin-runtime.yml` for authenticated administration controller/model CRUD and permission behavior.

Together these layers provide static, database, HTTP, OCMOD, administration-controller and JavaScript/browser coverage for the supported OpenCart 3 range.

## Release impact

This stage adds tests and documentation only. It does not change the production module version, database schema or installation package. ProBG Team remains at version 1.0.2 with schema version 1.0.0 until a production-code change requires a new release.
