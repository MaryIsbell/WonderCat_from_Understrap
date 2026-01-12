<?php
/**
 * Template Name: Technology Glossary Template
 *
 * A custom page to display terms from the technology taxonomy.
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

                // Fetch all top-level technology terms alphabetically
                $taxonomy = 'technology';
                $terms = get_terms([
                    'taxonomy'   => $taxonomy,
                    'hide_empty' => false,
                    'orderby'    => 'name',
                    'order'      => 'ASC',
                    'parent'     => 0,
                ]);

                if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) :
                    ?>
                    <table class="glossary-table" style="width:100%; border-collapse: collapse; border: 1px solid #333;">
                        <thead>
                            <tr>
                                <th style="text-align:left; padding: 8px; border:1px solid #333;">Term</th>
                                <th style="text-align:left; padding: 8px; border:1px solid #333;">Description</th>
                                <th style="text-align:left; padding: 8px; border:1px solid #333;">Related Terms</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $terms as $term ) :

                                $term_link = get_term_link( $term );
                                $description = term_description( $term );

                                // Fetch child terms
                                $child_terms = get_terms([
                                    'taxonomy'   => $taxonomy,
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
                                ?>
                                <tr>
                                    <td style="padding: 8px; border:1px solid #333; vertical-align: top;">
                                        <a href="<?php echo esc_url( $term_link ); ?>"><?php echo esc_html( $term->name ); ?></a>
                                    </td>
                                    <td style="padding: 8px; border:1px solid #333; vertical-align: top;">
    									<?php echo esc_html( wp_strip_all_tags( term_description( $term ) ) ); ?>
									</td>
                                    <td style="padding: 8px; border:1px solid #333; vertical-align: top;">
                                        <?php echo $child_links; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p>No terms found in this taxonomy.</p>
                <?php endif; ?>

            </main><!-- #main -->

        </div><!-- .row -->
    </div><!-- #content -->
</div><!-- #technology-glossary-wrapper -->

<?php get_footer(); ?>