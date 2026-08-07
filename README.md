# ProBG Team for OpenCart 3

Version: 0.9.0

Multilingual OpenCart 3 extension for a team section, team categories, team-member profiles, configurable card blocks and compact Team menus for Layout positions.

## Compatibility

Designed for OpenCart 3.0.2.x and 3.0.3.x. The package does not replace core files. SEO, search, sitemap, administration-menu and metadata integrations are applied through OCMOD.

Version 0.9.0 adds a separate category-aware Team menu module and remains a release candidate prepared for real-store testing. Static, migration and simulated model tests are included in the release process, but the package still requires verification on the target OpenCart installation, theme, PHP version and cache driver before it is treated as production-ready 1.0.0.

## Main features

### Administration

- Dedicated **Team** menu with categories, members and global settings.
- Multilingual section title, HTML description, metadata and SEO URL.
- Multilingual category content and metadata.
- Multilingual member content, contact details, working hours, social profiles and metadata.
- Main image and multiple additional images.
- Status, sort order, date added and date modified.
- Automatic meta-title fallback from the corresponding title when the field is empty.
- Automatic Cyrillic-to-Latin SEO URL generation.
- Member SEO URLs generated as `ID-title`, for example `18-ivan-ivanov`.
- Unique suffixes for automatically generated SEO URLs when a collision exists in the same store and language.
- Filters for member ID, title, category, city and status.
- Member thumbnails, category totals, modified dates and direct preview buttons.
- Manual cache refresh button in the global settings.
- Diagnostics tab with database, OCMOD, store-relation and SEO checks.
- Safe **Check and repair** action for idempotent schema and relation repair.

### Multi-store support

- Section title, description and metadata can be configured separately for every store and language.
- The default-store content is required.
- An empty title for an additional store inherits the default-store content for the corresponding language.
- Section, category and member SEO URLs are stored separately for every store and language.
- Category and member content remains multilingual and shared, while visibility is controlled separately for each store.
- Every category and member must be assigned to at least one store.
- A member can only be assigned to stores that are enabled for its category.
- Public pages, Layout blocks, search results and sitemap entries are filtered by the active store.

### Public pages

- Team section page with active categories.
- Category page with active members and pagination.
- Member profile with image, gallery, descriptions, contacts, working hours and social profiles.
- Configurable image dimensions and visibility of contact fields.
- Open Graph and Twitter Card metadata.
- Schema.org JSON-LD for section, category and member pages.
- Breadcrumbs, metadata, canonical URLs, 404 validation and canonical 301 redirects.

### Layout module instances

Real OpenCart module instances can be assigned through **Design > Layouts**. Every block can configure:

- internal block name;
- multilingual public title;
- all members or one selected category;
- result limit;
- one, two, three, four or six columns;
- sort order, alphabetical order or newest members;
- visibility of category, city and short description;
- status.

When a block is filtered by category, its fallback title and “view more” link use that category.

### Team menu module instances

The separate **ProBG Team — menu** module can be assigned through **Design > Layouts** and is intended for sidebar or compact navigation positions. Every menu instance can configure:

- internal menu name;
- multilingual public title;
- optional category selection, including **All categories**;
- member-result limit from 1 to 1000;
- status.

When the title is empty, the menu uses the selected category title or the main Team section title. The menu lists active members in their configured sort order, highlights the currently opened member, and links “View all” to the selected category or the main section. When all categories are selected, the category name is shown below each member.

## SEO hierarchy

- `/section-keyword`
- `/section-keyword/category-keyword`
- `/section-keyword/category-keyword/member-keyword`

The hierarchy is encoded and decoded through OCMOD changes to the standard OpenCart SEO controller. Extra query parameters, including pagination and array values, are preserved with RFC 3986 query-string encoding.

## Cache

Optional OpenCart cache support is included for:

- section descriptions;
- category lists and category pages;
- member lists, totals and profiles;
- additional member images;
- SEO keyword lookups;
- sitemap datasets;
- team search results.

The cache namespace is rotated automatically when settings, categories or members are saved or deleted. It can also be refreshed manually from **Team > Settings**. The versioned namespace works with file, Redis, Memcached and other OpenCart cache drivers without relying on prefix deletion.

## Search integration

When **Show in OpenCart search** is enabled, matching team members are appended to the standard `product/search` page. Search covers member title, descriptions, city, working hours and category title.

## XML Sitemap

Standalone sitemap:

`index.php?route=extension/feed/probg_team_sitemap`

The same URLs can also be appended to the standard OpenCart Google Sitemap:

`index.php?route=extension/feed/google_sitemap`

## Diagnostics and repair

Open **Team > Settings > Diagnostics** to check:

- required module tables;
- the `working_hours` database column;
- registered and enabled OCMOD integration;
- category and member totals;
- members linked to a missing category;
- member-store links that are not allowed by the selected category;
- section SEO-record coverage across configured stores and languages.

The **Check and repair** action is idempotent. It can:

- create missing module tables;
- add or normalize the working-hours column;
- add missing default settings;
- restore store assignments only for records that have no assignments;
- remove member-store relations that are not allowed by the member category;
- update the internal schema-version marker;
- rotate the public cache namespace.

It does not delete category or member content and does not automatically delete orphan members.

## Runtime hardening in v0.8.0

- Global scalar settings are stored in the standard `setting` table.
- Legacy duplicated description and SEO-array settings from v0.5.x–v0.7.x are recovered into the normalized tables when needed and then removed.
- Multilingual section content remains only in `team_setting_description`.
- Section SEO URLs remain only in `seo_url`.
- Numeric limits and image dimensions are normalized server-side to values from 1 to 5000.
- Store and language identifiers from submitted data are checked against configured OpenCart records.
- Category and member store assignments are normalized before saving.
- Member store assignments are intersected with the stores enabled for the selected category.
- Category totals use the same title-filter semantics as the category list.
- Schema upgrades use an internal version marker and can be safely rerun through Diagnostics.

## Installation

1. Upload `probg-team-v0.9.0.ocmod.zip` through **Extensions > Installer**.
2. Refresh modifications through **Extensions > Modifications**.
3. Install **ProBG Team** through **Extensions > Extensions > Modules**.
4. Install **ProBG Team — menu** from the same Modules list when compact Team navigation is needed.
5. Grant access and modify permissions if required.
6. Open **Team > Settings** and configure the default store and active languages.
7. Open **Diagnostics** and verify that all checks are successful.
8. Add categories and members.
9. Enable SEO URLs in OpenCart and make sure the standard `.htaccess` rewrite is active.
10. Create optional card blocks and menu instances from **Extensions > Extensions > Modules**, then assign them through **Design > Layouts**.

## Upgrade from v0.8.0

1. Upload the v0.9.0 package without uninstalling the existing module.
2. Refresh modifications through **Extensions > Modifications**.
3. Open **Extensions > Extensions > Modules** and install **ProBG Team — menu**.
4. Create one or more menu instances with an optional category and a member limit.
5. Assign each menu through **Design > Layouts**.
6. Open **Team > Settings > Diagnostics** and verify the existing checks.

Existing content, store assignments, SEO URLs, settings, images and card Layout module instances are preserved. The new Team menu instances are stored separately under the `probg_team_menu` module code. Do not uninstall the previous version before uploading v0.9.0.

## Theme overrides

Default templates are installed under:

`catalog/view/theme/default/template/extension/probg_team/`

and:

`catalog/view/theme/default/template/extension/module/probg_team.twig`

`catalog/view/theme/default/template/extension/module/probg_team_menu.twig`

A custom theme can override them under the equivalent path inside its own theme directory. OCMOD search-page and header integrations require the custom theme to retain the standard OpenCart Twig insertion points or to provide equivalent manual integration.

## Uninstallation warning

Uninstalling the main module removes its custom database tables, content, SEO records, card block instances and saved Team menu instances. Image files selected through the OpenCart Image Manager are not physically deleted.
