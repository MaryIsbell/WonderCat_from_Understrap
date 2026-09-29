#!/usr/bin/env node
/**
 * QA/integration script for GET /wp-json/wondercat/v1/experiences.
 *
 * Validates response schema, cross-item type consistency, pagination
 * clamping, encoding, and FacetWP filtering correctness against a running
 * wp-env instance. Not a unit test framework -- this repo has none; see
 * inc/wikidata/docs/FACETWP-ARCHIVE-FILTERING.md for the endpoint contract
 * and the "Known Issues / QA Findings" section for prior results.
 *
 * Usage: node scripts/test-facetwp-endpoint.js
 * Env:   WONDERCAT_TEST_BASE_URL (default http://localhost:8888)
 */

'use strict';

const BASE_URL = process.env.WONDERCAT_TEST_BASE_URL || 'http://localhost:8888';
const ENDPOINT = `${BASE_URL}/wp-json/wondercat/v1/experiences`;
const WIKIDATA_FACET_NAMES = [
	'wondercat_wd_instance',
	'wondercat_wd_genre',
	'wondercat_wd_depicts',
	'wondercat_wd_country',
	'wondercat_wd_language',
];

/** @type {Array<{name:string,status:'PASS'|'FAIL'|'WARN'|'SKIP',detail:string}>} */
const results = [];

function record( name, status, detail = '' ) {
	results.push( { name, status, detail } );
}

function jsType( value ) {
	if ( value === null ) {
		return 'null';
	}
	if ( Array.isArray( value ) ) {
		return 'array';
	}
	return typeof value;
}

async function fetchJson( params = {} ) {
	const url = new URL( ENDPOINT );
	for ( const [ key, value ] of Object.entries( params ) ) {
		if ( Array.isArray( value ) ) {
			for ( const v of value ) {
				url.searchParams.append( `${key}[]`, v );
			}
		} else {
			url.searchParams.set( key, value );
		}
	}

	const response = await fetch( url );
	const text = await response.text();
	return { response, text, url: url.toString() };
}

/**
 * Fetch and parse, reporting the "raw body corrupted by a stray PHP
 * notice/warning" failure mode distinctly from a generic parse error.
 */
async function fetchParsed( params = {} ) {
	const { response, text, url } = await fetchJson( params );

	if ( ! text.trimStart().startsWith( '{' ) ) {
		record(
			'raw-body-sanity',
			'FAIL',
			`Body for ${url} does not start with '{' (status ${response.status}). ` +
				`Likely a stray PHP notice/warning printed before REST output. First 200 chars: ${text.slice( 0, 200 )}`
		);
		return { response, body: null };
	}

	try {
		return { response, body: JSON.parse( text ) };
	} catch ( err ) {
		record( 'json-parse', 'FAIL', `Failed to parse JSON from ${url}: ${err.message}` );
		return { response, body: null };
	}
}

async function checkRawBodySanityAndEnvelope() {
	const { response, text } = await fetchJson();

	if ( response.status !== 200 ) {
		record( 'baseline-status', 'FAIL', `Expected 200, got ${response.status}` );
		return null;
	}
	record( 'baseline-status', 'PASS' );

	const contentType = response.headers.get( 'content-type' ) || '';
	if ( ! contentType.includes( 'application/json' ) ) {
		record( 'content-type', 'WARN', `Expected application/json, got "${contentType}"` );
	} else {
		record( 'content-type', 'PASS' );
	}

	if ( ! text.trimStart().startsWith( '{' ) ) {
		record(
			'raw-body-sanity',
			'FAIL',
			`Body does not start with '{'. Likely a stray PHP notice/warning before REST output. First 200 chars: ${text.slice( 0, 200 )}`
		);
		return null;
	}
	record( 'raw-body-sanity', 'PASS' );

	let body;
	try {
		body = JSON.parse( text );
	} catch ( err ) {
		record( 'json-parse', 'FAIL', err.message );
		return null;
	}
	record( 'json-parse', 'PASS' );

	for ( const key of [ 'items', 'facets', 'pager' ] ) {
		if ( ! ( key in body ) ) {
			record( 'top-level-shape', 'FAIL', `Missing top-level key "${key}"` );
		}
	}
	if ( ! Array.isArray( body.items ) ) {
		record( 'top-level-shape', 'FAIL', '"items" is not an array' );
	} else {
		record( 'top-level-shape', 'PASS', `items=${body.items.length}` );
	}
	record( 'facets-shape-observed', 'PASS', `typeof facets = ${jsType( body.facets )}` );
	record( 'pager-shape-observed', 'PASS', `typeof pager = ${jsType( body.pager )}, keys=${Object.keys( body.pager || {} ).join( ',' )}` );

	return body;
}

function checkPerItemSchema( items ) {
	if ( ! items.length ) {
		record( 'per-item-schema', 'SKIP', 'No items in baseline response to validate' );
		return;
	}

	const requiredScalarKeys = [ 'id', 'title', 'permalink', 'featured_image', 'feature', 'benefit_of_experience', 'title_of_creative_work', 'wikidata_qid' ];
	const requiredArrayKeys = [ 'experience', 'technology' ];
	let failures = 0;

	for ( const [ index, item ] of items.entries() ) {
		for ( const key of [ ...requiredScalarKeys, ...requiredArrayKeys, 'wikidata' ] ) {
			if ( ! ( key in item ) ) {
				record( 'per-item-schema', 'FAIL', `items[${index}] missing key "${key}"` );
				failures++;
			}
		}
		if ( typeof item.id !== 'number' ) {
			record( 'per-item-schema', 'FAIL', `items[${index}].id is not a number (${jsType( item.id )})` );
			failures++;
		}
		if ( typeof item.title !== 'string' ) {
			record( 'per-item-schema', 'FAIL', `items[${index}].title is not a string (${jsType( item.title )})` );
			failures++;
		}
		if ( typeof item.permalink !== 'string' || ! item.permalink.startsWith( 'http' ) ) {
			record( 'per-item-schema', 'FAIL', `items[${index}].permalink is not an http(s) string ("${item.permalink}")` );
			failures++;
		}
		if ( item.featured_image !== null && typeof item.featured_image !== 'string' ) {
			record( 'per-item-schema', 'FAIL', `items[${index}].featured_image is ${jsType( item.featured_image )} (${item.featured_image}), expected string|null` );
			failures++;
		}
		for ( const key of requiredArrayKeys ) {
			if ( ! Array.isArray( item[ key ] ) ) {
				record( 'per-item-schema', 'FAIL', `items[${index}].${key} is not an array (${jsType( item[ key ] )})` );
				failures++;
			}
		}
	}

	if ( ! failures ) {
		record( 'per-item-schema', 'PASS', `Validated ${items.length} items` );
	}
}

function checkCrossItemTypeConsistency( items ) {
	if ( ! items.length ) {
		record( 'type-consistency', 'SKIP', 'No items to compare' );
		return;
	}

	const fieldTypes = new Map();
	for ( const item of items ) {
		for ( const [ key, value ] of Object.entries( item ) ) {
			if ( value === null ) {
				continue;
			}
			if ( ! fieldTypes.has( key ) ) {
				fieldTypes.set( key, new Set() );
			}
			fieldTypes.get( key ).add( jsType( value ) );
		}
	}

	let inconsistent = 0;
	for ( const [ key, types ] of fieldTypes.entries() ) {
		if ( types.size > 1 ) {
			record(
				'type-consistency',
				'WARN',
				`Field "${key}" has inconsistent non-null types across items: ${[ ...types ].join( ', ' )} ` +
					'-- will not flatten cleanly with jsonlite::fromJSON / purrr::map_dfr in R/Shiny.'
			);
			inconsistent++;
		}
	}

	if ( ! inconsistent ) {
		record( 'type-consistency', 'PASS', `${fieldTypes.size} fields checked, all consistent` );
	}
}

function checkWikidataShapeAsymmetry( items ) {
	if ( ! items.length ) {
		record( 'wikidata-shape-asymmetry', 'SKIP', 'No items to compare' );
		return;
	}

	let failures = 0;

	for ( const item of items ) {
		if ( jsType( item.wikidata ) !== 'object' ) {
			record( 'wikidata-shape-asymmetry', 'FAIL', `items[id=${item.id}].wikidata is ${jsType( item.wikidata )}, expected an object keyed by facet name (regression: array_fill_keys default removed?)` );
			failures++;
			continue;
		}

		for ( const [ facetName, values ] of Object.entries( item.wikidata ) ) {
			if ( ! Array.isArray( values ) ) {
				record( 'wikidata-shape-asymmetry', 'FAIL', `items[id=${item.id}].wikidata.${facetName} is not an array (${jsType( values )})` );
				failures++;
				continue;
			}
			for ( const value of values ) {
				if ( jsType( value ) !== 'object' || typeof value.qid !== 'string' || typeof value.label !== 'string' ) {
					record( 'wikidata-shape-asymmetry', 'FAIL', `items[id=${item.id}].wikidata.${facetName} entry missing string qid/label: ${JSON.stringify( value )}` );
					failures++;
				}
			}
		}
	}

	if ( ! failures ) {
		record( 'wikidata-shape-asymmetry', 'PASS', `${items.length} items all expose "wikidata" as an object of {qid,label} arrays` );
	}
}

function checkEncoding( items ) {
	if ( ! items.length ) {
		record( 'encoding-scan', 'SKIP', 'No items to scan' );
		return;
	}

	const textKeys = [ 'title', 'feature', 'benefit_of_experience', 'title_of_creative_work' ];
	const entityPattern = /&#?\w+;/;
	const tagPattern = /<[a-z][\s\S]*>/i;
	const offenders = [];

	for ( const item of items ) {
		for ( const key of textKeys ) {
			const value = item[ key ];
			if ( typeof value !== 'string' ) {
				continue;
			}
			if ( entityPattern.test( value ) || tagPattern.test( value ) ) {
				offenders.push( `items[id=${item.id}].${key} = "${value.slice( 0, 80 )}"` );
			}
		}
	}

	if ( offenders.length ) {
		record( 'encoding-scan', 'FAIL', `Un-decoded HTML entities/tags found:\n  ${offenders.join( '\n  ' )}` );
	} else {
		record( 'encoding-scan', 'PASS' );
	}
}

async function checkPaginationClamping() {
	const { body: zeroPerPage } = await fetchParsed( { per_page: 0 } );
	if ( zeroPerPage && zeroPerPage.items.length > 1 ) {
		record( 'pagination-clamp-min', 'FAIL', `per_page=0 returned ${zeroPerPage.items.length} items, expected clamp to 1` );
	} else if ( zeroPerPage ) {
		record( 'pagination-clamp-min', 'PASS' );
	}

	const { body: overPerPage } = await fetchParsed( { per_page: 500 } );
	if ( overPerPage && overPerPage.items.length > 100 ) {
		record( 'pagination-clamp-max', 'FAIL', `per_page=500 returned ${overPerPage.items.length} items, expected clamp to 100` );
	} else if ( overPerPage ) {
		record( 'pagination-clamp-max', 'PASS', `${overPerPage.items.length} items returned` );
	}

	const { response: zeroPageResponse } = await fetchJson( { page: 0 } );
	if ( zeroPageResponse.status >= 500 ) {
		record( 'pagination-clamp-page', 'FAIL', `page=0 caused a server error (${zeroPageResponse.status})` );
	} else {
		record( 'pagination-clamp-page', 'PASS', `page=0 handled with status ${zeroPageResponse.status}` );
	}
}

async function checkInvalidFacetValue() {
	const { response, body } = await fetchParsed( { wondercat_wd_genre: [ 'NOT_A_REAL_QID' ] } );

	if ( response.status !== 200 ) {
		record( 'invalid-facet-value', 'FAIL', `Expected 200 for an unmatched facet value, got ${response.status}` );
		return;
	}
	if ( body && Array.isArray( body.items ) && body.items.length === 0 ) {
		record( 'invalid-facet-value', 'PASS' );
	} else if ( body ) {
		record( 'invalid-facet-value', 'WARN', `Expected empty items for a nonexistent QID, got ${body.items.length}` );
	}
}

/**
 * Read a facet's real choices out of the baseline (unfiltered) response.
 *
 * The endpoint now always includes every known facet key in its internal fetch
 * request (see wondercat_experiences_rest_callback() in inc/facetwp.php), so a
 * single unfiltered GET already returns choices/counts for every registered facet
 * -- no per-facet probe request is needed.
 */
function discoverFacetChoices( facetName, baselineBody ) {
	const choices = baselineBody && baselineBody.facets && baselineBody.facets[ facetName ] && baselineBody.facets[ facetName ].choices;
	return Array.isArray( choices ) ? choices : [];
}

function pickChoiceWithCount( choices ) {
	return choices.find( ( c ) => Number( c.count ) > 0 ) || null;
}

function checkFacetsBlockAlwaysPopulated( baselineBody ) {
	const allFacetNames = [ ...WIKIDATA_FACET_NAMES, 'wondercat_experience', 'wondercat_narrative_technology' ];
	const missing = allFacetNames.filter( ( name ) => ! ( baselineBody.facets && name in baselineBody.facets ) );

	if ( missing.length ) {
		record(
			'facets-block-always-populated',
			'FAIL',
			`Unfiltered baseline request is missing facet choices for: ${missing.join( ', ' )} (expected every registered facet key present, ` +
				'even unselected -- see wondercat_experiences_rest_callback() in inc/facetwp.php)'
		);
	} else {
		record( 'facets-block-always-populated', 'PASS', `All ${allFacetNames.length} registered facets returned choices on the unfiltered baseline request` );
	}
}

async function checkSingleFacetFilter( baselineBody ) {
	let chosenFacetName = null;
	let choice = null;

	for ( const facetName of WIKIDATA_FACET_NAMES ) {
		choice = pickChoiceWithCount( discoverFacetChoices( facetName, baselineBody ) );
		if ( choice ) {
			chosenFacetName = facetName;
			break;
		}
	}

	if ( ! chosenFacetName ) {
		record( 'single-facet-filter', 'SKIP', 'No Wikidata facet choice with count > 0 discovered (dev DB may have no indexed QIDs)' );
		return;
	}

	const facetValue = choice.value;
	const { body } = await fetchParsed( { [ chosenFacetName ]: [ facetValue ] } );

	if ( ! body ) {
		record( 'single-facet-filter', 'FAIL', `No parsable body when filtering by ${chosenFacetName}=${facetValue}` );
		return;
	}

	const expectedCount = Number( choice.count );
	if ( body.items.length > expectedCount ) {
		record( 'single-facet-filter', 'FAIL', `Filtering ${chosenFacetName}=${facetValue} returned ${body.items.length} items, expected <= ${expectedCount}` );
		return;
	}

	// Match on the raw qid (not the label) now that the endpoint exposes it -- avoids the
	// prior ambiguity where two different QIDs sharing a label couldn't be told apart.
	const allMatch = body.items.every( ( item ) => ( item.wikidata[ chosenFacetName ] || [] ).some( ( w ) => w.qid.toLowerCase() === facetValue.toLowerCase() ) );
	if ( ! allMatch ) {
		record(
			'single-facet-filter',
			'FAIL',
			`Not every returned item's wikidata.${chosenFacetName} contains qid "${facetValue}"`
		);
	} else {
		record( 'single-facet-filter', 'PASS', `${chosenFacetName}=${facetValue} -> ${body.items.length} items, all matched by qid` );
	}
}

async function checkMultiFacetAnd( baselineBody ) {
	const candidates = [];
	for ( const facetName of WIKIDATA_FACET_NAMES ) {
		const choice = pickChoiceWithCount( discoverFacetChoices( facetName, baselineBody ) );
		if ( choice ) {
			candidates.push( { facetName, choice } );
		}
		if ( candidates.length === 2 ) {
			break;
		}
	}

	if ( candidates.length < 2 ) {
		record( 'multi-facet-and', 'SKIP', 'Fewer than two Wikidata facets with a discoverable choice' );
		return;
	}

	const [ a, b ] = candidates;
	const { body: singleA } = await fetchParsed( { [ a.facetName ]: [ a.choice.value ] } );
	const { body: combined } = await fetchParsed( {
		[ a.facetName ]: [ a.choice.value ],
		[ b.facetName ]: [ b.choice.value ],
	} );

	if ( ! singleA || ! combined ) {
		record( 'multi-facet-and', 'FAIL', 'Missing parsable body for single or combined facet request' );
		return;
	}

	if ( combined.items.length > singleA.items.length ) {
		record(
			'multi-facet-and',
			'FAIL',
			`Combining ${a.facetName} + ${b.facetName} returned more items (${combined.items.length}) than ${a.facetName} alone (${singleA.items.length}); expected AND-across-facets narrowing`
		);
	} else {
		record( 'multi-facet-and', 'PASS', `${a.facetName} alone=${singleA.items.length}, combined with ${b.facetName}=${combined.items.length}` );
	}
}

async function checkTaxonomyFacetFilter( baselineBody ) {
	for ( const facetName of [ 'wondercat_experience', 'wondercat_narrative_technology' ] ) {
		const choice = pickChoiceWithCount( discoverFacetChoices( facetName, baselineBody ) );
		if ( ! choice ) {
			record( 'taxonomy-facet-filter', 'SKIP', `No discoverable choice for ${facetName}` );
			continue;
		}

		const value = choice.value;
		const { body } = await fetchParsed( { [ facetName ]: [ value ] } );

		if ( ! body ) {
			record( 'taxonomy-facet-filter', 'FAIL', `No parsable body filtering ${facetName}=${value}` );
			continue;
		}

		if ( body.items.length === 0 ) {
			record(
				'taxonomy-facet-filter',
				'WARN',
				`Filtering ${facetName}=${value} (from the facet's own reported choices) returned zero items -- possible param-format mismatch ` +
					'(slug vs term_id vs facet_value) between what the facets block reports and what the endpoint expects.'
			);
		} else {
			record( 'taxonomy-facet-filter', 'PASS', `${facetName}=${value} -> ${body.items.length} items` );
		}
	}
}

async function checkSearchFacet( baselineItems ) {
	const source = baselineItems.find( ( item ) => typeof item.feature === 'string' && item.feature.trim().length > 3 );
	if ( ! source ) {
		record( 'search-facet', 'SKIP', 'No item with a usable "feature" text to search for' );
		return;
	}

	const term = source.feature.trim().split( /\s+/ )[ 0 ];
	const { body } = await fetchParsed( { wondercat_search: term } );

	if ( ! body ) {
		record( 'search-facet', 'FAIL', `No parsable body searching for "${term}"` );
		return;
	}

	const found = body.items.some( ( item ) => item.id === source.id );
	if ( ! found ) {
		record( 'search-facet', 'WARN', `Searching "${term}" (drawn from items[id=${source.id}].feature) did not return that item` );
	} else {
		record( 'search-facet', 'PASS', `"${term}" -> ${body.items.length} items, source item present` );
	}
}

function printSummary() {
	const order = { FAIL: 0, WARN: 1, PASS: 2, SKIP: 3 };
	const sorted = [ ...results ].sort( ( a, b ) => order[ a.status ] - order[ b.status ] );

	console.log( `\nFacetWP experiences endpoint QA -- ${ENDPOINT}\n` );
	for ( const r of sorted ) {
		console.log( `[${r.status}] ${r.name}${r.detail ? ` -- ${r.detail}` : ''}` );
	}

	const counts = results.reduce( ( acc, r ) => {
		acc[ r.status ] = ( acc[ r.status ] || 0 ) + 1;
		return acc;
	}, {} );
	console.log( `\nTotals: ${JSON.stringify( counts )}\n` );

	return counts.FAIL ? 1 : 0;
}

async function main() {
	const baselineBody = await checkRawBodySanityAndEnvelope();

	if ( baselineBody ) {
		checkPerItemSchema( baselineBody.items );
		checkCrossItemTypeConsistency( baselineBody.items );
		checkWikidataShapeAsymmetry( baselineBody.items );
		checkEncoding( baselineBody.items );
	}

	await checkPaginationClamping();
	await checkInvalidFacetValue();

	if ( baselineBody ) {
		checkFacetsBlockAlwaysPopulated( baselineBody );
		await checkSingleFacetFilter( baselineBody );
		await checkMultiFacetAnd( baselineBody );
		await checkTaxonomyFacetFilter( baselineBody );
		await checkSearchFacet( baselineBody.items );
	}

	process.exitCode = printSummary();
}

main().catch( ( err ) => {
	console.error( 'Fatal error running the QA script:', err );
	process.exitCode = 1;
} );
