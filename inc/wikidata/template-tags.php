<?php
/**
 * Wikidata Template Tags
 * 
 * WordPress template tags for displaying Wikidata entity information in theme templates.
 * These functions retrieve and display data from the wikidata_entities table.
 * 
 * @package WonderCat
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Get the current site's language code from WordPress locale.
 * 
 * Extracts the two-letter language code from the WordPress locale setting.
 * For example, 'en_US' becomes 'en', 'es_ES' becomes 'es'.
 * 
 * @return string Two-letter language code (e.g., 'en', 'es', 'fr').
 */
function wikidata_get_site_language() {
    $locale = get_locale();
    
    // Extract the language code (first two characters before underscore)
    $lang = substr($locale, 0, 2);
    
    return $lang ? $lang : 'en';
}

/**
 * Get decoded Wikidata JSON for a post.
 * 
 * Retrieves the QID from a post's custom field, looks up the entity in the database,
 * and returns the decoded JSON data. Results are cached in a static variable for 
 * performance (avoids repeated database queries and JSON decoding in the same request).
 * 
 * @param int|null $post_id Optional. The post ID. Defaults to current post in The Loop.
 * @return array|null Decoded Wikidata entity array, or null if not found.
 */
function wikidata_get_entity_data($post_id = null) {
    // Use static caching to avoid repeated lookups in the same request
    static $cache = array();
    
    if ($post_id === null) {
        $post_id = get_the_ID();
    }
    
    if (!$post_id) {
        return null;
    }
    
    // Check cache first
    if (isset($cache[$post_id])) {
        return $cache[$post_id];
    }
    
    // Get the QID from the post's custom field
    $qid = get_field(WONDERCAT_QID_FIELD, $post_id);
    
    if (!$qid) {
        $cache[$post_id] = null;
        return null;
    }
    
    // Look up the entity in the wikidata table
    $entity = wikidata_get_by_qid($qid);
    
    if (!$entity || !$entity->json_data) {
        $cache[$post_id] = null;
        return null;
    }
    
    // Decode the JSON
    $data = json_decode($entity->json_data, true);
    
    if (!$data || !isset($data['entities'][$qid])) {
        $cache[$post_id] = null;
        return null;
    }
    
    // Cache and return the entity data
    $cache[$post_id] = $data['entities'][$qid];
    return $cache[$post_id];
}

/**
 * Get a value from Wikidata entity using dot notation path.
 * 
 * This is the core accessor function that traverses the Wikidata JSON structure.
 * Supports language-specific paths with automatic fallback to English.
 * 
 * @param string   $path    Dot notation path (e.g., 'labels.en.value' or 'descriptions').
 * @param int|null $post_id Optional. The post ID. Defaults to current post.
 * @param array    $options Optional. Configuration options:
 *                          - 'lang' (string): Language code. Defaults to site language.
 *                          - 'fallback' (string): Custom fallback message.
 *                          - 'fallback_lang' (bool): Whether to fallback to 'en'. Default true.
 * @return mixed The value at the path, or fallback message if not found.
 * 
 * @example
 * // Get English label
 * $label = wikidata_get_value('labels.en.value');
 * 
 * @example
 * // Get description in site's language with fallback to English
 * $desc = wikidata_get_value('descriptions', null, array('lang' => 'es'));
 * 
 * @example
 * // Get birth date from claims
 * $birth = wikidata_get_value('claims.P569.0.mainsnak.datavalue.value.time');
 */
function wikidata_get_value($path, $post_id = null, $options = array()) {
    $defaults = array(
        'lang' => null,
        'fallback' => null,
        'fallback_lang' => true,
    );
    
    $options = wp_parse_args($options, $defaults);
    
    // Get entity data
    $entity = wikidata_get_entity_data($post_id);
    
    if (!$entity) {
        return $options['fallback'] ?: '[Wikidata: entity not found]';
    }
    
    // Determine language
    $lang = $options['lang'] ?: wikidata_get_site_language();
    
    // Replace {lang} placeholder in path with actual language code
    $path = str_replace('{lang}', $lang, $path);
    
    // Split path into segments
    $segments = explode('.', $path);
    $current = $entity;
    
    // Traverse the path
    foreach ($segments as $segment) {
        if (!is_array($current) || !isset($current[$segment])) {
            // Path not found, try fallback to English if enabled
            if ($options['fallback_lang'] && $lang !== 'en' && strpos($path, '.' . $lang . '.') !== false) {
                $fallback_path = str_replace('.' . $lang . '.', '.en.', $path);
                return wikidata_get_value($fallback_path, $post_id, array_merge($options, array('fallback_lang' => false)));
            }
            
            return $options['fallback'] ?: '[Wikidata: ' . explode('.', $path)[0] . ' not available]';
        }
        
        $current = $current[$segment];
    }
    
    return $current;
}

/**
 * Get the label (name/title) of a Wikidata entity.
 * 
 * @param int|null    $post_id Optional. The post ID. Defaults to current post.
 * @param string|null $lang    Optional. Language code. Defaults to site language.
 * @return string The label or fallback message.
 * 
 * @example
 * // Get label in site's language
 * $label = get_wikidata_label();
 * 
 * @example
 * // Get Spanish label
 * $label_es = get_wikidata_label(null, 'es');
 */
function get_wikidata_label($post_id = null, $lang = null) {
    $lang = $lang ?: wikidata_get_site_language();
    return wikidata_get_value("labels.{$lang}.value", $post_id, array('lang' => $lang));
}

/**
 * Display the label (name/title) of a Wikidata entity.
 * 
 * @param int|null    $post_id Optional. The post ID. Defaults to current post.
 * @param string|null $lang    Optional. Language code. Defaults to site language.
 * 
 * @example
 * <h2><?php the_wikidata_label(); ?></h2>
 */
function the_wikidata_label($post_id = null, $lang = null) {
    echo esc_html(get_wikidata_label($post_id, $lang));
}

/**
 * Get the description of a Wikidata entity.
 * 
 * @param int|null    $post_id Optional. The post ID. Defaults to current post.
 * @param string|null $lang    Optional. Language code. Defaults to site language.
 * @return string The description or fallback message.
 * 
 * @example
 * // Get description in site's language
 * $desc = get_wikidata_description();
 * 
 * @example
 * // Get German description
 * $desc_de = get_wikidata_description(null, 'de');
 */
function get_wikidata_description($post_id = null, $lang = null) {
    $lang = $lang ?: wikidata_get_site_language();
    return wikidata_get_value("descriptions.{$lang}.value", $post_id, array('lang' => $lang));
}

/**
 * Display the description of a Wikidata entity.
 * 
 * @param int|null    $post_id Optional. The post ID. Defaults to current post.
 * @param string|null $lang    Optional. Language code. Defaults to site language.
 * 
 * @example
 * <p class="description"><?php the_wikidata_description(); ?></p>
 */
function the_wikidata_description($post_id = null, $lang = null) {
    echo esc_html(get_wikidata_description($post_id, $lang));
}

/**
 * Get all aliases (alternative names) for a Wikidata entity.
 * 
 * @param int|null    $post_id Optional. The post ID. Defaults to current post.
 * @param string|null $lang    Optional. Language code. Defaults to site language.
 * @return array Array of alias objects, or empty array if none found.
 * 
 * @example
 * $aliases = get_wikidata_aliases();
 * foreach ($aliases as $alias) {
 *     echo $alias['value'];
 * }
 */
function get_wikidata_aliases($post_id = null, $lang = null) {
    $lang = $lang ?: wikidata_get_site_language();
    $aliases = wikidata_get_value("aliases.{$lang}", $post_id, array('lang' => $lang, 'fallback' => array()));
    
    return is_array($aliases) ? $aliases : array();
}

/**
 * Get a specific alias by index.
 * 
 * @param int         $index   The zero-based index of the alias to retrieve.
 * @param int|null    $post_id Optional. The post ID. Defaults to current post.
 * @param string|null $lang    Optional. Language code. Defaults to site language.
 * @return string The alias value or fallback message.
 * 
 * @example
 * // Get the first alias
 * $first_alias = get_wikidata_alias(0);
 */
function get_wikidata_alias($index = 0, $post_id = null, $lang = null) {
    $aliases = get_wikidata_aliases($post_id, $lang);
    
    if (isset($aliases[$index]['value'])) {
        return $aliases[$index]['value'];
    }
    
    return '[Wikidata: alias not available]';
}

/**
 * Display a specific alias by index.
 * 
 * @param int         $index   The zero-based index of the alias to retrieve.
 * @param int|null    $post_id Optional. The post ID. Defaults to current post.
 * @param string|null $lang    Optional. Language code. Defaults to site language.
 * 
 * @example
 * <span class="aka"><?php the_wikidata_alias(0); ?></span>
 */
function the_wikidata_alias($index = 0, $post_id = null, $lang = null) {
    echo esc_html(get_wikidata_alias($index, $post_id, $lang));
}

/**
 * Get the Wikimedia Commons image filename for a Wikidata entity.
 * 
 * Returns the 'image' property (P18) from Wikidata. This is just the filename,
 * not a full URL. To display the image, you'll need to construct a Commons URL.
 * 
 * @param int|null $post_id Optional. The post ID. Defaults to current post.
 * @return string|null The image filename or null if not available.
 * 
 * @example
 * $image = get_wikidata_image();
 * if ($image) {
 *     // Construct Commons URL (thumbnail example)
 *     $url = "https://commons.wikimedia.org/wiki/Special:FilePath/" . urlencode($image) . "?width=300";
 *     echo '<img src="' . esc_url($url) . '" alt="' . esc_attr(get_wikidata_label()) . '">';
 * }
 */
function get_wikidata_image($post_id = null) {
    $image = wikidata_get_value('claims.P18.0.mainsnak.datavalue.value', $post_id, array('fallback' => null));
    
    return ($image && !is_array($image)) ? $image : null;
}

/**
 * Display an HTML img tag for the Wikidata entity's image.
 * 
 * @param int|null $post_id Optional. The post ID. Defaults to current post.
 * @param int      $width   Optional. Image width in pixels. Default 300.
 * @param array    $args    Optional. Additional arguments:
 *                          - 'class' (string): CSS class for img tag
 *                          - 'alt' (string): Alt text (defaults to entity label)
 * 
 * @example
 * <?php the_wikidata_image(null, 500, array('class' => 'featured-image')); ?>
 */
function the_wikidata_image($post_id = null, $width = 300, $args = array()) {
    $image = get_wikidata_image($post_id);
    
    if (!$image) {
        return;
    }
    
    $defaults = array(
        'class' => 'wikidata-image',
        'alt' => get_wikidata_label($post_id),
    );
    
    $args = wp_parse_args($args, $defaults);
    
    // Construct Commons URL
    $url = 'https://commons.wikimedia.org/wiki/Special:FilePath/' . urlencode($image);
    if ($width) {
        $url .= '?width=' . absint($width);
    }
    
    printf(
        '<img src="%s" alt="%s" class="%s" width="%d">',
        esc_url($url),
        esc_attr($args['alt']),
        esc_attr($args['class']),
        absint($width)
    );
}

/**
 * Get the URL to the Wikidata entity page.
 * 
 * @param int|null $post_id Optional. The post ID. Defaults to current post.
 * @return string|null The Wikidata URL or null if no QID found.
 * 
 * @example
 * $url = get_wikidata_url();
 * echo '<a href="' . esc_url($url) . '">View on Wikidata</a>';
 */
function get_wikidata_url($post_id = null) {
    if ($post_id === null) {
        $post_id = get_the_ID();
    }
    
    $qid = get_field(WONDERCAT_QID_FIELD, $post_id);
    
    if (!$qid) {
        return null;
    }
    
    return 'https://www.wikidata.org/wiki/' . $qid;
}

/**
 * Display a link to the Wikidata entity page.
 * 
 * @param string   $text    Optional. Link text. Defaults to 'View on Wikidata'.
 * @param int|null $post_id Optional. The post ID. Defaults to current post.
 * @param array    $args    Optional. Additional arguments:
 *                          - 'class' (string): CSS class for link
 *                          - 'target' (string): Link target attribute
 * 
 * @example
 * <?php the_wikidata_url('Learn more'); ?>
 */
function the_wikidata_url($text = 'View on Wikidata', $post_id = null, $args = array()) {
    $url = get_wikidata_url($post_id);
    
    if (!$url) {
        return;
    }
    
    $defaults = array(
        'class' => 'wikidata-link',
        'target' => '_blank',
    );
    
    $args = wp_parse_args($args, $defaults);
    
    printf(
        '<a href="%s" class="%s" target="%s" rel="noopener">%s</a>',
        esc_url($url),
        esc_attr($args['class']),
        esc_attr($args['target']),
        esc_html($text)
    );
}

/**
 * Get a claim (property) value from Wikidata.
 * 
 * Wikidata stores structured data as "claims" with property IDs (e.g., P569 for birth date).
 * This function retrieves the main value of the first claim for a given property.
 * 
 * Note: This returns raw values. Complex datatypes (dates, coordinates, quantities)
 * will need additional parsing. For time values, use get_wikidata_claim_time().
 * 
 * @param string   $property The Wikidata property ID (e.g., 'P569' for birth date).
 * @param int|null $post_id  Optional. The post ID. Defaults to current post.
 * @param int      $index    Optional. Claim index if multiple values exist. Default 0.
 * @return mixed The claim value or fallback message.
 * 
 * @example
 * // Get birth date (P569)
 * $birth = get_wikidata_claim('P569');
 * 
 * @example
 * // Get occupation (P106) - returns entity ID like Q1930187
 * $occupation = get_wikidata_claim('P106');
 */
function get_wikidata_claim($property, $post_id = null, $index = 0) {
    $path = "claims.{$property}.{$index}.mainsnak.datavalue.value";
    $value = wikidata_get_value($path, $post_id, array('fallback' => null));
    
    // If value is an entity reference (has 'id' key), return the entity ID
    if (is_array($value) && isset($value['id'])) {
        return $value['id'];
    }
    
    return $value ?: '[Wikidata: property ' . $property . ' not available]';
}

/**
 * Display a claim (property) value.
 * 
 * @param string   $property The Wikidata property ID (e.g., 'P569' for birth date).
 * @param int|null $post_id  Optional. The post ID. Defaults to current post.
 * @param int      $index    Optional. Claim index if multiple values exist. Default 0.
 * 
 * @example
 * <span class="birth-year"><?php the_wikidata_claim('P569'); ?></span>
 */
function the_wikidata_claim($property, $post_id = null, $index = 0) {
    $value = get_wikidata_claim($property, $post_id, $index);
    
    if (is_array($value)) {
        echo esc_html(json_encode($value));
    } else {
        echo esc_html($value);
    }
}

/**
 * Get a time value from a Wikidata claim and format it.
 * 
 * Many Wikidata time properties return complex objects with 'time', 'precision', etc.
 * This helper extracts the time string and can optionally format it.
 * 
 * @param string   $property The Wikidata property ID (e.g., 'P569' for birth date).
 * @param int|null $post_id  Optional. The post ID. Defaults to current post.
 * @param string   $format   Optional. PHP date format string. Default 'Y-m-d'.
 * @param int      $index    Optional. Claim index. Default 0.
 * @return string|null Formatted date string or null if not available.
 * 
 * @example
 * // Get birth date as year only
 * $birth_year = get_wikidata_claim_time('P569', null, 'Y');
 * 
 * @example
 * // Get death date with full format
 * $death = get_wikidata_claim_time('P570', null, 'F j, Y');
 */
function get_wikidata_claim_time($property, $post_id = null, $format = 'Y-m-d', $index = 0) {
    $path = "claims.{$property}.{$index}.mainsnak.datavalue.value.time";
    $time_string = wikidata_get_value($path, $post_id, array('fallback' => null));
    
    if (!$time_string) {
        return null;
    }
    
    // Wikidata time format: +YYYY-MM-DDTHH:MM:SSZ or +YYYY-MM-DD (with + or - prefix)
    // Remove the leading + or - and timezone info
    $time_string = ltrim($time_string, '+-');
    $time_string = str_replace('T00:00:00Z', '', $time_string);
    
    // Try to parse and format
    try {
        $datetime = new DateTime($time_string);
        return $datetime->format($format);
    } catch (Exception $e) {
        return $time_string; // Return raw if parsing fails
    }
}

/**
 * Display a formatted time value from a Wikidata claim.
 * 
 * @param string   $property The Wikidata property ID (e.g., 'P569' for birth date).
 * @param int|null $post_id  Optional. The post ID. Defaults to current post.
 * @param string   $format   Optional. PHP date format string. Default 'Y-m-d'.
 * @param int      $index    Optional. Claim index. Default 0.
 * 
 * @example
 * Born: <?php the_wikidata_claim_time('P569', null, 'Y'); ?>
 */
function the_wikidata_claim_time($property, $post_id = null, $format = 'Y-m-d', $index = 0) {
    $time = get_wikidata_claim_time($property, $post_id, $format, $index);
    
    if ($time) {
        echo esc_html($time);
    }
}

/**
 * Check if a post has Wikidata information.
 * 
 * @param int|null $post_id Optional. The post ID. Defaults to current post.
 * @return bool True if post has a QID and entity data exists, false otherwise.
 * 
 * @example
 * <?php if (has_wikidata()): ?>
 *     <div class="wikidata-info">
 *         <?php the_wikidata_description(); ?>
 *     </div>
 * <?php endif; ?>
 */
function has_wikidata($post_id = null) {
    if ($post_id === null) {
        $post_id = get_the_ID();
    }
    
    if (!$post_id) {
        return false;
    }
    
    $qid = get_field(WONDERCAT_QID_FIELD, $post_id);
    
    if (!$qid) {
        return false;
    }
    
    $entity = wikidata_get_entity_data($post_id);
    
    return $entity !== null;
}
