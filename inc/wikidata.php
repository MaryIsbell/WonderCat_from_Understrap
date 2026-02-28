<?php

// Exit if accessed directly.
defined('ABSPATH') || exit;

define('WONDERCAT_QID_FIELD', 'wikidata-qid');
define('WONDERCAT_POST_TYPE', 'user-experience');

require_once dirname(__FILE__) . '/wikidata/utilities.php';
require_once dirname(__FILE__) . '/wikidata/table.php';


// Check if ACF is active
if (! class_exists('ACF')) {
    add_action('admin_notices', function () {
        echo '<div class="error"><p>WonderCat requires the Advanced Custom Fields plugin to be installed and activated.</p></div>';
    });
    return;
}

/**
 * Listen for post saves and process the WONDERCAT_QID_FIELD custom field.
 * Using save_post_post action (priority 20) to ensure post is fully saved.
 *
 * @param int $post_id The ID of the post being saved.
 */
function wondercat_process_qid_field( $post_id ) {


    // Check if this is an autosave or a revision.
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    // Check if this is a revision
    if ( wp_is_post_revision( $post_id ) ) {
        return;
    }

    // Get the post status
    $post = get_post( $post_id );
    if ( ! $post || 'publish' !== $post->post_status ) {
        return;
    }

    // Get the WONDERCAT_QID_FIELD custom field value.
    $qid = get_post_meta( $post_id, WONDERCAT_QID_FIELD, true );

    if ( $qid ) {
        // Process the QID.
        wikidata_upsert( 
            $qid, // QID
            wikidata_get_rest_api_url($qid), // URL
            get_post_field('post_title', $post_id, 'raw'), // Label (raw title without HTML escaping)
            null, // Description (could be enhanced to pull from post content or another field)
            wikidata_fetch_json_by_id($qid) // JSON data from Wikidata API
        );
    }

    global $wondercat_process_already_run;
    $wondercat_process_already_run = true;
}
add_action('acf/save_post', 'wondercat_process_qid_field', 20, 1 );

