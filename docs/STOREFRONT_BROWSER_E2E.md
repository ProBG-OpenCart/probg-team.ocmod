# ProBG Team storefront browser E2E validation

This stage extends the ProBG Team runtime suite with real Chromium storefront validation for responsive rendering, Layout integration and OpenCart theme fallback behavior.

## Matrix

The workflow `.github/workflows/storefront-browser.yml` runs against:

- OpenCart 3.0.2.0;
- OpenCart 3.0.3.9;
- the stock `default` theme;
- a synthetic non-default theme that overrides the common header while intentionally inheriting ProBG Team templates from `catalog/view/theme/default`.

Every matrix job exercises desktop, tablet and mobile viewports.

## Covered routes and behavior

The Playwright scenario validates:

- Team landing page;
- Team category page;
- Team member profile;
- standard OpenCart Layout placement of a typed Members block;
- standard OpenCart Layout placement of a typed Team Menu;
- category-specific Layout inheritance on member profiles through the real OCMOD-modified layout model;
- custom-theme fallback to ProBG Team templates in the default theme directory;
- OCMOD Open Graph injection into a non-default theme header;
- main member image and additional gallery image rendering;
- Magnific Popup lightbox behavior;
- desktop, tablet and mobile horizontal-overflow protection;
- responsive image sizing;
- containment of intentionally wide rich HTML content;
- wrapping of long member URLs and unbroken text.

## Responsive hardening

`catalog/view/javascript/probg_team/probg_team.css` now keeps Team flex/grid children shrinkable and prevents rich HTML, long URLs, images and preformatted text from widening the page.

Wide rich content is kept inside its Team content container with local horizontal scrolling when necessary. This preserves the original HTML instead of silently truncating administrator-authored content.

## Custom-theme fixture

The synthetic `probg_e2e` theme is created only inside CI. It copies the stock OpenCart header and adds a deterministic marker, while leaving ProBG Team page/module templates absent. OpenCart must therefore use its normal default-theme template fallback for Team views.

The custom header exists before OCMOD refresh so the wildcard header modification is applied to the non-default theme as it would be on a real installed theme.

## Failure artifacts

On failure the workflow uploads:

- a full-page screenshot;
- current page HTML;
- uncaught browser errors.

The PHP server log is also scanned for fatal errors, uncaught exceptions and parse errors.

## Release impact

This is the first development stage after v1.0.2. The responsive CSS hardening is a production-code change intended for the next patch release. Database schema remains unchanged at 1.0.0.
