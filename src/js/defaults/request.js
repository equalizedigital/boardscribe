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
 * Query params are driven entirely by edbsConfig.restArgMap (see
 * FieldRegistry::rest_arg_map()) rather than a hardcoded field list, so
 * a field any plugin (free or Pro, e.g. Pro's `category`) marks rest_arg
 * forwards here with no edit to this file. Falls back to the free
 * plugin's own core rest_arg fields when restArgMap wasn't localized at
 * all (e.g. a unit test stubbing window.edbsConfig) - a Pro-only field
 * like `category` is simply absent from instanceCfg in that case too.
 *
 * @param {Object} instanceCfg - The per-instance configuration.
 * @param {number} page        - The 1-based page number to request.
 * @return {string} The URL to fetch.
 */
export function defaultBuildRequestUrl( instanceCfg, page ) {
	const url = new URL( apiBaseUrl, window.location.origin );
	const restArgMap = ( window.edbsConfig && window.edbsConfig.restArgMap ) || [
		{ key: 'included_years', configKey: 'includedYears' },
		{ key: 'start_date', configKey: 'startDate' },
		{ key: 'end_date', configKey: 'endDate' },
		{ key: 'held_date_format', configKey: 'heldDateFormat' },
		{ key: 'not_held_date_format', configKey: 'notHeldDateFormat' },
		{ key: 'posts_per_page', configKey: 'postsPerPage' },
		{ key: 'agenda_link_label', configKey: 'agendaLinkLabel' },
		{ key: 'minutes_link_label', configKey: 'minutesLinkLabel' },
	];

	restArgMap.forEach( function( field ) {
		url.searchParams.set( field.key, instanceCfg[ field.configKey ] ?? '' );
	} );
	url.searchParams.set( 'page', page );
	return url.toString();
}
