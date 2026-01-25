<?php
/**
 * Understrap Child Theme functions and definitions
 *
 * @package UnderstrapChild
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * Removes the parent themes stylesheet and scripts from inc/enqueue.php
 */
function understrap_remove_scripts() {
	wp_dequeue_style( 'understrap-styles' );
	wp_deregister_style( 'understrap-styles' );

	wp_dequeue_script( 'understrap-scripts' );
	wp_deregister_script( 'understrap-scripts' );
}
add_action( 'wp_enqueue_scripts', 'understrap_remove_scripts', 20 );



/**
 * Enqueue our stylesheet and javascript file
 */
function theme_enqueue_styles() {

	// Get the theme data.
	$the_theme = wp_get_theme();

	$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
	// Grab asset urls.
	$theme_styles  = "/css/child-theme{$suffix}.css";
	$theme_scripts = "/js/child-theme{$suffix}.js";

	wp_enqueue_style( 'child-understrap-styles', get_stylesheet_directory_uri() . $theme_styles, array(), $the_theme->get( 'Version' ) );
	wp_enqueue_script( 'jquery' );
	wp_enqueue_script( 'child-understrap-scripts', get_stylesheet_directory_uri() . $theme_scripts, array(), $the_theme->get( 'Version' ), true );
	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'theme_enqueue_styles' );



/**
 * Load the child theme's text domain
 */
function add_child_theme_textdomain() {
	load_child_theme_textdomain( 'understrap-child', get_stylesheet_directory() . '/languages' );
}
add_action( 'after_setup_theme', 'add_child_theme_textdomain' );



/**
 * Overrides the theme_mod to default to Bootstrap 5
 *
 * This function uses the `theme_mod_{$name}` hook and
 * can be duplicated to override other theme settings.
 *
 * @return string
 */
function understrap_default_bootstrap_version() {
	return 'bootstrap5';
}
add_filter( 'theme_mod_understrap_bootstrap_version', 'understrap_default_bootstrap_version', 20 );



/**
 * Loads javascript for showing customizer warning dialog.
 */
function understrap_child_customize_controls_js() {
	wp_enqueue_script(
		'understrap_child_customizer',
		get_stylesheet_directory_uri() . '/js/customizer-controls.js',
		array( 'customize-preview' ),
		'20130508',
		true
	);
}
add_action( 'customize_controls_enqueue_scripts', 'understrap_child_customize_controls_js' );

//loads inc contents
require_once get_theme_file_path( 'inc/acf.php' );

/* Add USER EXPERIENCE to author archives */
function ue_post_author_archive($query) {
    if (!$query->is_main_query() || !$query->is_author()) {
        return;
    }
    
    $query->set('post_type', array('user-experience', 'post'));
}
add_action('pre_get_posts', 'ue_post_author_archive');

/*Allows users to set experiences to private through gravity form*/
add_action( 'transition_post_status', function( $new, $old, $post ) {

    if ( $post->post_type !== 'user-experience' ) return;

    // Get visibility directly from GF entry
    $entry = GFAPI::get_entry( get_post_meta( $post->ID, '_gform-entry-id', true ) );

    if ( is_wp_error( $entry ) ) return;

    $visibility = strtolower( trim( rgar( $entry, '26' ) ) );

    if ( $visibility !== 'private' ) return;

    if ( $new !== 'private' ) {

        remove_action( 'transition_post_status', __FUNCTION__, 10 );

        wp_update_post([
            'ID' => $post->ID,
            'post_status' => 'private'
        ]);

    }

}, 10, 3 );

add_action( 'gform_after_create_post', 'set_multiple_taxonomy_terms_by_id', 10, 3 );
function set_multiple_taxonomy_terms_by_id( $post_id, $feed, $entry ) {

    // Ensure a valid post ID
    if ( ! $post_id ) {
        return;
    }

    /**
     * Map Gravity Form field IDs to your taxonomy names
     * 'GF field ID' => 'taxonomy_name'
     */
    $taxonomy_map = array(
        '4' => 'experience', // GF field 4 maps to experience
        '5' => 'technology', // GF field 5 maps to technology
    );

    foreach ( $taxonomy_map as $field_id => $taxonomy_name ) {

        // Get term ID from GF entry
        $term_id = rgar( $entry, $field_id );

        // Skip if not a valid numeric ID
        if ( ! $term_id || ! is_numeric( $term_id ) ) {
            continue;
	error_log( 'GF Entry ID: ' . $entry['id'] );
    error_log( 'Experience Field Value: ' . print_r( $experience_id, true ) );
    error_log( 'Technology Field Value: ' . print_r( $technology_id, true ) );
        }

        // Update the taxonomy for the post
        wp_set_post_terms( $post_id, array( intval( $term_id ) ), $taxonomy_name );

        // Optional: update ACF field if it exists for this taxonomy
        // update_field( $taxonomy_name . '_acf_field', intval( $term_id ), $post_id );
    }
}
