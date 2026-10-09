/**
 * Whether a field's picker should show, per its visibleWhen descriptor
 * (field key => allowed values; every condition must hold).
 *
 * @param {Object}   field    Field descriptor from the localized registry schema.
 * @param {Function} getValue Returns the current value for a field key.
 * @return {boolean} True when the field has no conditions or all are met.
 */
export function isFieldVisible( field, getValue ) {
	const conditions = field.visibleWhen;
	if ( ! conditions ) {
		return true;
	}

	return Object.keys( conditions ).every( ( key ) => {
		const value = getValue( key );
		return conditions[ key ].includes( String( value === undefined || value === null ? '' : value ) );
	} );
}
