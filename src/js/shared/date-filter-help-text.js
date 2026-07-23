import { __ } from '@wordpress/i18n';

/**
 * Overrides a field's description with a conditional note about how
 * included_years/start_date/end_date interact - start_date/end_date
 * silently take priority over included_years whenever either is set
 * (see BoardScribeEndpoint::get_meetings()), which isn't obvious from
 * the three fields' own static descriptions alone once more than one
 * has a value. Every other field is returned unchanged.
 *
 * Shared by the shortcode builder app and the block editor sidebar,
 * which key their values differently (snake_case shortcode attribute
 * vs. camelCase block attribute) - callers pass the three current
 * values directly rather than this function assuming a naming
 * convention.
 *
 * @param {Object} field         The field descriptor being rendered.
 * @param {string} includedYears Current included_years value.
 * @param {string} startDate     Current start_date value.
 * @param {string} endDate       Current end_date value.
 * @return {Object} The field descriptor, with `description` overridden if applicable.
 */
export function withDateFilterHelpText( field, includedYears, startDate, endDate ) {
	const hasRange = Boolean( startDate || endDate );
	const hasYears = Boolean( includedYears );

	if ( 'included_years' === field.key && hasRange ) {
		return {
			...field,
			description: __( 'Ignored: a start/end date is set below, which takes priority.', 'boardscribe' ),
		};
	}

	if ( 'start_date' === field.key && hasYears && ! hasRange ) {
		return {
			...field,
			description: __( 'Only show meetings on or after this date. Overrides Included Years above as soon as either date is set.', 'boardscribe' ),
		};
	}

	if ( 'end_date' === field.key && hasYears && ! hasRange ) {
		return {
			...field,
			description: __( 'Only show meetings on or before this date. Overrides Included Years above as soon as either date is set.', 'boardscribe' ),
		};
	}

	return field;
}
