A child theme of [Understrap](https://understrap.com/), customized for [WonderCat](https://wonder-cat.org/).

## Local Development With wp-env

This repository includes a committed [../.wp-env.json](../.wp-env.json) configuration for local WordPress development.

### Prerequisites

1. Docker Desktop (or another local Docker runtime) running.
2. Node.js 14+.
3. wp-env installed globally:
	- `npm -g install @wordpress/env`
4. Project dependencies installed:
	- `npm install`
	- `composer install`

### Start and Stop

1. Start local WordPress containers:
	- `npm run wp-env:start`
2. Stop containers:
	- `npm run wp-env:stop`
3. Destroy containers and local DB data:
	- `npm run wp-env:destroy`

Default URLs used by wp-env:

1. Site: `http://localhost:8888`
2. Admin: `http://localhost:8888/wp-admin`

### Theme and Plugin Provisioning

The committed wp-env config installs and maps:

1. Understrap parent theme from `https://downloads.wordpress.org/theme/understrap.1.2.4.zip`
2. This child theme as `wp-content/themes/wondercat`
3. Advanced Custom Fields (free) plugin from WordPress.org
4. Auto-activation of the `wondercat` theme on startup via wp-env lifecycle script
5. Automatic ACF JSON sync on startup using `scripts/wp-env/acf-sync.php`

### ACF JSON Import Behavior

ACF import is automated and does not depend on Gravity Forms:

1. On `npm run wp-env:start`, wp-env runs the theme activation check and then executes `scripts/wp-env/acf-sync.php`.
2. The script imports data from [../acf-json](../acf-json/) and stores a hash in WordPress options so repeated starts with unchanged JSON are no-op.
3. If you need to force a repair/import manually, run:
	- `npm run wp-env:acf-sync`
	- `npm run wp-env:acf-sync:force` (re-import even when JSON signature is unchanged)

### Manual Paid Plugin Setup

This theme also depends on licensed plugins that are not committed to the repository:

1. Advanced Custom Fields Pro
2. Gravity Forms core
3. Gravity Flow add-on
4. Gravity Forms Advanced Post Creation add-on

Install and activate these manually after wp-env starts.

You can keep local-only plugin mounting in `.wp-env.override.json` (already ignored by git).

Example override pattern:

```json
{
  "plugins": [
	 "/absolute/path/to/acf-pro",
	 "/absolute/path/to/gravityforms",
	 "/absolute/path/to/gravityflow",
	 "/absolute/path/to/gravityformsadvancedpostcreation"
  ]
}
```

### Required First-Run Checklist

1. Activate the child theme (`wondercat`) in Appearance > Themes.
	- Note: this is now automated on `wp-env:start`; verify in wp-admin if needed.
2. Confirm ACF field groups are available in Custom Fields after startup (import is automatic).
3. Set permalinks to a non-Plain structure in Settings > Permalinks.

### Optional Gravity Forms Setup (Not Required For ACF Import)

Import Gravity Forms from [../FormArchive/gravityforms-export-2026-01-22.json](../FormArchive/gravityforms-export-2026-01-22.json) only if you need Gravity Forms workflows.

Manual import commands:

1. Import all JSON exports found in [../FormArchive](../FormArchive):
	- `npm run wp-env:gf-import`
2. Force import without duplicate-title checks:
	- `npm run wp-env:gf-import:force`

Behavior notes:

1. The importer runs via `scripts/wp-env/gf-import.php` inside the wp-env CLI container.
2. Default behavior skips any form whose title already exists in Gravity Forms.
3. The command imports form definitions only; entries and add-on feed data are not imported.
4. Gravity Forms must be installed and active before running these commands.

1. Verify Gravity Forms ID assumptions are satisfied:
	- Form `8` fields `4` and `5` for taxonomy dropdown population in [../inc/acf.php](../inc/acf.php).
	- Form `1` fields `9`, `10`, `11`, `12`, and `25` for term history query behavior in [../term-version-history.php](../term-version-history.php).

### Troubleshooting ACF Sync

1. Re-run the sync script:
	- `npm run wp-env:acf-sync`
2. Force re-import if needed:
	- `npm run wp-env:acf-sync:force`
3. Confirm ACF plugin is active in wp-admin.
4. Check wp-env logs:
	- `npm run wp-env:logs`
5. If needed, destroy and restart the environment:
	- `npm run wp-env:destroy`
	- `npm run wp-env:start`

### Frontend Asset Workflow

1. Build assets once:
	- `npm run dist`
2. Watch assets:
	- `npm run watch`
3. Run BrowserSync against wp-env:
	- `npm run bs:wp-env`

The BrowserSync proxy is environment-driven via `BROWSERSYNC_PROXY`. Default remains `localhost/` for non-wp-env workflows.

### Notes

1. Wikidata entity routes depend on non-Plain permalinks due custom rewrite rules.
2. The custom Wikidata table is created on theme activation/init and must be present for Wikidata lookups.

## AI Transparency Links

All consultations with generative AI have been archived in numbered chat files that we are working to reference in comments in the code of these theme files. This is a work in progress, but the full chats are available here in the interest of transparency.

1. [Gravity Forms Taxonomy Hook](https://www.dropbox.com/scl/fi/ckah6g492u5e6i1x4lkgy/Gravity-Forms-Taxonomy-Hook_chatGPT_numbered.pdf?rlkey=ob2tgf2arxv285rgjolxkko7x&dl=0) (none of this worked, so the code proposed is not currently in use)
2. [HTML CSS from Image](https://www.dropbox.com/scl/fi/pcn66rpv02zvu2kbpyf6f/HTML-CSS-from-Image_chatGPT_numbered.pdf?rlkey=26io9oe7drynuvtmklkeuufel&dl=0)
3. [Link Author to Archive](https://www.dropbox.com/scl/fi/h1mrsteayhr02cu8umwlr/Link-Author-to-Archive_chatGPT_numbered.pdf?rlkey=q4r2ajrhrir7bm1qj2ipikrir&dl=0)
