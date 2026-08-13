# FacetWP Filtering on the User-Experience Archive by Wikidata Properties — Implementation Plan

**Status:** Draft for review (v2 — archive scope)
**Scope:** `archive-user-experience.php` + new `inc/facetwp.php` (+ `functions.php` require)
**Plugin dependency:** FacetWP (licensed; already used elsewhere on the site)

---

## 1. Context

The client wants the FacetWP interface on **`/user-experience/`** — the existing
ACF-registered CPT archive rendered by `archive-user-experience.php`. Visitors
should be able to filter the archive by **Wikidata properties**: Genre (P136),
Depicts (P180), Instance of (P31), Country of Origin (P495, fallback P17),
Language (P407), etc.

### The core challenge

Each `user-experience` post links to Wikidata via the `wikidata-qid` ACF meta
(`inc/wikidata.php:6`). The actual property values are **not** stored in
`postmeta` or taxonomies — they live inside the `wikidata_entities` custom
table as the raw Wikidata claims JSON in the `json_data` LONGTEXT column
(`inc/wikidata/table.php:35-47`).

FacetWP can only index facet values sourced from `postmeta` / taxonomies into
its own `facetwp_index` table — it cannot read `wikidata_entities` directly.
However, **FacetWP filtering is index-table-driven**: a chosen facet value is
resolved by looking up matching post IDs in `facetwp_index`, which are then
applied to the active query. So the entire problem reduces to *feeding the
derived Wikidata property values into `facetwp_index` at indexing time*.

### Answers to the key design questions

- **Do we convert the page to something custom?** No. `archive-user-experience.php`
  is a standard WP archive and FacetWP supports those natively — it auto-detects
  the main query loop, and facets combine with it.
- **How do we filter on a separate table's JSON?** We never query
  `wikidata_entities` at filter time. Instead we inject the derived property
  values into the FacetWP index via the `facetwp_index_row` hook (with
  `$class->insert()` per value, skipping the default row). Once indexed,
  FacetWP's standard index lookups do the filtering.
- **No data duplication?** Correct. `wikidata_entities` remains the single
  source of truth; no postmeta denormalization and no schema migration.
- **What if FacetWP is inactive?** The archive falls back to its current native
  loop and `understrap_pagination()`.

---

## 2. Decisions

| Topic | Decision |
|---|---|
| Filter page | `/user-experience/` CPT archive (`archive-user-experience.php`) |
| Index approach | Derive property values from `wikidata_entities.json_data` during indexing via `facetwp_index_row` (`$class->insert()` per value, `return false` to skip the default row). Prefer `facetwp_indexer_row_data` if the installed version supports it |
| Index trigger | Each Wikidata facet's data source is `cf/wikidata-qid` (a real meta key), so FacetWP only produces rows for posts that actually have Wikidata data |
| Facet value vs label | `facet_value` = property **QID** (stable, appears in URL); `facet_display_value` = resolved **label** (via existing `wikidata_entity_get_claim_entity_items`) |
| Wikidata property facets | `wd_instance`, `wd_genre`, `wd_depicts`, `wd_country` (P495 → P17 fallback), `wd_language` — all dropdowns |
| Taxonomy facets | `experience` (Experience) and `narrative_technology` (Narrative Technology) — dropdowns, kept alongside Wikidata facets |
| Reset / Pager | `reset` facet (Dropdown-style Reset) and a FacetWP `pager` facet |
| Facet UI | Dropdowns for all filter facets (matches existing site style) |
| Layout | `col-md-3` faceting sidebar + `col-md-9` results; stacks on mobile |
| Pagination | Replace `understrap_pagination()` with a FacetWP **Pager** facet (inside the template container) when FacetWP is active |
| Facet config location | Code-registered via `facetwp_facets` (committed to repo, survives env resets, shown as "locked") |
| Index freshness | Re-index the post on save after the Wikidata upsert completes via `FWP()->indexer->index( $post_id )` |
| Entity pages (`/wikidata/{qid}`) | Out of scope in this plan (v1 shelved); taxonomy FacetWP work on entity pages can be revisited separately |
| Degradation | Native loop + native pagination when FacetWP is inactive |

---

## 3. Implementation

### 3.1 New file: `inc/facetwp.php`

Required from `functions.php` (next to `inc/acf.php`, `inc/wikidata.php`).
All FacetWP-facing code guarded with `function_exists( 'FWP' )`.

#### a) Property → facet map

```php
if ( ! defined( 'WONDERCAT_WD_FACETS' ) ) {
	define( 'WONDERCAT_WD_FACETS', array(
		'wd_instance' => array(
			'label' => __( 'Instance of', 'understrap' ),
			'props' => array( 'P31' ),
		),
		'wd_genre' => array(
			'label' => __( 'Genre', 'understrap' ),
			'props' => array( 'P136' ),
		),
		'wd_depicts' => array(
			'label' => __( 'Depicts', 'understrap' ),
			'props' => array( 'P180' ),
		),
		'wd_country' => array(
			'label' => __( 'Country of Origin', 'understrap' ),
			'props' => array( 'P495' ),
			'fallback' => array( 'P17' ),
		),
		'wd_language' => array(
			'label' => __( 'Language', 'understrap' ),
			'props' => array( 'P407' ),
		),
	) );
}
```

#### b) Register facets (`facetwp_facets`)

The `source => 'cf/wikidata-qid'` on Wikidata facets is the trick that makes
FacetWP generate a row for exactly the posts that have a QID; our index filter
then replaces that row with the derived property rows.

```php
add_filter( 'facetwp_facets', function ( $facets ) {
	foreach ( WONDERCAT_WD_FACETS as $name => $spec ) {
		$facets[] = array(
			'name'     => $name,
			'label'    => $spec['label'],
			'type'     => 'dropdown',
			'source'   => 'cf/wikidata-qid',
			'operator' => 'and',
		);
	}

	$facets[] = array(
		'name'     => 'experience',
		'label'    => __( 'Experience', 'understrap' ),
		'type'     => 'dropdown',
		'source'   => 'tax/experience',
		'operator' => 'and',
	);
	$facets[] = array(
		'name'     => 'narrative_technology',
		'label'    => __( 'Narrative Technology', 'understrap' ),
		'type'     => 'dropdown',
		'source'   => 'tax/technology',
		'operator' => 'and',
	);

	$facets[] = array(
		'name'  => 'pager',
		'label' => __( 'Pager', 'understrap' ),
		'type'  => 'pager',
	);
	$facets[] = array(
		'name'  => 'reset',
		'label' => __( 'Reset', 'understrap' ),
		'type'  => 'reset',
	);

	return $facets;
} );
```

> **Facet name uniqueness:** facet names are global across the site. Before
> landing, check the facet names already registered elsewhere and align
> `experience`, `narrative_technology`, `pager`, `reset` with any existing ones
> so the shared index data is reused rather than duplicated/colliding.

#### c) Feed derived values into the index (`facetwp_index_row`)

Runs per facet × post during a full re-index and during per-post auto-indexing.
Reuses existing Wikidata template helpers — no new Wikidata logic required.

```php
add_filter( 'facetwp_index_row', function ( $params, $class ) {
	$map = WONDERCAT_WD_FACETS;

	if ( ! isset( $map[ $params['facet_name'] ] ) ) {
		return $params; // Non-Wikidata facets: leave untouched (taxonomies, etc.).
	}

	$post_id = $params['post_id'];
	$qid     = get_post_meta( $post_id, WONDERCAT_QID_FIELD, true );

	if ( ! $qid ) {
		return false; // No Wikidata data → index nothing for this facet.
	}

	$entity = wikidata_get_by_qid( $qid );

	if ( ! $entity || empty( $entity->json_data ) ) {
		return false;
	}

	$entity_data = wikidata_decode_entity_row( $entity, $qid );

	if ( ! is_array( $entity_data ) ) {
		return false;
	}

	$spec  = $map[ $params['facet_name'] ];
	$items = array();
	$seen  = array();

	foreach ( $spec['props'] as $property ) {
		foreach ( wikidata_entity_get_claim_entity_items( $entity_data, $property ) as $item ) {
			if ( ! isset( $seen[ $item['qid'] ] ) ) {
				$seen[ $item['qid'] ] = true;
				$items[]              = $item;
			}
		}
	}

	// Country fallback: try P17 when P495 is empty.
	if ( empty( $items ) && ! empty( $spec['fallback'] ) ) {
		foreach ( $spec['fallback'] as $property ) {
			foreach ( wikidata_entity_get_claim_entity_items( $entity_data, $property ) as $item ) {
				if ( ! isset( $seen[ $item['qid'] ] ) ) {
					$seen[ $item['qid'] ] = true;
					$items[]              = $item;
				}
			}
		}
	}

	foreach ( $items as $item ) {
		$params['facet_value']         = $item['qid'];   // Technical value in URL.
		$params['facet_display_value'] = $item['label']; // Shown in the dropdown.
		$class->insert( $params );
	}

	return false; // Skip the default wikidata-qid row.
}, 10, 2 );
```

> `wikidata_entity_get_claim_entity_items()` already resolves labels (site
> language with English fallback) and batch-prefetches any referenced entities
> missing locally (`inc/wikidata/template-tags.php:736-767`).

> **Alternative (`facetwp_indexer_row_data`, v4+):** if the installed FacetWP
> exposes this hook, use it instead — build rows from `$params['defaults']`,
> append each to `$rows`, and return `$rows` (no `return false` semantics
> involved). Verify `$params['defaults']` includes `post_id` for the installed
> version during implementation.

#### d) Index freshness after save (ordering gotcha)

`wondercat_process_qid_field` runs on `acf/save_post` at **priority 20**
(`inc/wikidata.php:80`) and fetches/upserts the Wikidata JSON *after* FacetWP's
own save-time auto-index (priority 10). Without extra handling, the index would
be built from stale or absent JSON. Re-index after the upsert at priority 30:

```php
function wondercat_fq_reindex_post( $post_id ) {
	if ( function_exists( 'FWP' ) && is_object( FWP()->indexer ) ) {
		$post_id = absint( $post_id );
		if ( $post_id > 0 ) {
			FWP()->indexer->index( $post_id ); // FacetWP: re-index a single post.
		}
	}
}
add_action( 'acf/save_post', 'wondercat_fq_reindex_post', 30, 1 );
```

Optional (keep labels/values current when the background Wikidata refresh
rewrites a row): also re-index referencing posts from
`WONDERCAT_WIKIDATA_REFRESH_QID_HOOK` / `WONDERCAT_WIKIDATA_REFRESH_BATCH_HOOK`
at priority 20.

### 3.2 Edit `archive-user-experience.php`

Facets go **outside** the `facetwp-template` container (a FacetWP requirement);
the loop — including the `else` branch — goes **inside** so AJAX can replace it.
The FacetWP Pager replaces Understrap's pagination when active.

```php
<main class="site-main" id="main">

	<?php if ( function_exists( 'facetwp_display' ) ) : ?>

		<header class="page-header">
			<h1 class="page-title"><?php esc_html_e( 'Archive: Story Experiences', 'understrap-child' ); ?></h1>
		</header><!-- .page-header -->

		<div class="row">
			<aside class="col-md-3">
				<?php
				foreach ( array_keys( WONDERCAT_WD_FACETS ) as $facet_name ) {
					echo facetwp_display( 'facet', $facet_name );
				}
				echo facetwp_display( 'facet', 'experience' );
				echo facetwp_display( 'facet', 'narrative_technology' );
				echo facetwp_display( 'facet', 'reset' );
				?>
			</aside>

			<div class="col-md-9">
				<div class="facetwp-template">
					<?php if ( have_posts() ) : ?>
						<?php
						while ( have_posts() ) {
							the_post();
							get_template_part( 'loop-templates/content-user-experience' );
						}
						?>
					<?php else : ?>
						<?php get_template_part( 'loop-templates/content', 'none' ); ?>
					<?php endif; ?>
					<?php echo facetwp_display( 'facet', 'pager' ); ?>
				</div>
			</div>
		</div><!-- .row -->

	<?php else : ?>
		<?php
		// Fallback: existing native loop + understrap_pagination() (current behavior).
		?>
	<?php endif; ?>

</main>
```

FacetWP's archive detection normally adds `facetwp-template` itself via a
`<!--fwp-loop-->` marker; per the docs ("Fix the loop detection") we add the
class manually for robustness.

### 3.3 Local development

- Add FacetWP to `.wp-env.override.json` (alongside the other licensed plugins)
  so filtering can be tested in the wp-env environment.
- Verify existing facet names on the site and align `experience`,
  `narrative_technology`, `pager`, `reset` (see 3.1b note).

---

## 4. Verification checklist

- [ ] FacetWP active; code-registered facets visible in **FacetWP → Settings**,
      marked as **locked** (code-registered).
- [ ] Run **Re-index** once after first activation.
- [ ] `/user-experience/` renders the facet sidebar and listing inside a
      `.facetwp-template` container.
- [ ] `Instance of`, `Genre`, `Depicts`, `Country of Origin`, `Language`
      dropdowns show resolved **labels** (not QIDs) and filter the listing.
- [ ] Selecting across multiple facets returns the intersection.
- [ ] Posts **without** a QID appear in the unfiltered archive but correctly
      drop out as soon as a Wikidata-property facet is selected.
- [ ] Pager facet paginates and survives AJAX refresh (URL hash preserves
      selections).
- [ ] Saving a `user-experience` post (e.g. changing its QID) updates its
      facet rows without a manual re-index.
- [ ] Deactivating FacetWP returns the archive to the current native loop +
      pagination.
- [ ] `composer php-lint` passes; `vendor/bin/phpcs` scoped to changed files
      clean (note: repo-wide `composer phpcs` fatals on
      `inc/wikidata/utilities.php` — pre-existing, unrelated).

---

## 5. Risks & gotchas

- **Index ordering on save** — handled by the priority-30 re-index (3.1d).
  Without it, QID changes would not appear until a manual re-index.
- **Remote fetches during indexing** — `wikidata_entity_get_claim_entity_items`
  prefetches referenced entities missing locally, so the first full re-index
  may make chunked, cached API calls. Existing TTL/cron refresh machinery
  applies.
- **Facet name collisions** — facet names are global. Reuse/align with names
  already used on the site instead of inventing duplicates.
- **`facetwp_index_row` only fires when the source returns a non-empty value** —
  exactly why the Wikidata facets use `cf/wikidata-qid` as their source.
- **Duplicate display labels** — two referenced QIDs sharing one label render
  as two identical-looking dropdown options (both still filter correctly).
  Accepted unless the label collision rate becomes a UX problem.
- **`facetwp_indexer_row_data`** requires a recent FacetWP v4.x; fall back to
  `facetwp_index_row` + `$class->insert()` on older builds. Confirm the
  installed version during implementation.
- **No changes to `inc/wikidata/`** — therefore no
  `inc/wikidata/docs` sync required. If we later generalize a "multi-property
  claim items" helper into `template-tags.php`, run the wikidata-doc-sync skill
  first.
- **i18n / PHPCS** — new strings use the `understrap` text domain (see
  AGENTS.md convention). Run PHPCS scoped to changed files.

---

## 6. Out of scope / future

- **Wikidata entity pages** (`/wikidata/{qid}`, v1 of this plan): shelved.
  A FacetWP-enabled experience list on entity pages can be revisited
  separately.
- **Publication year / date (P577)**: not included initially. It uses a time
  datatype rather than entity references and would likely want a Date Range or
  Slider facet. Consider as a follow-up.