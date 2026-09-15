import { BaseControl, Button, CheckboxControl, TextareaControl, TextControl } from '@wordpress/components';
import { RawHTML } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { ResourceField } from './resource-field';
import { ResourceListField } from './resource-list-field';

const CONTROL_PROPS = {
	__next40pxDefaultSize: true,
	__nextHasNoMarginBottom: true,
};

/**
 * Opens the wp.media() library modal and reports the selected
 * attachment's URL back to the field.
 *
 * @param {string}   title    Modal title.
 * @param {Function} onSelect Called with the selected attachment's URL.
 */
function openMediaLibrary( title, onSelect ) {
	if ( ! window.wp || ! window.wp.media ) {
		return;
	}

	const frame = window.wp.media( {
		title,
		button: { text: __( 'Use this file', 'boardscribe' ) },
		multiple: false,
	} );

	frame.on( 'select', () => {
		const attachment = frame.state().get( 'selection' ).first().toJSON();
		onSelect( attachment.url );
	} );

	frame.open();
}

/**
 * Renders one Meeting Details field. Built-in types are text/url/date/
 * checkbox; any other type defers to window.edbsMetaBoxControls[type] (a
 * plugin-registered custom control - see src/js/metabox/index.js), falling
 * back to a plain text input if nothing is registered for it.
 *
 * @param {Object}   props                  Component props.
 * @param {Object}   props.field            Field descriptor from MetaBoxFieldRegistry::js_schema().
 * @param {*}        props.value            Current value.
 * @param {Function} props.onChange         Called with the new value.
 * @param {string}   [props.sourceValue]    Only the 'resource' type reads it - the value's `{key}_source`
 *                                          sibling (see MetaBoxApp and resource-field.js).
 * @param {Function} [props.onSourceChange] Only the 'resource' type reads it - pairs with sourceValue.
 * @param {Object}   [props.allValues]      Every field's current value, keyed by field key - only the
 *                                          'resource' type reads it (for a {date}-templated card title).
 * @param {Object}   [props.attachedField]  Only the 'resource' type reads it - present when this field's
 *                                          `attachedFieldKey` points at an `attached` field, as
 *                                          `{ field, value, onChange }` for that attached field
 *                                          (MetaBoxApp resolves the lookup - see its own docblock).
 * @return {JSX.Element} The field's row content.
 */
export function MetaField( { field, value, onChange, sourceValue, onSourceChange, allValues, attachedField } ) {
	const id = field.key;

	if ( 'resource' === field.type ) {
		return (
			<ResourceField
				field={ field }
				value={ value }
				onChange={ onChange }
				sourceValue={ sourceValue }
				onSourceChange={ onSourceChange }
				allValues={ allValues }
				attachedField={ attachedField }
			/>
		);
	}

	if ( 'resource_list' === field.type ) {
		return <ResourceListField field={ field } value={ value } onChange={ onChange } />;
	}

	if ( 'checkbox' === field.type ) {
		// The row's left-column text is field.label (e.g. "Meeting Not
		// Held") like every other field type's row, but it's a plain
		// heading here, not a functional <label for> - field.description
		// becomes the checkbox's own clickable label on the right (e.g.
		// "This meeting was not held") instead, natively associated by
		// CheckboxControl itself. Reusing BaseControl's `label` prop for
		// the left side, as every other type does, would give it the
		// *same* id CheckboxControl needs for its own real <label for>,
		// producing two different elements sharing one id - so this
		// builds the row's markup directly against the same
		// .components-base-control(__field/__label) classes the CSS
		// already targets, rather than going through BaseControl.
		// Falls back to field.label as CheckboxControl's label if a
		// plugin registers a checkbox field with no description, so the
		// control is never unlabeled.
		return (
			<div className="components-base-control">
				<div className="components-base-control__field">
					<span className="components-base-control__label">{ field.label }</span>
					<CheckboxControl
						{ ...CONTROL_PROPS }
						id={ id }
						name={ field.key }
						label={ field.description || field.label }
						checked={ !! value }
						onChange={ onChange }
					/>
				</div>
			</div>
		);
	}

	if ( 'date' === field.type ) {
		return (
			<BaseControl id={ id } label={ field.label } help={ field.description || undefined } __nextHasNoMarginBottom>
				<input
					type="date"
					id={ id }
					name={ field.key }
					value={ value || '' }
					required={ !! field.required }
					aria-describedby={ field.description ? `${ id }__help` : undefined }
					onChange={ ( event ) => onChange( event.target.value ) }
				/>
			</BaseControl>
		);
	}

	if ( 'url' === field.type ) {
		return (
			<BaseControl id={ id } label={ field.label } help={ field.description || undefined } __nextHasNoMarginBottom>
				<div className="edbs-metabox-app__url-row">
					<input
						type="url"
						id={ id }
						name={ field.key }
						className="large-text"
						placeholder={ field.placeholder || '' }
						value={ value || '' }
						aria-describedby={ field.description ? `${ id }__help` : undefined }
						onChange={ ( event ) => onChange( event.target.value ) }
					/>
					{ field.mediaPicker && (
						<Button
							variant="secondary"
							onClick={ () => openMediaLibrary( field.mediaTitle, onChange ) }
						>
							{ __( 'Media Library', 'boardscribe' ) }
						</Button>
					) }
				</div>
			</BaseControl>
		);
	}

	if ( 'textarea' === field.type ) {
		return (
			<TextareaControl
				{ ...CONTROL_PROPS }
				id={ id }
				name={ field.key }
				label={ field.label }
				help={ field.description || undefined }
				placeholder={ field.placeholder || '' }
				value={ value || '' }
				onChange={ onChange }
			/>
		);
	}

	if ( 'html' === field.type ) {
		// The field's content is server-rendered markup - see
		// MetaBoxFieldRegistry's render_callback and MetaBox::render_meta_box(),
		// which resolves it into initialValues[field.key] per-post. Used for a
		// self-contained widget (its own inputs, own state) a plugin already
		// has working PHP + plain-JS for and doesn't want to reimplement as a
		// React component - see the edbs_meeting_meta_fields filter's docblock.
		return (
			<BaseControl id={ id } label={ field.label } help={ field.description || undefined } __nextHasNoMarginBottom>
				<RawHTML>{ value || '' }</RawHTML>
			</BaseControl>
		);
	}

	const CustomControl = window.edbsMetaBoxControls && window.edbsMetaBoxControls[ field.type ];
	if ( CustomControl ) {
		return <CustomControl field={ field } value={ value } onChange={ onChange } id={ id } />;
	}

	return (
		<TextControl
			{ ...CONTROL_PROPS }
			id={ id }
			name={ field.key }
			label={ field.label }
			help={ field.description || undefined }
			value={ value || '' }
			onChange={ onChange }
		/>
	);
}
