<?php

// Exit if accessed directly.
defined('ABSPATH') || exit;

if ( ! defined( 'WONDERCAT_WIKIDATA_STALE_TTL' ) ) {
    define( 'WONDERCAT_WIKIDATA_STALE_TTL', 7 * DAY_IN_SECONDS );
}

if ( ! defined( 'WONDERCAT_WIKIDATA_CACHE_TTL' ) ) {
    define( 'WONDERCAT_WIKIDATA_CACHE_TTL', 30 * DAY_IN_SECONDS );
}

if ( ! defined( 'WONDERCAT_WIKIDATA_REFRESH_QID_HOOK' ) ) {
    define( 'WONDERCAT_WIKIDATA_REFRESH_QID_HOOK', 'wondercat_wikidata_refresh_qid' );
}

if ( ! defined( 'WONDERCAT_WIKIDATA_REFRESH_BATCH_HOOK' ) ) {
    define( 'WONDERCAT_WIKIDATA_REFRESH_BATCH_HOOK', 'wondercat_wikidata_refresh_batch' );
}

/** 
 * Wikidata-related functions and definitions
 */



/**
 * Simple logging function for debugging purposes. 
 */
if (!function_exists('wc_log')) {
    function wc_log($message)
    {
        if (defined('WC_LOGFILE') && WP_DEBUG === true) {
            $logfile = WC_LOGFILE;
            $timestamp = date("Y-m-d H:i:s");
            $formatted_message = is_array($message) || is_object($message) ? print_r($message, true) : $message;
            @error_log("[$timestamp] $formatted_message\n", 3, $logfile);
        }
    }
}

/**
 * Get unique 'wikidata-qid' values from all posts.
 *
 * @return string[] Array of unique wikidata-qid values.
 */
function wikidata_find_posts_with_qid()
{
    global $wpdb;

    $meta_key = 'wikidata-qid';
    $sql = $wpdb->prepare(
        "SELECT DISTINCT pm.meta_value
         FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
         WHERE pm.meta_key = %s
         AND p.post_status = 'publish'
         ORDER BY pm.meta_value ASC",
        $meta_key
    );

    $results = $wpdb->get_col($sql);

    // Filter to ensure we only return valid QIDs (strings starting with 'Q').
    return array_filter(array_unique($results), function ($qid) {
        return is_string($qid) && strpos($qid, 'Q') === 0;
    });
}

/**
 * Check whether a given QID is associated with at least one published
 * user-experience post.
 *
 * @param string $qid A QID.
 * @return bool True when at least one published post references this QID.
 */
function wikidata_qid_has_published_post( $qid ) {
    $qid = wikidata_normalize_qid( $qid );

    if ( ! $qid ) {
        return false;
    }

    global $wpdb;

    $meta_key = WONDERCAT_QID_FIELD;
    $post_type = WONDERCAT_POST_TYPE;

    $sql = $wpdb->prepare(
        "SELECT COUNT(1)
         FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
         WHERE pm.meta_key = %s
         AND pm.meta_value = %s
         AND p.post_type = %s
         AND p.post_status = 'publish'",
        $meta_key,
        $qid,
        $post_type
    );

    return (bool) $wpdb->get_var( $sql );
}

/**
 * Normalize a candidate QID string.
 *
 * @param string $qid Candidate QID.
 * @return string|null Normalized QID or null when invalid.
 */
function wikidata_normalize_qid( $qid ) {
    if ( ! is_string( $qid ) ) {
        return null;
    }

    $qid = strtoupper( trim( sanitize_text_field( $qid ) ) );

    if ( ! preg_match( '/^Q[0-9]+$/', $qid ) ) {
        return null;
    }

    return $qid;
}

/**
 * Build a cache key for stored Wikidata JSON payloads.
 *
 * @param string $qid A valid QID.
 * @return string Cache key.
 */
function wikidata_json_cache_key( $qid ) {
    return 'wondercat_wikidata_json_' . strtolower( $qid );
}

/**
 * Build cache metadata key for stored Wikidata JSON payloads.
 *
 * @param string $qid A valid QID.
 * @return string Cache metadata key.
 */
function wikidata_json_cache_meta_key( $qid ) {
    return 'wondercat_wikidata_json_meta_' . strtolower( $qid );
}

/**
 * Determine whether a Unix timestamp is stale.
 *
 * @param int $timestamp     Unix timestamp.
 * @param int $stale_seconds Optional. Stale threshold.
 * @return bool
 */
function wikidata_is_timestamp_stale( $timestamp, $stale_seconds = WONDERCAT_WIKIDATA_STALE_TTL ) {
    if ( empty( $timestamp ) ) {
        return true;
    }

    return ( time() - (int) $timestamp ) > (int) $stale_seconds;
}

/**
 * Determine whether a wikidata entity row is stale based on updated_at.
 *
 * @param object $entity_row   Entity row from wikidata table.
 * @param int    $stale_seconds Optional. Stale threshold.
 * @return bool
 */
function wikidata_entity_row_is_stale( $entity_row, $stale_seconds = WONDERCAT_WIKIDATA_STALE_TTL ) {
    if ( ! is_object( $entity_row ) || empty( $entity_row->updated_at ) ) {
        return true;
    }

    $updated_at = strtotime( $entity_row->updated_at );

    if ( false === $updated_at ) {
        return true;
    }

    return wikidata_is_timestamp_stale( $updated_at, $stale_seconds );
}

/**
 * Get cache metadata for a QID.
 *
 * @param string $qid A valid QID.
 * @return array{fetched_at:int}|array Empty array when metadata missing.
 */
function wikidata_get_cached_json_meta( $qid ) {
    $meta_key = wikidata_json_cache_meta_key( $qid );

    $meta = wp_cache_get( $meta_key, 'wikidata' );
    if ( is_array( $meta ) ) {
        return $meta;
    }

    $meta = get_transient( $meta_key );
    if ( is_array( $meta ) ) {
        wp_cache_set( $meta_key, $meta, 'wikidata', WONDERCAT_WIKIDATA_CACHE_TTL );
        return $meta;
    }

    return array();
}

/**
 * Get cached JSON payload and freshness state.
 *
 * @param string $qid A valid QID.
 * @return array{json:string|false,is_stale:bool}
 */
function wikidata_get_cached_json_with_status( $qid ) {
    $json = wikidata_get_cached_json( $qid );

    if ( false === $json ) {
        return array(
            'json'      => false,
            'is_stale'  => false,
        );
    }

    $meta      = wikidata_get_cached_json_meta( $qid );
    $fetched_at = isset( $meta['fetched_at'] ) ? (int) $meta['fetched_at'] : 0;

    return array(
        'json'     => $json,
        'is_stale' => wikidata_is_timestamp_stale( $fetched_at ),
    );
}

/**
 * Get cached JSON payload for a QID from object cache/transients.
 *
 * @param string $qid A valid QID.
 * @return string|false JSON string when cached, otherwise false.
 */
function wikidata_get_cached_json( $qid ) {
    $cache_key = wikidata_json_cache_key( $qid );

    $cached = wp_cache_get( $cache_key, 'wikidata' );
    if ( false !== $cached ) {
        return $cached;
    }

    $cached = get_transient( $cache_key );
    if ( false !== $cached ) {
        wp_cache_set( $cache_key, $cached, 'wikidata', DAY_IN_SECONDS );
        return $cached;
    }

    return false;
}

/**
 * Cache JSON payload for a QID in object cache/transients.
 *
 * @param string $qid A valid QID.
 * @param string $json JSON payload.
 * @param int    $ttl  Optional. Cache TTL in seconds.
 * @return void
 */
function wikidata_set_cached_json( $qid, $json, $ttl = WONDERCAT_WIKIDATA_CACHE_TTL ) {
    $cache_key = wikidata_json_cache_key( $qid );
    $meta_key  = wikidata_json_cache_meta_key( $qid );
    $meta      = array(
        'fetched_at' => time(),
    );

    wp_cache_set( $cache_key, $json, 'wikidata', $ttl );
    set_transient( $cache_key, $json, $ttl );

    wp_cache_set( $meta_key, $meta, 'wikidata', $ttl );
    set_transient( $meta_key, $meta, $ttl );
}

/**
 * Schedule a background refresh for a single QID.
 *
 * @param string $qid A QID.
 * @return void
 */
function wikidata_schedule_refresh_qid( $qid ) {
    $qid = wikidata_normalize_qid( $qid );

    if ( ! $qid ) {
        return;
    }

    if ( wp_next_scheduled( WONDERCAT_WIKIDATA_REFRESH_QID_HOOK, array( $qid ) ) ) {
        return;
    }

    wp_schedule_single_event( time() + MINUTE_IN_SECONDS, WONDERCAT_WIKIDATA_REFRESH_QID_HOOK, array( $qid ) );
}

/**
 * Schedule a background refresh for multiple QIDs.
 *
 * @param string[] $qids QIDs to refresh.
 * @return void
 */
function wikidata_schedule_refresh_batch( $qids ) {
    if ( ! is_array( $qids ) || empty( $qids ) ) {
        return;
    }

    $normalized_qids = array();

    foreach ( $qids as $qid ) {
        $normalized_qid = wikidata_normalize_qid( $qid );

        if ( $normalized_qid ) {
            $normalized_qids[] = $normalized_qid;
        }
    }

    $normalized_qids = array_values( array_unique( $normalized_qids ) );
    sort( $normalized_qids );

    if ( empty( $normalized_qids ) ) {
        return;
    }

    if ( wp_next_scheduled( WONDERCAT_WIKIDATA_REFRESH_BATCH_HOOK, array( $normalized_qids ) ) ) {
        return;
    }

    wp_schedule_single_event( time() + MINUTE_IN_SECONDS, WONDERCAT_WIKIDATA_REFRESH_BATCH_HOOK, array( $normalized_qids ) );
}

/**
 * Convert a Wikidata entities map into per-QID JSON payloads.
 *
 * @param array $entities Entities map from wbgetentities response.
 * @return array<string,string> Map of QID => JSON payload.
 */
function wikidata_entities_to_json_map( $entities ) {
    if ( ! is_array( $entities ) ) {
        return array();
    }

    $result = array();

    foreach ( $entities as $qid => $entity_data ) {
        $normalized_qid = wikidata_normalize_qid( $qid );

        if ( ! $normalized_qid || ! is_array( $entity_data ) ) {
            continue;
        }

        $result[ $normalized_qid ] = wp_json_encode(
            array(
                'entities' => array(
                    $normalized_qid => $entity_data,
                ),
            )
        );
    }

    return $result;
}


// @url https://developer.wordpress.org/reference/functions/wp_remote_get/
// @example https://www.wikidata.org/w/api.php?action=wbgetentities&format=json&ids=Q223880
function wikidata_fetch_json_by_id($qid, $force_refresh = false)
{
    $qid = wikidata_normalize_qid( $qid );

    if ( ! $qid ) {
        return false;
    }

    if ( ! $force_refresh ) {
        $cached = wikidata_get_cached_json_with_status( $qid );

        if ( false !== $cached['json'] ) {
            if ( ! empty( $cached['is_stale'] ) ) {
                wikidata_schedule_refresh_qid( $qid );
            }

            return $cached['json'];
        }
    }

    $response = wp_remote_get(wikidata_get_rest_api_url($qid), [
        'timeout' => 10,
        'headers' => [
            'Accept' => 'application/json',
        ],
    ]);
    if (is_array($response) && ! is_wp_error($response)) {
        if ( ! empty( $response['body'] ) ) {
            wikidata_set_cached_json( $qid, $response['body'] );
            return $response['body']; // use the content
        }

        return false;
    } else {
        wc_log($response);
        return false;
    }
}

/**
 * Batch fetch entity JSON for a list of QIDs.
 *
 * Returns a map keyed by QID where each value is a JSON payload compatible
 * with existing storage expectations (contains an `entities` root key).
 *
 * @param string[] $qids List of QIDs.
 * @return array<string,string> Map of QID => JSON payload.
 */
function wikidata_batch_fetch_json_by_ids( $qids, $force_refresh = false ) {
    if ( ! is_array( $qids ) || empty( $qids ) ) {
        return array();
    }

    $normalized_qids = array();

    foreach ( $qids as $qid ) {
        $normalized_qid = wikidata_normalize_qid( $qid );

        if ( $normalized_qid ) {
            $normalized_qids[] = $normalized_qid;
        }
    }

    $normalized_qids = array_values( array_unique( $normalized_qids ) );

    if ( empty( $normalized_qids ) ) {
        return array();
    }

    $results    = array();
    $missing    = array();
    $stale_qids = array();

    foreach ( $normalized_qids as $qid ) {
        if ( $force_refresh ) {
            $missing[] = $qid;
            continue;
        }

        $cached = wikidata_get_cached_json_with_status( $qid );

        if ( false !== $cached['json'] ) {
            $results[ $qid ] = $cached['json'];

            if ( ! empty( $cached['is_stale'] ) ) {
                $stale_qids[] = $qid;
            }

            continue;
        }

        $missing[] = $qid;
    }

    if ( ! empty( $stale_qids ) ) {
        wikidata_schedule_refresh_batch( $stale_qids );
    }

    if ( empty( $missing ) ) {
        return $results;
    }

    $chunks = array_chunk( $missing, 50 );

    foreach ( $chunks as $chunk ) {
        $endpoint = add_query_arg(
            array(
                'action'  => 'wbgetentities',
                'format'  => 'json',
                'ids'     => implode( '|', $chunk ),
                'props'   => 'labels|descriptions|claims',
                'languages' => 'en',
                'languagefallback' => 1,
            ),
            'https://www.wikidata.org/w/api.php'
        );

        $response = wp_remote_get(
            $endpoint,
            array(
                'timeout' => 12,
                'headers' => array(
                    'Accept' => 'application/json',
                ),
            )
        );

        if ( ! is_array( $response ) || is_wp_error( $response ) || empty( $response['body'] ) ) {
            wc_log( $response );
            continue;
        }

        $decoded = json_decode( $response['body'], true );

        if ( ! is_array( $decoded ) || empty( $decoded['entities'] ) ) {
            continue;
        }

        $json_map = wikidata_entities_to_json_map( $decoded['entities'] );

        foreach ( $json_map as $qid => $json_payload ) {
            wikidata_set_cached_json( $qid, $json_payload );
            $results[ $qid ] = $json_payload;
        }
    }

    return $results;
}

/**
 * Refresh a single QID in the background.
 *
 * @param string $qid A QID.
 * @return void
 */
function wikidata_handle_refresh_qid_event( $qid ) {
    $qid = wikidata_normalize_qid( $qid );

    if ( ! $qid ) {
        return;
    }

    if ( ! wikidata_qid_has_published_post( $qid ) ) {
        return;
    }

    $json = wikidata_fetch_json_by_id( $qid, true );

    if ( ! $json ) {
        return;
    }

    $entity      = wikidata_get_by_qid( $qid );
    $decoded     = json_decode( $json, true );
    $label       = $entity ? $entity->label : $qid;
    $description = $entity ? $entity->description : null;

    if ( isset( $decoded['entities'][ $qid ] ) && is_array( $decoded['entities'][ $qid ] ) ) {
        $entity_data = $decoded['entities'][ $qid ];

        if ( isset( $entity_data['labels']['en']['value'] ) ) {
            $label = $entity_data['labels']['en']['value'];
        }

        if ( isset( $entity_data['descriptions']['en']['value'] ) ) {
            $description = $entity_data['descriptions']['en']['value'];
        }
    }

    wikidata_upsert( $qid, wikidata_get_rest_api_url( $qid ), $label, $description, $json );
}

/**
 * Refresh multiple QIDs in the background.
 *
 * @param string[] $qids QIDs to refresh.
 * @return void
 */
function wikidata_handle_refresh_batch_event( $qids ) {
    if ( ! is_array( $qids ) || empty( $qids ) ) {
        return;
    }

    // Only refresh QIDs still associated with published user-experience posts.
    $qids = array_values(
        array_filter( $qids, function ( $qid ) {
            return wikidata_qid_has_published_post( $qid );
        } )
    );

    if ( empty( $qids ) ) {
        return;
    }

    $json_map = wikidata_batch_fetch_json_by_ids( $qids, true );

    if ( empty( $json_map ) ) {
        return;
    }

    foreach ( $qids as $qid ) {
        $normalized_qid = wikidata_normalize_qid( $qid );

        if ( ! $normalized_qid || empty( $json_map[ $normalized_qid ] ) ) {
            continue;
        }

        $entity      = wikidata_get_by_qid( $normalized_qid );
        $decoded     = json_decode( $json_map[ $normalized_qid ], true );
        $label       = $entity ? $entity->label : $normalized_qid;
        $description = $entity ? $entity->description : null;

        if ( isset( $decoded['entities'][ $normalized_qid ] ) && is_array( $decoded['entities'][ $normalized_qid ] ) ) {
            $entity_data = $decoded['entities'][ $normalized_qid ];

            if ( isset( $entity_data['labels']['en']['value'] ) ) {
                $label = $entity_data['labels']['en']['value'];
            }

            if ( isset( $entity_data['descriptions']['en']['value'] ) ) {
                $description = $entity_data['descriptions']['en']['value'];
            }
        }

        wikidata_upsert( $normalized_qid, wikidata_get_rest_api_url( $normalized_qid ), $label, $description, $json_map[ $normalized_qid ] );
    }
}

add_action( WONDERCAT_WIKIDATA_REFRESH_QID_HOOK, 'wikidata_handle_refresh_qid_event', 10, 1 );
add_action( WONDERCAT_WIKIDATA_REFRESH_BATCH_HOOK, 'wikidata_handle_refresh_batch_event', 10, 1 );

function wikidata_get_rest_api_url($qid)
{

    // also "https://www.wikidata.org/w/api.php?action=wbgetentities&format=json&ids=$qid"
    return "https://www.wikidata.org/wiki/Special:EntityData/$qid.json";
}