import { escapeAttribute, escapeHTML } from '@wordpress/escape-html';
import { __ } from '@wordpress/i18n';
import { i18n } from '../config';

/**
 * Builds the default year-switcher controls (a year <select> plus
 * prev/next buttons) and wires them to goToYear(). Used when a
 * template doesn't override renderYearSwitcher. Only invoked by
 * instance.js when instanceCfg.yearView is true.
 *
 * @param {Object}      data        - The REST response data. data.available_years
 *                                  is the full, unfiltered list of years with meetings,
 *                                  newest first.
 * @param {Object}      instanceCfg - The per-instance configuration.
 * @param {HTMLElement} element     - The year-switcher container element.
 * @param {Function}    goToYear    - Navigates to the given year.
 */
export function defaultRenderYearSwitcher( data, instanceCfg, element, goToYear ) {
	const years = Array.isArray( data.available_years ) ? data.available_years : [];

	if ( ! years.length ) {
		element.innerHTML = '';
		return;
	}

	const currentYear = instanceCfg.currentYear || years[ 0 ];
	const currentIndex = years.indexOf( currentYear );
	// years is newest-first, so "previous" (an earlier year) is the next
	// index up, and "next" (a later year) is the index below.
	const hasOlderYear = -1 !== currentIndex && currentIndex < years.length - 1;
	const hasNewerYear = currentIndex > 0;

	let html = '<div class="edbs-year-switcher-buttons">';

	html += '<button type="button" class="edbs-year-switcher-button prev"' +
		' aria-label="' + escapeAttribute( i18n.previousYear || __( 'Previous Year', 'boardscribe' ) ) + '"' +
		' data-year="' + escapeAttribute( hasOlderYear ? String( years[ currentIndex + 1 ] ) : '' ) + '"' +
		( hasOlderYear ? '' : ' disabled' ) + '>' +
		escapeHTML( i18n.previous || __( 'Previous', 'boardscribe' ) ) +
		'</button>';

	html += '<label class="edbs-year-switcher-label" for="' + escapeAttribute( element.id + '-select' ) + '">' +
		'<span class="screen-reader-text">' + escapeHTML( i18n.selectYear || __( 'Select year', 'boardscribe' ) ) + '</span>' +
		'</label>';
	html += '<select class="edbs-year-switcher-select" id="' + escapeAttribute( element.id + '-select' ) + '">';
	years.forEach( function( year ) {
		html += '<option value="' + escapeAttribute( String( year ) ) + '"' + ( year === currentYear ? ' selected' : '' ) + '>' +
			escapeHTML( String( year ) ) +
			'</option>';
	} );
	html += '</select>';

	html += '<button type="button" class="edbs-year-switcher-button next"' +
		' aria-label="' + escapeAttribute( i18n.nextYear || __( 'Next Year', 'boardscribe' ) ) + '"' +
		' data-year="' + escapeAttribute( hasNewerYear ? String( years[ currentIndex - 1 ] ) : '' ) + '"' +
		( hasNewerYear ? '' : ' disabled' ) + '>' +
		escapeHTML( i18n.next || __( 'Next', 'boardscribe' ) ) +
		'</button>';

	html += '</div>';

	element.innerHTML = html;

	element.querySelectorAll( 'button:not([disabled])' ).forEach( function( button ) {
		button.addEventListener( 'click', function( e ) {
			e.preventDefault();
			goToYear( parseInt( button.getAttribute( 'data-year' ), 10 ) );
		} );
	} );

	const select = element.querySelector( 'select' );
	if ( select ) {
		select.addEventListener( 'change', function() {
			goToYear( parseInt( select.value, 10 ) );
		} );
	}
}
