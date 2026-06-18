<?php
/**
 * Sync ACF Local JSON into the database in wp-env.
 *
 * This script is designed to be idempotent. It stores a content hash option
 * so repeated runs with unchanged JSON become a no-op.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Output helper for WP-CLI and non-CLI contexts.
 */
function wondercat_acf_sync_log( $message ) {
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::log( $message );
		return;
	}

	error_log( '[wondercat-acf-sync] ' . $message );
}

if ( ! class_exists( 'ACF' ) ) {
	wondercat_acf_sync_log( 'ACF not active; skipping JSON sync.' );
	return;
}

$is_force = false;
if ( isset( $argv ) && is_array( $argv ) ) {
	$is_force = in_array( '--force', $argv, true );
}

if ( '1' === getenv( 'WONDERCAT_ACF_SYNC_FORCE' ) ) {
	$is_force = true;
}

/**
 * Normalize JSON payload for signature checks.
 *
 * ACF import operations can touch volatile fields (for example "modified").
 * We intentionally exclude those so unchanged schema data remains a no-op.
 */
function wondercat_acf_sync_normalize( $value ) {
	if ( is_array( $value ) ) {
		unset( $value['modified'], $value['ID'] );

		foreach ( $value as $key => $child ) {
			$value[ $key ] = wondercat_acf_sync_normalize( $child );
		}

		ksort( $value );
	}

	return $value;
}

$theme_json_dir = trailingslashit( get_stylesheet_directory() ) . 'acf-json';
$json_files     = glob( $theme_json_dir . '/*.json' );

if ( empty( $json_files ) ) {
	wondercat_acf_sync_log( 'No acf-json files found; nothing to sync.' );
	return;
}

sort( $json_files );

$signature_parts = array();
$parsed_items    = array();

foreach ( $json_files as $json_file ) {
	$raw_data = file_get_contents( $json_file );
	$data     = json_decode( $raw_data, true );

	if ( ! is_array( $data ) || empty( $data['key'] ) ) {
		continue;
	}

	$normalized        = wondercat_acf_sync_normalize( $data );
	$signature_parts[] = $data['key'] . ':' . md5( wp_json_encode( $normalized ) );
	$parsed_items[]    = array(
		'file' => $json_file,
		'data' => $data,
	);
}


$current_signature = md5( implode( '|', $signature_parts ) );
$option_key        = 'wondercat_acf_json_sync_signature';
$saved_signature   = (string) get_option( $option_key, '' );


if ( ! $is_force && $saved_signature === $current_signature ) {
	wondercat_acf_sync_log( 'ACF JSON unchanged; sync skipped.' );
	return;
}

if ( function_exists( 'remove_filter' ) ) {
	// Prevent import from rewriting tracked acf-json files in this repository.
	remove_filter( 'acf/settings/save_json', 'wondercat_json_save_point' );
}

if ( function_exists( 'add_filter' ) ) {
	// Stop all local-json save paths during import so this remains read-only.
	add_filter( 'acf/json/save_paths', '__return_empty_array', 999, 2 );
}

$imported = array();
$skipped  = array();

foreach ( $parsed_items as $item ) {
	$json_file = $item['file'];
	$data      = $item['data'];

	if ( ! is_array( $data ) || empty( $data['key'] ) ) {
		$skipped[] = basename( $json_file ) . ': invalid-json';
		continue;
	}

	$key = (string) $data['key'];

	if ( strpos( $key, 'group_' ) === 0 && function_exists( 'acf_import_field_group' ) ) {
		try {
			acf_import_field_group( $data );
			$imported[] = $key;
		} catch ( Throwable $error ) {
			$skipped[] = basename( $json_file ) . ': import-error-' . $error->getMessage();
		}
		continue;
	}

	if (
		( strpos( $key, 'post_type_' ) === 0 || strpos( $key, 'taxonomy_' ) === 0 )
		&& function_exists( 'acf_import_internal_post_type' )
	) {
		$internal_type = strpos( $key, 'taxonomy_' ) === 0 ? 'taxonomy' : 'post_type';

		try {
			acf_import_internal_post_type( $data, $internal_type );
			$imported[] = $key;
		} catch ( Throwable $error ) {
			$skipped[] = basename( $json_file ) . ': import-error-' . $error->getMessage();
		}
		continue;
	}

	$skipped[] = basename( $json_file ) . ': unsupported-key-' . $key;
}

update_option( $option_key, $current_signature, false );

$message = sprintf(
	'ACF JSON sync complete. Imported: %d. Skipped: %d.',
	count( $imported ),
	count( $skipped )
);

wondercat_acf_sync_log( $message );

if ( ! empty( $skipped ) ) {
	wondercat_acf_sync_log( 'Skipped items: ' . implode( ', ', $skipped ) );
}
