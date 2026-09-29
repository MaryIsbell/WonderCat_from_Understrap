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
| `wondercat_search` | Search | search | WP Default + postmeta | — |
| `wondercat_wd_instance` | Instance of | fselect | `cf/wikidata-qid` | P31 |
| `wondercat_wd_genre` | Genre | fselect | `cf/wikidata-qid` | P136 |
| `wondercat_wd_depicts` | Depicts | fselect | `cf/wikidata-qid` | P180 |
| `wondercat_wd_country` | Country of Origin | fselect | `cf/wikidata-qid` | P495 (fallback P17) |
| `wondercat_wd_language` | Language | fselect | `cf/wikidata-qid` | P407 |
| `wondercat_experience` | Experience | fselect | `tax/experience` | — |
| `wondercat_narrative_technology` | Narrative Technology | fselect | `tax/technology` | — |

The filter dropdowns use FacetWP's built-in **fSelect** facet type (`multiple = yes`): FacetWP enqueues `fSelect.css`/`fSelect.js` and re-initializes the widgets on every `facetwp-loaded`, so selections (including multi-select) survive AJAX refreshes with no theme JS.
| `wondercat_pager` | Pager | pager | — | — |
| `wondercat_reset` | Reset | reset | — | — |

The Wikidata facet spec map is stored in the `WONDERCAT_WD_FACETS` constant (`inc/facetwp.php`), keyed by facet name with `label`, `props`, and optional `fallback` (used for Country: P495 → P17 when P495 is empty).

## Facet registration (`facetwp_facets`)

`wondercat_register_facetwp_facets` appends code-registered facets on `facetwp_facets`. Because they are registered in code, they appear in **FacetWP → Settings** as **locked** and cannot be edited in the admin — this is the intended source of truth (per client decision).

### Gotcha: code-registered facets skip FacetWP's admin defaults

Admin-saved facets carry every setting FacetWP's renderers read (they are persisted with the form's defaults). Code-registered facets get only the keys you supply, and several renderers access settings unguarded, emitting `Undefined array key` warnings. Verified against FacetWP 4.5, the required keys are:

- fSelect facets: `operator` → `'or'` (read unguarded via `FacetWP_Facet_fSelect::settings_js()`, `facets/fselect.php`), `orderby` → `'count'` (read through `FacetWP_Facet_Checkboxes::load_values()`), and `multiple` → `'yes'` for multi-select.
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

- Facets render **outside** the `.facetwp-template` container (a FacetWP requirement), in a `col-md-3` sidebar (`#facetwp-sidebar`) on the **right** of the results column, ordered: Search, Wikidata property facets, Experience, Narrative Technology, Reset. The results (`col-md-9`) come first in the DOM so the panel sits to the right; the column stacks below the results on mobile.
- FacetWP does not render a visible label above a dropdown, so the template emits an `<h2>` heading above each facet block that is given Bootstrap typography classes (`h6 text-uppercase fw-bold mb-2`) to identify the filter in the UI.
- The sidebar is wrapped in a Bootstrap `card` (with `card-body`, `shadow-sm`, `sticky-top`) so the filters stick while scrolling on desktop; each facet block uses `mb-3` spacing.
- FacetWP-generated controls are restyled via the `facetwp_facet_html` filter (`wondercat_bootstrap_facet_html` in `inc/facetwp.php`): the reset control gets `btn btn-outline-dark`, and the search input gets `form-control`. The fSelect filter dropdowns use FacetWP's built-in fSelect widget (their native `<select>` is hidden after initialization), so they are themed instead via scoped `.facetwp-type-fselect` SCSS in `src/sass/theme/_child_theme.scss` (`fs-wrap` set to 100% width, `.fs-label-wrap`/`.fs-dropdown` matching the Bootstrap `form-select` look, checkbox accent for the multi-select mode). The original `facetwp-*` classes are preserved so FacetWP's frontend JS keeps working.
- The post loop — including the `else`/no-results branch — renders **inside** `.facetwp-template` (required so AJAX can replace it), in a `col-md-9` column. The Pager facet renders **after** (outside) the template container, still within `col-md-9` — every facet, including the pager, must live outside `.facetwp-template` or FacetWP logs "Facets should not be inside the facetwp-template container" and the placeholder gets wiped on AJAX refresh.
- Layout stacks on mobile via Bootstrap's responsive grid.

The `.facetwp-template` class is added manually for robust loop detection.

## Degradation

When FacetWP is inactive, `function_exists( 'facetwp_display' )` is false and the template renders the original native loop with `understrap_pagination()` and the theme-mod sidebar checks. `inc/facetwp.php` is loaded unconditionally but every FWP call is guarded.

## Verification

- [ ] Run **FacetWP → Re-index** once after first activation.
- [ ] `/user-experience/` renders the facet sidebar and listing inside `.facetwp-template`.
- [ ] Search matches `feature`/`title_of_creative_work`/`benefit_of_experience` text and title-only terms; results auto-refresh on typing and clear cleanly.
- [ ] Wikidata fSelect dropdowns show resolved labels and filter the listing; multi-select is per-facet OR ("match any") while selections across facets intersect. Choices with zero results are disabled; the fSelect search box narrows options.
- [ ] Posts without a QID appear unfiltered but drop out when a Wikidata-property facet is selected.
- [ ] Pager facet paginates and survives AJAX refresh.
- [ ] Saving a `user-experience` post (QID change) updates its facet rows without a manual re-index; same for a new frontend form submission.
- [ ] Deactivating FacetWP returns the archive to the native loop + pagination.

## Operational notes

- The first full re-index may make chunked, cached API calls: `wikidata_entity_get_claim_entity_items` prefetches referenced entities missing locally. Existing TTL/cron refresh machinery applies.
- Label staleness after a background Wikidata refresh is accepted until the next save/re-index (facet values are QIDs and remain stable; only display labels can drift).
- `composer php-lint` passes; `vendor/bin/phpcs` scoped to `inc/facetwp.php`, `functions.php`, and `archive-user-experience.php` is clean.

- **Publication year / date (P577)**: not included; uses a time datatype and would want a Date Range or Slider facet.
- **`benefit` taxonomy**: registered and public but not surfaced as a facet.

## Search facet (`wondercat_search`)

Rendered at the top of the sidebar card (`archive-user-experience.php`), before the Wikidata dropdowns. Registered in code with `type` `search`, `search_engine` empty (**WP Default**), `auto_refresh` `yes` (500 ms debounce on keystroke), `enable_relevance` `yes`.

### Why an extra postmeta clause is needed

Native WordPress search only covers `post_title`/`post_content`/`post_excerpt`, and `user-experience` posts have no content/excerpt — the readable text lives in ACF postmeta. Without extending the query, search would match titles only.

`wondercat_search_query_args` (on `facetwp_search_query_args`) scopes the facet's `WP_Query` to `user-experience`, raises `posts_per_page` from FacetWP's hardcoded 200 cap to 500, and registers a one-time `posts_search` filter:

- `wondercat_search_posts_search`: wraps the native search WHERE with an `EXISTS` subquery (`meta_key IN ('feature','title_of_creative_work','benefit_of_experience') AND meta_value LIKE <term>`) OR'd into the native term match. A `meta_query` alone would AND with the title clause; the OR-in-WHERE wrapper keeps base query conditions (post type/status) intact. The `EXISTS` form avoids the duplicate post rows a `wp_postmeta` JOIN would produce. The filter removes itself inside the callback so only the search facet query is affected.

Matching is effectively `post_title` OR `feature` OR `title_of_creative_work` OR `benefit_of_experience` (case-insensitive LIKE). Matched posts are capped at 500 via the facet's own `WP_Query`; there is **no** interaction with `wp_facetwp_index` and no re-index is required for changes to this facet. The `facetwp-*` classes of the rendered input are preserved (per the Bootstrap restyling above) so FacetWP's `facetwp/refresh/search` handler keeps working.

## JSON REST endpoints

Two public, read-only REST endpoints expose the same filtering used by the archive sidebar, for external consumers that want JSON instead of the HTML/AJAX widget.

### `GET /wp-json/wondercat/v1/experiences`

Registered in `inc/facetwp.php` (`wondercat_register_experiences_rest_route`). Accepts one query param per facet name from the table above (`wondercat_wd_instance`, `wondercat_wd_genre`, `wondercat_wd_depicts`, `wondercat_wd_country`, `wondercat_wd_language`, `wondercat_experience`, `wondercat_narrative_technology`, `wondercat_search`), each an array of selected values (e.g. `?wondercat_wd_genre[]=q175173&wondercat_wd_genre[]=q744038` — see the QA findings below for why these are lowercase), plus `page` and `per_page` (max 100). The callback (`wondercat_experiences_rest_callback`) dispatches an internal `rest_do_request()` call to FacetWP's own `POST /facetwp/v1/fetch` route (below) — the same filtering engine FacetWP's `/facetwp/v1/refresh` AJAX handler uses — so results always match what the archive UI would show for the same selection. (FacetWP 4.5 has no `FWP()->request_handler`; an earlier version of this callback called that nonexistent property, which raised a PHP warning that corrupted the JSON response body — see QA findings.)

Unlike FacetWP's own results (which are just post IDs), the response expands each matched post into: `title`, `permalink`, `featured_image`, the `feature`/`benefit_of_experience`/`title_of_creative_work` ACF fields (HTML entities decoded), `wikidata_qid`, `experience`/`technology` taxonomy terms, and a `wikidata` map of facet name to `{qid, label, url}` items (always present for all `WONDERCAT_WD_FACETS` keys, even when the post has no QID) — via `wondercat_get_wikidata_facet_items`, the same helper the indexer uses, including the country P495 → P17 fallback. The response also includes FacetWP's own `facets` (choices + counts, always populated for every registered facet regardless of what was selected) and `pager` blocks, unmodified, so a consumer can build its own filter UI from a single request. Results are restricted to `post_status = publish` regardless of the requesting user, since the endpoint has no authentication.

See `inc/wikidata/docs/FACETWP-API-CONSUMER-GUIDE.md` for the external, collaborator-facing "how to" reference (parameters, response schema, pagination, and curl/R/Python recipes).

### `POST /wp-json/facetwp/v1/fetch`

FacetWP's native endpoint, enabled via `add_filter( 'facetwp_api_can_access', '__return_true' )` in `inc/facetwp.php` (disabled by default upstream). Takes a stringified JSON `data` param with `facets` and `query_args` keys and returns raw `results` (post IDs) + `facets` + `pager` — no post enrichment. Useful for low-level facet-count introspection without the enrichment cost of `/wondercat/v1/experiences`. See [FacetWP's REST API docs](https://facetwp.com/help-center/developers/facetwp-rest-api/) for the request/response shape.

## Known Issues / QA Findings

Found via `scripts/test-facetwp-endpoint.js` (`npm run test:facetwp-endpoint`), a QA script that exercises `GET /wp-json/wondercat/v1/experiences` against a running `wp-env` instance and checks response schema, cross-item type consistency, encoding, pagination clamping, and FacetWP filtering correctness — targeted at consumers (like a Shiny/R app) that parse the raw JSON rather than rendering the archive UI. Re-run it after any change to `inc/facetwp.php`'s REST callback or `wondercat_build_experience_rest_item()`.

**Fixed during this QA pass:**

- `FWP()->request_handler->request()` doesn't exist in FacetWP 4.5 (verified: `request_handler` isn't a property on the `FWP()` singleton at all). Every call to the endpoint returned a 503 with a PHP warning (`Undefined property: FacetWP::$request_handler`) printed *before* the JSON body, corrupting it for any strict JSON parser. Fixed by dispatching an internal `rest_do_request()` call to FacetWP's own `POST /facetwp/v1/fetch` route instead (see `wondercat_experiences_rest_callback`).
- `FacetWP_API_Fetch::process_request()` only initializes `FWP()->facet->facets` when at least one facet is selected; an endpoint request with **no** facet filters left it `null`, and FacetWP's own `get_filtered_post_ids()` then does `foreach ( $this->facets as ... )` on `null`, emitting another body-corrupting warning. Worked around by initializing `FWP()->facet->facets = array()` before dispatching the request when it isn't already an array.
- `featured_image` was `get_the_post_thumbnail_url()`'s return value directly, which is `false` (bool) for posts without a thumbnail rather than `null`. Every item was affected (100% of a 100-item sample had no thumbnail set, all typed `false`). Normalized to `null` in `wondercat_build_experience_rest_item()` so the field is consistently `string|null`, since a JSON field that alternates between `false` and a string is a common source of column-type coercion errors in R (`jsonlite`/`purrr`).

**Fixed in a follow-up pass (external consumer guide preparation):**

- **`wikidata` field no longer alternates between a JSON array and a JSON object.** `wondercat_build_experience_rest_item()` now initializes `$item['wikidata']` with every `WONDERCAT_WD_FACETS` key defaulting to `array()` before the QID check, so the field is always a JSON object with a stable set of keys, whether or not the post has a QID.
- **Wikidata facet values now expose the raw qid alongside the label.** Each entry in the `wikidata` map is now `{qid, label, url}` (the same shape `wondercat_get_wikidata_facet_items()` already produced internally) instead of a bare label string, so a consumer can filter on and correlate the exact (lowercase) facet value without guessing `strtolower($qid)` or risking a label collision.
- **An unfiltered request now returns every registered facet's choices.** `wondercat_experiences_rest_callback()` always includes every key from `wondercat_experiences_facet_keys()` in the `facets` param sent to FacetWP's `facetwp/v1/fetch` route (as an empty array when unselected), so a single unfiltered `GET` returns `choices`/`counts` for all facets — no per-facet probing required.
- **HTML entities are decoded in text fields.** `title`, `feature`, `benefit_of_experience`, and `title_of_creative_work` are passed through `wp_kses_decode_entities()` in `wondercat_build_experience_rest_item()`, so e.g. `"Baldur&#8217;s Gate 3"` is returned as `"Baldur's Gate 3"`.

**Open findings (not yet fixed — flagged for follow-up):**

- None currently open; all findings from the original QA pass have been addressed. Re-run `npm run test:facetwp-endpoint` after any future change to the REST callback or `wondercat_build_experience_rest_item()` to catch regressions (the script now asserts these fixes as hard PASS/FAIL checks rather than just documenting them).

## Out of scope / future

- **Wikidata entity pages** (`/wikidata/{qid}`): FacetWP-enabled experience lists there are not included.
- **Publication year / date (P577)**: not included; uses a time datatype and would want a Date Range or Slider facet.
- **`benefit` taxonomy**: registered and public but not surfaced as a facet.
- **Search over Wikidata-derived values / taxonomies**: the search facet matches postmeta and title only; a custom `facetwp_facet_search_engines` engine or index-backed matching would be required to search taxonomy terms or `wikidata_entities` claims.
