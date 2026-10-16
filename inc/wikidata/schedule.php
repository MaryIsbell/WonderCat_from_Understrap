<?php
/**
 * Scheduled Wikidata refresh: recurring sweep, WP-CLI resync and run state.
 *
 * This file provides the traffic-independent refresh path. A sweep collects
 * every QID referenced by at least one published user-experience post and
 * upserts label, description, json_data and updated_at through the existing
 * batched refresh handler (wikidata_handle_refresh_batch_event). QIDs without
 * a published post are never part of the work set, so entity rows that are not
 * referenced by a published post are left untouched (no pruning, no rewriting).
 *
 * Three triggers are supported and all of them run the same code path:
 * 1. weekly WP-Cron event (WONDERCAT_WIKIDATA_SWEEP_HOOK)
 * 2. WP-CLI command `wp wondercat wikidata-sweep`
 * 3. manual "Run full sweep now" admin action on the WikiData Entities screen
 *
 * @package Wondercat
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Recurring sweep hook (scheduled weekly via WP-Cron).
if ( ! defined( 'WONDERCAT_WIKIDATA_SWEEP_HOOK' ) ) {
	define( 'WONDERCAT_WIKIDATA_SWEEP_HOOK', 'wondercat_wikidata_sweep' );
}

// Cron recurrence for the sweep. "weekly" is 7 days, matching
// WONDERCAT_WIKIDATA_STALE_TTL (7 * DAY_IN_SECONDS).
if ( ! defined( 'WONDERCAT_WIKIDATA_SWEEP_RECURRENCE' ) ) {
	define( 'WONDERCAT_WIKIDATA_SWEEP_RECURRENCE', 'weekly' );
}

// Option holding the last sweep summary (persistent last-run/freshness signal).
if ( ! defined( 'WONDERCAT_WIKIDATA_SWEEP_STATE_OPTION' ) ) {
	define( 'WONDERCAT_WIKIDATA_SWEEP_STATE_OPTION', 'wondercat_wikidata_sweep_state' );
}

/**
 * Collect every QID referenced by at least one published user-experience post.
 *
 * The result is the complete sweep work set: normalized, unique and sorted, so
 * one sweep visits each referenced QID exactly once (no gaps, no duplicates).
 *
 * @return array<int,string> Sorted, unique, normalized QIDs.
 */
function wikidata_find_referenced_qids() {
	global $wpdb;

	$sql = $wpdb->prepare(
		"SELECT DISTINCT pm.meta_value
         FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
         WHERE pm.meta_key = %s
         AND p.post_type = %s
         AND p.post_status = 'publish'
         ORDER BY pm.meta_value ASC",
		WONDERCAT_QID_FIELD,
		WONDERCAT_POST_TYPE
	);

	$qids = array();

	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Query is built with $wpdb->prepare() above; a plain read is intentional here.
	foreach ( (array) $wpdb->get_col( $sql ) as $candidate ) {
		$normalized_qid = wikidata_normalize_qid( $candidate );

		if ( null !== $normalized_qid ) {
			$qids[] = $normalized_qid;
		}
	}
	// phpcs:enable

	$qids = array_values( array_unique( $qids ) );
	sort( $qids );

	return $qids;
}

/**
 * Read the updated_at value of each requested QID.
 *
 * @param array<int,string> $qids QIDs to read.
 * @return array<string,string> Map of QID => updated_at. Missing rows are omitted.
 */
function wikidata_sweep_snapshot_updated_at( $qids ) {
	global $wpdb;

	$snapshot = array();

	if ( empty( $qids ) ) {
		return $snapshot;
	}

	$qids         = array_values( array_unique( $qids ) );
	$table        = wikidata_table_name();
	$placeholders = implode( ',', array_fill( 0, count( $qids ), '%s' ) );
	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Table name comes from wikidata_table_name() and placeholders are generated per QID; every value is still bound by $wpdb->prepare().
	$sql = $wpdb->prepare(
		"SELECT qid, updated_at FROM {$table} WHERE qid IN ({$placeholders})",
		$qids
	);

	foreach ( (array) $wpdb->get_results( $sql, ARRAY_A ) as $row ) {
		if ( ! is_array( $row ) || ! isset( $row['qid'], $row['updated_at'] ) ) {
			continue;
		}

		if ( ! is_string( $row['qid'] ) || ! is_string( $row['updated_at'] ) ) {
			continue;
		}

		$snapshot[ $row['qid'] ] = $row['updated_at'];
	}
	// phpcs:enable

	return $snapshot;
}

/**
 * Compute which QIDs still need a refresh by comparing two snapshots.
 *
 * @param array<int,string>    $qids    Sweep work set.
 * @param array<string,string> $before Snapshot taken before the refresh.
 * @param array<string,string> $after  Snapshot taken after the refresh.
 * @return array<int,string> QIDs that are missing a row or whose row did not change.
 */
function wikidata_sweep_collect_pending( $qids, $before, $after ) {
	$pending = array();

	foreach ( $qids as $qid ) {
		$was = isset( $before[ $qid ] ) ? $before[ $qid ] : '';
		$now = isset( $after[ $qid ] ) ? $after[ $qid ] : '';

		if ( '' === $now || $now === $was ) {
			$pending[] = $qid;
		}
	}

	return $pending;
}

/**
 * Compute which QIDs were refreshed by comparing two snapshots.
 *
 * @param array<int,string>    $qids    Sweep work set.
 * @param array<string,string> $before Snapshot taken before the refresh.
 * @param array<string,string> $after  Snapshot taken after the refresh.
 * @return array{refreshed:array<int,string>, failed:array<int,string>}
 */
function wikidata_sweep_collect_result( $qids, $before, $after ) {
	$refreshed = array();
	$failed    = array();

	foreach ( $qids as $qid ) {
		$was = isset( $before[ $qid ] ) ? $before[ $qid ] : '';
		$now = isset( $after[ $qid ] ) ? $after[ $qid ] : '';

		if ( '' !== $now && $now !== $was ) {
			$refreshed[] = $qid;
		} else {
			$failed[] = $qid;
		}
	}

	return array(
		'refreshed' => $refreshed,
		'failed'    => $failed,
	);
}

/**
 * Run a full sweep over every QID referenced by a published user-experience post.
 *
 * Pass 1 goes through the existing batched fetch/upsert path with a forced
 * refresh. Pass 2 is a bounded batched retry for QIDs pass 1 could not write
 * (for example a failed chunk request). Only QIDs that still have not changed
 * afterwards are reported as failed. Nothing outside the work set is touched.
 *
 * @param string $source What triggered the sweep: cron, cli or admin.
 * @return array{at:int, source:string, referenced:int, refreshed:int, failed:array<int,string>, seconds:float}
 */
function wikidata_run_full_sweep( $source ) {
	$started_at = microtime( true );

	// Sweep payloads can be large; raise the ceiling for this request only.
	wp_raise_memory_limit( 'admin' );

	$qids   = wikidata_find_referenced_qids();
	$before = wikidata_sweep_snapshot_updated_at( $qids );

	// Pass 1: existing batched fetch/upsert path (gating applied inside).
	wikidata_handle_refresh_batch_event( $qids );

	$pending = wikidata_sweep_collect_pending( $qids, $before, wikidata_sweep_snapshot_updated_at( $qids ) );

	// Pass 2: one bounded batched retry for everything pass 1 did not write.
	if ( ! empty( $pending ) ) {
		wikidata_handle_refresh_batch_event( $pending );
	}

	$result = wikidata_sweep_collect_result( $qids, $before, wikidata_sweep_snapshot_updated_at( $qids ) );

	$summary = array(
		'at'         => time(),
		'source'     => (string) $source,
		'referenced' => count( $qids ),
		'refreshed'  => count( $result['refreshed'] ),
		'failed'     => $result['failed'],
		'seconds'    => round( microtime( true ) - $started_at, 2 ),
	);

	update_option( WONDERCAT_WIKIDATA_SWEEP_STATE_OPTION, $summary, false );

	return $summary;
}

/**
 * Cron callback for the recurring sweep.
 *
 * @return void
 */
function wikidata_handle_sweep_event() {
	wikidata_run_full_sweep( 'cron' );
}

/**
 * Make sure the recurring sweep event exists (idempotent, self-healing).
 *
 * Registers the event when it is missing (fresh install, cleared cron option,
 * restored backup) and leaves an existing event alone, so a dead WP-Cron is not
 * masked by endless re-scheduling.
 *
 * @return void
 */
function wikidata_schedule_sweep_event() {
	if ( wp_next_scheduled( WONDERCAT_WIKIDATA_SWEEP_HOOK ) ) {
		return;
	}

	// phpcs:ignore WPThemeReview.PluginTerritory.ForbiddenFunctions.cron_functionality_wp_schedule_event -- A weekly background sweep is the purpose of this module. Production runs it via system cron, WP-CLI or the admin action (see inc/wikidata/docs/OPS-RUNBOOK.md).
	wp_schedule_event(
		time() + HOUR_IN_SECONDS,
		WONDERCAT_WIKIDATA_SWEEP_RECURRENCE,
		WONDERCAT_WIKIDATA_SWEEP_HOOK
	);
}

/**
 * Read an integer from the persisted sweep state.
 *
 * @param array<string,mixed> $state Persisted sweep state.
 * @param string              $key               State key.
 * @return int Value when numeric, otherwise 0.
 */
function wikidata_sweep_state_int( $state, $key ) {
	return isset( $state[ $key ] ) && is_numeric( $state[ $key ] ) ? (int) $state[ $key ] : 0;
}

/**
 * Read a float from the persisted sweep state.
 *
 * @param array<string,mixed> $state Persisted sweep state.
 * @param string              $key               State key.
 * @return float Value when numeric, otherwise 0.0.
 */
function wikidata_sweep_state_float( $state, $key ) {
	return isset( $state[ $key ] ) && is_numeric( $state[ $key ] ) ? (float) $state[ $key ] : 0.0;
}

/**
 * Read a string from the persisted sweep state.
 *
 * @param array<string,mixed> $state Persisted sweep state.
 * @param string              $key               State key.
 * @return string Value when a string, otherwise an empty string.
 */
function wikidata_sweep_state_string( $state, $key ) {
	return isset( $state[ $key ] ) && is_string( $state[ $key ] ) ? $state[ $key ] : '';
}

/**
 * Normalize an arbitrary list of QIDs into a clean string list.
 *
 * @param mixed $values Candidate values.
 * @return array<int,string> Unique, normalized QIDs.
 */
function wikidata_sweep_normalize_qid_list( $values ) {
	if ( ! is_array( $values ) ) {
		return array();
	}

	$qids = array();

	foreach ( $values as $value ) {
		if ( ! is_string( $value ) ) {
			continue;
		}

		$normalized_qid = wikidata_normalize_qid( $value );

		if ( null !== $normalized_qid ) {
			$qids[] = $normalized_qid;
		}
	}

	return array_values( array_unique( $qids ) );
}

/**
 * Read the persisted last-sweep summary.
 *
 * Returns a fully typed, empty summary when nothing has been recorded yet, so
 * callers always have a stable shape to render or print.
 *
 * @return array{at:int, source:string, referenced:int, refreshed:int, failed:array<int,string>, seconds:float}
 */
function wikidata_sweep_last_run_summary() {
	$summary = array(
		'at'         => 0,
		'source'     => '',
		'referenced' => 0,
		'refreshed'  => 0,
		'failed'     => array(),
		'seconds'    => 0.0,
	);

	$state = get_option( WONDERCAT_WIKIDATA_SWEEP_STATE_OPTION, array() );

	if ( ! is_array( $state ) ) {
		return $summary;
	}

	$summary['at']         = wikidata_sweep_state_int( $state, 'at' );
	$summary['source']     = wikidata_sweep_state_string( $state, 'source' );
	$summary['referenced'] = wikidata_sweep_state_int( $state, 'referenced' );
	$summary['refreshed']  = wikidata_sweep_state_int( $state, 'refreshed' );
	$summary['seconds']    = wikidata_sweep_state_float( $state, 'seconds' );
	$summary['failed']     = wikidata_sweep_normalize_qid_list( isset( $state['failed'] ) ? $state['failed'] : array() );

	return $summary;
}

/**
 * Unix timestamp of the next scheduled sweep run.
 *
 * @return int Timestamp, or 0 when no event is scheduled.
 */
function wikidata_sweep_next_run_at() {
	$next_run = wp_next_scheduled( WONDERCAT_WIKIDATA_SWEEP_HOOK );

	return is_int( $next_run ) ? $next_run : 0;
}

/**
 * Whether the last sweep still sits inside the stale TTL window.
 *
 * Uses the same staleness helper as the lazy refresh path, so the run state and
 * the row/cache staleness checks can never drift apart.
 *
 * @return bool True when a sweep ran within WONDERCAT_WIKIDATA_STALE_TTL.
 */
function wikidata_sweep_is_fresh() {
	$summary = wikidata_sweep_last_run_summary();

	if ( $summary['at'] <= 0 ) {
		return false;
	}

	return ! wikidata_is_timestamp_stale( $summary['at'], WONDERCAT_WIKIDATA_STALE_TTL );
}

add_action( WONDERCAT_WIKIDATA_SWEEP_HOOK, 'wikidata_handle_sweep_event' );
add_action( 'init', 'wikidata_schedule_sweep_event' );

/**
 * Run the full sweep from WP-CLI.
 *
 * ## OPTIONS
 *
 * [--status]
 * : Print the recorded last-run summary and the next scheduled run instead of
 * running a sweep.
 *
 * ## EXAMPLES
 *
 *     wp wondercat wikidata-sweep
 *     wp wondercat wikidata-sweep --status
 *
 * @param array<int,string>   $args       Positional arguments (unused).
 * @param array<string,mixed> $assoc_args Associative arguments.
 * @return void
 */
function wikidata_cli_sweep( $args, $assoc_args ) {
	unset( $args );

	if ( ! empty( $assoc_args['status'] ) ) {
		wikidata_cli_print_status();
		return;
	}

	$summary = wikidata_run_full_sweep( 'cli' );

	WP_CLI::log( sprintf( 'Referenced QIDs: %d', $summary['referenced'] ) );
	WP_CLI::log( sprintf( 'Refreshed QIDs:  %d', $summary['refreshed'] ) );
	WP_CLI::log( sprintf( 'Duration:        %s seconds', $summary['seconds'] ) );

	if ( ! empty( $summary['failed'] ) ) {
		WP_CLI::log( sprintf( 'Not refreshed:   %s', implode( ', ', $summary['failed'] ) ) );
		WP_CLI::error( sprintf( '%d referenced QID(s) could not be refreshed.', count( $summary['failed'] ) ) );
		return;
	}

	WP_CLI::success( sprintf( 'Sweep complete: %d/%d referenced QIDs refreshed.', $summary['refreshed'], $summary['referenced'] ) );
}

/**
 * Print the recorded sweep state (used by `wp wondercat wikidata-sweep --status`).
 *
 * @return void
 */
function wikidata_cli_print_status() {
	$summary  = wikidata_sweep_last_run_summary();
	$next_run = wikidata_sweep_next_run_at();

	if ( $summary['at'] > 0 ) {
		WP_CLI::log(
			sprintf(
				'Last sweep: %s UTC via %s (took %s seconds)',
				gmdate( 'Y-m-d H:i:s', $summary['at'] ),
				'' !== $summary['source'] ? $summary['source'] : 'unknown',
				$summary['seconds']
			)
		);
		WP_CLI::log( sprintf( 'Last run counts: %d referenced, %d refreshed, %d not refreshed', $summary['referenced'], $summary['refreshed'], count( $summary['failed'] ) ) );
	} else {
		WP_CLI::log( 'Last sweep: never recorded' );
	}

	if ( ! empty( $summary['failed'] ) ) {
		WP_CLI::log( sprintf( 'Not refreshed: %s', implode( ', ', $summary['failed'] ) ) );
	}

	WP_CLI::log( sprintf( 'Within stale TTL: %s', wikidata_sweep_is_fresh() ? 'yes' : 'no' ) );
	WP_CLI::log( sprintf( 'Next scheduled: %s', $next_run > 0 ? gmdate( 'Y-m-d H:i:s', $next_run ) . ' UTC' : 'not scheduled' ) );
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'wondercat wikidata-sweep', 'wikidata_cli_sweep' );
}
