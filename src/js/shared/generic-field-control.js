import {
	TextControl,
	TextareaControl,
	ToggleControl,
	SelectControl,
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';

/**
 * Renders the control matching a field's type - the generic renderer
 * shared by the block editor's InspectorControls and the admin
 * shortcode builder app. One renderer per FieldRegistry type (see
 * FieldRegistry.php). Callers own the value/onChange wiring (the block
 * keys fields by attributeKey, the builder by key), so this component
 * stays agnostic of where the value lives.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Field descriptor from the localized registry schema.
 * @param {*}        props.value    Current value.
 * @param {Function} props.onChange Called with the new value.
 * @return {JSX.Element} The control.
 */
export function GenericFieldControl( { field, value = '', onChange } ) {
	const help = field.description || undefined;

	switch ( field.type ) {
		case 'checkbox':
			return (
				<ToggleControl
					label={ field.label }
					help={ help }
					checked={ !! value }
					onChange={ onChange }
				/>
			);

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
				/>
			);

		case 'textarea':
			return (
				<TextareaControl
					label={ field.label }
					help={ help }
					value={ value }
					onChange={ onChange }
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
				/>
			);
	}
}
