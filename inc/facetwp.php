<?php
/**
 * FacetWP integration for filtering the user-experience archive by Wikidata properties.
 *
 * @package WonderCat
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WONDERCAT_WD_FACETS' ) ) {
	// phpcs:disable PHPCompatibility.InitialValue.NewConstantArraysUsingDefine.Found
	define(
		'WONDERCAT_WD_FACETS',
		array(
			'wondercat_wd_instance' => array(
				'label' => __( 'Instance of', 'understrap' ),
				'props' => array( 'P31' ),
			),
			'wondercat_wd_genre'    => array(
				'label' => __( 'Genre', 'understrap' ),
				'props' => array( 'P136' ),
			),
			'wondercat_wd_depicts'  => array(
				'label' => __( 'Depicts', 'understrap' ),
				'props' => array( 'P180' ),
			),
			'wondercat_wd_country'  => array(
				'label'    => __( 'Country of Origin', 'understrap' ),
				'props'    => array( 'P495' ),
				'fallback' => array( 'P17' ),
			),
			'wondercat_wd_language' => array(
				'label' => __( 'Language', 'understrap' ),
				'props' => array( 'P407' ),
			),
		)
	);
	// phpcs:enable PHPCompatibility.InitialValue.NewConstantArraysUsingDefine.Found
}

add_filter( 'facetwp_facets', 'wondercat_register_facetwp_facets', 10 );
add_filter( 'facetwp_search_query_args', 'wondercat_search_query_args', 10, 2 );
add_filter( 'facetwp_indexer_row_data', 'wondercat_index_wikidata_facet_rows', 10, 2 );
add_filter( 'facetwp_facet_html', 'wondercat_bootstrap_facet_html', 10, 2 );
add_action( 'acf/save_post', 'wondercat_reindex_post', 30, 1 );
add_action( 'gform_advancedpostcreation_post_after_creation', 'wondercat_reindex_after_post_creation', 10, 1 );

/**
 * Register code-locked FacetWP facets.
 *
 * @param array $facets Existing facet configs.
 * @return array
 */
function wondercat_register_facetwp_facets( $facets ) {
	foreach ( WONDERCAT_WD_FACETS as $name => $spec ) {
		$facets[] = array(
			'name'     => $name,
			'label'    => $spec['label'],
			'type'     => 'fselect',
			'source'   => 'cf/wikidata-qid',
			'operator' => 'or',
			'orderby'  => 'count',
			'multiple' => 'yes',
		);
	}

	$facets[] = array(
		'name'     => 'wondercat_experience',
		'label'    => __( 'Experience', 'understrap' ),
		'type'     => 'fselect',
		'source'   => 'tax/experience',
		'operator' => 'or',
		'orderby'  => 'count',
		'multiple' => 'yes',
	);
	$facets[] = array(
		'name'     => 'wondercat_narrative_technology',
		'label'    => __( 'Narrative Technology', 'understrap' ),
		'type'     => 'fselect',
		'source'   => 'tax/technology',
		'operator' => 'or',
		'orderby'  => 'count',
		'multiple' => 'yes',
	);
	$facets[] = array(
		'name'       => 'wondercat_pager',
		'label'      => __( 'Pager', 'understrap' ),
		'type'       => 'pager',
		'pager_type' => 'numbers',
		'inner_size' => 2,
		'dots_label' => '…',
		'prev_label' => '« Prev',
		'next_label' => 'Next »',
	);
	$facets[] = array(
		'name'     => 'wondercat_reset',
		'label'    => __( 'Reset', 'understrap' ),
		'type'     => 'reset',
		'reset_ui' => 'link',
	);
	$facets[] = array(
		'name'             => 'wondercat_search',
		'label'            => __( 'Search', 'understrap' ),
		'type'             => 'search',
		'search_engine'    => '',
		'placeholder'      => __( 'Search experiences...', 'understrap' ),
		'auto_refresh'     => 'yes',
		'enable_relevance' => 'yes',
	);

	return $facets;
}

/**
 * Scope and extend the FacetWP search facet query.
 *
 * Native WordPress search only covers post_title/content/excerpt. For
 * user-experience posts the meaningful text lives in ACF postmeta, so this
 * scopes the query to the post type, raises the plugin's 200-post cap, and
 * registers a one-time posts_search extension that ORs in a postmeta EXISTS
 * clause for the notable free-text fields.
 *
 * @param array $search_args WP_Query args from FacetWP's search facet.
 * @param array $params      Facet request params (includes the facet config).
 * @return array
 */
function wondercat_search_query_args( $search_args, $params ) {
	$facet = isset( $params['facet'] ) ? $params['facet'] : array();
	$name  = isset( $facet['name'] ) ? $facet['name'] : '';

	if ( 'wondercat_search' !== $name ) {
		return $search_args;
	}

	$search_args['post_type']      = WONDERCAT_POST_TYPE;
	$search_args['posts_per_page'] = 500; // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page,WPThemeReview.CoreFunctionality.PostsPerPage.posts_per_page_posts_per_page

	add_filter( 'posts_search', 'wondercat_search_posts_search', 10, 2 );

	return $search_args;
}

/**
 * Append a postmeta EXISTS clause to the native search WHERE statement.
 *
 * A meta_query alone would AND with the native title/content clause; the
 * WHERE is wrapped so meta matches OR into the existing search. An EXISTS
 * subquery avoids the row duplication a postmeta JOIN would introduce. The
 * filter removes itself so later queries on the request are unaffected.
 *
 * The LIKE matches the notable free-text ACF fields: the feature quotation,
 * the creative work title, and the benefit of the experience.
 *
 * @param string   $search Generated search WHERE clause.
 * @param WP_Query $query  Current WP_Query instance.
 * @return string
 */
function wondercat_search_posts_search( $search, $query ) {
	global $wpdb;

	remove_filter( 'posts_search', 'wondercat_search_posts_search' );

	$term = isset( $query->query_vars['s'] ) ? (string) $query->query_vars['s'] : '';

	if ( '' === $term ) {
		return $search;
	}

	$like   = '%' . $wpdb->esc_like( $term ) . '%';
	$meta   = $wpdb->prepare(
		" EXISTS ( SELECT 1 FROM {$wpdb->postmeta}
			WHERE {$wpdb->postmeta}.post_id = {$wpdb->posts}.ID
			AND {$wpdb->postmeta}.meta_key IN ( 'feature', 'title_of_creative_work', 'benefit_of_experience' )
			AND {$wpdb->postmeta}.meta_value LIKE %s )",
		$like
	);
	$search = preg_replace( '/^\s*AND\s+/i', '', trim( $search ) );
	$search = ' AND ( ' . $search . ' OR ' . $meta . ' )';

	return $search;
}

/**
 * Apply Bootstrap classes to FacetWP facet output.
 *
 * FacetWP renders dropmenus and the reset control with its own markup, so the
 * theme adds Bootstrap styling via the facetwp_facet_html filter. The original
 * facetwp-* classes are preserved for FacetWP's frontend JavaScript. The
 * archive's own filter dropdowns are fSelect facets, which FacetWP hides and
 * replaces with its built-in widget (themed via SCSS), so they carry no
 * Bootstrap form classes here.
 *
 * @param string $output Facet HTML.
 * @param array  $args   Facet render args (includes the facet config).
 * @return string
 */
function wondercat_bootstrap_facet_html( $output, $args ) {
	$facet = isset( $args['facet'] ) ? $args['facet'] : array();
	$type  = isset( $facet['type'] ) ? $facet['type'] : '';

	if ( 'dropdown' === $type ) {
		$output = str_replace(
			'class="facetwp-dropdown"',
			'class="facetwp-dropdown form-select"',
			$output
		);
	} elseif ( 'search' === $type ) {
		$output = str_replace(
			'class="facetwp-search"',
			'class="facetwp-search form-control"',
			$output
		);
	} elseif ( 'reset' === $type ) {
		$output = preg_replace(
			'/class="facetwp-reset"/',
			'class="facetwp-reset btn btn-outline-dark"',
			$output
		);
	}

	return $output;
}

/**
 * Replace default index rows for Wikidata facets with derived property rows.
 *
 * Runs during full re-index and single-post auto-indexing. The facet source
 * (cf/wikidata-qid) guarantees FacetWP only generates rows for posts with a QID.
 *
 * @param array $rows   Default rows for the current post/facet.
 * @param array $params Indexer helper data.
 * @return array
 */
function wondercat_index_wikidata_facet_rows( $rows, $params ) {
	$defaults = isset( $params['defaults'] ) ? $params['defaults'] : array();

	if ( ! isset( WONDERCAT_WD_FACETS[ $defaults['facet_name'] ] ) || ! defined( 'WONDERCAT_QID_FIELD' ) ) {
		return $rows;
	}

	$post_id = absint( $defaults['post_id'] );

	if ( ! $post_id ) {
		return array();
	}

	$qid = get_post_meta( $post_id, WONDERCAT_QID_FIELD, true );

	if ( ! $qid ) {
		return array();
	}

	$entity = wikidata_get_by_qid( $qid );

	if ( ! $entity || empty( $entity->json_data ) ) {
		return array();
	}

	$entity_data = wikidata_decode_entity_row( $entity, $qid );

	if ( ! is_array( $entity_data ) ) {
		return array();
	}

	$spec  = WONDERCAT_WD_FACETS[ $defaults['facet_name'] ];
	$items = array();
	$seen  = array();

	foreach ( $spec['props'] as $property ) {
		foreach ( wikidata_entity_get_claim_entity_items( $entity_data, $property ) as $item ) {
			if ( ! isset( $seen[ $item['qid'] ] ) ) {
				$seen[ $item['qid'] ] = true;
				$items[]              = $item;
			}
		}
	}

	if ( empty( $items ) && ! empty( $spec['fallback'] ) ) {
		foreach ( $spec['fallback'] as $property ) {
			foreach ( wikidata_entity_get_claim_entity_items( $entity_data, $property ) as $item ) {
				if ( ! isset( $seen[ $item['qid'] ] ) ) {
					$seen[ $item['qid'] ] = true;
					$items[]              = $item;
				}
			}
		}
	}

	$rows = array();

	foreach ( $items as $item ) {
		$row                        = $defaults;
		$row['facet_value']         = $item['qid'];
		$row['facet_display_value'] = $item['label'];
		$rows[]                     = $row;
	}

	return $rows;
}

/**
 * Re-index a single user-experience post in FacetWP.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function wondercat_reindex_post( $post_id ) {
	$post_id = absint( $post_id );

	if ( ! $post_id || ! function_exists( 'FWP' ) || ! is_object( FWP()->indexer ) ) {
		return;
	}

	if ( defined( 'WONDERCAT_POST_TYPE' ) && WONDERCAT_POST_TYPE !== get_post_type( $post_id ) ) {
		return;
	}

	FWP()->indexer->index( $post_id );
}

/**
 * Process QID data and re-index after a Gravity Forms post creation feed.
 *
 * Advanced Post Creation writes ACF post meta directly and does not fire
 * acf/save_post, so the standard save-time upsert and indexing never run for
 * frontend-submitted experiences without this hook.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function wondercat_reindex_after_post_creation( $post_id ) {
	$post_id = absint( $post_id );

	if ( ! $post_id ) {
		return;
	}

	if ( function_exists( 'wondercat_process_qid_field' ) ) {
		wondercat_process_qid_field( $post_id );
	}

	wondercat_reindex_post( $post_id );
}
