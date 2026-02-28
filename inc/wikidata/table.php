<?php
/**
 * WikiData Entities custom table installer.
 *
 * Drop this file into your theme or plugin and call the install function on
 * activation (plugin) or after_switch_theme (theme).
 */

defined('ABSPATH') || exit;

/**
 * Fully-qualified table name (with WP prefix).
 */
function wikidata_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'wikidata_entities';
}

/**
 * Create or upgrade the custom table.
 *
 * Notes:
 * - qid is required and unique (e.g., "Q42").
 * - url is optional but unique when present.
 * - json_data is stored as LONGTEXT for broad compatibility.
 */
function wikidata_install_table() {
    global $wpdb;

    $table = wikidata_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql = "CREATE TABLE {$table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      qid VARCHAR(32) NOT NULL,
      url VARCHAR(255) NULL,
      label VARCHAR(255) NULL,
      description TEXT NULL,
      json_data LONGTEXT NULL,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY  (id),
      UNIQUE KEY qid (qid),
      UNIQUE KEY url (url)
    ) {$charset_collate};";

    dbDelta($sql);

    // Store schema version so future changes can be applied safely.
    update_option('wikidata_schema_version', '1.0.0', true);
}

/**
 * Optional existence check.
 */
function wikidata_table_exists() {
    global $wpdb;
    $table = wikidata_table_name();
    $like  = $wpdb->esc_like($table);
    $sql   = $wpdb->prepare('SHOW TABLES LIKE %s', $like);
    return $wpdb->get_var($sql) === $table;
}

/**
 * Upsert by qid (insert new or update existing).
 *
 * Uses conditional logic to avoid wasting auto-increment IDs.
 * Checks if the record exists, then UPDATEs or INSERTs accordingly.
 */
function wikidata_upsert($qid, $url = null, $label = null, $description = null, $json = null) {
    global $wpdb;

    $table = wikidata_table_name();
    $now   = current_time('mysql');

    $json_data = is_string($json) ? $json : wp_json_encode($json);

    // Check if this QID already exists
    $existing = wikidata_get_by_qid( $qid );

    if ( $existing ) {
        // Record exists, so UPDATE it
        $sql = $wpdb->prepare(
            "UPDATE {$table} SET
               url = %s,
               label = %s,
               description = %s,
               json_data = %s,
               updated_at = %s
             WHERE qid = %s",
            $url, $label, $description, $json_data, $now, $qid
        );

        $result = $wpdb->query( $sql );

        return $result;
    } else {
        // Record doesn't exist, so INSERT it
        $sql = $wpdb->prepare(
            "INSERT INTO {$table} (qid, url, label, description, json_data, created_at, updated_at)
             VALUES (%s, %s, %s, %s, %s, %s, %s)",
            $qid, $url, $label, $description, $json_data, $now, $now
        );

        $result = $wpdb->query( $sql );

        return $result;
    }
}

/**
 * Retrieve a single wikidata entity by QID.
 *
 * @param string $qid The WikiData entity ID (e.g., "Q42").
 * @return object|null The entity record as an object, or null if not found.
 */
function wikidata_get_by_qid( $qid ) {
    global $wpdb;

    $table = wikidata_table_name();
    $sql   = $wpdb->prepare(
        "SELECT * FROM {$table} WHERE qid = %s LIMIT 1",
        $qid
    );

    return $wpdb->get_row( $sql );
}
