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
defined('ABSPATH') || exit;

// Retrieve and validate the QID from the URL.
$raw_qid = get_query_var('wikidata_qid', '');
$qid     = strtoupper(sanitize_text_field($raw_qid));

// Validate QID format: must be Q followed by one or more digits.
if (! preg_match('/^Q[0-9]+$/', $qid)) {
	global $wp_query;
	$wp_query->set_404();
	status_header(404);
	nocache_headers();
	include get_404_template();
	exit;
}

// Look up the entity in the database.
$entity = wikidata_get_by_qid($qid);

if (! $entity) {
	global $wp_query;
	$wp_query->set_404();
	status_header(404);
	nocache_headers();
	include get_404_template();
	exit;
}

// Build metadata values from the stored entity payload.
$entity_data       = wikidata_decode_entity_row($entity, $qid);
$prefetch_qids     = wikidata_entity_collect_referenced_qids($entity_data);
wikidata_prefetch_entity_labels_by_qids($prefetch_qids);
$publication_date  = get_wikidata_entity_publication_date($entity_data);
$media_type_links  = get_wikidata_entity_media_type_links_html($entity_data);
$country_links     = get_wikidata_entity_country_of_origin_links_html($entity_data);
$genre_links       = get_wikidata_entity_genres_links_html($entity_data);
$language_links    = get_wikidata_entity_languages_links_html($entity_data);
$not_available     = __('Not available', 'understrap-child');
$wikidata_url      = 'https://www.wikidata.org/wiki/' . rawurlencode($qid);
$allowed_link_html = array(
	'a' => array(
		'href'   => array(),
		'target' => array(),
		'rel'    => array(),
	),
);

// Build title for <head>.
$page_title = $entity->label ? $entity->label : $qid;

// Render the page.
get_header();

$container = get_theme_mod('understrap_container_type');
?>

<div class="wrapper" id="wikidata-entity-wrapper">

	<div class="<?php echo esc_attr($container); ?>" id="content" tabindex="-1">

		<div class="row">

			<main class="site-main col" id="main">

				<article class="wikidata-entity" id="wikidata-entity-<?php echo esc_attr($qid); ?>">



					<div class="entry-content">



						<dl class="wikidata-metadata m-5" aria-label="<?php esc_attr_e('Wikidata metadata', 'understrap-child'); ?>">

							<div class="row">
								<header class="entry-header">
									<h3 class="entry-title"><?php echo esc_html($page_title); ?></h3>
								</header>
							</div>

							<div class="row mb-2">



								<div class="col-12 col-md-6">
									<dt class="fw-bold mb-0 d-inline">
										<?php esc_html_e('Instance of', 'understrap-child'); ?>
									</dt>
									<dd class="mb-0 d-inline">
										<?php if ($media_type_links) : ?>
											<?php echo wp_kses($media_type_links, $allowed_link_html); ?>
										<?php else : ?>
											<?php echo esc_html($not_available); ?>
										<?php endif; ?>
									</dd>
								</div>


								<div class="col-12 col-md-6">
									<dt class="fw-bold mb-0 d-inline">
										<?php esc_html_e('Country of Origin', 'understrap-child'); ?>
									</dt>
									<dd class="mb-0 d-inline">
										<?php if ($country_links) : ?>
											<?php echo wp_kses($country_links, $allowed_link_html); ?>
										<?php else : ?>
											<?php echo esc_html($not_available); ?>
										<?php endif; ?>
									</dd>
								</div>


							</div>



							<div class="row mb-2">



								<div class="col-12 col-md-6">
									<dt class="fw-bold mb-0 d-inline">
										<?php esc_html_e('Genre', 'understrap-child'); ?>
									</dt>
									<dd class="mb-0 d-inline">
										<?php if ($genre_links) : ?>
											<?php echo wp_kses($genre_links, $allowed_link_html); ?>
										<?php else : ?>
											<?php echo esc_html($not_available); ?>
										<?php endif; ?>
									</dd>
								</div>

								<div class="col-12 col-md-6">
									<dt class="fw-bold mb-0 d-inline">
										<?php esc_html_e('Language', 'understrap-child'); ?>
									</dt>
									<dd class="mb-0 d-inline">
										<?php if ($language_links) : ?>
											<?php echo wp_kses($language_links, $allowed_link_html); ?>
										<?php else : ?>
											<?php echo esc_html($not_available); ?>
										<?php endif; ?>
									</dd>
								</div>


							</div>


							<div class="row mb-2">



								<div class="col-12 col-md-6">
									<dt class="fw-bold mb-0 d-inline">
										<?php esc_html_e('Wikidata ID', 'understrap-child'); ?>
									</dt>
									<dd class="mb-0 d-inline">
										<a href="<?php echo esc_url($wikidata_url); ?>" target="_blank" rel="noopener">
											<?php echo esc_html($qid); ?>
										</a>
									</dd>
								</div>

								<div class="col-12 col-md-6">
									<dt class="fw-bold mb-0 d-inline">
										<?php esc_html_e('Publication Date', 'understrap-child'); ?>
									</dt>
									<dd class="mb-0 d-inline">
										<?php echo esc_html($publication_date ? $publication_date : $not_available); ?>
									</dd>
								</div>


							</div>
						</dl>

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

				if ($associated_posts->have_posts()) :
				?>
					<section class="wikidata-associated-experiences">
						<h2><?php esc_html_e('Story Experiences', 'understrap-child'); ?></h2>
						<?php
						while ($associated_posts->have_posts()) {
							$associated_posts->the_post();
							get_template_part('loop-templates/content-user-experience');
						}
						wp_reset_postdata();
						?>
					</section><!-- .wikidata-associated-experiences -->
				<?php endif; ?>

			</main><!-- #main -->

		</div><!-- .row -->

	</div><!-- #content -->

</div><!-- #wikidata-entity-wrapper -->

<?php
get_footer();
