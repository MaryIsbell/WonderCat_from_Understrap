# Wikidata Refresh Ops Runbook

Audience: whoever operates the production WordPress install (hosting admin, DevOps, maintainer).

Purpose: keep Wikidata entity rows fresh **without depending on page traffic**. Data flow details live in [DATA-FLOW.md](DATA-FLOW.md); this file is about scheduling, verification and incident handling.

## What runs and when

| Mechanism | Cadence | Requires | Entry point |
|---|---|---|---|
| WP-Cron recurring event `wondercat_wikidata_sweep` | weekly (`weekly` = 7 days, matching the 7-day stale TTL) | something that triggers WP-Cron | `wikidata_handle_sweep_event` |
| WP-CLI full sweep | on demand (recommended: schedule this from system cron) | shell access + WP-CLI | `wp wondercat wikidata-sweep` |
| Admin "Run full sweep now" | on demand | `manage_options` in wp-admin | WikiData Entities screen |
| Lazy single/batch refresh | when a stale row or cache entry is read | page traffic | `wondercat_wikidata_refresh_qid` / `wondercat_wikidata_refresh_batch` |

All three full-sweep triggers run the same code (`wikidata_run_full_sweep`) and record which one fired in the sweep state (`cron`, `cli` or `admin`).

A sweep re-fetches **every QID referenced by at least one published `user-experience` post** and upserts `label`, `description`, `json_data` and `updated_at`. Rows that no published post references are left untouched; nothing is pruned.

## Production scheduling paths

Pick one primary path. `DISABLE_WP_CRON` should be `true` on production if you use a real cron trigger, so sweeps do not run inside visitor requests.

### Path A — system cron triggering WP-Cron (recommended)

Runs the whole cron queue (the sweep plus every other plugin event) on a real schedule:

```
*/5 * * * *  curl -s -o /dev/null https://example.com/wp-cron.php?doing_wp_cron
```

The theme schedules `wondercat_wikidata_sweep` on its own (`init` re-registers it when missing), so no theme-side configuration is needed. WP-Cron only runs events when something hits `wp-cron.php`, hence the external trigger.

### Path B — system cron running the sweep directly with WP-CLI

Ignores WP-Cron entirely for the sweep and is the most robust option when you cannot rely on `wp-cron.php` being hit:

```
30 3 * * 2  cd /path/to/wordpress && wp wondercat wikidata-sweep --allow-root >> wp-content/wikidata-sweep.log 2>&1
```

Exit code is non-zero when at least one QID could not be refreshed, so the command can be alerted on. The WP-Cron event stays scheduled as a second, independent safety net (it is harmless if it never fires; the admin screen will show an overdue next run).

### Path C — hosting cron / task scheduler

Any managed "WordPress cron" or scheduled-task feature works as long as it either hits `wp-cron.php` (Path A) or runs the WP-CLI command (Path B). Do not use the WordPress "WP-Cron" pseudo-scheduler alone on a site with little traffic: events only run when a visitor request happens.

### Path D — manual admin action

WikiData Entities → "Run full sweep now". Runs synchronously inside the request (measured ~8–15 seconds for a few hundred QIDs; longer on a slow API link, and it scales linearly with the number of QIDs). Use it for spot checks or immediately after bulk content edits, not as the production schedule.

## Health checks

```sh
# 1. Is the recurring event scheduled, and when is the next run?
wp cron event list | grep wondercat
# wondercat_wikidata_sweep   <date>   <relative>   1 week

# 2. Did the last sweep run, and did it cover everything?
wp wondercat wikidata-sweep --status
# Last sweep: 2026-10-16 18:33:51 UTC via cron (took 14.39 seconds)
# Last run counts: 250 referenced, 250 refreshed, 0 not refreshed
# Within stale TTL: yes
# Next scheduled: 2026-10-23 18:33:36 UTC

# 3. Raw state
wp option get wondercat_wikidata_sweep_state --format=json

# 4. Full resync now
wp wondercat wikidata-sweep
```

In wp-admin, **WikiData Entities** shows the same information: green border = last sweep inside the 7-day TTL, amber = older than the TTL, red = never ran. An "overdue" line next to the next-run date means WP-Cron has not executed the event yet.

### SQL checks (no admin/CLI needed, useful for read-only monitoring)

```sql
-- Referenced QIDs without an entity row (must be 0 after a sweep):
SELECT DISTINCT pm.meta_value
FROM wp_postmeta pm
JOIN wp_posts p ON p.ID = pm.post_id
LEFT JOIN wp_wikidata_entities e ON e.qid = pm.meta_value
WHERE pm.meta_key = 'wikidata-qid'
  AND p.post_type = 'user-experience'
  AND p.post_status = 'publish'
  AND pm.meta_value REGEXP '^Q[0-9]+$'
  AND e.qid IS NULL;

-- Referenced rows older than 7 days (must be 0 when a sweep ran on time):
SELECT COUNT(*)
FROM wp_wikidata_entities e
WHERE e.qid IN (
  SELECT DISTINCT pm.meta_value
  FROM wp_postmeta pm
  JOIN wp_posts p ON p.ID = pm.post_id
  WHERE pm.meta_key = 'wikidata-qid'
    AND p.post_type = 'user-experience'
    AND p.post_status = 'publish'
    AND pm.meta_value REGEXP '^Q[0-9]+$'
)
AND e.updated_at < (NOW() - INTERVAL 7 DAY);

-- Rows that are not referenced by any published post (never touched by a sweep):
SELECT e.qid, e.updated_at
FROM wp_wikidata_entities e
LEFT JOIN wp_postmeta pm
  ON pm.meta_key = 'wikidata-qid' AND pm.meta_value = e.qid
LEFT JOIN wp_posts p
  ON p.ID = pm.post_id AND p.post_type = 'user-experience' AND p.post_status = 'publish'
WHERE p.ID IS NULL;
```

## Verifying the feature in production after deploy

The acceptance check is that the **Updated** column on the WikiData Entities screen advances on its own, without anyone visiting `/wikidata/{qid}` pages:

1. Record the maximum `updated_at` (`SELECT MAX(updated_at) FROM wp_wikidata_entities;`) or note the Updated column.
2. Wait for one full interval (7 days) with the chosen path active — or compress the check by running `wp wondercat wikidata-sweep` and confirming the columns advance immediately.
3. Confirm `wp wondercat wikidata-sweep --status` shows the expected `source` (`cron` for Path A, `cli` for Path B, `admin` for Path D) and `refreshed == referenced`.
4. Repeat after a second interval to prove the cadence is stable, not a one-off.
5. Confirm unreferenced rows did not move (third SQL check above) and that no entity rows disappeared.

## Failure modes

| Symptom | Likely cause | Action |
|---|---|---|
| Admin screen says "No recurring sweep event is scheduled" | cron option cleared or theme just activated | load any page or run `wp eval 'wikidata_schedule_sweep_event();'`; the event re-registers on `init` |
| Next run is in the past ("overdue") | WP-Cron never fires on production | set up Path A or Path B |
| Sweep reports `failed` QIDs | Wikidata API unreachable/timeout for a chunk | re-run `wp wondercat wikidata-sweep`; if it persists, check API status and outbound HTTPS from the server |
| Sweep is slow or times out (admin action) | many QIDs, slow API link | use Path B (WP-CLI, no web time limit) instead of the admin button |
| Rows stale but sweep state is fresh | QIDs removed from published posts are intentionally not refreshed | check the post/QID meta; the work set only covers published `user-experience` posts |
| Duplicated QID rows | should be impossible (unique key on `qid`) | check the table schema with `SHOW CREATE TABLE wp_wikidata_entities;` |

## Notes and limits

1. Sweeps are idempotent; there is no run lock. Two overlapping runs cost duplicate API calls, not duplicate rows.
2. The sweep only writes QIDs referenced by published `user-experience` posts. Cleaning up rows that lost their post reference is a manual/retention decision, deliberately out of scope.
3. One meta value in the current content set is a URL (`https://en.wikiquote.org/wiki/Mark_Twain`) rather than a QID; normalization drops such values, so they are reported as neither referenced nor failed.
4. The recurring event uses the built-in `weekly` interval. If `WONDERCAT_WIKIDATA_STALE_TTL` is changed, change `WONDERCAT_WIKIDATA_SWEEP_RECURRENCE` (or register a custom cron interval) to keep them aligned.
5. Clearing the cron option (`wp cron event clear`) removes the sweep event; it is restored on the next request that runs `init`.
