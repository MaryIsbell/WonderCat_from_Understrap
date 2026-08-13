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
add_filter( 'facetwp_indexer_row_data', 'wondercat_index_wikidata_facet_rows', 10, 2 );
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
			'type'     => 'dropdown',
			'source'   => 'cf/wikidata-qid',
			'operator' => 'and',
			'orderby'  => 'count',
		);
	}

	$facets[] = array(
		'name'     => 'wondercat_experience',
		'label'    => __( 'Experience', 'understrap' ),
		'type'     => 'dropdown',
		'source'   => 'tax/experience',
		'operator' => 'and',
		'orderby'  => 'count',
	);
	$facets[] = array(
		'name'     => 'wondercat_narrative_technology',
		'label'    => __( 'Narrative Technology', 'understrap' ),
		'type'     => 'dropdown',
		'source'   => 'tax/technology',
		'operator' => 'and',
		'orderby'  => 'count',
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

	return $facets;
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
