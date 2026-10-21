import { __ } from '@wordpress/i18n';
import { i18n } from '../config';

/**
 * Updates the off-screen aria-live region with pagination info.
 * Default implementation, used when a template doesn't override renderInfo.
 *
 * @param {Object}      data        - The REST response data.
 * @param {Object}      instanceCfg - The per-instance configuration.
 * @param {HTMLElement} element     - The aria-live info element.
 */
export function defaultRenderInfo( data, instanceCfg, element ) {
	if ( ! element ) {
		return;
	}

	// data.per_page is the endpoint's own effective per-page count (see
	// BoardScribeEndpoint::get_meetings()) - distinct from
	// instanceCfg.postsPerPage, which can be -1 ("show all") or exceed the
	// server's cap, either of which would otherwise produce a nonsensical
	// range (e.g. "Showing 1 to -1"). endEntry is derived from how many
	// rows actually came back rather than postsPerPage again, so a
	// short/last page reports its real range instead of overshooting past
	// totalEntries.
	const perPage = data.per_page || instanceCfg.postsPerPage || 20;
	const totalEntries = data.total_entries || 0;
	const meetingsCount = ( data.meetings || [] ).length;
	const startEntry = totalEntries === 0 ? 0 : ( ( data.current_page - 1 ) * perPage ) + 1;
	const endEntry = totalEntries === 0 ? 0 : startEntry + meetingsCount - 1;

	// translators: %1$s: first entry number, %2$s: last entry number, %3$s: total number of entries.
	const template = i18n.showingEntries || __( 'Showing %1$s to %2$s of %3$s entries', 'boardscribe' );
	element.textContent = template
		.replace( '%1$s', startEntry )
		.replace( '%2$s', endEntry )
		.replace( '%3$s', totalEntries );
}
