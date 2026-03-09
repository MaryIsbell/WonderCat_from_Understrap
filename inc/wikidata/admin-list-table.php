<?php
/**
 * WikiData Entities List Table
 *
 * Extends WP_List_Table to display wikidata entities in the admin interface.
 *
 * @package Wondercat
 */

defined('ABSPATH') || exit;

// Ensure WP_List_Table is available
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * WikiData_Entities_List_Table class.
 */
class WikiData_Entities_List_Table extends WP_List_Table {

    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct( array(
            'singular' => 'wikidata_entity',
            'plural'   => 'wikidata_entities',
            'ajax'     => false,
        ) );
    }

    /**
     * Get a list of columns.
     *
     * @return array Column slug => Column label.
     */
    public function get_columns() {
        return array(
            'cb'          => '<input type="checkbox" />',
            'qid'         => __( 'QID', 'wondercat' ),
            'label'       => __( 'Label', 'wondercat' ),
            'description' => __( 'Description', 'wondercat' ),
            'url'         => __( 'URL', 'wondercat' ),
            'created_at'  => __( 'Created', 'wondercat' ),
            'updated_at'  => __( 'Updated', 'wondercat' ),
        );
    }

    /**
     * Get a list of sortable columns.
     *
     * @return array Column slug => array( orderby, default_order ).
     */
    protected function get_sortable_columns() {
        return array(
            'qid'        => array( 'qid', false ),
            'label'      => array( 'label', false ),
            'created_at' => array( 'created_at', true ), // true = already sorted by this
            'updated_at' => array( 'updated_at', false ),
        );
    }

    /**
     * Get the list of bulk actions.
     *
     * @return array Bulk action slug => label.
     */
    protected function get_bulk_actions() {
        return array(
            'bulk_delete'  => __( 'Delete', 'wondercat' ),
            'bulk_refresh' => __( 'Refresh from WikiData', 'wondercat' ),
        );
    }

    /**
     * Render the checkbox column.
     *
     * @param object $item The current item.
     * @return string Checkbox HTML.
     */
    protected function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="ids[]" value="%s" />',
            esc_attr( $item->id )
        );
    }

    /**
     * Render the QID column with action links.
     *
     * @param object $item The current item.
     * @return string Column HTML.
     */
    protected function column_qid( $item ) {
        $edit_url = add_query_arg(
            array(
                'page'   => 'wikidata-entities',
                'action' => 'edit',
                'id'     => $item->id,
            ),
            admin_url( 'admin.php' )
        );

        $delete_url = wp_nonce_url(
            add_query_arg(
                array(
                    'page'   => 'wikidata-entities',
                    'action' => 'delete',
                    'id'     => $item->id,
                ),
                admin_url( 'admin.php' )
            ),
            'delete_wikidata_' . $item->id
        );

        $refresh_url = wp_nonce_url(
            add_query_arg(
                array(
                    'page'   => 'wikidata-entities',
                    'action' => 'refresh',
                    'id'     => $item->id,
                ),
                admin_url( 'admin.php' )
            ),
            'refresh_wikidata_' . $item->id
        );

        $actions = array(
            'edit'    => sprintf(
                '<a href="%s">%s</a>',
                esc_url( $edit_url ),
                __( 'Edit Description', 'wondercat' )
            ),
            'refresh' => sprintf(
                '<a href="%s">%s</a>',
                esc_url( $refresh_url ),
                __( 'Refresh from WikiData', 'wondercat' )
            ),
            'delete'  => sprintf(
                '<a href="%s" class="submitdelete" onclick="return confirm(\'%s\');">%s</a>',
                esc_url( $delete_url ),
                esc_js( __( 'Are you sure you want to delete this entity?', 'wondercat' ) ),
                __( 'Delete', 'wondercat' )
            ),
        );

        return sprintf(
            '<strong><a href="%s">%s</a></strong> %s',
            esc_url( $edit_url ),
            esc_html( $item->qid ),
            $this->row_actions( $actions )
        );
    }

    /**
     * Render the URL column.
     *
     * @param object $item The current item.
     * @return string Column HTML.
     */
    protected function column_url( $item ) {
        if ( empty( $item->url ) ) {
            return '—';
        }

        return sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url( $item->url ),
            __( 'View on WikiData', 'wondercat' )
        );
    }

    /**
     * Render the description column.
     *
     * @param object $item The current item.
     * @return string Column HTML.
     */
    protected function column_description( $item ) {
        if ( empty( $item->description ) ) {
            return '—';
        }

        // Truncate long descriptions
        $description = esc_html( $item->description );
        if ( strlen( $description ) > 100 ) {
            $description = substr( $description, 0, 100 ) . '…';
        }

        return $description;
    }

    /**
     * Default column rendering.
     *
     * @param object $item The current item.
     * @param string $column_name The column name.
     * @return string Column HTML.
     */
    protected function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'label':
                return esc_html( $item->label );
            case 'created_at':
            case 'updated_at':
                return esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item->$column_name ) );
            default:
                return esc_html( $item->$column_name );
        }
    }

    /**
     * Prepare items for display.
     *
     * Fetches data from the database and sets up pagination.
     */
    public function prepare_items() {
        // Handle column headers
        $columns  = $this->get_columns();
        $hidden   = array();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array( $columns, $hidden, $sortable );

        // Get pagination parameters
        $per_page     = 20;
        $current_page = $this->get_pagenum();

        // Get sort parameters
        $orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'created_at';
        $order   = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'DESC';

        // Fetch data
        $this->items = wikidata_get_all( $per_page, $current_page, $orderby, $order );

        // Get total count for pagination
        $total_items = wikidata_get_total_count();

        // Set pagination args
        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page ),
        ) );
    }

    /**
     * Display message when no items are found.
     */
    public function no_items() {
        _e( 'No WikiData entities found.', 'wondercat' );
    }
}
