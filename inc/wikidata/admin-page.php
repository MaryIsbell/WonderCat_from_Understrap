<?php
/**
 * WikiData Entities Admin Page
 *
 * Registers the admin menu and handles the list view page.
 *
 * @package Wondercat
 */

defined('ABSPATH') || exit;

// Load the list table class
require_once dirname( __FILE__ ) . '/admin-list-table.php';

/**
 * Register the admin menu.
 */
function wikidata_register_admin_menu() {
    add_menu_page(
        __( 'WikiData Entities', 'wondercat' ),           // Page title
        __( 'WikiData Entities', 'wondercat' ),           // Menu title
        'manage_options',                                  // Capability
        'wikidata-entities',                               // Menu slug
        'wikidata_admin_page_render',                     // Callback function
        'dashicons-database',                              // Icon
        30                                                 // Position
    );
}
add_action( 'admin_menu', 'wikidata_register_admin_menu' );

/**
 * Handle admin actions (delete, refresh, bulk operations).
 */
function wikidata_handle_admin_actions() {
    // Check user capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Only process on our admin page
    if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'wikidata-entities' ) {
        return;
    }

    $action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';

    // Handle single delete action
    if ( $action === 'delete' && isset( $_GET['id'] ) ) {
        $id = absint( $_GET['id'] );
        
        // Verify nonce
        if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'delete_wikidata_' . $id ) ) {
            wp_die( __( 'Security check failed.', 'wondercat' ) );
        }

        $result = wikidata_delete_by_id( $id );
        
        if ( $result !== false && $result > 0 ) {
            wp_redirect( add_query_arg(
                array( 'page' => 'wikidata-entities', 'message' => 'deleted' ),
                admin_url( 'admin.php' )
            ) );
            exit;
        } else {
            wp_redirect( add_query_arg(
                array( 'page' => 'wikidata-entities', 'message' => 'delete_error' ),
                admin_url( 'admin.php' )
            ) );
            exit;
        }
    }

    // Handle single refresh action
    if ( $action === 'refresh' && isset( $_GET['id'] ) ) {
        $id = absint( $_GET['id'] );
        
        // Verify nonce
        if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'refresh_wikidata_' . $id ) ) {
            wp_die( __( 'Security check failed.', 'wondercat' ) );
        }

        $entity = wikidata_get_by_id( $id );
        
        if ( $entity ) {
            // Fetch fresh data from WikiData API
            $json = wikidata_fetch_json_by_id( $entity->qid );
            
            if ( $json ) {
                // Decode to extract label and description if needed
                $data = json_decode( $json, true );
                $label = null;
                $description = null;
                
                if ( isset( $data['entities'][ $entity->qid ] ) ) {
                    $entity_data = $data['entities'][ $entity->qid ];
                    
                    // Extract English label
                    if ( isset( $entity_data['labels']['en']['value'] ) ) {
                        $label = $entity_data['labels']['en']['value'];
                    }
                    
                    // Extract English description
                    if ( isset( $entity_data['descriptions']['en']['value'] ) ) {
                        $description = $entity_data['descriptions']['en']['value'];
                    }
                }
                
                // Update the entity
                wikidata_upsert(
                    $entity->qid,
                    wikidata_get_rest_api_url( $entity->qid ),
                    $label ?: $entity->label,
                    $description ?: $entity->description,
                    $json
                );
                
                wp_redirect( add_query_arg(
                    array( 'page' => 'wikidata-entities', 'message' => 'refreshed' ),
                    admin_url( 'admin.php' )
                ) );
                exit;
            } else {
                wp_redirect( add_query_arg(
                    array( 'page' => 'wikidata-entities', 'message' => 'refresh_error' ),
                    admin_url( 'admin.php' )
                ) );
                exit;
            }
        }
    }

    // Handle manual full sweep (traffic-independent resync of every referenced QID).
    if ( $action === 'sweep' ) {
        // Verify nonce
        $sweep_nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

        if ( ! wp_verify_nonce( $sweep_nonce, 'sweep_wikidata' ) ) {
            wp_die( __( 'Security check failed.', 'understrap' ) );
        }

        $sweep = wikidata_run_full_sweep( 'admin' );

        wp_redirect( add_query_arg(
            array(
                'page'    => 'wikidata-entities',
                'message' => empty( $sweep['failed'] ) ? 'sweep_done' : 'sweep_partial',
                'count'   => $sweep['refreshed'],
                'errors'  => count( $sweep['failed'] ),
            ),
            admin_url( 'admin.php' )
        ) );
        exit;
    }

    // Handle bulk actions
    $bulk_action = '';
    if ( isset( $_POST['action'] ) && $_POST['action'] !== '-1' ) {
        $bulk_action = sanitize_text_field( wp_unslash( $_POST['action'] ) );
    } elseif ( isset( $_POST['action2'] ) && $_POST['action2'] !== '-1' ) {
        $bulk_action = sanitize_text_field( wp_unslash( $_POST['action2'] ) );
    }

    if ( $bulk_action && isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ) {
        // Verify nonce
        check_admin_referer( 'bulk-wikidata_entities' );

        $ids = array_map( 'absint', $_POST['ids'] );
        $count = 0;
        $errors = 0;

        if ( $bulk_action === 'bulk_delete' ) {
            foreach ( $ids as $id ) {
                $result = wikidata_delete_by_id( $id );
                if ( $result !== false && $result > 0 ) {
                    $count++;
                } else {
                    $errors++;
                }
            }
            
            wp_redirect( add_query_arg(
                array(
                    'page'    => 'wikidata-entities',
                    'message' => 'bulk_deleted',
                    'count'   => $count,
                    'errors'  => $errors,
                ),
                admin_url( 'admin.php' )
            ) );
            exit;
        } elseif ( $bulk_action === 'bulk_refresh' ) {
            $entities_by_id = array();
            $qids_to_fetch  = array();

            foreach ( $ids as $id ) {
                $entity = wikidata_get_by_id( $id );

                if ( ! $entity ) {
                    $errors++;
                    continue;
                }

                $normalized_qid = wikidata_normalize_qid( $entity->qid );

                if ( ! $normalized_qid ) {
                    $errors++;
                    continue;
                }

                $entities_by_id[ $normalized_qid ] = $entity;
                $qids_to_fetch[]                   = $normalized_qid;
            }

            $json_map = wikidata_batch_fetch_json_by_ids( $qids_to_fetch, true );

            foreach ( $entities_by_id as $qid => $entity ) {
                if ( empty( $json_map[ $qid ] ) ) {
                    $errors++;
                    continue;
                }

                $data        = json_decode( $json_map[ $qid ], true );
                $label       = null;
                $description = null;

                if ( isset( $data['entities'][ $qid ] ) ) {
                    $entity_data = $data['entities'][ $qid ];

                    if ( isset( $entity_data['labels']['en']['value'] ) ) {
                        $label = $entity_data['labels']['en']['value'];
                    }

                    if ( isset( $entity_data['descriptions']['en']['value'] ) ) {
                        $description = $entity_data['descriptions']['en']['value'];
                    }
                }

                wikidata_upsert(
                    $qid,
                    wikidata_get_rest_api_url( $qid ),
                    $label ?: $entity->label,
                    $description ?: $entity->description,
                    $json_map[ $qid ]
                );

                $count++;
            }
            
            wp_redirect( add_query_arg(
                array(
                    'page'    => 'wikidata-entities',
                    'message' => 'bulk_refreshed',
                    'count'   => $count,
                    'errors'  => $errors,
                ),
                admin_url( 'admin.php' )
            ) );
            exit;
        }
    }
}
add_action( 'admin_init', 'wikidata_handle_admin_actions' );

/**
 * Display admin notices.
 */
function wikidata_admin_notices() {
    if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'wikidata-entities' ) {
        return;
    }

    if ( ! isset( $_GET['message'] ) ) {
        return;
    }

    $message = sanitize_text_field( wp_unslash( $_GET['message'] ) );
    $count   = isset( $_GET['count'] ) ? absint( $_GET['count'] ) : 0;
    $errors  = isset( $_GET['errors'] ) ? absint( $_GET['errors'] ) : 0;

    $notice_class = 'notice notice-success is-dismissible';
    $notice_text  = '';

    switch ( $message ) {
        case 'deleted':
            $notice_text = __( 'Entity deleted successfully.', 'wondercat' );
            break;
        case 'refreshed':
            $notice_text = __( 'Entity refreshed from WikiData successfully.', 'wondercat' );
            break;
        case 'updated':
            $notice_text = __( 'Entity updated successfully.', 'wondercat' );
            break;
        case 'bulk_deleted':
            /* translators: %d: number of items */
            $notice_text = sprintf( _n( '%d entity deleted.', '%d entities deleted.', $count, 'wondercat' ), $count );
            if ( $errors > 0 ) {
                /* translators: %d: number of errors */
                $notice_text .= ' ' . sprintf( _n( '%d error occurred.', '%d errors occurred.', $errors, 'wondercat' ), $errors );
                $notice_class = 'notice notice-warning is-dismissible';
            }
            break;
        case 'bulk_refreshed':
            /* translators: %d: number of items */
            $notice_text = sprintf( _n( '%d entity refreshed.', '%d entities refreshed.', $count, 'wondercat' ), $count );
            if ( $errors > 0 ) {
                /* translators: %d: number of errors */
                $notice_text .= ' ' . sprintf( _n( '%d error occurred.', '%d errors occurred.', $errors, 'wondercat' ), $errors );
                $notice_class = 'notice notice-warning is-dismissible';
            }
            break;
        case 'sweep_done':
            /* translators: %d: number of entities refreshed by the full sweep */
            $notice_text = sprintf( _n( '%d entity refreshed by the full sweep.', '%d entities refreshed by the full sweep.', $count, 'understrap' ), $count );
            break;
        case 'sweep_partial':
            /* translators: %d: number of entities refreshed by the full sweep */
            $notice_text = sprintf( _n( '%d entity refreshed by the full sweep.', '%d entities refreshed by the full sweep.', $count, 'understrap' ), $count );
            if ( $errors > 0 ) {
                /* translators: %d: number of errors */
                $notice_text .= ' ' . sprintf( _n( '%d error occurred.', '%d errors occurred.', $errors, 'understrap' ), $errors );
                $notice_class = 'notice notice-warning is-dismissible';
            }
            break;
        case 'delete_error':
            $notice_class = 'notice notice-error is-dismissible';
            $notice_text  = __( 'Error deleting entity.', 'wondercat' );
            break;
        case 'refresh_error':
            $notice_class = 'notice notice-error is-dismissible';
            $notice_text  = __( 'Error refreshing entity from WikiData.', 'wondercat' );
            break;
    }

    if ( $notice_text ) {
        printf( '<div class="%s"><p>%s</p></div>', esc_attr( $notice_class ), esc_html( $notice_text ) );
    }
}
add_action( 'admin_notices', 'wikidata_admin_notices' );

/**
 * Human readable label for the recorded sweep source.
 *
 * @param string $source Recorded source (cron, cli or admin).
 * @return string
 */
function wikidata_sweep_admin_source_label( $source ) {
    switch ( $source ) {
        case 'cron':
            return __( 'WP-Cron event', 'understrap' );
        case 'cli':
            return __( 'WP-CLI', 'understrap' );
        case 'admin':
            return __( 'admin action', 'understrap' );
        default:
            return '' !== $source ? $source : __( 'unknown source', 'understrap' );
    }
}

/**
 * Sentence describing the last recorded sweep.
 *
 * @param array{at:int, source:string, referenced:int, refreshed:int, failed:array<int,string>, seconds:float} $summary Sweep summary.
 * @return string
 */
function wikidata_sweep_admin_last_run_text( $summary ) {
    if ( $summary['at'] <= 0 ) {
        return __( 'never run.', 'understrap' );
    }

    $when = sprintf(
        /* translators: 1: date and time of the last sweep, 2: source that triggered it, 3: duration in seconds */
        __( '%1$s by %2$s in %3$s seconds.', 'understrap' ),
        wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $summary['at'] ),
        wikidata_sweep_admin_source_label( $summary['source'] ),
        $summary['seconds']
    );

    $counts = sprintf(
        /* translators: 1: number of referenced QIDs, 2: number of refreshed QIDs, 3: number of QIDs not refreshed */
        __( '%1$d referenced QIDs, %2$d refreshed, %3$d not refreshed.', 'understrap' ),
        $summary['referenced'],
        $summary['refreshed'],
        count( $summary['failed'] )
    );

    return $when . ' ' . $counts;
}

/**
 * Render the scheduled-refresh status block and the manual sweep trigger.
 *
 * Signals persistence: the last-run summary comes from an option written by
 * every sweep (WP-Cron, WP-CLI or admin action), and the next-run value comes
 * from the cron schedule, so a stalled WP-Cron is visible here without needing
 * page traffic.
 *
 * @return void
 */
function wikidata_render_sweep_status() {
    $summary  = wikidata_sweep_last_run_summary();
    $next_run = wikidata_sweep_next_run_at();
    $fresh    = wikidata_sweep_is_fresh();

    $state_color = $summary['at'] <= 0 ? '#d63638' : ( $fresh ? '#00a32a' : '#dba617' );
    $date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

    $sweep_url = wp_nonce_url(
        add_query_arg(
            array(
                'page'   => 'wikidata-entities',
                'action' => 'sweep',
            ),
            admin_url( 'admin.php' )
        ),
        'sweep_wikidata'
    );
    ?>
    <div class="wikidata-sweep-status" style="margin: 20px 0; padding: 12px 16px; border: 1px solid #c3c4c7; border-left: 4px solid <?php echo esc_attr( $state_color ); ?>; background: #fff; max-width: 900px;">
        <p style="margin-top: 0;">
            <strong><?php esc_html_e( 'Scheduled refresh:', 'understrap' ); ?></strong>
            <?php
            printf(
                /* translators: 1: cron recurrence name, 2: interval in days */
                esc_html__( '%1$s (every %2$d days, matching the stale TTL).', 'understrap' ),
                esc_html( WONDERCAT_WIKIDATA_SWEEP_RECURRENCE ),
                absint( round( WONDERCAT_WIKIDATA_STALE_TTL / DAY_IN_SECONDS ) )
            );
            ?>
            <?php if ( $next_run > 0 ) : ?>
                <br>
                <?php
                printf(
                    /* translators: 1: date and time of the next scheduled sweep */
                    esc_html__( 'Next run: %1$s', 'understrap' ),
                    esc_html( (string) wp_date( $date_format, $next_run ) )
                );
                ?>
                <?php if ( $next_run < time() ) : ?>
                    &mdash; <?php esc_html_e( 'overdue: WP-Cron has not run this event, check the production cron setup (see the ops runbook).', 'understrap' ); ?>
                <?php else : ?>
                    (<?php echo esc_html( (string) human_time_diff( time(), $next_run ) ); ?>)
                <?php endif; ?>
            <?php else : ?>
                <br><?php esc_html_e( 'No recurring sweep event is scheduled.', 'understrap' ); ?>
            <?php endif; ?>
        </p>
        <p>
            <strong><?php esc_html_e( 'Last sweep:', 'understrap' ); ?></strong>
            <?php echo esc_html( wikidata_sweep_admin_last_run_text( $summary ) ); ?>
        </p>
        <p style="margin-bottom: 0;">
            <a class="button button-secondary" href="<?php echo esc_url( $sweep_url ); ?>"><?php esc_html_e( 'Run full sweep now', 'understrap' ); ?></a>
            <span class="description"><?php esc_html_e( 'Refreshes every QID referenced by a published Experience post. Rows without a published post reference are not touched.', 'understrap' ); ?></span>
        </p>
    </div>
    <?php
}

/**
 * Render the admin page.
 */
function wikidata_admin_page_render() {
    // Check if we're showing the edit form
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' && isset( $_GET['id'] ) ) {
        require_once dirname( __FILE__ ) . '/admin-edit.php';
        wikidata_admin_edit_page_render();
        return;
    }

    // Show the list table
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php esc_html_e( 'WikiData Entities', 'wondercat' ); ?></h1>
        <hr class="wp-header-end">

        <?php wikidata_render_sweep_status(); ?>

        <form method="post">
            <?php
            $list_table = new WikiData_Entities_List_Table();
            $list_table->prepare_items();
            $list_table->display();
            ?>
        </form>
    </div>
    <?php
}
