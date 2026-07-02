/**
 * Escapes a string for safe use inside an HTML attribute value.
 *
 * @param {string} value - The raw value to escape.
 * @return {string} The escaped value.
 */
export function escapeAttr( value ) {
	return String( value )
		.replace( /&/g, '&amp;' )
		.replace( /"/g, '&quot;' )
		.replace( /'/g, '&#039;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' );
}
