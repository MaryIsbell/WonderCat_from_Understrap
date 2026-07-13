# Wikidata Data Flow and Runtime Notes

Audience: maintainers working on WonderCat Wikidata ingestion, storage, and rendering.

This document is a code-aligned map of how a QID moves through validation, fetch/caching, persistence, and front-end rendering.

## Core constants and hooks

| Name | Type | Current value |
|---|---|---|
| `WONDERCAT_QID_FIELD` | constant | `wikidata-qid` |
| `WONDERCAT_POST_TYPE` | constant | `user-experience` |
| `WONDERCAT_WIKIDATA_STALE_TTL` | constant | `7 * DAY_IN_SECONDS` |
| `WONDERCAT_WIKIDATA_CACHE_TTL` | constant | `30 * DAY_IN_SECONDS` |
| `WONDERCAT_WIKIDATA_REFRESH_QID_HOOK` | action hook | `wondercat_wikidata_refresh_qid` |
| `WONDERCAT_WIKIDATA_REFRESH_BATCH_HOOK` | action hook | `wondercat_wikidata_refresh_batch` |

Main runtime hooks:

| Hook | Callback | Purpose |
|---|---|---|
| `acf/save_post` | `wondercat_process_qid_field` | Save-time fetch and upsert for published `user-experience` posts |
| `acf/validate_save_post` | `wondercat_validate_qid_on_save` | Validation sweep over posted ACF values |
| `acf/validate_value/name=wikidata-qid` | `wondercat_validate_qid_field` | Field-specific QID validation |
| `acf/validate_value` | `wondercat_validate_qid_field_common` | Generic fallback validation path |
| `update_post_metadata` | `wondercat_guard_wikidata_qid_meta_update` | Blocks invalid QID meta writes if validation is bypassed |
| `query_vars` | `wondercat_wikidata_query_vars` | Registers `wikidata_qid` query var |
| `init` | `wondercat_wikidata_rewrite_rule` | Adds `/wikidata/{qid}` rewrite rule |
| `template_include` | `wondercat_wikidata_template_include` | Routes entity requests to `wikidata-entity.php` |
| `wondercat_wikidata_refresh_qid` | `wikidata_handle_refresh_qid_event` | Background single-QID refresh |
| `wondercat_wikidata_refresh_batch` | `wikidata_handle_refresh_batch_event` | Background batch refresh |

## Storage model

`wikidata_install_table` creates a prefixed table named `${wpdb->prefix}wikidata_entities` with unique keys on `qid` and `url`.

Relevant fields:

| Column | Purpose |
|---|---|
| `qid` | Canonical entity key (for example `Q42`) |
| `json_data` | Raw entity payload used by template helpers |
| `label` | Stored label fallback (typically English or existing value) |
| `description` | Stored description fallback |
| `updated_at` | Used for row staleness checks |

Link to content is via post meta:

1. A published `user-experience` post stores meta key `wikidata-qid`.
2. That QID maps to one row in `wikidata_entities`.
3. Multiple posts can reference the same QID.

## End-to-end lifecycle

### 1. Edit/save validation path

Validation is layered and only targets `user-experience` edit/save context.

1. `wondercat_is_user_experience_edit_context` determines whether current request is editing a target post type.
2. `wondercat_is_wikidata_qid_field` identifies the QID field by name, key, label, or input name.
3. `wondercat_get_qid_validation_error` enforces:
   - empty values allowed
   - normalized format must match `Q` + digits
   - existence check via `wikidata_validate_qid_with_status`
4. If invalid, ACF receives a specific user-facing message.
5. `wondercat_guard_wikidata_qid_meta_update` blocks direct metadata updates for invalid QIDs on `user-experience` posts.

Validation status reasons from `wikidata_validate_qid_with_status`:

| Reason | Meaning |
|---|---|
| `invalid_format` | Input failed normalization |
| `exists` | Found in local table, cached validation, or API check |
| `not_found` | API confirmed missing entity |
| `unverifiable` | API/network response could not verify existence |

### 2. Save-time fetch and upsert path

`wondercat_process_qid_field` runs on `acf/save_post` and exits early for:

1. autosaves
2. revisions
3. non-published posts
4. non-`user-experience` posts

When a QID exists:

1. Calls `wikidata_fetch_json_by_id($qid)`.
2. Upserts only if the fetch returned JSON (not `false`).
3. Uses post title as the upsert label and stores full payload in `json_data`.

## Fetch and cache behavior

### Single-entity fetch: `wikidata_fetch_json_by_id`

Inputs:

1. QID (normalized by `wikidata_normalize_qid`)
2. optional `force_refresh`

Behavior:

1. If not force-refresh, reads cache via `wikidata_get_cached_json_with_status`.
2. If cached payload exists:
   - returns cached JSON immediately
   - if stale, schedules async refresh but still serves stale payload
3. If cache missing (or force refresh), requests `Special:EntityData/{qid}.json`.
4. On success, writes payload + fetched timestamp metadata into object cache and transients.

Cache keys:

| Key | Data |
|---|---|
| `wondercat_wikidata_json_{qid-lower}` | JSON payload |
| `wondercat_wikidata_json_meta_{qid-lower}` | metadata array containing `fetched_at` |

### Batch fetch: `wikidata_batch_fetch_json_by_ids`

1. Normalizes/deduplicates QIDs.
2. Uses cache for non-force path and collects stale QIDs.
3. Schedules batch refresh for stale cached QIDs.
4. Fetches missing items through `wbgetentities` in chunks of 50 IDs.
5. Stores per-QID payloads in the same cache tier as single fetches.

## Background refresh behavior

### Scheduling

1. `wikidata_schedule_refresh_qid` schedules one event per QID (deduped with `wp_next_scheduled`).
2. `wikidata_schedule_refresh_batch` schedules one event for a normalized/sorted QID list.

### Execution safeguards

Both refresh handlers skip work when no published `user-experience` post currently references the QID (`wikidata_qid_has_published_post`).

Single refresh (`wikidata_handle_refresh_qid_event`):

1. force-fetches fresh payload
2. extracts label/description from payload when available
3. upserts updated row

Batch refresh (`wikidata_handle_refresh_batch_event`) does the same per QID using force batch fetch.

## Front-end route and render flow

Route setup:

1. query var `wikidata_qid` registered
2. rewrite pattern `^wikidata/([^/]+)/?$`
3. template switched to `wikidata-entity.php` when query var exists

Render guard flow in `wikidata-entity.php`:

1. reject invalid QID format with 404
2. attempt local row lookup
3. if missing row, only fetch remote data if QID is referenced by at least one published `user-experience` post
4. after potential fetch/upsert, require both:
   - entity row exists
   - QID still has at least one published post association
5. otherwise return 404

The template then decodes entity data and resolves linked labels for claim-based metadata (type, country, genre, language, publication date).

## Template-helper fetch points

`wikidata_prefetch_entity_labels_by_qids` and `wikidata_get_entity_label_by_qid` can fetch related entities referenced inside claims to resolve labels.

Important distinction:

1. These helper fetches are not gated by post association checks.
2. They are driven by already-fetched claim data, not direct user-entered QIDs.

## Admin refresh behavior

Admin list actions (`refresh`, `bulk_refresh`) in `admin-page.php` fetch and upsert immediately.

Operational difference from cron handlers:

1. admin refresh does not enforce `wikidata_qid_has_published_post`
2. cron refresh does enforce it

This is intentional today and should be considered when evaluating table retention behavior.

## Guards and failure modes

| Area | Guard/failure behavior |
|---|---|
| Invalid QID format | blocked by validation, blocked again at entity route |
| API not reachable during validation | treated as `unverifiable`; save is blocked |
| API fetch failure during save | no upsert is performed |
| Stale cached payload | served, with async refresh scheduled |
| Unreferenced QID on entity route | returns 404 |
| Unreferenced QID in cron refresh | refresh skipped |

## Maintainer notes

> Maintainer Note: background refresh relies on WP-Cron execution. On low-traffic or cron-disabled environments, stale rows can persist longer than the stale TTL until an event runs.

> Maintainer Note: `/wikidata/{qid}` routing depends on non-plain permalinks and rewrite rules being flushed after theme activation/switch.

> Maintainer Note: this code assumes ACF is active. `inc/wikidata.php` returns early when ACF is missing, so Wikidata save validation and save hooks are not registered in that condition.

## File-level map

| File | Responsibility |
|---|---|
| `inc/wikidata.php` | constants, ACF-dependent save/validation hooks, module loading |
| `inc/wikidata/utilities.php` | normalization, fetch, cache, validation status, refresh scheduling/handlers |
| `inc/wikidata/table.php` | custom table install and CRUD/upsert |
| `inc/wikidata/rewrite.php` | query var, rewrite rule, template routing |
| `inc/wikidata/template-tags.php` | template API and entity helper functions |
| `inc/wikidata/admin-page.php` | admin list actions including manual refresh |
| `wikidata-entity.php` | direct entity-page guard and render logic |
| `functions.php` | theme-level include and table install/upgrade hooks |

Last synchronized: 2026-07-13
