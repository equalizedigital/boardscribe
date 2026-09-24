import {
	TextControl,
	TextareaControl,
	ToggleControl,
	SelectControl,
	FormTokenField,
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Renders the control matching a field's type - the generic renderer
 * shared by the block editor's InspectorControls and the admin
 * shortcode builder app. One renderer per FieldRegistry type (see
 * FieldRegistry.php). Callers own the value/onChange wiring (the block
 * keys fields by attributeKey, the builder by key), so this component
 * stays agnostic of where the value lives.
 *
 * @param {Object}   props              Component props.
 * @param {Object}   props.field        Field descriptor from the localized registry schema.
 * @param {*}        props.value        Current value.
 * @param {Function} props.onChange     Called with the new value.
 * @param {Object}   props.controlProps Extra props spread onto the rendered control - the
 *                                      builder passes the new-sizing/margin opt-ins here
 *                                      while the block keeps the editor defaults.
 * @return {JSX.Element} The control.
 */
export function GenericFieldControl( { field, value = '', onChange, controlProps = {} } ) {
	// A select can describe each choice; the selected one's text wins over the
	// field-level description (PRO-1416).
	const choiceHelp = ( field.choiceDescriptions || {} )[ value ];
	const help = choiceHelp || field.description || undefined;

	switch ( field.type ) {
		case 'checkbox': {
			// ToggleControl has no size variants - only the margin opt-in
			// applies to it.
			const { __next40pxDefaultSize, ...toggleProps } = controlProps;
			return (
				<ToggleControl
					label={ field.label }
					help={ help }
					checked={ !! value }
					onChange={ onChange }
					{ ...toggleProps }
				/>
			);
		}

		case 'select': {
			const choices = field.choices || {};
			const options = Object.keys( choices ).map( ( choiceValue ) => ( {
				value: choiceValue,
				label: choices[ choiceValue ],
			} ) );
			return (
				<SelectControl
					label={ field.label }
					help={ help }
					value={ value }
					options={ options }
					onChange={ onChange }
					{ ...controlProps }
				/>
			);
		}

		case 'multiselect': {
			// Stored/shortcode-attribute value is a comma-separated slug
			// string (see FieldRegistry::sanitize_multiselect()) - FormTokenField
			// itself only knows plain display strings, so choices' labels are
			// what it shows/accepts, mapped back to slugs on change. A typed
			// token that doesn't match any known label (a typo, or a term
			// that no longer exists) is silently dropped rather than saved as
			// free text - this field only ever means "filter by these real
			// terms", not "create a new one".
			//
			// WordPress allows two terms - even in a flat, non-hierarchical
			// taxonomy - to share the same display name with different
			// slugs (wp_insert_term() only enforces a unique slug, not a
			// unique name). A plain label -> slug map would then collide:
			// whichever term was processed last would own that label, and
			// picking the token would always save that one slug regardless
			// of which same-named term the user meant. Disambiguated here by
			// suffixing " (slug)" onto a label only when it's not unique,
			// so an ordinary field with no naming collisions renders
			// exactly as before.
			const choices = field.choices || {};
			const labelCounts = {};
			Object.values( choices ).forEach( ( label ) => {
				labelCounts[ label ] = ( labelCounts[ label ] || 0 ) + 1;
			} );
			const slugToToken = {};
			const tokenToSlug = {};
			Object.keys( choices ).forEach( ( slug ) => {
				const label = choices[ slug ];
				const token = labelCounts[ label ] > 1 ? `${ label } (${ slug })` : label;
				slugToToken[ slug ] = token;
				tokenToSlug[ token ] = slug;
			} );
			const selectedSlugs = ( value || '' ).split( ',' ).filter( Boolean );
			const selectedTokens = selectedSlugs.map( ( slug ) => slugToToken[ slug ] || slug );
			const suggestions = Object.values( slugToToken );
			const noChoicesHelp = __( 'No terms exist yet to filter by.', 'boardscribe' );

			return (
				<FormTokenField
					label={ field.label }
					help={ suggestions.length ? help : noChoicesHelp }
					value={ selectedTokens }
					suggestions={ suggestions }
					onChange={ ( tokens ) => {
						const slugs = tokens
							.map( ( token ) => tokenToSlug[ token ] )
							.filter( Boolean );
						onChange( slugs.join( ',' ) );
					} }
					{ ...controlProps }
				/>
			);
		}

		case 'number':
		case 'number_with_all':
			return (
				<NumberControl
					label={ field.label }
					help={ help }
					value={ value }
					onChange={ ( val ) => onChange( parseInt( val, 10 ) || 0 ) }
					min={ 'number_with_all' === field.type ? -1 : 1 }
					{ ...controlProps }
				/>
			);

		case 'textarea':
			return (
				<TextareaControl
					label={ field.label }
					help={ help }
					value={ value }
					onChange={ onChange }
					{ ...controlProps }
				/>
			);

		case 'date':
			return (
				<TextControl
					type="date"
					label={ field.label }
					help={ help }
					value={ value }
					onChange={ onChange }
					{ ...controlProps }
				/>
			);

		case 'text':
		default:
			return (
				<TextControl
					label={ field.label }
					help={ help }
					placeholder={ field.placeholder || '' }
					value={ value }
					onChange={ onChange }
					{ ...controlProps }
				/>
			);
	}
}
