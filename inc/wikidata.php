<?php

// Exit if accessed directly.
defined('ABSPATH') || exit;

define('WONDERCAT_QID_FIELD', 'wikidata-qid');
define('WONDERCAT_POST_TYPE', 'user-experience');

require_once dirname(__FILE__) . '/wikidata/utilities.php';
require_once dirname(__FILE__) . '/wikidata/table.php';
require_once dirname(__FILE__) . '/wikidata/template-tags.php';
require_once dirname(__FILE__) . '/wikidata/rewrite.php';

// Load admin interface files when in admin context
if ( is_admin() ) {
    require_once dirname(__FILE__) . '/wikidata/admin-page.php';
    require_once dirname(__FILE__) . '/wikidata/admin-edit.php';
}


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

    // Only process QIDs on user-experience posts.
    if ( WONDERCAT_POST_TYPE !== $post->post_type ) {
        return;
    }

    // Get the WONDERCAT_QID_FIELD custom field value.
    $qid = get_post_meta( $post_id, WONDERCAT_QID_FIELD, true );

    if ( $qid ) {
        // Fetch JSON from Wikidata API; only upsert if fetch returned valid data.
        $json = wikidata_fetch_json_by_id($qid);

        if ( false !== $json ) {
            wikidata_upsert(
                $qid,
                wikidata_get_rest_api_url($qid),
                get_post_field('post_title', $post_id, 'raw'),
                null,
                $json
            );
        }
    }

    global $wondercat_process_already_run;
    $wondercat_process_already_run = true;
}
add_action('acf/save_post', 'wondercat_process_qid_field', 20, 1 );

/**
 * Determine whether current request context targets a user-experience post edit.
 *
 * @return bool
 */
function wondercat_is_user_experience_edit_context() {
    $post_id = 0;

    if ( isset( $_POST['post_ID'] ) ) {
        $post_id = absint( $_POST['post_ID'] );
    } elseif ( isset( $_POST['post_id'] ) && is_numeric( $_POST['post_id'] ) ) {
        $post_id = absint( $_POST['post_id'] );
    } elseif ( isset( $_POST['post_id'] ) && is_string( $_POST['post_id'] ) && 0 === strpos( $_POST['post_id'], 'post_' ) ) {
        $post_id = absint( substr( $_POST['post_id'], 5 ) );
    }

    if ( $post_id > 0 ) {
        return WONDERCAT_POST_TYPE === get_post_type( $post_id );
    }

    if ( isset( $_POST['post_type'] ) ) {
        return WONDERCAT_POST_TYPE === sanitize_key( $_POST['post_type'] );
    }

    return false;
}

/**
 * Determine whether an ACF field represents the WonderCat Wikidata QID input.
 *
 * @param array  $field ACF field config.
 * @param string $name  Submitted input name.
 * @return bool
 */
function wondercat_is_wikidata_qid_field( $field, $name = '' ) {
    if ( ! is_array( $field ) ) {
        return false;
    }

    $field_name = isset( $field['name'] ) ? (string) $field['name'] : '';
    if ( WONDERCAT_QID_FIELD === $field_name || 'wikidata_qid' === $field_name ) {
        return true;
    }

    $field_key = isset( $field['key'] ) ? (string) $field['key'] : '';
    if ( 'field_66ec85b50b8f1' === $field_key ) {
        return true;
    }

    $field_label = isset( $field['label'] ) ? strtolower( trim( (string) $field['label'] ) ) : '';
    if ( 'wikidata qid' === $field_label ) {
        return true;
    }

    if ( is_string( $name ) && false !== strpos( $name, WONDERCAT_QID_FIELD ) ) {
        return true;
    }

    return false;
}

/**
 * Convert a candidate QID value into a user-facing validation error.
 *
 * @param mixed $value Submitted value.
 * @return string Empty string when value is valid.
 */
function wondercat_get_qid_validation_error( $value ) {
    if ( '' === trim( (string) $value ) ) {
        return '';
    }

    $normalized_qid = wikidata_normalize_qid( $value );

    if ( ! $normalized_qid ) {
        return __( 'Enter a Wikidata QID in the format Q123 (capital Q followed by digits).', 'understrap-child' );
    }

    $validation = wikidata_validate_qid_with_status( $normalized_qid );

    if ( ! empty( $validation['valid'] ) ) {
        return '';
    }

    if ( isset( $validation['reason'] ) && 'not_found' === $validation['reason'] ) {
        return __( 'This Wikidata QID does not appear to exist. Please check the ID and try again.', 'understrap-child' );
    }

    return __( 'This Wikidata QID could not be verified right now (for example, a temporary API issue). Saving is blocked to prevent invalid data. Please try again shortly.', 'understrap-child' );
}

/**
 * Shared ACF field validator for the WonderCat QID field.
 *
 * @param string|bool $valid Current validation status.
 * @param mixed       $value Field value.
 * @param array       $field Field config.
 * @param string      $name  Input name.
 * @return string|bool
 */
function wondercat_validate_qid_field_common( $valid, $value, $field, $name ) {
    if ( true !== $valid ) {
        return $valid;
    }

    if ( ! wondercat_is_user_experience_edit_context() ) {
        return $valid;
    }

    if ( ! wondercat_is_wikidata_qid_field( $field, $name ) ) {
        return $valid;
    }

    $error_message = wondercat_get_qid_validation_error( $value );

    if ( '' !== $error_message ) {
        return $error_message;
    }

    return $valid;
}

/**
 * Validate posted ACF values before save using runtime field resolution.
 *
 * This catches environments where field-name-specific hooks do not match.
 *
 * @return void
 */
function wondercat_validate_qid_on_save() {
    if ( ! wondercat_is_user_experience_edit_context() ) {
        return;
    }

    if ( empty( $_POST['acf'] ) || ! is_array( $_POST['acf'] ) ) {
        return;
    }

    foreach ( $_POST['acf'] as $field_key => $value ) {
        if ( ! is_string( $field_key ) || ! function_exists( 'acf_get_field' ) ) {
            continue;
        }

        $field = acf_get_field( $field_key );
        if ( ! wondercat_is_wikidata_qid_field( $field, (string) $field_key ) ) {
            continue;
        }

        $error_message = wondercat_get_qid_validation_error( $value );
        if ( '' !== $error_message ) {
            acf_add_validation_error( sprintf( 'acf[%s]', $field_key ), $error_message );
        }
    }
}

/**
 * Prevent invalid QID meta values from persisting when validation is bypassed.
 *
 * @param null|bool $check      Short-circuit value.
 * @param int       $object_id  Post ID.
 * @param string    $meta_key   Meta key being updated.
 * @param mixed     $meta_value Meta value being updated.
 * @return null|bool
 */
function wondercat_guard_wikidata_qid_meta_update( $check, $object_id, $meta_key, $meta_value ) {
    if ( WONDERCAT_QID_FIELD !== $meta_key ) {
        return $check;
    }

    if ( WONDERCAT_POST_TYPE !== get_post_type( $object_id ) ) {
        return $check;
    }

    if ( '' === trim( (string) $meta_value ) ) {
        return $check;
    }

    if ( '' === wondercat_get_qid_validation_error( $meta_value ) ) {
        return $check;
    }

    return false;
}
add_action( 'acf/validate_save_post', 'wondercat_validate_qid_on_save', 5 );
add_filter( 'update_post_metadata', 'wondercat_guard_wikidata_qid_meta_update', 10, 4 );

/**
 * Validate the wikidata-qid ACF field value on save.
 *
 * Field is optional (empty is OK), but if filled the QID must correspond
 * to an existing Wikidata entity.
 *
 * @param string $valid   Current validation status.
 * @param mixed  $value   Field value.
 * @param array  $field   Field config.
 * @param string $name    Input name.
 * @return string|bool    Error message on failure, true on success.
 */
function wondercat_validate_qid_field( $valid, $value, $field, $name ) {
    return wondercat_validate_qid_field_common( $valid, $value, $field, $name );
}
add_filter( 'acf/validate_value/name=wikidata-qid', 'wondercat_validate_qid_field', 10, 4 );
add_filter( 'acf/validate_value', 'wondercat_validate_qid_field_common', 10, 4 );

