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
	const help = field.description || undefined;

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
