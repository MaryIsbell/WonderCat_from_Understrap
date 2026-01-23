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
 * Requires a UNIQUE KEY on qid.
 */
function wikidata_upsert($qid, $url = null, $label = null, $description = null, $json = null) {
    global $wpdb;

    $table = wikidata_table_name();
    $now   = current_time('mysql');

    $json_data = is_string($json) ? $json : wp_json_encode($json);

    // If url is NULL, the unique index allows multiple NULLs in MySQL.
    $sql = $wpdb->prepare(
        "INSERT INTO {$table} (qid, url, label, description, json_data, created_at, updated_at)
         VALUES (%s, %s, %s, %s, %s, %s, %s)
         ON DUPLICATE KEY UPDATE
           url = VALUES(url),
           label = VALUES(label),
           description = VALUES(description),
           json_data = VALUES(json_data),
           updated_at = VALUES(updated_at)",
        $qid, $url, $label, $description, $json_data, $now, $now
    );

    return $wpdb->query($sql);
}

/**
 * Get unique 'wikidata-qid' values from all posts.
 *
 * @return string[] Array of unique wikidata-qid values.
 */
function wikidata_find_posts_with_qid() {
    global $wpdb;

    $meta_key = 'wikidata-qid';
    $sql = $wpdb->prepare(
        "SELECT DISTINCT pm.meta_value
         FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
         WHERE pm.meta_key = %s
         AND p.post_status = 'publish'
         ORDER BY pm.meta_value ASC",
        $meta_key
    );

    $results = $wpdb->get_col( $sql );
    return array_filter( array_unique( $results ) );
}


var_dump(wikidata_find_posts_with_qid());