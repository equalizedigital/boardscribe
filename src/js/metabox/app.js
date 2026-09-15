import { Fragment, useEffect, useState } from '@wordpress/element';
import { MetaField } from './field-control';

/**
 * Reads a field's starting value out of the post's current meta, applying
 * a type-appropriate default when the meta key has never been saved.
 *
 * @param {Object} field         Field descriptor.
 * @param {Object} initialValues Post meta values keyed by meta key.
 * @return {*} The starting value.
 */
function startingValue( field, initialValues ) {
	const raw = initialValues[ field.key ];

	if ( 'checkbox' === field.type ) {
		return '1' === raw;
	}

	if ( 'resource_list' === field.type ) {
		try {
			const parsed = JSON.parse( raw || '[]' );
			return Array.isArray( parsed ) ? parsed : [];
		} catch ( e ) {
			return [];
		}
	}

	return raw ?? '';
}

/**
 * The Meeting Details meta box's React app. Renders one row per field from
 * MetaBoxFieldRegistry::js_schema() (core fields + anything a plugin added
 * via edbs_meeting_meta_fields), each a real form control with the field's
 * meta key as its `name` - the native post edit form saves them exactly
 * like the PHP-rendered fields it replaces, so MetaBox::save_meta() and
 * the edbs_save_meeting_meta action are unaffected. A field carrying a
 * `group` gets a heading row inserted before it whenever that `group`
 * differs from the field immediately before it (in final, already
 * insert_after-resolved order) - ungrouped fields (`group` unset/null)
 * render with no heading, so a plugin only needs to opt fields into a
 * named section when it wants one; the meta box's own native title
 * already covers everything else as a single implicit first group.
 *
 * @param {Object} props               Component props.
 * @param {Array}  props.fields        Field descriptors from MetaBoxFieldRegistry::js_schema().
 * @param {Object} props.initialValues Post meta values keyed by meta key.
 * @return {JSX.Element} The app.
 */
export function MetaBoxApp( { fields, initialValues } ) {
	const [ values, setValues ] = useState( () => {
		const initial = {};
		fields.forEach( ( field ) => {
			initial[ field.key ] = startingValue( field, initialValues );
			// A 'resource' field's value stays a plain URL string (see
			// resource-field.js), but which Add/Replace-modal source
			// produced it is tracked in a sibling `{key}_source` meta -
			// stored here under a synthetic key of the same name rather
			// than in the field's own schema entry, since it isn't a
			// field the registry renders a row for.
			if ( 'resource' === field.type ) {
				initial[ field.key + '_source' ] = initialValues[ field.key + '_source' ] || '';
			}
		} );
		return initial;
	} );

	// A field's `initFn` (used with type "html") names a global function
	// that hydrates its server-rendered markup - e.g. wiring up an
	// existing AJAX-backed combobox widget. Called once, after every
	// field's markup already exists in the DOM (children commit before a
	// parent's own effect runs), deduped by name since more than one
	// field can share an initializer that scans for all of its own
	// instances itself (e.g. two widgets of the same kind on one page).
	useEffect( () => {
		const initFnNames = [ ...new Set( fields.map( ( field ) => field.initFn ).filter( Boolean ) ) ];
		initFnNames.forEach( ( name ) => {
			if ( 'function' === typeof window[ name ] ) {
				window[ name ]();
			}
		} );
		// Runs once, after the initial mount only - fields don't change after mount.
	}, [] );

	// `attached` fields (e.g. Recording's caption tracks) never get their
	// own row - a 'resource' field surfaces one inline via its own
	// `attachedFieldKey` instead (see the byKey/attachedField lookup
	// below, and AttachedRepeaterField) - so they're filtered out before
	// the group-heading sequence is computed, exactly as if they were
	// never in the registry's visible order at all.
	const visibleFields = fields.filter( ( field ) => ! field.attached );
	const byKey = {};
	fields.forEach( ( field ) => {
		byKey[ field.key ] = field;
	} );

	let previousGroup = null;

	return (
		<div className="edbs-metabox-app">
			{ visibleFields.map( ( field ) => {
				const showGroupHeading = field.group && field.group !== previousGroup;
				previousGroup = field.group;

				const attachedFieldDescriptor = field.attachedFieldKey ? byKey[ field.attachedFieldKey ] : null;
				const attachedField = attachedFieldDescriptor
					? {
						field: attachedFieldDescriptor,
						value: values[ attachedFieldDescriptor.key ] || [],
						onChange: ( nextValue ) =>
							setValues( ( current ) => ( { ...current, [ attachedFieldDescriptor.key ]: nextValue } ) ),
					}
					: null;

				return (
					<Fragment key={ field.key }>
						{ showGroupHeading && (
							<h3 className="edbs-metabox-app__group-heading">
								{ field.group }
							</h3>
						) }
						{ /*
						 * The `edbs-metabox-app__field--{type}` modifier lets
						 * metabox.css single out a field type's layout - currently
						 * only 'date', whose description sits under the field
						 * (right column) rather than under the label (left column,
						 * every other type's default) per the shared UX mockup.
						 */ }
						<div className={ `edbs-metabox-app__field edbs-metabox-app__field--${ field.type }` }>
							<MetaField
								field={ field }
								value={ values[ field.key ] }
								sourceValue={ values[ field.key + '_source' ] }
								allValues={ values }
								attachedField={ attachedField }
								onChange={ ( nextValue ) =>
									setValues( ( current ) => ( { ...current, [ field.key ]: nextValue } ) )
								}
								onSourceChange={ ( nextSource ) =>
									setValues( ( current ) => ( { ...current, [ field.key + '_source' ]: nextSource } ) )
								}
							/>
						</div>
					</Fragment>
				);
			} ) }
		</div>
	);
}
