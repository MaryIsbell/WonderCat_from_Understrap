<?php
/**
 * ACF related functions and definitions
 *
 * @package Wondercat
 **/

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;


/**
 * NEW CODE FROM TOM, TESTING ON AUGUST 12
 * tie taxonomy to form field https://docs.gravityforms.com/gform_pre_render/
 * 
 **/
add_filter( 'gform_pre_render_6',            'cat_tech_populate_dropdown' );
add_filter( 'gform_pre_validation_6',        'cat_tech_populate_dropdown' );
add_filter( 'gform_pre_submission_filter_6', 'cat_tech_populate_dropdown' );
add_filter( 'gform_admin_pre_render_6',      'cat_tech_populate_dropdown' );

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
 

add_filter( 'gform_pre_render_6',            'cat_exp_populate_dropdown' );
add_filter( 'gform_pre_validation_6',        'cat_exp_populate_dropdown' );
add_filter( 'gform_pre_submission_filter_6', 'cat_exp_populate_dropdown' );
add_filter( 'gform_admin_pre_render_6',      'cat_exp_populate_dropdown' );

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

