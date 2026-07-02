import { apiBaseUrl } from '../config';

/**
 * Builds the default REST request URL for a page of meetings.
 * Used when a template doesn't override buildRequestUrl or request.
 *
 * @param {Object} instanceCfg - The per-instance configuration.
 * @param {number} page        - The 1-based page number to request.
 * @return {string} The URL to fetch.
 */
export function defaultBuildRequestUrl( instanceCfg, page ) {
	return apiBaseUrl +
		'?included_years=' + encodeURIComponent( instanceCfg.includedYears || '' ) +
		'&held_date_format=' + encodeURIComponent( instanceCfg.heldDateFormat || 'Y/m/d' ) +
		'&not_held_date_format=' + encodeURIComponent( instanceCfg.notHeldDateFormat || 'Y/m' ) +
		'&posts_per_page=' + encodeURIComponent( instanceCfg.postsPerPage || 20 ) +
		'&agenda_link_label=' + encodeURIComponent( instanceCfg.agendaLinkLabel || '' ) +
		'&minutes_link_label=' + encodeURIComponent( instanceCfg.minutesLinkLabel || '' ) +
		'&category=' + encodeURIComponent( instanceCfg.category || '' ) +
		'&page=' + encodeURIComponent( page );
}
