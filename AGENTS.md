# AGENTS.md

## Project Overview

WonderCat is a WordPress child theme built on Understrap.

- Theme type: WordPress child theme (`Template: understrap`)
- Core integrations: Advanced Custom Fields (ACF), Gravity Forms, custom Wikidata integration
- Frontend stack: SCSS + Rollup + PostCSS + BrowserSync
- PHP quality tooling: PHPCS (WPCS + WPThemeReview), PHPStan, PHPMD

For general project background, see [.github/README.md](.github/README.md).

## Quick Start For Agents

1. Install dependencies:
   - `npm install`
   - `composer install`
2. Start local WordPress runtime:
   - `npm run wp-env:start`
3. Build assets:
   - `npm run dist`
4. Run PHP checks before finalizing PHP changes:
   - `composer php-lint`
   - `composer phpcs`
   - `composer phpstan`
   - `composer phpmd`

For BrowserSync with wp-env, use `npm run bs:wp-env`.

ACF JSON import is automated on `wp-env:start`; manual fallback: `npm run wp-env:acf-sync`.
Use `npm run wp-env:acf-sync:force` when you need to re-import unchanged JSON intentionally.

Gravity Forms is not required for ACF JSON import.

If licensed plugins are required for a task, follow the manual setup checklist in [.github/README.md](.github/README.md).

Use targeted commands when appropriate to keep iteration fast.

## High-Signal Paths

- Theme bootstrap and hooks: [functions.php](functions.php)
- ACF + Gravity Forms integration: [inc/acf.php](inc/acf.php)
- Wikidata integration entrypoint: [inc/wikidata.php](inc/wikidata.php)
- Wikidata modules: [inc/wikidata/](inc/wikidata/)
- Wikidata template tag docs: [inc/wikidata/docs/TEMPLATE-TAGS.md](inc/wikidata/docs/TEMPLATE-TAGS.md)
- ACF JSON source of truth: [acf-json/](acf-json/)
- Source assets (edit these first): [src/sass/](src/sass/), [src/js/](src/js/)
- Compiled assets: [css/](css/), [js/](js/)

## Theme-Specific Conventions

- Prefer editing source assets under `src/`; then rebuild to update `css/` and `js/` outputs.
- Follow WordPress escaping/sanitization conventions in templates and admin handlers.
- Keep existing text domains and i18n patterns consistent with surrounding code.
- Preserve Understrap template structure and function usage unless a task explicitly requires divergence.

## ACF + Gravity Forms Notes

- Gravity Forms field population is currently hard-wired to form `8` and specific field IDs in [inc/acf.php](inc/acf.php).
- When changing taxonomy-driven dropdown behavior, verify both filters and field IDs remain aligned.
- ACF Local JSON is configured to save/load from [acf-json/](acf-json/). Keep these files in sync with field group changes.

## Wikidata Notes

- QID constants and save hooks are defined in [inc/wikidata.php](inc/wikidata.php).
- The custom table and rewrite handling are loaded via [inc/wikidata/](inc/wikidata/).
- If changing Wikidata rendering, check template behavior in [wikidata-entity.php](wikidata-entity.php) and helper/template-tag usage.

## Change Safety Checklist

Before completing work:

1. Rebuild assets if `src/sass` or `src/js` changed.
2. Run relevant Composer checks for PHP changes.
3. Keep plugin assumptions explicit (ACF and Gravity Forms are expected active dependencies).
4. Do not remove or silently rename form IDs, field IDs, taxonomy slugs, or ACF field keys without updating dependent logic.

## Documentation Strategy

- Link to existing docs instead of duplicating them.
- For Wikidata template helper usage, prefer referencing [inc/wikidata/docs/TEMPLATE-TAGS.md](inc/wikidata/docs/TEMPLATE-TAGS.md).
- After completing implementation work, update existing docs or create new docs for behavior/API/workflow changes before finishing.
- For Wikidata-related changes, update or add docs under [inc/wikidata/docs/](inc/wikidata/docs/).
