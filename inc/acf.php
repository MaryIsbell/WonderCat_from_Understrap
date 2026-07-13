<?php
/**
 * ACF related functions and definitions
 *
 * @package Wondercat
 **/

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Normalize text for case-insensitive field matching.
 *
 * @param mixed $value Field metadata value.
 * @return string
 */
function wondercat_gf_normalize_match_text( $value ) {
    if ( ! is_string( $value ) ) {
        return '';
    }

    $value = trim( preg_replace( '/\s+/', ' ', $value ) );

    if ( function_exists( 'mb_strtolower' ) ) {
        return mb_strtolower( $value, 'UTF-8' );
    }

    return strtolower( $value );
}

/**
 * Determine whether a Gravity Forms field represents the Wikidata QID input.
 *
 * @param object $field Gravity Forms field object.
 * @return bool
 */
function wondercat_gf_is_wikidata_qid_field( $field ) {
    if ( ! is_object( $field ) ) {
        return false;
    }

    $label      = wondercat_gf_normalize_match_text( $field->label ?? '' );
    $admin_label = wondercat_gf_normalize_match_text( $field->adminLabel ?? '' );
    $input_name = wondercat_gf_normalize_match_text( $field->inputName ?? '' );
    $css_class  = wondercat_gf_normalize_match_text( $field->cssClass ?? '' );

    if ( in_array( $input_name, array( 'wikidata-qid', 'wikidata_qid' ), true ) ) {
        return true;
    }

    if ( 'wikidata qid' === $label || 'wikidata qid' === $admin_label ) {
        return true;
    }

    if ( false !== strpos( $css_class, 'wikidata-qid' ) || false !== strpos( $css_class, 'wikidata_qid' ) ) {
        return true;
    }

    if ( function_exists( 'wondercat_is_wikidata_qid_field' ) ) {
        $acf_like_field = array(
            'name'  => (string) ( $field->inputName ?? '' ),
            'label' => (string) ( $field->label ?? '' ),
        );

        return wondercat_is_wikidata_qid_field( $acf_like_field, (string) ( $field->inputName ?? '' ) );
    }

    return false;
}

/**
 * Get submitted value for a Gravity Forms field.
 *
 * @param object $field Gravity Forms field object.
 * @return mixed
 */
function wondercat_gf_get_submitted_field_value( $field ) {
    if ( ! is_object( $field ) || ! isset( $field->id ) ) {
        return '';
    }

    $input_key = 'input_' . (string) $field->id;

    if ( ! isset( $_POST[ $input_key ] ) ) {
        return '';
    }

    $value = wp_unslash( $_POST[ $input_key ] );

    if ( is_array( $value ) ) {
        return '';
    }

    return $value;
}

/**
 * Validate any Gravity Forms field that maps to Wikidata QID.
 *
 * @param array $validation_result Gravity Forms validation result.
 * @return array
 */
function wondercat_gform_validate_wikidata_qid_fields( $validation_result ) {
    if ( ! function_exists( 'wondercat_get_qid_validation_error' ) ) {
        return $validation_result;
    }

    if ( ! is_array( $validation_result ) || empty( $validation_result['form'] ) || ! is_array( $validation_result['form'] ) ) {
        return $validation_result;
    }

    $form = $validation_result['form'];

    if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
        return $validation_result;
    }

    foreach ( $form['fields'] as &$field ) {
        if ( ! wondercat_gf_is_wikidata_qid_field( $field ) ) {
            continue;
        }

        $submitted_value = wondercat_gf_get_submitted_field_value( $field );
        $error_message   = wondercat_get_qid_validation_error( $submitted_value );

        if ( '' === $error_message ) {
            continue;
        }

        $validation_result['is_valid'] = false;
        $field->failed_validation      = true;
        $field->validation_message     = $error_message;
    }
    unset( $field );

    $validation_result['form'] = $form;

    return $validation_result;
}
add_filter( 'gform_validation', 'wondercat_gform_validate_wikidata_qid_fields' );


/**
 * NEW CODE FROM TOM, TESTING ON AUGUST 12
 * tie taxonomy to form field https://docs.gravityforms.com/gform_pre_render/
 * 
 **/
add_filter( 'gform_pre_render_8',            'cat_tech_populate_dropdown' );
add_filter( 'gform_pre_validation_8',        'cat_tech_populate_dropdown' );
add_filter( 'gform_pre_submission_filter_8', 'cat_tech_populate_dropdown' );
add_filter( 'gform_admin_pre_render_8',      'cat_tech_populate_dropdown' );

function cat_tech_populate_dropdown( $form ) {
    $field_id        = 5;               // your Drop Down field ID
    $custom_taxonomy = 'technology';

    foreach ( $form['fields'] as &$field ) {
        if ( (int) $field->id !== (int) $field_id ) {
            continue;
        }

        // Make sure this field behaves like a pure Drop Down (no sub-inputs).
        $field->type      = 'select';   // or 'dropdown' depending on GF version; 'select' is typical
        $field->inputType = 'select';
        $field->inputs    = null;       // <-- critical: remove leftover sub-inputs

        // Build choices
        $choices = [];

        // Optional neutral first option (recommended instead of a custom "test" sub-input)
        //$choices[] = [ 'text' => '— Select —', 'value' => '' ];

        // Top manual option (if you still want it)
        $choices[] = [ 'text' => 'Select a term', 'value' => '' ];


$terms = get_terms([
'taxonomy' => $custom_taxonomy,
'hide_empty' => false,
'orderby' => 'title',
'order' => 'ASC',
]);
if ( is_wp_error( $terms ) ) {
continue;
}

foreach ( $terms as $term ) {
    $choices[] = [ 'text' => $term->name, 'value' => $term->name ];
}


        $field->choices           = array_values( $choices );
        $field->enableChoiceValue = true;
        // Optional: make it feel like a placeholder (requires Enhanced UI in GF settings)
        // $field->placeholder = 'Select a technology';
    }

    return $form;
}
 

add_filter( 'gform_pre_render_8',            'cat_exp_populate_dropdown' );
add_filter( 'gform_pre_validation_8',        'cat_exp_populate_dropdown' );
add_filter( 'gform_pre_submission_filter_8', 'cat_exp_populate_dropdown' );
add_filter( 'gform_admin_pre_render_8',      'cat_exp_populate_dropdown' );

function cat_exp_populate_dropdown( $form ) {
    $field_id        = 4;               // your Drop Down field ID
    $custom_taxonomy = 'experience';

    foreach ( $form['fields'] as &$field ) {
        if ( (int) $field->id !== (int) $field_id ) {
            continue;
        }

        // Make sure this field behaves like a pure Drop Down (no sub-inputs).
        $field->type      = 'select';   // or 'dropdown' depending on GF version; 'select' is typical
        $field->inputType = 'select';
        $field->inputs    = null;       // <-- critical: remove leftover sub-inputs 

        // Build choices
        $choices = [];

        // Optional neutral first option (recommended instead of a custom "test" sub-input)
        //$choices[] = [ 'text' => '— Select —', 'value' => '' ];

        // Top manual option (if you still want it)
        $choices[] = [ 'text' => 'Select a term', 'value' => '' ];

        $terms = get_terms([
            'taxonomy'   => $custom_taxonomy,
            'hide_empty' => false,
            'orderby'    => 'title',
            'order'      => 'ASC',
        ]);
        if ( is_wp_error( $terms ) ) {
            continue;
        }

        foreach ( $terms as $term ) {
    $choices[] = [ 'text' => $term->name, 'value' => $term->name ];
        }

        $field->choices           = array_values( $choices );
        $field->enableChoiceValue = true;
        // Optional: make it feel like a placeholder (requires Enhanced UI in GF settings)
        // $field->placeholder = 'Select a technology';
    }

    return $form;
}



//save acf json
add_filter('acf/settings/save_json', 'wondercat_json_save_point');
 
function wondercat_json_save_point( $path ) {
    
    // update path
    $path = get_stylesheet_directory(__FILE__) . '/acf-json'; //replace w get_stylesheet_directory() for theme	    
    
    // return
    return $path;
    
}


// load acf json
add_filter('acf/settings/load_json', 'wondercat_json_load_point');

function wondercat_json_load_point( $paths ) {
    
    // remove original path (optional)
    unset($paths[0]);
    
    
    // append path
    $paths[] = get_stylesheet_directory(__FILE__)  . '/acf-json';//replace w get_stylesheet_directory() for theme
    
    
    // return
    return $paths;
    
}

