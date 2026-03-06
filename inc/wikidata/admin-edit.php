<?php
/**
 * WikiData Entities Edit Page
 *
 * Provides an edit form for updating entity descriptions.
 *
 * @package Wondercat
 */

defined('ABSPATH') || exit;

/**
 * Handle form submission.
 */
function wikidata_handle_edit_form() {
    // Check if form was submitted
    if ( ! isset( $_POST['wikidata_edit_submit'] ) ) {
        return;
    }

    // Check user capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have permission to perform this action.', 'wondercat' ) );
    }

    // Verify nonce
    if ( ! isset( $_POST['wikidata_edit_nonce'] ) || ! wp_verify_nonce( $_POST['wikidata_edit_nonce'], 'wikidata_edit_entity' ) ) {
        wp_die( __( 'Security check failed.', 'wondercat' ) );
    }

    // Get and validate ID
    $id = isset( $_POST['entity_id'] ) ? absint( $_POST['entity_id'] ) : 0;
    if ( ! $id ) {
        wp_die( __( 'Invalid entity ID.', 'wondercat' ) );
    }

    // Get the new description
    $description = isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '';

    // Update the description
    $result = wikidata_update_description( $id, $description );

    if ( $result !== false ) {
        wp_redirect( add_query_arg(
            array( 'page' => 'wikidata-entities', 'message' => 'updated' ),
            admin_url( 'admin.php' )
        ) );
        exit;
    } else {
        wp_redirect( add_query_arg(
            array(
                'page'    => 'wikidata-entities',
                'action'  => 'edit',
                'id'      => $id,
                'error'   => '1',
            ),
            admin_url( 'admin.php' )
        ) );
        exit;
    }
}
add_action( 'admin_init', 'wikidata_handle_edit_form' );

/**
 * Render the edit page.
 */
function wikidata_admin_edit_page_render() {
    // Check user capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have permission to access this page.', 'wondercat' ) );
    }

    // Get entity ID
    $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
    if ( ! $id ) {
        wp_die( __( 'Invalid entity ID.', 'wondercat' ) );
    }

    // Get entity data
    $entity = wikidata_get_by_id( $id );
    if ( ! $entity ) {
        wp_die( __( 'Entity not found.', 'wondercat' ) );
    }

    // Check for error
    $has_error = isset( $_GET['error'] ) && $_GET['error'] === '1';
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php esc_html_e( 'Edit WikiData Entity', 'wondercat' ); ?></h1>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wikidata-entities' ) ); ?>" class="page-title-action">
            <?php esc_html_e( 'Back to List', 'wondercat' ); ?>
        </a>
        <hr class="wp-header-end">

        <?php if ( $has_error ) : ?>
            <div class="notice notice-error is-dismissible">
                <p><?php esc_html_e( 'Error updating entity. Please try again.', 'wondercat' ); ?></p>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <?php wp_nonce_field( 'wikidata_edit_entity', 'wikidata_edit_nonce' ); ?>
            <input type="hidden" name="entity_id" value="<?php echo esc_attr( $entity->id ); ?>">

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e( 'QID', 'wondercat' ); ?></label>
                        </th>
                        <td>
                            <strong><?php echo esc_html( $entity->qid ); ?></strong>
                            <p class="description">
                                <?php esc_html_e( 'QID cannot be edited.', 'wondercat' ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e( 'Label', 'wondercat' ); ?></label>
                        </th>
                        <td>
                            <strong><?php echo esc_html( $entity->label ?: '—' ); ?></strong>
                            <p class="description">
                                <?php esc_html_e( 'Label is managed automatically from WikiData.', 'wondercat' ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="description"><?php esc_html_e( 'Description', 'wondercat' ); ?></label>
                        </th>
                        <td>
                            <textarea 
                                name="description" 
                                id="description" 
                                rows="5" 
                                cols="50" 
                                class="large-text"
                            ><?php echo esc_textarea( $entity->description ); ?></textarea>
                            <p class="description">
                                <?php esc_html_e( 'You can edit the description here. Use "Refresh from WikiData" to restore the original description from WikiData.', 'wondercat' ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e( 'URL', 'wondercat' ); ?></label>
                        </th>
                        <td>
                            <?php if ( $entity->url ) : ?>
                                <a href="<?php echo esc_url( $entity->url ); ?>" target="_blank" rel="noopener noreferrer">
                                    <?php echo esc_html( $entity->url ); ?>
                                </a>
                            <?php else : ?>
                                <span>—</span>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e( 'Created', 'wondercat' ); ?></label>
                        </th>
                        <td>
                            <?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entity->created_at ) ); ?>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e( 'Last Updated', 'wondercat' ); ?></label>
                        </th>
                        <td>
                            <?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entity->updated_at ) ); ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <p class="submit">
                <input 
                    type="submit" 
                    name="wikidata_edit_submit" 
                    id="submit" 
                    class="button button-primary" 
                    value="<?php esc_attr_e( 'Update Description', 'wondercat' ); ?>"
                >
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wikidata-entities' ) ); ?>" class="button">
                    <?php esc_html_e( 'Cancel', 'wondercat' ); ?>
                </a>
            </p>
        </form>
    </div>
    <?php
}
