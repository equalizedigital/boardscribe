/**
 * Builds the [edbs_boardscribe] shortcode string from the current
 * builder values. Pure function so the generation rules are easy to
 * follow (and match what the pre-React inline builder script emitted):
 * a field is only included when its value differs from the field's
 * default, checkboxes only when checked (as `key="true"`).
 *
 * @param {Array}  fields Field descriptors from the localized registry schema.
 * @param {Object} values Current values keyed by field.key.
 * @return {string} The generated shortcode.
 */
export function buildShortcode( fields, values ) {
	let shortcode = '[edbs_boardscribe';

	fields.forEach( ( field ) => {
		const value = values[ field.key ];

		if ( 'checkbox' === field.type ) {
			if ( value ) {
				shortcode += ' ' + field.key + '="true"';
			}
			return;
		}

		const stringValue = String( value === undefined || value === null ? '' : value ).trim();
		const defaultValue = String( field.default === undefined || field.default === null ? '' : field.default );

		if ( stringValue && stringValue !== defaultValue ) {
			shortcode += ' ' + field.key + '="' + sanitizeValue( stringValue ) + '"';
		}
	} );

	return shortcode + ']';
}

/**
 * Sanitizes one attribute value for embedding in the shortcode string.
 *
 * WordPress shortcode parsing never decodes HTML entities in attribute
 * values, so entity-escaping quotes alone doesn't stop a literal "]" in
 * a free-text field from prematurely closing the generated shortcode -
 * it has to be stripped instead.
 *
 * @param {string} value Raw attribute value.
 * @return {string} Sanitized attribute value.
 */
function sanitizeValue( value ) {
	return value.replace( /[[\]]/g, '' ).replace( /"/g, '&quot;' );
}
