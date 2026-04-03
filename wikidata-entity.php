<?php
/**
 * Template for displaying a single Wikidata entity by QID.
 *
 * Accessible at /wikidata/{qid} (e.g. /wikidata/Q42).
 * Renders the entity label and publication date (P577) stored in the
 * wikidata_entities table.
 *
 * @package WonderCat
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Retrieve and validate the QID from the URL.
$raw_qid = get_query_var( 'wikidata_qid', '' );
$qid     = strtoupper( sanitize_text_field( $raw_qid ) );

// Validate QID format: must be Q followed by one or more digits.
if ( ! preg_match( '/^Q[0-9]+$/', $qid ) ) {
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
	include get_404_template();
	exit;
}

// Look up the entity in the database.
$entity = wikidata_get_by_qid( $qid );

if ( ! $entity ) {
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
	include get_404_template();
	exit;
}

// Extract and format the publication date (P577) from stored JSON.
$publication_date = null;

if ( ! empty( $entity->json_data ) ) {
	$json = json_decode( $entity->json_data, true );

	if ( isset( $json['entities'][ $qid ]['claims']['P577'][0]['mainsnak']['datavalue']['value']['time'] ) ) {
		$raw_time = $json['entities'][ $qid ]['claims']['P577'][0]['mainsnak']['datavalue']['value']['time'];

		// Wikidata time format: +YYYY-MM-DDTHH:MM:SSZ — strip sign and time component.
		$date_string = ltrim( $raw_time, '+-' );
		$date_string = str_replace( 'T00:00:00Z', '', $date_string );

		try {
			$datetime         = new DateTime( $date_string );
			$publication_date = $datetime->format( get_option( 'date_format' ) );
		} catch ( Exception $e ) {
			$publication_date = esc_html( $date_string );
		}
	}
}

// Build title for <head>.
$page_title = $entity->label ? $entity->label : $qid;

// Render the page.
get_header();

$container = get_theme_mod( 'understrap_container_type' );
?>

<div class="wrapper" id="wikidata-entity-wrapper">

	<div class="<?php echo esc_attr( $container ); ?>" id="content" tabindex="-1">

		<div class="row">

			<?php get_template_part( 'global-templates/left-sidebar-check' ); ?>

			<main class="site-main" id="main">

				<article class="wikidata-entity" id="wikidata-entity-<?php echo esc_attr( $qid ); ?>">

					<header class="entry-header">
						<h1 class="entry-title"><?php echo esc_html( $page_title ); ?></h1>
					</header>

					<div class="entry-content">

						<?php if ( $publication_date ) : ?>
							<p class="wikidata-publication-date">
								<strong><?php esc_html_e( 'Publication Date:', 'understrap-child' ); ?></strong>
								<?php echo esc_html( $publication_date ); ?>
							</p>
						<?php endif; ?>

					</div><!-- .entry-content -->

				</article><!-- .wikidata-entity -->

				<?php
				// Query user-experience posts associated with this QID.
				$associated_posts = new WP_Query(
					array(
						'post_type'   => WONDERCAT_POST_TYPE,
						'post_status' => 'publish',
						'meta_key'    => WONDERCAT_QID_FIELD,
						'meta_value'  => $qid,
						'orderby'     => 'date',
						'order'       => 'DESC',
					)
				);

				if ( $associated_posts->have_posts() ) :
					?>
					<section class="wikidata-associated-experiences">
						<h2><?php esc_html_e( 'Story Experiences', 'understrap-child' ); ?></h2>
						<?php
						while ( $associated_posts->have_posts() ) {
							$associated_posts->the_post();
							get_template_part( 'loop-templates/content-user-experience' );
						}
						wp_reset_postdata();
						?>
					</section><!-- .wikidata-associated-experiences -->
				<?php endif; ?>

			</main><!-- #main -->

			<?php get_template_part( 'global-templates/right-sidebar-check' ); ?>

		</div><!-- .row -->

	</div><!-- #content -->

</div><!-- #wikidata-entity-wrapper -->

<?php
get_footer();
