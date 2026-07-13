# Wikidata Template Tags and Entity Helpers

Audience: theme developers building templates against stored Wikidata payloads.

This document covers the current API in `inc/wikidata/template-tags.php`, grouped by usage context.

For ingestion, validation, caching, and cron behavior, see `DATA-FLOW.md`.

## API groups at a glance

| Group | Primary use |
|---|---|
| Loop-oriented post helpers | Display Wikidata fields for the current post or a given post ID |
| Generic value accessor | Read arbitrary JSON paths from an entity payload |
| Entity-page helpers | Build metadata blocks from a decoded entity row |
| Claim entity resolvers | Resolve referenced QIDs to labels/links |

## Important behavior notes

1. Most `the_*` helpers echo escaped output.
2. `get_wikidata_*` helpers return raw values or fallback strings/null depending on function.
3. Language fallback is built into `wikidata_get_value` when path includes a language segment.
4. `wikidata_get_entity_data` uses static in-request caching keyed by post ID.
5. These helpers read from local table rows; they do not persist post meta.

## Loop-oriented post helpers

### Presence and context

| Function | Signature | Returns |
|---|---|---|
| `has_wikidata` | `has_wikidata($post_id = null)` | `bool` |
| `wikidata_get_site_language` | `wikidata_get_site_language()` | `string` language code |
| `wikidata_get_entity_data` | `wikidata_get_entity_data($post_id = null)` | `array|null` entity payload |

`has_wikidata` requires both:

1. a QID in post meta/ACF field
2. locally stored/decodeable entity JSON for that QID

### Generic value accessor

| Function | Signature | Returns |
|---|---|---|
| `wikidata_get_value` | `wikidata_get_value($path, $post_id = null, $options = array())` | `mixed` |

Supported options:

| Option | Default | Meaning |
|---|---|---|
| `lang` | `null` | language to use; defaults to site language |
| `fallback` | `null` | fallback value/message when path missing |
| `fallback_lang` | `true` | retry with `en` when language-specific path is missing |

Path tips:

1. Use dot notation.
2. You can include `{lang}` in the path and provide `lang` in options.
3. Missing values produce fallback text unless `fallback` is set to something else.

### Label and description

| Function | Signature | Returns |
|---|---|---|
| `get_wikidata_label` | `get_wikidata_label($post_id = null, $lang = null)` | `string` |
| `the_wikidata_label` | `the_wikidata_label($post_id = null, $lang = null)` | echoes escaped string |
| `get_wikidata_description` | `get_wikidata_description($post_id = null, $lang = null)` | `string` |
| `the_wikidata_description` | `the_wikidata_description($post_id = null, $lang = null)` | echoes escaped string |

### Aliases

| Function | Signature | Returns |
|---|---|---|
| `get_wikidata_aliases` | `get_wikidata_aliases($post_id = null, $lang = null)` | `array` |
| `get_wikidata_alias` | `get_wikidata_alias($index = 0, $post_id = null, $lang = null)` | `string` |
| `the_wikidata_alias` | `the_wikidata_alias($index = 0, $post_id = null, $lang = null)` | echoes escaped string |

### Images and source links

| Function | Signature | Returns |
|---|---|---|
| `get_wikidata_image` | `get_wikidata_image($post_id = null)` | Commons filename or `null` |
| `the_wikidata_image` | `the_wikidata_image($post_id = null, $width = 300, $args = array())` | echoes `<img>` |
| `get_wikidata_url` | `get_wikidata_url($post_id = null)` | Wikidata entity URL or `null` |
| `the_wikidata_url` | `the_wikidata_url($text = 'View on Wikidata', $post_id = null, $args = array())` | echoes `<a>` |

`the_wikidata_image` args:

1. `class` default: `wikidata-image`
2. `alt` default: current label from `get_wikidata_label`

## Claim helpers for post context

| Function | Signature | Returns |
|---|---|---|
| `get_wikidata_claim` | `get_wikidata_claim($property, $post_id = null, $index = 0)` | `mixed` |
| `the_wikidata_claim` | `the_wikidata_claim($property, $post_id = null, $index = 0)` | echoes escaped value |
| `get_wikidata_claim_time` | `get_wikidata_claim_time($property, $post_id = null, $format = 'Y-m-d', $index = 0)` | formatted date string or `null` |
| `the_wikidata_claim_time` | `the_wikidata_claim_time($property, $post_id = null, $format = 'Y-m-d', $index = 0)` | echoes escaped string when available |

Time parsing behavior:

1. expects Wikidata time strings like `+YYYY-MM-DDT00:00:00Z`
2. strips leading sign and midnight suffix before `DateTime` parsing
3. returns raw transformed string if parsing throws

## Entity-row helpers (direct entity templates)

These functions are intended for pages that already loaded a row via `wikidata_get_by_qid`, such as `wikidata-entity.php`.

### Decode and claim access

| Function | Signature | Returns |
|---|---|---|
| `wikidata_decode_entity_row` | `wikidata_decode_entity_row($entity_row, $qid = null)` | `array|null` |
| `wikidata_entity_get_claim_datavalues` | `wikidata_entity_get_claim_datavalues($entity_data, $property)` | `array` |
| `wikidata_entity_get_claim_time` | `wikidata_entity_get_claim_time($entity_data, $property, $format = 'Y-m-d', $index = 0)` | `string|null` |

### QID extraction and prefetch

| Function | Signature | Returns |
|---|---|---|
| `wikidata_entity_extract_qids_from_datavalues` | `wikidata_entity_extract_qids_from_datavalues($values)` | `array<string>` |
| `wikidata_entity_collect_referenced_qids` | `wikidata_entity_collect_referenced_qids($entity_data, $properties = array('P31','P495','P17','P136','P407'))` | `array<string>` |
| `wikidata_prefetch_entity_labels_by_qids` | `wikidata_prefetch_entity_labels_by_qids($qids, $lang = null)` | `void` |

### Label resolution for referenced entities

| Function | Signature | Returns |
|---|---|---|
| `wikidata_get_entity_label_by_qid` | `wikidata_get_entity_label_by_qid($qid, $lang = null)` | `string` |
| `wikidata_entity_get_claim_entity_labels` | `wikidata_entity_get_claim_entity_labels($entity_data, $property, $lang = null)` | `array` |
| `wikidata_entity_get_claim_entity_labels_string` | `wikidata_entity_get_claim_entity_labels_string($entity_data, $property, $lang = null, $separator = ', ')` | `string|null` |
| `wikidata_entity_get_claim_entity_items` | `wikidata_entity_get_claim_entity_items($entity_data, $property, $lang = null)` | structured item array |

### Linked HTML helpers

| Function | Signature | Returns |
|---|---|---|
| `wikidata_entity_get_claim_entity_links_html` | `wikidata_entity_get_claim_entity_links_html($entity_data, $property, $lang = null, $separator = ', ')` | `string|null` HTML |
| `get_wikidata_entity_media_type_links_html` | `get_wikidata_entity_media_type_links_html($entity_data, $lang = null)` | `string|null` |
| `get_wikidata_entity_country_of_origin_links_html` | `get_wikidata_entity_country_of_origin_links_html($entity_data, $lang = null)` | `string|null` |
| `get_wikidata_entity_genres_links_html` | `get_wikidata_entity_genres_links_html($entity_data, $lang = null)` | `string|null` |
| `get_wikidata_entity_languages_links_html` | `get_wikidata_entity_languages_links_html($entity_data, $lang = null)` | `string|null` |

### Plain-text convenience helpers

| Function | Signature | Returns |
|---|---|---|
| `get_wikidata_entity_media_type` | `get_wikidata_entity_media_type($entity_data, $lang = null)` | `string|null` |
| `get_wikidata_entity_country_of_origin` | `get_wikidata_entity_country_of_origin($entity_data, $lang = null)` | `string|null` |
| `get_wikidata_entity_genres` | `get_wikidata_entity_genres($entity_data, $lang = null)` | `string|null` |
| `get_wikidata_entity_languages` | `get_wikidata_entity_languages($entity_data, $lang = null)` | `string|null` |
| `get_wikidata_entity_publication_date` | `get_wikidata_entity_publication_date($entity_data, $format = null)` | `string|null` |

`get_wikidata_entity_country_of_origin` and its links variant use `P495` first, then fallback to `P17`.

## Usage examples

### In The Loop

```php
<?php if (has_wikidata()) : ?>
  <h2><?php the_wikidata_label(); ?></h2>
  <p><?php the_wikidata_description(); ?></p>
  <?php the_wikidata_image(null, 400); ?>
  <?php the_wikidata_url('View on Wikidata'); ?>
<?php endif; ?>
```

### Entity-template metadata row

```php
<?php
$entity_data = wikidata_decode_entity_row($entity, $qid);
$publication = get_wikidata_entity_publication_date($entity_data);
$genres_html = get_wikidata_entity_genres_links_html($entity_data);
?>

<dd><?php echo esc_html($publication ?: __('Not available', 'understrap-child')); ?></dd>
<dd><?php echo $genres_html ? wp_kses_post($genres_html) : esc_html(__('Not available', 'understrap-child')); ?></dd>
```

## Fallback and escaping rules

1. `the_*` helpers are escaped internally.
2. Link-HTML helpers return HTML strings and must be sanitized by callers before output.
3. Several `get_*` helpers return bracketed fallback strings when data is missing.
4. Claim-time helpers return `null` when claim/time is unavailable.

## Maintainer notes

> Maintainer Note: `wikidata_get_entity_data` reads QID via `get_field`, so behavior depends on ACF field availability in the current context.

> Maintainer Note: `get_wikidata_url` uses the stored field value directly when building URL and does not normalize casing. Save-time validation should already enforce a valid format for standard edit flows.

> Maintainer Note: `wikidata_prefetch_entity_labels_by_qids` may issue network-backed fetches through batch utilities for referenced claim QIDs that are missing locally.

## Related files

1. `inc/wikidata/template-tags.php`
2. `inc/wikidata/utilities.php`
3. `inc/wikidata/table.php`
4. `wikidata-entity.php`
5. `inc/wikidata/docs/DATA-FLOW.md`

Last synchronized: 2026-07-13
