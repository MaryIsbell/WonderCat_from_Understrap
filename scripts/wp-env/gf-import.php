<?php
/**
 * Import Gravity Forms exports from FormArchive into wp-env.
 *
 * The default behavior skips forms whose titles already exist.
 * Set WONDERCAT_GF_IMPORT_FORCE=1 to bypass duplicate-title checks.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Output helper for WP-CLI and non-CLI contexts.
 */
function wondercat_gf_import_log( $message ) {
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::log( $message );
		return;
	}

	error_log( '[wondercat-gf-import] ' . $message );
}

if ( ! class_exists( 'GFAPI' ) || ! method_exists( 'GFAPI', 'add_form' ) ) {
	wondercat_gf_import_log( 'Gravity Forms API unavailable; skipping FormArchive import.' );
	return;
}

$is_force = false;
if ( isset( $argv ) && is_array( $argv ) ) {
	$is_force = in_array( '--force', $argv, true );
}

if ( '1' === getenv( 'WONDERCAT_GF_IMPORT_FORCE' ) ) {
	$is_force = true;
}

/**
 * Normalize title for duplicate checks.
 */
function wondercat_gf_import_normalize_title( $title ) {
	$title = trim( (string) $title );

	if ( '' === $title ) {
		return '';
	}

	if ( function_exists( 'mb_strtolower' ) ) {
		return mb_strtolower( $title, 'UTF-8' );
	}

	return strtolower( $title );
}

/**
 * Parse Gravity Forms export payload into a list of form objects.
 */
function wondercat_gf_import_extract_forms( $payload ) {
	if ( ! is_array( $payload ) ) {
		return array();
	}

	if ( isset( $payload['forms'] ) && is_array( $payload['forms'] ) ) {
		return array_values( $payload['forms'] );
	}

	if ( isset( $payload['fields'] ) && is_array( $payload['fields'] ) ) {
		return array( $payload );
	}

	$forms = array();
	foreach ( $payload as $key => $value ) {
		if ( is_numeric( (string) $key ) && is_array( $value ) ) {
			$forms[] = $value;
		}
	}

	if ( ! empty( $forms ) ) {
		return $forms;
	}

	if ( array_is_list( $payload ) ) {
		foreach ( $payload as $item ) {
			if ( is_array( $item ) ) {
				$forms[] = $item;
			}
		}
	}

	return $forms;
}

/**
 * Build a set of existing form titles.
 */
function wondercat_gf_import_existing_title_index() {
	$index = array();
	$forms = GFAPI::get_forms();

	if ( is_wp_error( $forms ) ) {
		wondercat_gf_import_log( 'Unable to read existing forms: ' . $forms->get_error_message() );
		return $index;
	}

	if ( ! is_array( $forms ) ) {
		return $index;
	}

	foreach ( $forms as $form ) {
		if ( ! is_array( $form ) || empty( $form['title'] ) ) {
			continue;
		}

		$normalized = wondercat_gf_import_normalize_title( $form['title'] );
		if ( '' !== $normalized ) {
			$index[ $normalized ] = true;
		}
	}

	return $index;
}

$archive_dir = trailingslashit( get_stylesheet_directory() ) . 'FormArchive';
$json_files  = glob( $archive_dir . '/*.json' );

if ( empty( $json_files ) ) {
	wondercat_gf_import_log( 'No JSON exports found in FormArchive; nothing to import.' );
	return;
}

sort( $json_files );

$existing_titles = wondercat_gf_import_existing_title_index();

$files_scanned   = 0;
$forms_discovery = 0;
$imported        = 0;
$skipped         = array();

foreach ( $json_files as $json_file ) {
	$files_scanned++;
	$raw_data = file_get_contents( $json_file );

	if ( false === $raw_data ) {
		$skipped[] = basename( $json_file ) . ': unreadable-file';
		continue;
	}

	$payload = json_decode( $raw_data, true );
	if ( ! is_array( $payload ) ) {
		$skipped[] = basename( $json_file ) . ': invalid-json';
		continue;
	}

	$forms = wondercat_gf_import_extract_forms( $payload );
	if ( empty( $forms ) ) {
		$skipped[] = basename( $json_file ) . ': no-forms-found';
		continue;
	}

	$forms_discovery += count( $forms );

	foreach ( $forms as $form ) {
		if ( ! is_array( $form ) ) {
			$skipped[] = basename( $json_file ) . ': invalid-form-payload';
			continue;
		}

		$title            = isset( $form['title'] ) ? (string) $form['title'] : '';
		$normalized_title = wondercat_gf_import_normalize_title( $title );

		if ( '' === $normalized_title ) {
			$skipped[] = basename( $json_file ) . ': missing-form-title';
			continue;
		}

		if ( ! $is_force && isset( $existing_titles[ $normalized_title ] ) ) {
			$skipped[] = basename( $json_file ) . ': duplicate-title-' . $title;
			continue;
		}

		$form_to_import = $form;
		unset( $form_to_import['id'] );

		$result = GFAPI::add_form( $form_to_import );
		if ( is_wp_error( $result ) ) {
			$skipped[] = basename( $json_file ) . ': import-error-' . $result->get_error_message();
			continue;
		}

		if ( empty( $result ) ) {
			$skipped[] = basename( $json_file ) . ': import-error-empty-result';
			continue;
		}

		$existing_titles[ $normalized_title ] = true;
		$imported++;
	}
}

$message = sprintf(
	'Gravity Forms import complete. Files scanned: %d. Forms discovered: %d. Imported: %d. Skipped: %d.',
	$files_scanned,
	$forms_discovery,
	$imported,
	count( $skipped )
);

wondercat_gf_import_log( $message );

if ( ! empty( $skipped ) ) {
	wondercat_gf_import_log( 'Skipped items: ' . implode( ', ', $skipped ) );
}
