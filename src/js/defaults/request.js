import { apiBaseUrl } from '../config';

/**
 * Builds the default REST request URL for a page of meetings.
 * Used when a template doesn't override buildRequestUrl or request.
 *
 * apiBaseUrl (from rest_url()) already contains its own query string
 * (`?rest_route=/edbs/v1/boardscribe/`) on any site using plain
 * permalinks, rather than the path-based `/wp-json/...` form pretty
 * permalinks produce - appending `?included_years=...` unconditionally
 * would nest a second `?` inside the first param's value instead of
 * starting a real query string, so the request 404s. Building through
 * URL/URLSearchParams merges onto whatever's already there instead of
 * assuming either form.
 *
 * @param {Object} instanceCfg - The per-instance configuration.
 * @param {number} page        - The 1-based page number to request.
 * @return {string} The URL to fetch.
 */
export function defaultBuildRequestUrl( instanceCfg, page ) {
	const url = new URL( apiBaseUrl, window.location.origin );
	url.searchParams.set( 'included_years', instanceCfg.includedYears || '' );
	url.searchParams.set( 'held_date_format', instanceCfg.heldDateFormat || 'l, F j, Y' );
	url.searchParams.set( 'not_held_date_format', instanceCfg.notHeldDateFormat || 'F Y' );
	url.searchParams.set( 'posts_per_page', instanceCfg.postsPerPage || 20 );
	url.searchParams.set( 'agenda_link_label', instanceCfg.agendaLinkLabel || '' );
	url.searchParams.set( 'minutes_link_label', instanceCfg.minutesLinkLabel || '' );
	url.searchParams.set( 'category', instanceCfg.category || '' );
	url.searchParams.set( 'page', page );
	return url.toString();
}
