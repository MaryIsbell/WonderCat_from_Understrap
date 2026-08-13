# FacetWP Archive Filtering by Wikidata Properties

Audience: maintainers working on the `/user-experience/` archive filtering and FacetWP integration.

Status: **Implemented**. Supersedes `docs/facetwp-wikidata-plan.md` (removed). Plugin dependency: **FacetWP 4.5** (licensed; used elsewhere on the site). All FacetWP-facing code lives in `inc/facetwp.php`, loaded from `functions.php`.

This document is a code-aligned map of how the archive is filtered: facet registration, index-time derivation of Wikidata property values, and index freshness on post saves.

## Why the index is the integration point

Each `user-experience` post links to Wikidata via the `wikidata-qid` ACF meta. Property values (Genre P136, Depicts P180, Instance of P31, Country P495→P17, Language P407) are **not** stored in `postmeta` or taxonomies — they live in the `wikidata_entities` custom table as raw claims JSON (`json_data`).

FacetWP only filters through its own `facetwp_index` table. The problem therefore reduces to *feeding derived property values into the index at indexing time*:

1. Each Wikidata facet uses `cf/wikidata-qid` as its data source, so FacetWP only generates rows for posts that actually have a QID.
2. The `facetwp_indexer_row_data` filter replaces that default row with one row per derived property value.
3. At filter time, FacetWP's standard index lookups do the work — `wikidata_entities` is never queried and no postmeta denormalization happens.

`wikidata_entities` remains the single source of truth.

## Facet inventory

All facet names are namespaced with `wondercat_` to avoid collisions with facets elsewhere on the site. Wikidata facets resolve `facet_value` = referenced QID (stable, appears in URL) and `facet_display_value` = resolved label.

| Facet name | Label | Type | Source | Properties |
|---|---|---|---|---|
| `wondercat_wd_instance` | Instance of | dropdown | `cf/wikidata-qid` | P31 |
| `wondercat_wd_genre` | Genre | dropdown | `cf/wikidata-qid` | P136 |
| `wondercat_wd_depicts` | Depicts | dropdown | `cf/wikidata-qid` | P180 |
| `wondercat_wd_country` | Country of Origin | dropdown | `cf/wikidata-qid` | P495 (fallback P17) |
| `wondercat_wd_language` | Language | dropdown | `cf/wikidata-qid` | P407 |
| `wondercat_experience` | Experience | dropdown | `tax/experience` | — |
| `wondercat_narrative_technology` | Narrative Technology | dropdown | `tax/technology` | — |
| `wondercat_pager` | Pager | pager | — | — |
| `wondercat_reset` | Reset | reset | — | — |

The Wikidata facet spec map is stored in the `WONDERCAT_WD_FACETS` constant (`inc/facetwp.php`), keyed by facet name with `label`, `props`, and optional `fallback` (used for Country: P495 → P17 when P495 is empty).

## Facet registration (`facetwp_facets`)

`wondercat_register_facetwp_facets` appends code-registered facets on `facetwp_facets`. Because they are registered in code, they appear in **FacetWP → Settings** as **locked** and cannot be edited in the admin — this is the intended source of truth (per client decision).

### Gotcha: code-registered facets skip FacetWP's admin defaults

Admin-saved facets carry every setting FacetWP's renderers read (they are persisted with the form's defaults). Code-registered facets get only the keys you supply, and several renderers access settings unguarded, emitting `Undefined array key` warnings. Verified against FacetWP 4.5, the required keys are:

- Dropdown facets: `orderby` → `'count'` (read via `FacetWP_Facet::get_orderby()`, `facets/base.php:15`).
- Pager facet: `pager_type` → `'numbers'`, plus `inner_size`, `dots_label`, `prev_label`, `next_label` (`facets/pager.php`).
- Reset facet: `reset_ui` → `'link'` (`facets/reset.php:14`).

Add these whenever registering new facets in code.

## Indexing derived values (`facetwp_indexer_row_data`)

`wondercat_index_wikidata_facet_rows` (FacetWP 4.5 hook) runs per facet × post during a full re-index and per-post auto-indexing:

1. Bails for non-Wikidata facets (returns `$rows` unchanged) — taxonomies index natively.
2. Reads `wikidata-qid` meta; bails with no rows when missing.
3. Looks up the entity row via `wikidata_get_by_qid` and decodes it with `wikidata_decode_entity_row`.
4. Collects claim entity items per property via `wikidata_entity_get_claim_entity_items` (which resolves labels in site language with English fallback and batch-prefetches missing referenced entities), deduplicating by QID.
5. Applies the P17 fallback for country only when P495 yielded nothing.
6. Builds one index row per item from `$params['defaults']`, setting `facet_value` = QID and `facet_display_value` = label.

Posts with a QID but no matching property values (and posts without a QID) produce **zero rows** for the Wikidata facets. They still appear in the unfiltered archive but drop out as soon as a Wikidata-property facet is selected.

## Index freshness on save

Save-time indexing has an ordering problem: FacetWP's own auto-index and ACF's meta save can run before the Wikidata upsert completes, so the index would be built from stale or absent JSON.

| Hook | Priority | Callback | Purpose |
|---|---|---|---|
| `acf/save_post` | 30 | `wondercat_reindex_post` | Re-index a single `user-experience` post after `wondercat_process_qid_field` (priority 20) upserts the JSON |
| `gform_advancedpostcreation_post_after_creation` | 10 | `wondercat_reindex_after_post_creation` | For frontend-submitted experiences: run the QID upsert, then re-index |

### Why the Gravity Forms hook is required

Advanced Post Creation (form **1**, "Enter a Story Experience") creates `user-experience` posts and writes the mapped ACF post meta directly. It does **not** fire `acf/save_post`, so without this hook frontend-submitted experiences would never have their Wikidata JSON fetched nor their facet rows built. `wondercat_reindex_after_post_creation` calls the existing `wondercat_process_qid_field` (from `inc/wikidata.php`) first, then re-indexes.

Both callbacks restrict indexing to `user-experience` posts and guard on `FWP()` availability.

## Template integration (`archive-user-experience.php`)

When `facetwp_display()` exists:

- Facets render **outside** the `.facetwp-template` container (a FacetWP requirement), in a `col-md-3` sidebar (`#facetwp-sidebar`) on the **right** of the results column, ordered: Wikidata property facets, Experience, Narrative Technology, Reset. The results (`col-md-9`) come first in the DOM so the panel sits to the right; the column stacks below the results on mobile.
- FacetWP does not render a visible label above a dropdown, so the template emits an `<h2 class="facetwp-facet-label">` heading above each facet block to identify the filter in the UI.
- The post loop — including the `else`/no-results branch — renders **inside** `.facetwp-template` (required so AJAX can replace it), in a `col-md-9` column. The Pager facet renders **after** (outside) the template container, still within `col-md-9` — every facet, including the pager, must live outside `.facetwp-template` or FacetWP logs "Facets should not be inside the facetwp-template container" and the placeholder gets wiped on AJAX refresh.
- Layout stacks on mobile via Bootstrap's responsive grid.

The `.facetwp-template` class is added manually for robust loop detection.

## Degradation

When FacetWP is inactive, `function_exists( 'facetwp_display' )` is false and the template renders the original native loop with `understrap_pagination()` and the theme-mod sidebar checks. `inc/facetwp.php` is loaded unconditionally but every FWP call is guarded.

## Verification

- [ ] Run **FacetWP → Re-index** once after first activation.
- [ ] `/user-experience/` renders the facet sidebar and listing inside `.facetwp-template`.
- [ ] Wikidata dropdowns show resolved labels and filter the listing; selections across facets intersect.
- [ ] Posts without a QID appear unfiltered but drop out when a Wikidata-property facet is selected.
- [ ] Pager facet paginates and survives AJAX refresh.
- [ ] Saving a `user-experience` post (QID change) updates its facet rows without a manual re-index; same for a new frontend form submission.
- [ ] Deactivating FacetWP returns the archive to the native loop + pagination.

## Operational notes

- The first full re-index may make chunked, cached API calls: `wikidata_entity_get_claim_entity_items` prefetches referenced entities missing locally. Existing TTL/cron refresh machinery applies.
- Label staleness after a background Wikidata refresh is accepted until the next save/re-index (facet values are QIDs and remain stable; only display labels can drift).
- `composer php-lint` passes; `vendor/bin/phpcs` scoped to `inc/facetwp.php`, `functions.php`, and `archive-user-experience.php` is clean.

## Out of scope / future

- **Wikidata entity pages** (`/wikidata/{qid}`): FacetWP-enabled experience lists there are not included.
- **Publication year / date (P577)**: not included; uses a time datatype and would want a Date Range or Slider facet.
- **`benefit` taxonomy**: registered and public but not surfaced as a facet.
- **Custom SCSS**: FacetWP's default dropdown styling is used for now.
