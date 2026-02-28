<?php

// Exit if accessed directly.
defined('ABSPATH') || exit;

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


// @url https://developer.wordpress.org/reference/functions/wp_remote_get/
// @example https://www.wikidata.org/w/api.php?action=wbgetentities&format=json&ids=Q223880
function wikidata_fetch_json_by_id($qid)
{
    $response = wp_remote_get(wikidata_get_rest_api_url($qid), [
        'timeout' => 10,
        'headers' => [
            'Accept' => 'application/json',
        ],
    ]);
    if (is_array($response) && ! is_wp_error($response)) {
        return $response['body']; // use the content
    } else {
        wc_log($response);
        return false;
    }
}

function wikidata_get_rest_api_url($qid)
{

    // also "https://www.wikidata.org/w/api.php?action=wbgetentities&format=json&ids=$qid"
    return "https://www.wikidata.org/wiki/Special:EntityData/$qid.json";
}