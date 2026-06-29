# AGENTS.md

## Project Overview

WonderCat is a WordPress child theme built on Understrap.

- Theme: WordPress child theme (`Template: understrap`), directory name `wondercat`
- Custom post type: `user-experience`
- Core integrations: ACF, Gravity Forms, custom Wikidata integration (`inc/wikidata/`)
- Frontend: SCSS + Rollup + PostCSS + BrowserSync, Bootstrap 5
- PHP quality: PHPCS (WPCS + WPThemeReview), PHPStan (level max, `inc/` only), PHPMD (with baseline)

## Quick Start

```sh
npm install && composer install                  # deps (+ auto-configures PHPCS paths)
npm run copy-assets                               # copies Bootstrap/FA/Understrap SCSS -> src/sass/assets/ (required before first build)
npm run wp-env:start                              # starts Docker, activates wondercat theme, auto-syncs ACF JSON
npm run dist                                      # compiles css/ and js/ from src/
```

- ACF JSON import is idempotent (content-hash skip). Force with `npm run wp-env:acf-sync:force`.
- Gravity Forms import: `npm run wp-env:gf-import` (requires GF active; idempotent by title dedup)
- Licensed plugins (ACF Pro, GF, Gravity Flow, GF Advanced Post Creation): mount in `.wp-env.override.json` (gitignored)
- BrowserSync via wp-env: `npm run bs:wp-env` (proxies `localhost:8888`)
- Watch mode: `npm run watch`

## Key Paths

| Area | Path |
|---|---|
| Theme bootstrap | `functions.php` (also loads `inc/acf.php`, `inc/wikidata.php`) |
| ACF + GF integration | `inc/acf.php` |
| Wikidata entrypoint | `inc/wikidata.php` / `inc/wikidata/` |
| Wikidata template tags | `inc/wikidata/docs/TEMPLATE-TAGS.md` |
| ACF JSON source of truth | `acf-json/` |
| Source assets (edit these) | `src/sass/`, `src/js/` |
| Compiled assets | `css/`, `js/` |
| WP-env config | `.wp-env.json` (WordPress 6.8, PHP 8.2) |
| GF form archive | `FormArchive/` |

## Conventions

- Edit `src/sass/` and `src/js/`; rebuild with `npm run dist` to update compiled output.
- WordPress escaping/sanitization conventions apply everywhere.
- Keep existing text domains (`understrap-child`) and i18n patterns.
- Preserve Understrap template structure unless the task requires divergence.

## ACF + Gravity Forms

- Gravity Forms form `8` fields `4` (experience dropdown) and `5` (technology dropdown) are populated from taxonomies via filters in `inc/acf.php`.
- `term-version-history.php` (Template Name: Term Version History) reads form `1` fields `9,10,11,12` + field `25` for term proposal workflow.
- ACF Local JSON saves/loads from `acf-json/`. Keep these in sync with field group changes.

## Wikidata

- QID constants and save hooks in `inc/wikidata.php`. Requires ACF active.
- Custom `wikidata_entities` table + rewrite rules loaded via `inc/wikidata/`.
- Template-rendering entry points: `wikidata-entity.php` (direct entity page at `/wikidata/{qid}`), template tags in `inc/wikidata/`.
- Wikidata entity routes depend on non-Plain permalink structure.
- Custom table created on theme activation and checked on `init`.

## PHP Quality Checks (run before finalizing PHP changes)

```sh
composer php-lint        # parallel lint
composer phpcs           # WPCS + WPThemeReview
composer phpstan         # level max, inc/ only (has baseline)
composer phpmd           # PHPMD (has baseline)
```

- `composer phpcs-fix` auto-fixes where possible.
- `composer phpstan-baseline` / `composer phpmd-baseline` regenerate baselines.

## Notable Repo Quirks

- `package-lock.json` is gitignored (only `composer.lock` tracked).
- `dist/` and `dist-product/` are gitignored (release build artifacts).
- No CI workflows exist. No test framework.
- PHPMD excludes `src/`, `js/`, `css/`, `*-templates/`, `woocommerce/`.
- PHPStan excludes `inc/deprecated.php` and `inc/class-wp-bootstrap-navwalker.php`.
- Lifecycle on `wp-env:start`: activates `wondercat` theme, runs ACF sync.
- Theme name in wp-env CLI paths is `wondercat` (e.g. `wp-content/themes/wondercat/scripts/...`).

## Change Safety Checklist

1. Rebuild assets if `src/sass` or `src/js` changed (`npm run dist`).
2. Run Composer checks for PHP changes.
3. Do not rename/remove form IDs, field IDs, taxonomy slugs, or ACF field keys without updating dependent logic.
4. Update docs under `inc/wikidata/docs/` for Wikidata changes.
