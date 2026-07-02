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

	const postsPerPage = instanceCfg.postsPerPage || 20;
	const totalEntries = data.total_entries || 0;
	const startEntry = totalEntries === 0 ? 0 : ( ( data.current_page - 1 ) * postsPerPage ) + 1;
	const endEntry = Math.min( data.current_page * postsPerPage, totalEntries );

	const template = i18n.showingEntries || 'Showing %1$s to %2$s of %3$s entries';
	element.textContent = template
		.replace( '%1$s', startEntry )
		.replace( '%2$s', endEntry )
		.replace( '%3$s', totalEntries );
}
