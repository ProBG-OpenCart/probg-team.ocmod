# Changelog

## 1.0.1 — 2026-08-25

### fix — Български

- Добавена е request-level защита срещу recursive повторно рендериране на един и същ Team Layout instance при нестандартни теми и Layout интеграции.
- Storefront изображенията на членовете и галерията вече се валидират чрез реалния път в `DIR_IMAGE`, което блокира опасни legacy path стойности и файлове извън Image директорията.
- Основното изображение и допълнителната галерия на профила вече използват вградения в OpenCart 3 Magnific Popup lightbox, без външен CDN.
- Добавени са gallery navigation, keyboard navigation, preload и zoom indicator, като нормалните image links остават fallback при липса на JavaScript.
- Версията е стабилизирана като `1.0.1`; database schema версията остава `1.0.0`, защото няма нова DB миграция.
- Инсталационният пакет за release-а се публикува като `dist/probg-team-1.0.01.ocmod.zip`.

### fix — English

- Added a request-level guard against recursive rendering of the same Team Layout instance in non-standard themes and Layout integrations.
- Storefront member and gallery images are now validated against their real path inside `DIR_IMAGE`, blocking unsafe legacy paths and files outside the image directory.
- The member profile image and additional gallery now use OpenCart 3's bundled Magnific Popup lightbox with no external CDN.
- Added gallery navigation, keyboard navigation, preload and a zoom indicator while preserving normal image links as the JavaScript fallback.
- Stabilized the module version as `1.0.1`; the database schema version remains `1.0.0` because this release requires no new DB migration.
- The release installation package is published as `dist/probg-team-1.0.01.ocmod.zip`.

## 1.0.0-beta.2 — 2026-08-25

### feat — Български

- Архитектурата на ProBG Team е синхронизирана с актуалния ProBG Blog 1.4.x там, където е приложима за модул „Екип“.
- Блоковете с членове и менютата вече се записват като стандартни OpenCart module instances под един код `probg_team`, с вътрешен тип `members` или `menu`.
- Добавена е автоматична миграция от старите `probg_team_members.<module_id>` и `probg_team_menu.<module_id>` записи към `probg_team.<module_id>`, без промяна на `module_id` или Layout позициите.
- Глобалните настройки, блоковете и менютата се управляват от една административна страница на ProBG Team.
- Добавена е таблица `team_category_to_layout` и избор на Layout за всяка категория по магазин.
- Профилите на членовете наследяват Layout-а на своята категория за активния магазин.
- Добавени са дата на добавяне/обновяване в административните списъци и форми за категории и членове.
- Старият route `extension/probg_team/setting` остава като compatibility redirect.
- Старите helper controllers/templates остават в пакета като преходна съвместимост, но след миграцията не се използват за нови Layout instances.

### feat — English

- Aligned ProBG Team with the current ProBG Blog 1.4.x architecture where that architecture is appropriate for a Team directory.
- Member blocks and menus are now stored as standard OpenCart module instances under the single `probg_team` code, using an internal `members` or `menu` type.
- Added automatic migration from legacy `probg_team_members.<module_id>` and `probg_team_menu.<module_id>` records to `probg_team.<module_id>` while preserving module IDs and Layout assignments.
- Global settings, member blocks and menus are managed from one ProBG Team administration page.
- Added `team_category_to_layout` and per-store Layout selection for Team categories.
- Member profiles inherit the Layout configured for their category in the active store.
- Added date-added/date-modified information to category and member administration lists and forms.
- Kept `extension/probg_team/setting` as a compatibility redirect.
- Legacy helper controllers/templates remain in the package for transition compatibility but are no longer used for new Layout instances after migration.

## 1.0.0-beta — 2026-08-15

- Published the first 1.0.0 Beta release of ProBG Team.
- Promoted the validated v0.9.1 codebase to the 1.0.0 beta milestone without database-schema changes.
- Kept the Blog-aligned administration structure with Settings, Categories and Members navigation.
- Kept the separate ProBG Team — members and ProBG Team — menu Layout modules.
- Preserved the automatic v0.9.0 Layout-module migration and all existing Team content, SEO URLs and store assignments.
- Marked the package as a beta/pre-release pending final runtime verification on target OpenCart 3 installations and themes.

## 0.9.1

- Aligned the Team administration structure with ProBG Blog.
- Changed the main **ProBG Team** module entry to open global Team settings.
- Moved member-card Layout instances to the separate **ProBG Team — members** module code.
- Added a safe one-time migration from `probg_team.<module_id>` to `probg_team_members.<module_id>` while preserving module IDs and Layout positions.
- Reordered the Team administration menu to **Members**, **Categories**, **Settings**.
- Removed Layout helper modules from the Team sidebar; they remain available under **Extensions > Extensions > Modules**, matching Blog.
- Added shared **Settings / Categories / Members** navigation tabs to the Team administration pages through OCMOD.
- Added category and member dashboard tiles to Team settings.
- Added Bulgarian and English labels for the unified administration navigation.
- Kept the legacy catalog `probg_team` Layout controller for backward compatibility until the migration runs.

## 0.9.0

- Added a separate **ProBG Team — menu** OpenCart Layout module.
- Added optional category filtering for each menu instance.
- Added a configurable member limit from 1 to 1000 records.
- Added multilingual menu titles with fallback to the selected category or main Team section title.
- Added a compact Bootstrap list-group frontend template suitable for sidebar and content Layout positions.
- Added automatic active-state highlighting for the current member profile.
- Added a “View all” link to the selected category or main Team section.
- Added category labels when the menu displays members from all categories.
- Added Bulgarian and English administration and catalog language files.
- Added menu-module permissions and cleanup of saved menu instances during full Team uninstallation.
- Updated the Team administration menu, documentation and package version.

## 0.8.0

- Added versioned, idempotent database upgrades with an internal `module_probg_team_schema_version` marker.
- Added a Diagnostics tab for table, column, OCMOD, data-integrity and section-SEO checks.
- Added a safe **Check and repair** action for missing schema elements and invalid member-store relations.
- Changed global-settings persistence so multilingual section descriptions and SEO URL arrays are no longer duplicated in the standard `setting` table.
- Added recovery and cleanup for legacy duplicated description and SEO-array setting rows from v0.5.x–v0.7.x, preventing data loss when a normalized table is missing.
- Added server-side normalization for member limits, search limits and image dimensions.
- Added validation and filtering for submitted store and language identifiers.
- Improved store-assignment migration to repair only records with no assignment, preserving intentional visibility choices.
- Added automatic synchronization that removes member-store relations not allowed by the member category.
- Improved category and member store normalization before SEO records are saved.
- Fixed category pagination totals so title filtering matches the category-list query.
- Fixed SEO URL generation for additional query parameters, including arrays and pagination, using RFC 3986 encoding.
- Verified the OCMOD insertion points against the official OpenCart 3.0.2.0 and 3.0.3.9 core files.
- Added simulated runtime tests for section, category and member data preparation.
- Updated Bulgarian and English language files and documentation.

## 0.7.0

- Added real store assignments for team categories and team members.
- Added store-selection tabs to the category and member administration forms.
- Added automatic migration that assigns all existing categories and members to all configured stores, preserving previous visibility.
- Added current-store filtering to public category lists, member lists, profiles, Layout blocks, OpenCart search and XML sitemaps.
- Added validation that requires at least one valid store for every category and member.
- Added validation that member stores must be a subset of the stores enabled for the selected category.
- Added automatic member-visibility cleanup when a category is removed from a store.
- Added image-path normalization and traversal protection for main and additional member images.
- Strengthened required-language validation by checking every configured OpenCart language.
- Added the `team_category_to_store` and `team_member_to_store` relation tables.
- Updated Bulgarian and English administration language files and documentation.

## 0.6.0

- Separated global Team settings from standard OpenCart Layout module instances.
- Added configurable block instances with multilingual title, category filter, limit, columns, sorting and field visibility.
- Added multi-store section titles, descriptions, metadata and section SEO URLs.
- Added store-specific SEO URL tabs for categories and members.
- Added default-store fallback for empty additional-store section content.
- Added direct public preview buttons for the section, categories and members.
- Added a manual cache namespace refresh action.
- Added alphabetical and newest-member sorting to frontend blocks.
- Added category-aware fallback titles and links for filtered blocks.
- Reworked category and member administration controllers for clearer validation and safer missing-input handling.
- Preserved previous module permissions as a backward-compatible fallback for the new settings route.
- Updated Bulgarian and English administration language files and documentation.

## 0.5.0

- Added optional OpenCart caching for public team data and SEO lookups.
- Added cache namespace rotation after settings, category and member changes for cache-driver-independent invalidation.
- Added a standalone XML sitemap for the team section, categories and members.
- Added OCMOD integration with the standard OpenCart Google Sitemap feed.
- Added OCMOD integration with the standard OpenCart product search page.
- Added configurable search result limit and search visibility setting.
- Added configurable cache and sitemap settings.
- Added member search across title, descriptions, category, city and working hours.
- Added member administration filters for ID and city.
- Added member thumbnails, modified dates and sortable administration columns.
- Added member totals to the category administration list.
- Updated Bulgarian and English language files and documentation.

## 0.4.0

- Added multilingual working hours to team members.
- Added a setting for showing or hiding working hours.
- Added Open Graph and Twitter Card metadata.
- Added Schema.org JSON-LD for section, category and member pages.
- Added responsive public-card, gallery and contact styling.
- Added automatic database migration for the working-hours column and new settings.

## 0.3.0

- Added automatic meta-title fallback for the section, categories and members.
- Added automatic section SEO URL generation from the section title.
- Added automatic category SEO URL generation from the transliterated category title.
- Added automatic member SEO URL generation in `ID-transliterated-title` format.
- Added Bulgarian and Cyrillic transliteration with URL-safe normalization.
- Added automatic numeric suffixes for collisions between generated SEO keywords in the same store and language.
- Preserved manually entered meta titles and SEO URLs.
- Added defensive server-side generation in the models, independent of JavaScript.
- Updated Bulgarian and English SEO field help text.

## 0.2.0

- Added the public team-section page.
- Added public category pages with member pagination.
- Added public member profiles and additional-image galleries.
- Added breadcrumbs, metadata and canonical URLs.
- Added configurable visibility for telephone, city, website and social profiles.
- Added a frontend layout module for selected members.
- Added three-level SEO URL encoding and decoding through OCMOD.
- Added hierarchy validation, HTTP 404 handling and canonical HTTP 301 redirects.
- Added Bulgarian and English catalog language files.

## 0.1.0

- Added installation and database tables.
- Added multilingual section settings.
- Added category administration.
- Added member administration.
- Added SEO URL storage.
