<?php
/**
 * Wikidata Rewrite Rules
 *
 * Registers the /wikidata/{qid} endpoint that renders a single Wikidata entity view.
 *
 * @package WonderCat
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Register the wikidata_qid query variable.
 *
 * @param string[] $vars Existing public query variables.
 * @return string[] Modified query variables.
 */
function wondercat_wikidata_query_vars( array $vars ): array {
	$vars[] = 'wikidata_qid';
	return $vars;
}
add_filter( 'query_vars', 'wondercat_wikidata_query_vars' );

/**
 * Register the /wikidata/{qid} rewrite rule.
 */
function wondercat_wikidata_rewrite_rule(): void {
	add_rewrite_rule(
		'^wikidata/([^/]+)/?$',
		'index.php?wikidata_qid=$matches[1]',
		'top'
	);
}
add_action( 'init', 'wondercat_wikidata_rewrite_rule' );

/**
 * Load the Wikidata entity template when the wikidata_qid query var is set.
 *
 * @param string $template The template file path WordPress resolved.
 * @return string Path to the Wikidata entity template, or unchanged $template.
 */
function wondercat_wikidata_template_include( string $template ): string {
	if ( ! get_query_var( 'wikidata_qid' ) ) {
		return $template;
	}

	$custom = get_stylesheet_directory() . '/wikidata-entity.php';

	if ( file_exists( $custom ) ) {
		return $custom;
	}

	return $template;
}
add_filter( 'template_include', 'wondercat_wikidata_template_include' );
