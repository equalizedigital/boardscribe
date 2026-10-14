import { BaseControl, Button, CheckboxControl, TextareaControl, TextControl } from '@wordpress/components';
import { RawHTML } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { openMediaLibrary } from './media-library';
import { ResourceField } from './resource-field';
import { ResourceListField } from './resource-list-field';

const CONTROL_PROPS = {
	__next40pxDefaultSize: true,
	__nextHasNoMarginBottom: true,
};

/**
 * The Meeting Time row: a start and an end time input, both optional. Its
 * value is `{ start, end }` of 24-hour HH:MM strings (what an <input
 * type="time"> submits), and the inputs post as the two time meta keys that
 * MetaBox::save_time_range() reads.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Field descriptor; its key is the start time's meta key.
 * @param {Object}   props.value    Current `{ start, end }` value.
 * @param {Function} props.onChange Called with the new `{ start, end }` value.
 * @param {string}   props.id       Base id for the row's elements.
 * @return {Element} The row.
 */
function TimeRangeField( { field, value, onChange, id } ) {
	const start = ( value && value.start ) || '';
	const end = ( value && value.end ) || '';
	const endBeforeStart = '' !== start && '' !== end && end < start;
	const labelId = `${ id }__label`;
	const helpId = `${ id }__help`;
	const hintId = `${ id }__order`;
	const describedBy = [ field.description ? helpId : null, endBeforeStart ? hintId : null ].filter( Boolean ).join( ' ' ) || undefined;

	return (
		<div className="components-base-control">
			<div className="components-base-control__field">
				<span className="components-base-control__label" id={ labelId }>{ field.label }</span>
				<div className="edbs-metabox-app__time-range" role="group" aria-labelledby={ labelId }>
					<label htmlFor={ id }>{ __( 'Start', 'boardscribe' ) }</label>
					<input
						type="time"
						id={ id }
						name={ field.key }
						value={ start }
						aria-describedby={ describedBy }
						onChange={ ( event ) => onChange( { start: event.target.value, end } ) }
					/>
					<label htmlFor={ `${ id }-end` }>{ __( 'End', 'boardscribe' ) }</label>
					<input
						type="time"
						id={ `${ id }-end` }
						name="edbs_meeting_end_time"
						value={ end }
						aria-describedby={ describedBy }
						onChange={ ( event ) => onChange( { start, end: event.target.value } ) }
					/>
				</div>
			</div>
			{ field.description && <p className="components-base-control__help" id={ helpId }>{ field.description }</p> }
			{ endBeforeStart && (
				<p className="edbs-metabox-app__time-hint" id={ hintId } role="status">
					{ __( 'The end time is before the start time.', 'boardscribe' ) }
				</p>
			) }
		</div>
	);
}

/**
 * Renders one Meeting Details field. Built-in types are text/url/date/
 * time_range/checkbox; any other type defers to window.edbsMetaBoxControls[type] (a
 * plugin-registered custom control - see src/js/metabox/index.js), falling
 * back to a plain text input if nothing is registered for it.
 *
 * @param {Object}   props                      Component props.
 * @param {Object}   props.field                Field descriptor from MetaBoxFieldRegistry::js_schema().
 * @param {*}        props.value                Current value.
 * @param {Function} props.onChange             Called with the new value.
 * @param {string}   [props.sourceValue]        Only the 'resource' type reads it - the value's `{key}_source`
 *                                              sibling (see MetaBoxApp and resource-field.js).
 * @param {Function} [props.onSourceChange]     Only the 'resource' type reads it - pairs with sourceValue.
 * @param {string}   [props.editUrlValue]       Only the 'resource' type reads it - the value's `{key}_edit_url`
 *                                              sibling (see MetaBoxApp and resource-field.js).
 * @param {Function} [props.onEditUrlChange]    Only the 'resource' type reads it - pairs with editUrlValue.
 * @param {number}   [props.documentIdValue]    Only the 'resource' type reads it - the value's
 *                                              `{key}_document_id` sibling (see MetaBoxApp and resource-field.js).
 * @param {Function} [props.onDocumentIdChange] Only the 'resource' type reads it - pairs with documentIdValue.
 * @param {Object}   [props.allValues]          Every field's current value, keyed by field key - only the
 *                                              'resource' type reads it (for a {date}-templated card title).
 * @param {Object}   [props.attachedField]      Only the 'resource' type reads it - present when this field's
 *                                              `attachedFieldKey` points at an `attached` field, as
 *                                              `{ field, value, onChange }` for that attached field
 *                                              (MetaBoxApp resolves the lookup - see its own docblock).
 * @return {JSX.Element} The field's row content.
 */
export function MetaField( { field, value, onChange, sourceValue, onSourceChange, editUrlValue, onEditUrlChange, documentIdValue, onDocumentIdChange, allValues, attachedField } ) {
	const id = field.key;

	if ( 'resource' === field.type ) {
		return (
			<ResourceField
				field={ field }
				value={ value }
				onChange={ onChange }
				sourceValue={ sourceValue }
				onSourceChange={ onSourceChange }
				editUrlValue={ editUrlValue }
				onEditUrlChange={ onEditUrlChange }
				documentIdValue={ documentIdValue }
				onDocumentIdChange={ onDocumentIdChange }
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

	if ( 'time_range' === field.type ) {
		return <TimeRangeField field={ field } value={ value } onChange={ onChange } id={ id } />;
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
							onClick={ () => openMediaLibrary( { title: field.mediaTitle, types: field.mediaTypes || undefined, onSelect: onChange } ) }
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
