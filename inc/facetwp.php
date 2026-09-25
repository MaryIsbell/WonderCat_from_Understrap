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

// Public read-only introspection of raw facet choices/counts; no data mutation is possible via this endpoint.
add_filter( 'facetwp_api_can_access', '__return_true' );
add_action( 'rest_api_init', 'wondercat_register_experiences_rest_route' );

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
 * Resolve deduplicated Wikidata entity items for a facet spec, applying the fallback properties.
 *
 * Shared by the FacetWP indexer and the `wondercat/v1/experiences` REST endpoint so both
 * surfaces resolve Wikidata facet values (and the country P495 -> P17 fallback) identically.
 *
 * @param array $entity_data Decoded Wikidata entity payload.
 * @param array $spec        Facet spec from WONDERCAT_WD_FACETS (props + optional fallback).
 * @return array<int,array{qid:string,label:string,url:string}> Deduplicated items.
 */
function wondercat_get_wikidata_facet_items( $entity_data, $spec ) {
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

	return $items;
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

	$items = wondercat_get_wikidata_facet_items( $entity_data, WONDERCAT_WD_FACETS[ $defaults['facet_name'] ] );
	$rows  = array();

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

/**
 * Facet names accepted by the `wondercat/v1/experiences` REST endpoint.
 *
 * @return string[]
 */
function wondercat_experiences_facet_keys() {
	return array_merge(
		array_keys( WONDERCAT_WD_FACETS ),
		array( 'wondercat_experience', 'wondercat_narrative_technology', 'wondercat_search' )
	);
}

/**
 * Sanitize a single facet value from the request (string or array of strings).
 *
 * @param mixed $value Raw request value.
 * @return string[]
 */
function wondercat_sanitize_facet_param( $value ) {
	return array_map( 'sanitize_text_field', (array) $value );
}

/**
 * REST arg schema for `wondercat/v1/experiences`, one entry per known facet plus paging.
 *
 * @return array
 */
function wondercat_experiences_rest_args() {
	$args = array(
		'page'     => array(
			'default'           => 1,
			'sanitize_callback' => 'absint',
		),
		'per_page' => array(
			'default'           => 20,
			'sanitize_callback' => 'absint',
		),
	);

	foreach ( wondercat_experiences_facet_keys() as $facet_key ) {
		$args[ $facet_key ] = array(
			'sanitize_callback' => 'wondercat_sanitize_facet_param',
		);
	}

	return $args;
}

/**
 * Register the public, read-only JSON feed of filtered story experiences.
 *
 * Mirrors the archive's FacetWP sidebar: accepts the same facet names as query params
 * and runs them through FacetWP's own filtering engine, then returns enriched post data
 * instead of bare post IDs.
 *
 * @return void
 */
function wondercat_register_experiences_rest_route() {
	register_rest_route(
		'wondercat/v1',
		'/experiences',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'wondercat_experiences_rest_callback',
			'permission_callback' => '__return_true',
			'args'                => wondercat_experiences_rest_args(),
		)
	);
}

/**
 * Map a single WP_Term to its REST payload shape.
 *
 * @param WP_Term $term Term object.
 * @return array
 */
function wondercat_experience_rest_term( $term ) {
	return array(
		'id'   => $term->term_id,
		'name' => $term->name,
		'slug' => $term->slug,
		'link' => get_term_link( $term ),
	);
}

/**
 * Build the taxonomy term payload for a REST experience item.
 *
 * @param int    $post_id  Post ID.
 * @param string $taxonomy Taxonomy slug.
 * @return array
 */
function wondercat_experience_rest_terms( $post_id, $taxonomy ) {
	$terms = get_the_terms( $post_id, $taxonomy );

	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return array();
	}

	return array_map( 'wondercat_experience_rest_term', $terms );
}

/**
 * Build the JSON payload for a single story-experience post.
 *
 * @param WP_Post $post Post object.
 * @return array
 */
function wondercat_build_experience_rest_item( $post ) {
	$post_id       = $post->ID;
	$qid           = get_field( WONDERCAT_QID_FIELD, $post_id );
	$thumbnail_url = get_the_post_thumbnail_url( $post_id, 'large' );

	$item = array(
		'id'                     => $post_id,
		'title'                  => wp_kses_decode_entities( get_the_title( $post_id ) ),
		'permalink'              => get_permalink( $post_id ),
		'featured_image'         => $thumbnail_url ? $thumbnail_url : null, // Normalize false (no thumbnail) to null for a consistent JSON type.
		'feature'                => wp_kses_decode_entities( (string) get_field( 'feature', $post_id ) ),
		'benefit_of_experience'  => wp_kses_decode_entities( (string) get_field( 'benefit_of_experience', $post_id ) ),
		'title_of_creative_work' => wp_kses_decode_entities( (string) get_field( 'title_of_creative_work', $post_id ) ),
		'wikidata_qid'           => $qid ? $qid : null,
		'experience'             => wondercat_experience_rest_terms( $post_id, 'experience' ),
		'technology'             => wondercat_experience_rest_terms( $post_id, 'technology' ),
		// Always emit every facet key (even with no QID) so the JSON shape is a stable object, never an ambiguous empty array.
		'wikidata'               => array_fill_keys( array_keys( WONDERCAT_WD_FACETS ), array() ),
	);

	if ( ! $qid ) {
		return $item;
	}

	$entity      = wikidata_get_by_qid( $qid );
	$entity_data = $entity ? wikidata_decode_entity_row( $entity, $qid ) : null;

	if ( ! is_array( $entity_data ) ) {
		return $item;
	}

	foreach ( WONDERCAT_WD_FACETS as $facet_name => $spec ) {
		// Keep the raw qid alongside the label so consumers can filter without guessing the lowercased facet value.
		$item['wikidata'][ $facet_name ] = wondercat_get_wikidata_facet_items( $entity_data, $spec );
	}

	return $item;
}

/**
 * Callback for `GET /wp-json/wondercat/v1/experiences`.
 *
 * @param WP_REST_Request $request REST request.
 * @return WP_REST_Response|WP_Error
 */
function wondercat_experiences_rest_callback( WP_REST_Request $request ) {
	if ( ! function_exists( 'FWP' ) ) {
		return new WP_Error( 'wondercat_facetwp_unavailable', __( 'FacetWP is not active.', 'understrap' ), array( 'status' => 503 ) );
	}

	$facets = array();

	// Always include every known facet key, even unselected (empty array), so FacetWP's fetch
	// route returns choices/counts for all facets on every request, not just requested ones.
	foreach ( wondercat_experiences_facet_keys() as $facet_key ) {
		$facets[ $facet_key ] = (array) $request->get_param( $facet_key );
	}

	$per_page = min( 100, max( 1, absint( $request->get_param( 'per_page' ) ) ) );
	$page     = max( 1, absint( $request->get_param( 'page' ) ) );

	// FacetWP_API_Fetch::process_request() only initializes FWP()->facet->facets when at
	// least one facet is selected; with none selected it stays null and its own
	// get_filtered_post_ids() does foreach ( $this->facets as ... ) on null.
	if ( ! is_array( FWP()->facet->facets ) ) {
		FWP()->facet->facets = array();
	}

	// FacetWP 4.5 has no FWP()->request_handler; route through its own facetwp/v1/fetch
	// endpoint (already enabled above) so the exact same engine backs both routes.
	$fetch_request = new WP_REST_Request( 'POST', '/facetwp/v1/fetch' );
	$fetch_request->set_param(
		'data',
		wp_json_encode(
			array(
				'facets'     => $facets,
				'query_args' => array(
					'post_type'      => WONDERCAT_POST_TYPE,
					'post_status'    => 'publish', // Public endpoint: never expose private/draft experiences.
					'posts_per_page' => $per_page,
					'paged'          => $page,
				),
			)
		)
	);

	$fetch_response = rest_do_request( $fetch_request );

	if ( $fetch_response->is_error() ) {
		return $fetch_response->as_error();
	}

	$result   = $fetch_response->get_data();
	$post_ids = isset( $result['results'] ) ? array_map( 'absint', (array) $result['results'] ) : array();
	$items    = array();

	if ( ! empty( $post_ids ) ) {
		$posts_query = new WP_Query(
			array(
				'post_type'      => WONDERCAT_POST_TYPE,
				'post_status'    => 'publish',
				'post__in'       => $post_ids,
				'orderby'        => 'post__in', // Preserve FacetWP's result order (e.g. active Sort facet).
				'posts_per_page' => count( $post_ids ), // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page,WPThemeReview.CoreFunctionality.PostsPerPage.posts_per_page_posts_per_page -- already capped to 100 above via $per_page.
			)
		);

		foreach ( $posts_query->posts as $post ) {
			$items[] = wondercat_build_experience_rest_item( $post );
		}
	}

	return rest_ensure_response(
		array(
			'items'  => $items,
			'facets' => isset( $result['facets'] ) ? $result['facets'] : array(),
			'pager'  => isset( $result['pager'] ) ? $result['pager'] : array(),
		)
	);
}
