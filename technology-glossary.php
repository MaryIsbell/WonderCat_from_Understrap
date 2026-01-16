<?php
/**
 * Template Name: Technology Glossary Template
 *
 * A custom page to display terms from the technology taxonomy, split into normal and combination terms,
 * with a "View Version History" link appended to the description.
 *
 * @package Understrap
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

get_header();

$container = get_theme_mod( 'understrap_container_type' );
?>

<div class="wrapper" id="technology-glossary-wrapper">
    <div class="<?php echo esc_attr( $container ); ?>" id="content" tabindex="-1">
        <div class="row">

            <main class="site-main col" id="main">

                <?php
                // Page title
                if ( have_posts() ) :
                    while ( have_posts() ) : the_post();
                        ?>
                        <h1 class="page-title"><?php the_title(); ?></h1>
                        <div class="page-content"><?php the_content(); ?></div>
                        <?php
                    endwhile;
                endif;

                $taxonomy = 'technology';
                $version_history_page = '/term-version-history/'; // change to your actual page slug

                // Fetch all top-level technology terms alphabetically
                $all_terms = get_terms([
                    'taxonomy'   => $taxonomy,
                    'hide_empty' => false,
                    'orderby'    => 'name',
                    'order'      => 'ASC',
                    'parent'     => 0,
                ]);

                $normal_terms = [];
                $combination_terms = [];

                if ( ! empty( $all_terms ) && ! is_wp_error( $all_terms ) ) {
                    foreach ( $all_terms as $term ) {
                        $combination_term = get_term_meta( $term->term_id, 'combination_term', true );
                        if ( strtolower($combination_term) === 'yes' ) {
                            $combination_terms[] = $term;
                        } else {
                            $normal_terms[] = $term;
                        }
                    }
                }

                // Render glossary table function (updated)
                function render_glossary_table( $terms, $heading, $version_history_page ) {
    if ( empty( $terms ) ) {
        return;
    }

    // Add spacing if this is the Combination Terms table
    $heading_style = '';
    if ( strtolower($heading) === 'combination terms' ) {
        $heading_style = ' style="margin-top:2rem;"';
    }

    echo '<h2' . $heading_style . '>' . esc_html( $heading ) . '</h2>';
    echo '<table class="glossary-table" style="width:100%; border-collapse: collapse; border: 1px solid #333;">';
    echo '<thead>
            <tr>
                <th style="text-align:left; padding:8px; border:1px solid #333;">Term</th>
                <th style="text-align:left; padding:8px; border:1px solid #333;">Description</th>
                <th style="text-align:left; padding:8px; border:1px solid #333;">Related Terms</th>
            </tr>
          </thead><tbody>';

                    foreach ( $terms as $term ) {
                        $term_link   = get_term_link( $term );
                        $description = term_description( $term );

                        // Fetch child terms
                        $child_terms = get_terms([
                            'taxonomy'   => $term->taxonomy,
                            'parent'     => $term->term_id,
                            'hide_empty' => false,
                            'orderby'    => 'name',
                            'order'      => 'ASC',
                        ]);

                        $child_links = '';
                        if ( ! empty( $child_terms ) && ! is_wp_error( $child_terms ) ) {
                            $links = [];
                            foreach ( $child_terms as $child ) {
                                $links[] = '<a href="' . esc_url( get_term_link( $child ) ) . '">' . esc_html( $child->name ) . '</a>';
                            }
                            $child_links = implode( ', ', $links );
                        }

                        // Build version history link using anchor
                        $term_anchor = sanitize_title( $term->taxonomy . '-' . $term->slug );
                        $history_link = esc_url( $version_history_page . '#' . $term_anchor );

                        echo '<tr>';
                        // Term name column
                        echo '<td style="padding:8px; border:1px solid #333; vertical-align:top;">
                                <a href="' . esc_url( $term_link ) . '">' . esc_html( $term->name ) . '</a>
                              </td>';

                        // Description column + bolded version history link
                        echo '<td style="padding:8px; border:1px solid #333; vertical-align:top;">'
                             . esc_html( wp_strip_all_tags( $description ) )
                             . '</td>';

                        // Related terms column
                        echo '<td style="padding:8px; border:1px solid #333; vertical-align:top;">' . $child_links . '</td>';

                        echo '</tr>';
                    }

                    echo '</tbody></table>';
                }

                // Render tables for both normal and combination terms
                render_glossary_table( $normal_terms, 'Technology Terms', $version_history_page );
                render_glossary_table( $combination_terms, 'Combination Terms', $version_history_page );
                ?>

            </main><!-- #main -->

        </div><!-- .row -->
    </div><!-- #content -->
</div><!-- #technology-glossary-wrapper -->

<?php get_footer(); ?>
