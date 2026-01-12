<?php
/**
 * The template for displaying archive pages
 *
 * Learn more: https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Understrap
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

get_header();

$container = get_theme_mod( 'understrap_container_type' );
?>

<div class="wrapper" id="archive-wrapper">

	<div class="<?php echo esc_attr( $container ); ?>" id="content" tabindex="-1">

		<div class="row">

			<?php
			// Do the left sidebar check and open div#primary.
			get_template_part( 'global-templates/left-sidebar-check' );
			?>

			<main class="site-main" id="main">

				<?php
				if ( have_posts() ) {
					?>
					<header class="page-header">
    <?php
    // Default title and description
    the_archive_title( '<h1 class="page-title">', '</h1>' );
    the_archive_description( '<div class="taxonomy-description">', '</div>' );

    // Custom parent/child info
    $term = get_queried_object();

    // Parent
    if ( $term->parent ) {
        $parent_term = get_term( $term->parent, $term->taxonomy );
        if ( $parent_term && ! is_wp_error( $parent_term ) ) {
            echo '<p>Primary term: <a href="' . esc_url( get_term_link( $parent_term ) ) . '">' . esc_html( $parent_term->name ) . '</a></p>';
        }
    }

    // Children
    $child_terms = get_terms([
        'taxonomy'   => $term->taxonomy,
        'parent'     => $term->term_id,
        'hide_empty' => false,
    ]);

    if ( ! empty( $child_terms ) && ! is_wp_error( $child_terms ) ) {
        echo '<p>Related terms: ';
        $links = [];
        foreach ( $child_terms as $child ) {
            $links[] = '<a href="' . esc_url( get_term_link( $child ) ) . '">' . esc_html( $child->name ) . '</a>';
        }
        echo implode( ', ', $links );
        echo '</p>';
    }
    ?>
</header><!-- .page-header -->
					<?php
					// Start the loop.
					while ( have_posts() ) {
						the_post();

						/*
						 * Include the Post-Format-specific template for the content.
						 * If you want to override this in a child theme, then include a file
						 * called content-___.php (where ___ is the Post Format name) and that will be used instead.
						 */
						get_template_part( 'loop-templates/content-user-experience' );
					}
				} else {
					get_template_part( 'loop-templates/content', 'none' );
				}
				?>

			</main>

			<?php
			// Display the pagination component.
			understrap_pagination();

			// Do the right sidebar check and close div#primary.
			get_template_part( 'global-templates/right-sidebar-check' );
			?>

		</div><!-- .row -->

	</div><!-- #content -->

</div><!-- #archive-wrapper -->

<?php
get_footer();
