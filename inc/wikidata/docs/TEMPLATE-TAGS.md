# Wikidata Template Tags Documentation

**Version:** 1.1.0  
**For:** WordPress Theme Developers

## Overview

The Wikidata template tags provide a simple, WordPress-native way to display Wikidata entity information in your theme templates. These functions integrate seamlessly with The Loop and follow WordPress coding standards.

### What You Can Display

- **Labels** — Entity names/titles in any language
- **Descriptions** — Short descriptions in any language
- **Aliases** — Alternative names
- **Images** — Wikimedia Commons images
- **Claims** — Structured data (dates, occupations, locations, etc.)
- **Links** — Direct links to Wikidata entity pages

### Key Features

✅ **Language Support** — Automatically uses site language with fallback to English  
✅ **Performance** — Built-in caching prevents repeated database queries  
✅ **Flexible** — Access any part of the Wikidata JSON structure  
✅ **Safe** — Automatic output escaping  
✅ **WordPress-Native** — Follows `get_*()` / `the_*()` conventions  
✅ **Loop-Friendly** — Works seamlessly with `have_posts()` / `while(have_posts())`

---

## Quick Start

### Basic Template Usage

```php
<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
    
    <?php if (has_wikidata()) : ?>
        <article class="wikidata-enhanced">
            <h1><?php the_wikidata_label(); ?></h1>
            <p class="description"><?php the_wikidata_description(); ?></p>
            
            <?php the_wikidata_image(null, 400); ?>
            
            <?php the_wikidata_url('View on Wikidata →'); ?>
        </article>
    <?php endif; ?>
    
<?php endwhile; endif; ?>
```

---

## Function Reference

### Checking for Wikidata

#### `has_wikidata($post_id)`

Check if a post has Wikidata information available.

**Parameters:**
- `$post_id` (int|null) — Optional. Post ID. Default: current post.

**Returns:** `bool`

**Example:**
```php
<?php if (has_wikidata()) : ?>
    <div class="wikidata-info">
        <!-- Display Wikidata content -->
    </div>
<?php endif; ?>
```

---

### Labels (Names/Titles)

#### `get_wikidata_label($post_id, $lang)`

Get the entity's label (name/title).

**Parameters:**
- `$post_id` (int|null) — Optional. Post ID. Default: current post.
- `$lang` (string|null) — Optional. Language code ('en', 'es', 'de', etc.). Default: site language.

**Returns:** `string`

**Example:**
```php
$label = get_wikidata_label();
$spanish_label = get_wikidata_label(null, 'es');

echo '<h1>' . esc_html($label) . '</h1>';
```

#### `the_wikidata_label($post_id, $lang)`

Display the entity's label (automatically escaped).

**Example:**
```php
<h1><?php the_wikidata_label(); ?></h1>

<!-- In Spanish -->
<h1 lang="es"><?php the_wikidata_label(null, 'es'); ?></h1>
```

---

### Descriptions

#### `get_wikidata_description($post_id, $lang)`

Get the entity's description.

**Parameters:**
- `$post_id` (int|null) — Optional. Post ID. Default: current post.
- `$lang` (string|null) — Optional. Language code. Default: site language.

**Returns:** `string`

**Example:**
```php
$description = get_wikidata_description();

if (strpos($description, '[Wikidata:') === false) {
    // Valid description found
    echo '<p>' . esc_html($description) . '</p>';
}
```

#### `the_wikidata_description($post_id, $lang)`

Display the entity's description (automatically escaped).

**Example:**
```php
<p class="lead"><?php the_wikidata_description(); ?></p>

<!-- In German -->
<p lang="de"><?php the_wikidata_description(null, 'de'); ?></p>
```

---

### Aliases (Alternative Names)

#### `get_wikidata_aliases($post_id, $lang)`

Get all aliases for an entity.

**Parameters:**
- `$post_id` (int|null) — Optional. Post ID. Default: current post.
- `$lang` (string|null) — Optional. Language code. Default: site language.

**Returns:** `array` — Array of alias objects

**Example:**
```php
$aliases = get_wikidata_aliases();

if (!empty($aliases)) {
    echo '<p>Also known as: ';
    
    $names = array();
    foreach ($aliases as $alias) {
        $names[] = esc_html($alias['value']);
    }
    
    echo implode(', ', $names);
    echo '</p>';
}
```

#### `get_wikidata_alias($index, $post_id, $lang)`

Get a specific alias by index.

**Parameters:**
- `$index` (int) — Zero-based index of the alias.
- `$post_id` (int|null) — Optional. Post ID. Default: current post.
- `$lang` (string|null) — Optional. Language code. Default: site language.

**Returns:** `string`

**Example:**
```php
$first_alias = get_wikidata_alias(0);
$second_alias = get_wikidata_alias(1);
```

#### `the_wikidata_alias($index, $post_id, $lang)`

Display a specific alias (automatically escaped).

**Example:**
```php
<p>Also known as: <em><?php the_wikidata_alias(0); ?></em></p>
```

---

### Images

#### `get_wikidata_image($post_id)`

Get the Wikimedia Commons image filename.

**Parameters:**
- `$post_id` (int|null) — Optional. Post ID. Default: current post.

**Returns:** `string|null` — Filename or null if not available

**Example:**
```php
$image = get_wikidata_image();

if ($image) {
    $url = 'https://commons.wikimedia.org/wiki/Special:FilePath/' . urlencode($image) . '?width=800';
    echo '<img src="' . esc_url($url) . '" alt="' . esc_attr(get_wikidata_label()) . '">';
}
```

#### `the_wikidata_image($post_id, $width, $args)`

Display an HTML img tag for the entity's image.

**Parameters:**
- `$post_id` (int|null) — Optional. Post ID. Default: current post.
- `$width` (int) — Optional. Image width in pixels. Default: 300.
- `$args` (array) — Optional. Additional arguments:
  - `class` (string) — CSS class. Default: 'wikidata-image'
  - `alt` (string) — Alt text. Default: entity label

**Example:**
```php
<!-- Default 300px width -->
<?php the_wikidata_image(); ?>

<!-- 800px width with custom class -->
<?php the_wikidata_image(null, 800, array('class' => 'hero-image')); ?>

<!-- Custom alt text -->
<?php the_wikidata_image(null, 400, array(
    'class' => 'featured-image',
    'alt' => 'Portrait of ' . get_wikidata_label()
)); ?>
```

---

### Wikidata URLs

#### `get_wikidata_url($post_id)`

Get the URL to the Wikidata entity page.

**Parameters:**
- `$post_id` (int|null) — Optional. Post ID. Default: current post.

**Returns:** `string|null` — URL or null if no QID found

**Example:**
```php
$url = get_wikidata_url();

if ($url) {
    echo '<a href="' . esc_url($url) . '" target="_blank">View on Wikidata</a>';
}
```

#### `the_wikidata_url($text, $post_id, $args)`

Display a link to the Wikidata entity page.

**Parameters:**
- `$text` (string) — Optional. Link text. Default: 'View on Wikidata'
- `$post_id` (int|null) — Optional. Post ID. Default: current post.
- `$args` (array) — Optional. Additional arguments:
  - `class` (string) — CSS class. Default: 'wikidata-link'
  - `target` (string) — Link target. Default: '_blank'

**Example:**
```php
<?php the_wikidata_url('Learn more →'); ?>

<?php the_wikidata_url('Source', null, array(
    'class' => 'btn btn-primary',
    'target' => '_blank'
)); ?>
```

---

### Claims (Properties)

Wikidata stores structured data as "claims" with property IDs. Common properties include:

- **P18** — Image
- **P569** — Date of birth
- **P570** — Date of death
- **P19** — Place of birth
- **P20** — Place of death
- **P106** — Occupation
- **P27** — Country of citizenship
- **P735** — Given name
- **P31** — Instance of

[Full property list](https://www.wikidata.org/wiki/Wikidata:List_of_properties)

#### `get_wikidata_claim($property, $post_id, $index)`

Get a claim (property) value.

**Parameters:**
- `$property` (string) — Wikidata property ID (e.g., 'P569')
- `$post_id` (int|null) — Optional. Post ID. Default: current post.
- `$index` (int) — Optional. Claim index if multiple values exist. Default: 0

**Returns:** `mixed` — The claim value or fallback message

**Example:**
```php
// Get occupation (returns entity ID like Q193391)
$occupation = get_wikidata_claim('P106');

// Get place of birth (returns entity ID like Q1492)
$birthplace = get_wikidata_claim('P19');

// Get second occupation if multiple exist
$occupation2 = get_wikidata_claim('P106', null, 1);
```

#### `the_wikidata_claim($property, $post_id, $index)`

Display a claim value (automatically escaped).

**Example:**
```php
<p>Occupation: <?php the_wikidata_claim('P106'); ?></p>
```

#### `get_wikidata_claim_time($property, $post_id, $format, $index)`

Get a formatted time value from a claim.

**Parameters:**
- `$property` (string) — Wikidata property ID (e.g., 'P569')
- `$post_id` (int|null) — Optional. Post ID. Default: current post.
- `$format` (string) — Optional. PHP date format string. Default: 'Y-m-d'
- `$index` (int) — Optional. Claim index. Default: 0

**Returns:** `string|null` — Formatted date or null

**Example:**
```php
// Get birth year
$birth_year = get_wikidata_claim_time('P569', null, 'Y');

// Get death date (full format)
$death_date = get_wikidata_claim_time('P570', null, 'F j, Y');

// Get birth date (ISO format)
$birth_date = get_wikidata_claim_time('P569', null, 'Y-m-d');
```

#### `the_wikidata_claim_time($property, $post_id, $format, $index)`

Display a formatted time value (automatically escaped).

**Example:**
```php
<p>Born: <?php the_wikidata_claim_time('P569', null, 'Y'); ?></p>
<p>Died: <?php the_wikidata_claim_time('P570', null, 'F j, Y'); ?></p>

<!-- Birth and death years -->
<p class="lifespan">
    (<?php the_wikidata_claim_time('P569', null, 'Y'); ?>–<?php the_wikidata_claim_time('P570', null, 'Y'); ?>)
</p>
```

---

### Advanced Access

#### `wikidata_get_value($path, $post_id, $options)`

Get any value from the Wikidata JSON using dot notation.

**Parameters:**
- `$path` (string) — Dot notation path (e.g., 'claims.P569.0.mainsnak.datavalue.value.time')
- `$post_id` (int|null) — Optional. Post ID. Default: current post.
- `$options` (array) — Optional. Configuration:
  - `lang` (string) — Language code
  - `fallback` (string) — Custom fallback message
  - `fallback_lang` (bool) — Fallback to English. Default: true

**Returns:** `mixed`

**Example:**
```php
// Get birth date with full path
$birth = wikidata_get_value('claims.P569.0.mainsnak.datavalue.value.time');

// Get first English alias
$alias = wikidata_get_value('aliases.en.0.value');

// Get label with custom fallback
$label = wikidata_get_value('labels.fr.value', null, array(
    'lang' => 'fr',
    'fallback' => 'No French label available'
));

// Use {lang} placeholder for dynamic language
$desc = wikidata_get_value('descriptions.{lang}.value', null, array(
    'lang' => 'es'
));
```

---

### Entity Template Helpers

These helpers are designed for direct Wikidata entity pages (for example [wikidata-entity.php](wikidata-entity.php)) where you already have a row from `wikidata_get_by_qid()` and are not working inside The Loop.

#### `wikidata_decode_entity_row($entity_row, $qid)`

Decode a `wikidata_entities` row into a single entity payload array.

**Parameters:**
- `$entity_row` (object) - Row object returned by `wikidata_get_by_qid()`.
- `$qid` (string|null) - Optional. QID to extract from the payload.

**Returns:** `array|null`

**Example:**
```php
$entity = wikidata_get_by_qid('Q42');
$entity_data = wikidata_decode_entity_row($entity, 'Q42');
```

#### `wikidata_get_entity_label_by_qid($qid, $lang)`

Resolve a label for any referenced QID using locally stored Wikidata rows.

**Parameters:**
- `$qid` (string) - QID to resolve.
- `$lang` (string|null) - Optional. Language code.

**Returns:** `string` - Label when available, otherwise the QID.

#### `wikidata_entity_get_claim_datavalues($entity_data, $property)`

Get all raw `datavalue.value` payloads for a property from an entity payload.

**Parameters:**
- `$entity_data` (array) - Decoded entity payload.
- `$property` (string) - Wikidata property ID (for example `P136`).

**Returns:** `array`

#### `wikidata_entity_get_claim_entity_labels($entity_data, $property, $lang)`

Resolve all entity-reference claim values to labels.

**Parameters:**
- `$entity_data` (array) - Decoded entity payload.
- `$property` (string) - Wikidata property ID.
- `$lang` (string|null) - Optional. Language code.

**Returns:** `array` - Deduplicated labels.

#### `wikidata_entity_get_claim_entity_labels_string($entity_data, $property, $lang, $separator)`

Get a joined string from `wikidata_entity_get_claim_entity_labels()`.

**Returns:** `string|null`

#### `wikidata_entity_get_claim_time($entity_data, $property, $format, $index)`

Format a time claim from entity payload data.

**Returns:** `string|null`

#### Entity Metadata Convenience Functions

These are shortcuts for commonly displayed fields on an entity page:

- `get_wikidata_entity_media_type($entity_data, $lang)` -> `P31`
- `get_wikidata_entity_country_of_origin($entity_data, $lang)` -> `P495` (fallback `P17`)
- `get_wikidata_entity_genres($entity_data, $lang)` -> `P136`
- `get_wikidata_entity_languages($entity_data, $lang)` -> `P407`
- `get_wikidata_entity_publication_date($entity_data, $format)` -> `P577`

#### Entity Metadata Block Example

```php
<?php
$entity_data       = wikidata_decode_entity_row($entity, $qid);
$publication_date  = get_wikidata_entity_publication_date($entity_data);
$media_type        = get_wikidata_entity_media_type($entity_data);
$country_of_origin = get_wikidata_entity_country_of_origin($entity_data);
$genres            = get_wikidata_entity_genres($entity_data);
$languages         = get_wikidata_entity_languages($entity_data);
$not_available     = __('Not available', 'understrap-child');
?>

<dl class="wikidata-metadata mb-0" aria-label="<?php esc_attr_e('Wikidata metadata', 'understrap-child'); ?>">
    <div class="row gy-2 py-3 border-bottom align-items-start">
        <dt class="col-12 col-md-4 fw-bold mb-0"><?php esc_html_e('Publication Date', 'understrap-child'); ?></dt>
        <dd class="col-12 col-md-8 mb-0"><?php echo esc_html($publication_date ?: $not_available); ?></dd>
    </div>

    <div class="row gy-2 py-3 border-bottom align-items-start">
        <dt class="col-12 col-md-4 fw-bold mb-0"><?php esc_html_e('Type of Media', 'understrap-child'); ?></dt>
        <dd class="col-12 col-md-8 mb-0"><?php echo esc_html($media_type ?: $not_available); ?></dd>
    </div>

    <div class="row gy-2 py-3 border-bottom align-items-start">
        <dt class="col-12 col-md-4 fw-bold mb-0"><?php esc_html_e('Country of Origin', 'understrap-child'); ?></dt>
        <dd class="col-12 col-md-8 mb-0"><?php echo esc_html($country_of_origin ?: $not_available); ?></dd>
    </div>

    <div class="row gy-2 py-3 border-bottom align-items-start">
        <dt class="col-12 col-md-4 fw-bold mb-0"><?php esc_html_e('Genre(s)', 'understrap-child'); ?></dt>
        <dd class="col-12 col-md-8 mb-0"><?php echo esc_html($genres ?: $not_available); ?></dd>
    </div>

    <div class="row gy-2 py-3 border-bottom align-items-start">
        <dt class="col-12 col-md-4 fw-bold mb-0"><?php esc_html_e('Language', 'understrap-child'); ?></dt>
        <dd class="col-12 col-md-8 mb-0"><?php echo esc_html($languages ?: $not_available); ?></dd>
    </div>
</dl>
```

---

## Complete Examples

### Biography Page

```php
<?php if (has_wikidata()) : ?>
<article class="biography">
    <header>
        <?php if (get_wikidata_image()) : ?>
            <figure class="portrait">
                <?php the_wikidata_image(null, 600, array('class' => 'img-fluid')); ?>
                <figcaption><?php the_wikidata_label(); ?></figcaption>
            </figure>
        <?php endif; ?>
        
        <h1><?php the_wikidata_label(); ?></h1>
        
        <?php 
        $birth = get_wikidata_claim_time('P569', null, 'Y');
        $death = get_wikidata_claim_time('P570', null, 'Y');
        if ($birth || $death) : ?>
            <p class="lifespan">
                <?php echo esc_html($birth ?: '?'); ?>–<?php echo esc_html($death ?: '?'); ?>
            </p>
        <?php endif; ?>
        
        <p class="lead"><?php the_wikidata_description(); ?></p>
    </header>
    
    <section class="details">
        <?php 
        $aliases = get_wikidata_aliases();
        if (!empty($aliases)) : ?>
            <div class="aliases">
                <strong>Also known as:</strong>
                <?php 
                $names = array();
                foreach ($aliases as $alias) {
                    $names[] = esc_html($alias['value']);
                }
                echo implode(', ', $names);
                ?>
            </div>
        <?php endif; ?>
        
        <?php the_wikidata_url('View full Wikidata record →', null, array(
            'class' => 'btn btn-secondary'
        )); ?>
    </section>
    
    <div class="content">
        <?php the_content(); ?>
    </div>
</article>
<?php endif; ?>
```

### Person Info Card

```php
<aside class="person-card">
    <?php the_wikidata_image(null, 200); ?>
    
    <h3><?php the_wikidata_label(); ?></h3>
    <p class="description"><?php the_wikidata_description(); ?></p>
    
    <dl>
        <?php $birth = get_wikidata_claim_time('P569', null, 'F j, Y'); ?>
        <?php if ($birth && strpos($birth, '[Wikidata:') === false) : ?>
            <dt>Born</dt>
            <dd><?php echo esc_html($birth); ?></dd>
        <?php endif; ?>
        
        <?php $death = get_wikidata_claim_time('P570', null, 'F j, Y'); ?>
        <?php if ($death && strpos($death, '[Wikidata:') === false) : ?>
            <dt>Died</dt>
            <dd><?php echo esc_html($death); ?></dd>
        <?php endif; ?>
    </dl>
    
    <?php the_wikidata_url('Learn more', null, array('class' => 'card-link')); ?>
</aside>
```

### Multilingual Display

```php
<?php if (has_wikidata()) : ?>
<div class="multilingual-info">
    <div class="language-tabs">
        <button data-lang="en">English</button>
        <button data-lang="es">Español</button>
        <button data-lang="de">Deutsch</button>
        <button data-lang="fr">Français</button>
    </div>
    
    <div class="language-content" data-lang="en">
        <h2><?php the_wikidata_label(null, 'en'); ?></h2>
        <p><?php the_wikidata_description(null, 'en'); ?></p>
    </div>
    
    <div class="language-content" data-lang="es" hidden>
        <h2><?php the_wikidata_label(null, 'es'); ?></h2>
        <p><?php the_wikidata_description(null, 'es'); ?></p>
    </div>
    
    <div class="language-content" data-lang="de" hidden>
        <h2><?php the_wikidata_label(null, 'de'); ?></h2>
        <p><?php the_wikidata_description(null, 'de'); ?></p>
    </div>
    
    <div class="language-content" data-lang="fr" hidden>
        <h2><?php the_wikidata_label(null, 'fr'); ?></h2>
        <p><?php the_wikidata_description(null, 'fr'); ?></p>
    </div>
</div>
<?php endif; ?>
```

### Timeline with Dates

```php
<?php if (has_wikidata()) : ?>
<div class="timeline">
    <h2><?php the_wikidata_label(); ?></h2>
    
    <ul class="events">
        <?php $birth = get_wikidata_claim_time('P569'); ?>
        <?php if ($birth) : ?>
            <li class="event">
                <time datetime="<?php echo esc_attr($birth); ?>">
                    <?php echo esc_html($birth); ?>
                </time>
                <span>Born</span>
            </li>
        <?php endif; ?>
        
        <?php $death = get_wikidata_claim_time('P570'); ?>
        <?php if ($death) : ?>
            <li class="event">
                <time datetime="<?php echo esc_attr($death); ?>">
                    <?php echo esc_html($death); ?>
                </time>
                <span>Died</span>
            </li>
        <?php endif; ?>
    </ul>
</div>
<?php endif; ?>
```

---

## Best Practices

### 1. Always Check for Data

Always use `has_wikidata()` to check if data exists before displaying complex layouts:

```php
<?php if (has_wikidata()) : ?>
    <!-- Wikidata content -->
<?php else : ?>
    <!-- Fallback content -->
<?php endif; ?>
```

### 2. Handle Fallback Messages

Template tags return fallback messages like `[Wikidata: description not available]` when data is missing. Check for these:

```php
<?php 
$description = get_wikidata_description();
if (strpos($description, '[Wikidata:') === false) {
    // Valid description found
    echo '<p>' . esc_html($description) . '</p>';
}
?>
```

### 3. Use Language Codes Consistently

Common language codes: `en` (English), `es` (Spanish), `de` (German), `fr` (French), `it` (Italian), `pt` (Portuguese), `nl` (Dutch)

### 4. Image Sizing

Wikimedia Commons supports dynamic image sizing. Use appropriate widths:

```php
<!-- Thumbnail -->
<?php the_wikidata_image(null, 150); ?>

<!-- Medium -->
<?php the_wikidata_image(null, 400); ?>

<!-- Large -->
<?php the_wikidata_image(null, 800); ?>

<!-- Full width (be careful with large images) -->
<?php the_wikidata_image(null, 1200); ?>
```

### 5. Caching

The template tags use static caching within a single request. However, for high-traffic sites, consider using WordPress transients for longer-term caching:

```php
$cache_key = 'wikidata_info_' . get_the_ID();
$info = get_transient($cache_key);

if (false === $info) {
    $info = array(
        'label' => get_wikidata_label(),
        'description' => get_wikidata_description(),
        'image' => get_wikidata_image(),
    );
    set_transient($cache_key, $info, DAY_IN_SECONDS);
}

echo esc_html($info['label']);
```

### 6. Validate Property Values

When working with claims, some properties return entity IDs that need to be resolved:

```php
$occupation_id = get_wikidata_claim('P106'); // Returns something like Q193391

// To get the actual occupation name, you'd need to fetch that entity's label
// This is an advanced use case requiring additional API calls
```

---

## Language Support

### Automatic Language Detection

By default, template tags use the WordPress site language:

```php
// If site is set to Spanish (es_ES), this returns Spanish label
the_wikidata_label();
```

### Override Language

You can override the language for any tag:

```php
// Force English
the_wikidata_label(null, 'en');

// Force Spanish
the_wikidata_label(null, 'es');
```

### Language Fallback Chain

When a requested language isn't available, the system falls back to English, then displays a fallback message:

1. **Requested language** (e.g., 'fr')
2. **English** ('en')
3. **Fallback message** ('[Wikidata: label not available]')

### Customize Fallback Messages

```php
$description = wikidata_get_value('descriptions.ja.value', null, array(
    'lang' => 'ja',
    'fallback' => 'Description not available in Japanese'
));
```

---

## Performance Considerations

### Built-in Caching

The template tags use static caching to avoid:
- Multiple database queries per request
- Repeated JSON decoding operations

### When to Add Your Own Caching

Consider additional caching if you're:
- Displaying Wikidata on high-traffic pages
- Making multiple complex JSON traversals
- Fetching data from many posts at once

### Example: Transient Caching

```php
function get_cached_wikidata_info($post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    $cache_key = 'wikidata_' . $post_id;
    $cached = get_transient($cache_key);
    
    if (false !== $cached) {
        return $cached;
    }
    
    $data = array(
        'label' => get_wikidata_label($post_id),
        'description' => get_wikidata_description($post_id),
        'image' => get_wikidata_image($post_id),
        'birth' => get_wikidata_claim_time('P569', $post_id, 'Y'),
        'death' => get_wikidata_claim_time('P570', $post_id, 'Y'),
    );
    
    set_transient($cache_key, $data, DAY_IN_SECONDS);
    
    return $data;
}
```

---

## Troubleshooting

### No Data Appears

**Check these in order:**

1. Does the post have a `wikidata-qid` custom field?
   ```php
   $qid = get_field('wikidata-qid');
   var_dump($qid);
   ```

2. Is there data in the database?
   ```php
   $entity = wikidata_get_by_qid($qid);
   var_dump($entity);
   ```

3. Is the JSON valid?
   ```php
   $data = wikidata_get_entity_data();
   var_dump($data);
   ```

### Fallback Messages Display

If you see `[Wikidata: description not available]`:
- The property doesn't exist in the entity
- The requested language isn't available
- Try checking the raw JSON or Wikidata page

### Image Not Displaying

If `the_wikidata_image()` shows nothing:
- The entity may not have a P18 (image) property
- Check manually: `var_dump(get_wikidata_image());`

### Wrong Language

If labels appear in the wrong language:
- Check your WordPress site language: Settings → General → Site Language
- Override manually: `the_wikidata_label(null, 'en')`

---

## Advanced Topics

### Accessing Complex Claim Structures

Some Wikidata properties have complex nested structures. Use `wikidata_get_value()` with full paths:

```php
// Get birth date precision
$precision = wikidata_get_value('claims.P569.0.mainsnak.datavalue.value.precision');

// Get the calendar model used
$calendar = wikidata_get_value('claims.P569.0.mainsnak.datavalue.value.calendarmodel');

// Get qualifiers for a claim
$start_time = wikidata_get_value('claims.P39.0.qualifiers.P580.0.datavalue.value.time');
```

### Working with Entity References

Many properties reference other entities (e.g., occupation, citizenship):

```php
// This returns an entity ID like Q193391
$occupation_id = get_wikidata_claim('P106');

// To display the occupation name, you would need to:
// 1. Fetch that entity from Wikidata
// 2. Get its label
// This requires additional implementation beyond these template tags
```

### Custom Formatting Functions

Create your own wrapper functions for consistent formatting:

```php
function format_person_lifespan($post_id = null) {
    $birth = get_wikidata_claim_time('P569', $post_id, 'Y');
    $death = get_wikidata_claim_time('P570', $post_id, 'Y');
    
    if (!$birth && !$death) {
        return '';
    }
    
    $birth = $birth ?: '?';
    $death = $death ?: 'present';
    
    return sprintf('(%s – %s)', $birth, $death);
}

// Usage
echo format_person_lifespan();
```

### Integration with Schema.org

Combine with schema.org markup for SEO:

```php
<div itemscope itemtype="https://schema.org/Person">
    <h1 itemprop="name"><?php the_wikidata_label(); ?></h1>
    <p itemprop="description"><?php the_wikidata_description(); ?></p>
    
    <?php 
    $birth = get_wikidata_claim_time('P569', null, 'Y-m-d');
    if ($birth) : ?>
        <meta itemprop="birthDate" content="<?php echo esc_attr($birth); ?>">
    <?php endif; ?>
    
    <?php if (get_wikidata_image()) : ?>
        <img itemprop="image" src="<?php echo esc_url('https://commons.wikimedia.org/wiki/Special:FilePath/' . urlencode(get_wikidata_image()) . '?width=400'); ?>" alt="<?php echo esc_attr(get_wikidata_label()); ?>">
    <?php endif; ?>
    
    <link itemprop="sameAs" href="<?php echo esc_url(get_wikidata_url()); ?>">
</div>
```

---

## Useful Wikidata Properties

Here are commonly used Wikidata properties for reference:

### Person Properties
- **P18** — Image
- **P21** — Sex or gender
- **P27** — Country of citizenship
- **P31** — Instance of
- **P106** — Occupation
- **P569** — Date of birth
- **P570** — Date of death
- **P19** — Place of birth
- **P20** — Place of death
- **P735** — Given name
- **P734** — Family name
- **P1477** — Birth name

### Organization Properties
- **P31** — Instance of
- **P571** — Inception (founding date)
- **P576** — Dissolved/abolished date
- **P159** — Headquarters location
- **P452** — Industry

### Place Properties
- **P31** — Instance of
- **P17** — Country
- **P625** — Coordinate location
- **P1082** — Population
- **P36** — Capital

### Work Properties
- **P50** — Author
- **P577** — Publication date
- **P123** — Publisher
- **P407** — Language of work
- **P136** — Genre

For a complete list, visit: [Wikidata:List of properties](https://www.wikidata.org/wiki/Wikidata:List_of_properties)

---

## Additional Resources

- **Wikidata Query Service:** https://query.wikidata.org/
- **Property Search:** https://www.wikidata.org/wiki/Special:ListProperties
- **Wikidata API Documentation:** https://www.wikidata.org/w/api.php
- **Wikimedia Commons:** https://commons.wikimedia.org/

---

## Support

For issues or questions about these template tags, refer to the source code:
- **Template Tags:** `inc/wikidata/template-tags.php`
- **Database Functions:** `inc/wikidata/table.php`
- **Utilities:** `inc/wikidata/utilities.php`

---

**Last Updated:** March 20, 2026  
**Compatibility:** WordPress 5.0+, PHP 7.4+
