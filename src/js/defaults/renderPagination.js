import { escapeAttribute, escapeHTML } from '@wordpress/escape-html';
import { __ } from '@wordpress/i18n';
import { i18n } from '../config';

const maxSlots = 7;

/**
 * Works out which page numbers (and "..." gaps) to show for the current page.
 *
 * @param {number} current - The current 1-based page number.
 * @param {number} total   - The total number of pages.
 * @return {Array<number|string>} Page numbers, with '...' marking gaps.
 */
export function calculatePaginationSlots( current, total ) {
	const slots = [ 1 ];

	if ( total <= maxSlots ) {
		for ( let i = 2; i <= total; i++ ) {
			slots.push( i );
		}
		return slots;
	}

	if ( current > 3 ) {
		slots.push( '...' );
	}

	const start = Math.max( 2, current - 1 );
	const end = Math.min( current + 1, total - 1 );

	for ( let i = start; i <= end; i++ ) {
		slots.push( i );
	}

	if ( current < total - 2 ) {
		slots.push( '...' );
	}

	slots.push( total );

	return slots;
}

/**
 * Builds the default pagination controls and wires them to goToPage().
 * Used when a template doesn't override renderPagination.
 *
 * @param {Object}      data        - The REST response data.
 * @param {Object}      instanceCfg - The per-instance configuration.
 * @param {HTMLElement} element     - The pagination container element.
 * @param {Function}    goToPage    - Navigates to the given page number.
 */
export function defaultRenderPagination( data, instanceCfg, element, goToPage ) {
	let pagination = '';
	if ( data.max_num_pages > 1 ) {
		pagination += '<nav aria-label="' + escapeAttribute( i18n.pagination || __( 'Pagination', 'edmm' ) ) + '">' +
			'<div class="edmm-pagination-buttons">';

		if ( data.current_page > 1 ) {
			pagination += '<button type="button" class="edmm-pagination-button prev"' +
				' aria-label="' + escapeAttribute( i18n.previousPage || __( 'Previous Page', 'edmm' ) ) + '"' +
				' data-page="' + ( data.current_page - 1 ) + '">' +
				escapeHTML( i18n.previous || __( 'Previous', 'edmm' ) ) +
				'</button>';
		}

		const slots = calculatePaginationSlots( data.current_page, data.max_num_pages );

		slots.forEach( function( slot ) {
			if ( slot === '...' ) {
				pagination += '<span class="pagination-ellipsis">...</span>';
			} else {
				const isCurrent = slot === data.current_page;
				// translators: %s: page number.
				const label = escapeAttribute( ( i18n.pageNum || __( 'Page %s', 'edmm' ) ).replace( '%s', slot ) );
				pagination += '<button type="button"' +
					' class="edmm-pagination-button' + ( isCurrent ? ' current' : '' ) + '"' +
					' data-page="' + slot + '"' +
					' aria-label="' + label + '"' +
					( isCurrent ? ' aria-current="true"' : '' ) +
					'>' + slot + '</button>';
			}
		} );

		if ( data.current_page < data.max_num_pages ) {
			pagination += '<button type="button" class="edmm-pagination-button next"' +
				' aria-label="' + escapeAttribute( i18n.nextPage || __( 'Next Page', 'edmm' ) ) + '"' +
				' data-page="' + ( data.current_page + 1 ) + '">' +
				escapeHTML( i18n.next || __( 'Next', 'edmm' ) ) +
				'</button>';
		}

		pagination += '</div></nav>';
	}

	element.innerHTML = pagination;

	// Attach click handlers to all pagination buttons in this instance.
	element.querySelectorAll( 'button' ).forEach( function( button ) {
		button.addEventListener( 'click', function( e ) {
			e.preventDefault();
			goToPage( parseInt( button.getAttribute( 'data-page' ), 10 ) );
		} );
	} );
}
