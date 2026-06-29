# Wikidata Data Flow & System Architecture

**Version:** 1.2.0  
**For:** WordPress Developers maintaining the WonderCat theme

## Overview

WonderCat integrates with Wikidata by storing entity data in a custom `wp_wikidata_entities` table. Each row represents one Wikidata entity (identified by its QID). A `user-experience` post references a QID via the `wikidata-qid` ACF field, creating a many-to-one relationship (many posts can reference the same QID).

This document explains how data flows from user input to storage and display — including validation, fetch guards, caching, and background refresh.

### Key Constants

| Constant | Value | Defined in |
|---|---|---|
| `WONDERCAT_QID_FIELD` | `'wikidata-qid'` | `inc/wikidata.php:6` |
| `WONDERCAT_POST_TYPE` | `'user-experience'` | `inc/wikidata.php:7` |
| `WONDERCAT_WIKIDATA_STALE_TTL` | `7 * DAY_IN_SECONDS` | `inc/wikidata/utilities.php:7` |
| `WONDERCAT_WIKIDATA_CACHE_TTL` | `30 * DAY_IN_SECONDS` | `inc/wikidata/utilities.php:11` |
| `WONDERCAT_WIKIDATA_REFRESH_QID_HOOK` | `'wondercat_wikidata_refresh_qid'` | `inc/wikidata/utilities.php:15` |
| `WONDERCAT_WIKIDATA_REFRESH_BATCH_HOOK` | `'wondercat_wikidata_refresh_batch'` | `inc/wikidata/utilities.php:19` |

---

## Data Model: `wp_wikidata_entities` Table

Created by `wikidata_install_table()` on theme switch and verified on `init`.

```sql
CREATE TABLE wp_wikidata_entities (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  qid         VARCHAR(32) NOT NULL,          -- e.g. "Q42" (unique)
  url         VARCHAR(255) NULL,             -- Wikidata REST API URL (unique)
  label       VARCHAR(255) NULL,             -- Entity label (English, from API or post title)
  description TEXT NULL,                     -- Entity description (English)
  json_data   LONGTEXT NULL,                 -- Full Wikidata JSON payload
  created_at  DATETIME NOT NULL,
  updated_at  DATETIME NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY qid (qid),
  UNIQUE KEY url (url)
)
```

### Row Relationship

A row in this table is **not** tied to a specific post — it represents a canonical Wikidata entity. The link to posts goes through post meta:

```
wp_wikidata_entities.qid  <--->  wp_postmeta (meta_key='wikidata-qid', meta_value=$qid)
                                     |
                                     +---> wp_posts (post_type='user-experience')
```

This means:
- Many `user-experience` posts can share the same QID.
- One QID produces exactly one row in `wp_wikidata_entities`.
- If no published post references a QID, the row can still exist in the table (but will eventually be skipped by background refresh).

---

## Lifecycle of a QID

The full path a QID takes from user entry to rendered page:

```
User enters QID on user-experience post
        │
        ▼
  [1] ACF validation (on save attempt)
        │
        ▼
  [2] acf/save_post hook → fetch from Wikidata API
        │
        ▼
  [3] wikidata_upsert → INSERT/UPDATE in wp_wikidata_entities
        │
        ├───► Background: cached in transients (30-day TTL)
        │
        └───► Background: stale data triggers cron refresh (7-day stale TTL)
                    │
                    ▼
              Front-end: /wikidata/{qid} renders entity + linked posts
```

### Step 1: ACF Validation (`inc/wikidata.php:94-105`)

Fires on `acf/validate_value/name=wikidata-qid`. The field is optional — empty values pass through. If filled, `wikidata_validate_qid()` checks the QID exists on Wikidata before allowing the save.

- **Pass:** Save proceeds normally.
- **Fail:** ACF returns an inline error: *"The Wikidata ID entered does not correspond to an existing Wikidata entity."* The post is not saved.

See [Validation Chain](#validation-chain) below.

### Step 2: Post-Save Fetch (`inc/wikidata.php:35-79`)

Fires on `acf/save_post` (priority 20). At this point the post is already published and its meta is saved. The function:

1. Skips autosaves and revisions.
2. Checks `post_status` is `publish`.
3. **Checks `post_type` is `user-experience`.** Other post types are ignored (March 2026 guard).
4. Reads the `wikidata-qid` meta value.
5. Calls `wikidata_fetch_json_by_id($qid)`:
   - Returns cached JSON if available (30-day TTL).
   - Falls through to `wp_remote_get()` to `Special:EntityData/$qid.json`.
   - On success: caches the JSON in both `wp_cache` and transients, returns the body.
   - On failure (404, timeout, etc.): returns `false`.
6. **Only calls `wikidata_upsert()` if the fetch returned valid JSON** — prevents storing junk rows with `json_data = "false"` (March 2026 guard).

### Step 3: Database Upsert (`inc/wikidata/table.php:72-111`)

`wikidata_upsert()` checks if the QID already has a row:

- **Exists:** UPDATEs `url`, `label`, `description`, `json_data`, and `updated_at`.
- **Missing:** INSERTs a new row with `created_at` and `updated_at` set to now.

The label is taken from the post title (provided by the post-save hook). The description is `null` (not populated at save time — the full JSON is stored for later extraction).

### Step 4: Caching (`inc/wikidata/utilities.php:226-238`)

After a successful API fetch, the raw JSON is stored in both:
- `wp_cache` (memory, per-request)
- WordPress transients (30-day TTL)

These caches are keyed by `wondercat_wikidata_json_{qid}` (lowercase). Metadata (fetch timestamp) is stored under `wondercat_wikidata_json_meta_{qid}`.

The next request for the same QID skips the API call entirely if the cache is fresh.

---

## Validation Chain

`wikidata_validate_qid()` in `inc/wikidata/utilities.php:634-694` uses a three-tier check:

```
                  wikidata_validate_qid($qid)
                           │
          ┌────────────────┼────────────────┐
          ▼                ▼                ▼
    Tier 1            Tier 2            Tier 3
  Local DB          Transient         Wikidata API
  (instant)         (6-hour TTL)      (props=info)
          │                │                │
          ▼                ▼                ▼
      Found? ──► true   Cached? ──► bool   Exists? ──► true
      Missing            Missing           Missing/error ──► false
```

### Tier 1: Local Database
Checks `wikidata_get_by_qid($qid)`. If the entity was previously fetched and stored, it's valid by definition. This is the fastest path — no API call.

### Tier 2: Transient Cache
Looks up `wondercat_wikidata_valid_{qid}` transient (6-hour TTL). Caches the boolean result of a previous API check so the same invalid QID doesn't trigger repeated API calls within the 6-hour window.

### Tier 3: Lightweight API Call
If both local checks miss, makes a single `wp_remote_get()` to:
```
https://www.wikidata.org/w/api.php?action=wbgetentities&ids=Q42&props=info&format=json
```
Using `props=info` returns only metadata (~200 bytes) instead of full entity data. The QID is valid if it appears in the `entities` map and is not marked as `missing`.

Result is cached in the 6-hour transient, then returned.

---

## Fetch & Upsert Guards (March 2026)

Three guards prevent unnecessary Wikidata API calls:

### 1. Post-Type Guard (`inc/wikidata.php:55-57`)
```php
if ( WONDERCAT_POST_TYPE !== $post->post_type ) {
    return;
}
```
Only `user-experience` posts trigger Wikidata fetches on save. Other post types with a `wikidata-qid` field are silently ignored.

### 2. QID-to-Post Existence Guard (`inc/wikidata/utilities.php:78-104`)
```php
function wikidata_qid_has_published_post( $qid )
```
Queries whether at least one published `user-experience` post references this QID. Applied at:

| Trigger point | Behavior when check fails |
|---|---|
| `wikidata-entity.php:36` (front-end fetch) | No API call; `$entity` stays `false` → 404 |
| `wikidata-entity.php:64` (final 404 check) | 404 even if entity exists in DB but no post references it |
| `wikidata_handle_refresh_qid_event()` | Refresh skipped |
| `wikidata_handle_refresh_batch_event()` | QID filtered out of batch |

### 3. Conditional Upsert (`inc/wikidata.php:66-74`)
```php
if ( false !== $json ) {
    wikidata_upsert( ... );
}
```
Before the March 2026 change, `wikidata_upsert()` was called unconditionally — if the API returned `false` (404, timeout), the upsert would store `json_data = "false"` (the string). Now the upsert only runs when valid JSON was returned.

### Which fetches are NOT gated

`wikidata_prefetch_entity_labels_by_qids()` and `wikidata_batch_fetch_json_by_ids()` (called during entity page rendering to resolve referenced QIDs like entity types, genres, etc.) are not gated by `wikidata_qid_has_published_post()`. These QIDs come from already-fetched Wikidata entity claims — they are by definition legitimate QIDs extracted from the API, not user input.

---

## QID-to-Post Relationship

### Post → QID
```php
$qid = get_field(WONDERCAT_QID_FIELD, $post_id);
// or: $qid = get_post_meta($post_id, WONDERCAT_QID_FIELD, true);
```
Each `user-experience` post stores its QID in the `wikidata-qid` meta field.

### QID → Posts
```php
$posts = new WP_Query(array(
    'post_type'   => WONDERCAT_POST_TYPE,
    'post_status' => 'publish',
    'meta_key'    => WONDERCAT_QID_FIELD,
    'meta_value'  => $qid,
));
```
Used in `wikidata-entity.php:227-238` to render the "Story Experiences" section on the entity page.

### All QIDs in Use
```php
$qids = wikidata_find_posts_with_qid();
```
Returns all unique `wikidata-qid` values from published `user-experience` posts. Used by the admin page for batch operations.

---

## All Wikidata Fetch Points

| # | Location | Trigger | Guarded? | Notes |
|---|---|---|---|---|
| 1 | `inc/wikidata.php:64` | Saving a published `user-experience` post | Post-type check; conditional upsert | Primary entry point |
| 2 | `wikidata-entity.php:39` | Visiting `/wikidata/{qid}` when entity not in DB | `wikidata_qid_has_published_post()` | On-demand fetch for URL access |
| 3 | `inc/wikidata/utilities.php:531` | WP Cron: `wondercat_wikidata_refresh_qid` | `wikidata_qid_has_published_post()` | Background refresh for stale data |
| 4 | `inc/wikidata/utilities.php:579` | WP Cron: `wondercat_wikidata_refresh_batch` | `wikidata_qid_has_published_post()` per QID | Batch refresh from admin |
| 5 | `inc/wikidata/template-tags.php:660` | Entity page rendering (label prefetch for referenced QIDs) | None | QIDs come from Wikidata claim data, not user input |
| 6 | `inc/wikidata/utilities.php:432-477` | Batch fetch for missing cached data | None | Internal; called from prefetch functions |

### Caching Behaviour

Fetch points #1 and #2 use `wikidata_fetch_json_by_id()`, which checks local caches before the API:
1. `wp_cache` (memory, per-request)
2. WordPress transients (30-day TTL)
3. If stale (>7 days): schedules a background cron refresh, returns stale data
4. If missing: makes API call, caches result

Fetch points #3 and #4 use `$force_refresh = true`, bypassing cache entirely.

---

## Background Refresh

### When it triggers
- A cached entity is older than `WONDERCAT_WIKIDATA_STALE_TTL` (7 days).
- A missing-cache fetch is made, returning stale cached data (graceful degradation).
- An admin manually triggers refresh via the admin list page.

### How it works
1. `wikidata_schedule_refresh_qid($qid)` schedules a single WP Cron event 1 minute in the future.
2. `wikidata_handle_refresh_qid_event($qid)` fires. It first checks `wikidata_qid_has_published_post($qid)` — if no post references the QID anymore, the refresh is skipped.
3. Force-fetches fresh JSON from the API.
4. Extracts label and description from the response.
5. Calls `wikidata_upsert()` to update the stored row.

Batch refresh (`wikidata_handle_refresh_batch_event`) works identically but for multiple QIDs at once, filtering out any QID without associated posts before fetching.

---

## File Reference

| File | Purpose |
|---|---|
| `inc/wikidata.php` | Entry point. Defines constants, loads submodules, hooks `wondercat_process_qid_field()` on `acf/save_post`, hooks `wondercat_validate_qid_field()` on ACF validation. |
| `inc/wikidata/utilities.php` | Core engine: API fetch (`wikidata_fetch_json_by_id`), batch fetch, caching, staleness detection, cron scheduling, `wikidata_validate_qid()`, `wikidata_qid_has_published_post()`. |
| `inc/wikidata/table.php` | Custom table CRUD: `wikidata_upsert()`, `wikidata_get_by_qid()`, install/upgrade. |
| `inc/wikidata/template-tags.php` | Front-end template tags (`get_wikidata_label()`, `has_wikidata()`, etc.) and entity rendering helpers (`wikidata_prefetch_entity_labels_by_qids`, `wikidata_decode_entity_row`). |
| `inc/wikidata/rewrite.php` | Rewrite rule for `/wikidata/{qid}` URL route. |
| `inc/wikidata/admin-page.php` | Admin submenu page listing all entities with bulk actions. |
| `inc/wikidata/admin-edit.php` | Admin edit form for entity descriptions. |
| `inc/wikidata/admin-list-table.php` | `WP_List_Table` subclass for the admin list. |
| `inc/wikidata/docs/TEMPLATE-TAGS.md` | Reference for front-end template tags. |
| `wikidata-entity.php` | Template file for the `/wikidata/{qid}` route. Contains its own on-demand fetch + guard + rendering. |
| `functions.php` | Loads `inc/wikidata.php` (line 132), `inc/acf.php` (line 91). Installs `wp_wikidata_entities` table on theme switch and `init`. |
| `inc/acf.php` | ACF-GF integration (populates dropdowns from taxonomies). Not involved in Wikidata fetch/validation logic. |

### Load Order

```
functions.php
  ├── inc/acf.php
  └── inc/wikidata.php         ← defines constants, loads:
        ├── wikidata/utilities.php    ← fetch, cache, validate, guard
        ├── wikidata/table.php        ← CRUD
        ├── wikidata/template-tags.php ← front-end helpers
        ├── wikidata/rewrite.php      ← URL routing
        └── (admin only) admin-*.php
```

---

## Change Checklist

When modifying Wikidata-related code:

1. **Keep constants in sync.** `WONDERCAT_QID_FIELD` and `WONDERCAT_POST_TYPE` are referenced across `inc/wikidata.php`, `inc/wikidata/utilities.php`, `inc/wikidata/template-tags.php`, `wikidata-entity.php`, and `loop-templates/content-user-experience.php`.

2. **Update the table schema in `wikidata_install_table()`** if adding/removing columns. Bump `wikidata_schema_version` option.

3. **When adding a new fetch point,** consider whether `wikidata_qid_has_published_post()` should gate it.

4. **When adding a new validation rule,** add it to `wikidata_validate_qid()` three-tier chain.

5. **Run PHP quality checks** after any PHP change:
   ```sh
   composer php-lint        # parallel PHP lint
   composer phpcs           # WPCS + WPThemeReview
   composer phpstan         # level max on inc/
   composer phpmd           # PHPMD
   ```

6. **Update this document** and `TEMPLATE-TAGS.md` if behavior changes.

---

**Last Updated:** June 2026  
**Compatibility:** WordPress 6.8+, PHP 8.2+
