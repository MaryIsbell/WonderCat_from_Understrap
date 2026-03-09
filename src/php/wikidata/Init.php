<?php
/**
 * Wikidata Database Table Initialization
 */

namespace UnderStrap\Wikidata;

class WikidadataTable {
    const TABLE_NAME = 'wikidata';

    /**
     * Initialize the wikidata table on theme activation
     */
    public static function activate() {
        self::create_table_if_not_exists();
    }

    /**
     * Create wikidata table if it doesn't exist
     */
    private static function create_table_if_not_exists() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
            $sql = "CREATE TABLE $table_name (
                id bigint(20) unsigned NOT NULL auto_increment,
                qid varchar(50) NOT NULL,
                url varchar(255),
                label varchar(255),
                description text,
                json_data longtext,
                created datetime DEFAULT CURRENT_TIMESTAMP,
                updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY qid (qid)
            ) $charset_collate;";

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta( $sql );
        }
    }
}