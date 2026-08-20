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

			<?php if ( function_exists( 'facetwp_display' ) ) : ?>

				<header class="page-header"><h1 class="page-title">Archive: Story Experiences</h1></header><!-- .page-header -->

				<div class="col-md-9">
					<div class="facetwp-template">
						<?php
						if ( have_posts() ) {
							while ( have_posts() ) {
								the_post();
								get_template_part( 'loop-templates/content-user-experience' );
							}
						} else {
							get_template_part( 'loop-templates/content', 'none' );
						}
						?>
					</div>
					<?php echo facetwp_display( 'facet', 'wondercat_pager' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>

				<aside class="col-md-3" id="facetwp-sidebar">
					<div class="card shadow-sm sticky-top">
						<div class="card-header"><?php echo esc_html__( 'Filter by', 'understrap' ); ?></div>
						<div class="card-body">
							<?php foreach ( WONDERCAT_WD_FACETS as $facet_name => $spec ) : ?>
								<div class="facetwp-facet-block mb-3">
									<h2 class="facetwp-facet-label h6 text-uppercase fw-bold mb-2"><?php echo esc_html( $spec['label'] ); ?></h2>
									<?php echo facetwp_display( 'facet', $facet_name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</div>
							<?php endforeach; ?>

							<div class="facetwp-facet-block mb-3">
								<h2 class="facetwp-facet-label h6 text-uppercase fw-bold mb-2">Experience</h2>
								<?php echo facetwp_display( 'facet', 'wondercat_experience' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>

							<div class="facetwp-facet-block mb-3">
								<h2 class="facetwp-facet-label h6 text-uppercase fw-bold mb-2">Narrative Technology</h2>
								<?php echo facetwp_display( 'facet', 'wondercat_narrative_technology' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>

							<div class="facetwp-facet-block mb-0">
								<?php echo facetwp_display( 'facet', 'wondercat_reset' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						</div>
					</div>
				</aside><!-- #facetwp-sidebar -->

			<?php else : ?>

				<?php
				// Do the left sidebar check and open div#primary.
				get_template_part( 'global-templates/left-sidebar-check' );
				?>

				<main class="site-main" id="main">

					<?php
					if ( have_posts() ) {
						?>
						<header class="page-header"><h1 class="page-title">Archive: Story Experiences</h1>

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

			<?php endif; ?>

		</div><!-- .row -->

	</div><!-- #content -->

</div><!-- #archive-wrapper -->

<?php
get_footer();
