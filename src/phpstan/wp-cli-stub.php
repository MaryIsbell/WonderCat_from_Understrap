<?php
/**
 * Minimal WP-CLI stub used only by PHPStan.
 *
 * WP-CLI is available in the runtime environment (wp-env, production shell) but
 * is not a Composer dependency, so PHPStan reports "unknown class WP_CLI" for
 * every CLI command callback. This file is loaded through PHPStan's autoload
 * file only; WordPress never includes it.
 *
 * @package Understrap
 */

// Respect a real WP-CLI installation when one is loaded for analysis.
if ( class_exists( 'WP_CLI' ) ) {
	return;
}

/**
 * Analysis-only stub of the WP-CLI facade.
 */
class WP_CLI {

	/**
	 * Print a message to the command output.
	 *
	 * @param mixed $message Message text.
	 * @return void
	 */
	public static function log( $message ) {}

	/**
	 * Print a success message.
	 *
	 * @param mixed $message Message text.
	 * @return void
	 */
	public static function success( $message ) {}

	/**
	 * Print a warning message.
	 *
	 * @param mixed $message Message text.
	 * @return void
	 */
	public static function warning( $message ) {}

	/**
	 * Print an error message and stop the command with a non-zero exit code.
	 *
	 * @param mixed $message Message text.
	 * @return void
	 */
	public static function error( $message ) {}

	/**
	 * Register a command or command namespace.
	 *
	 * @param string $name     Command name, for example "wondercat wikidata-sweep".
	 * @param mixed  $callable Command callback.
	 * @return void
	 */
	public static function add_command( $name, $callable ) {}
}
