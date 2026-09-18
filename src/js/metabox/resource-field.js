import { BaseControl } from '@wordpress/components';
import { speak } from '@wordpress/a11y';
import { useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { AttachedRepeaterField } from './attached-repeater-field';
import { ResourceCard, ResourceCardEmpty } from './resource-card';
import { ResourceModal, resourceModalTitle } from './resource-modal';
import { focusFirstActionable, HiddenFields, resolveFieldSources, resolveResourceDisplay, resolveResourceTitle } from './resource-utils';

/**
 * A "resource" field: a card (or, empty, a dashed add-prompt) fed by a
 * single URL-shaped value, with an Add/Replace modal offering one or more
 * sources (Media Library, an external URL, or a plugin-registered source -
 * see field.sources and window.edbsResourceSources in resource-modal.js).
 * The underlying value stays a plain URL string - the same shape the
 * pre-card 'url' type field stored - so no meta migration is needed; the
 * card's chip/meta line are derived from the URL plus which source
 * produced it (see resource-utils.js's resolveResourceDisplay()).
 *
 * @param {Object}   props                   Component props.
 * @param {Object}   props.field             Field descriptor from MetaBoxFieldRegistry::js_schema().
 * @param {string}   props.value             Current value (a URL, or '').
 * @param {Function} props.onChange          Called with the new value.
 * @param {string}   [props.sourceValue]     The value's `{key}_source` sibling meta - which Add/Replace-modal
 *                                           source produced the current value ('media_library'/'external_url',
 *                                           or a plugin-registered id) - see MetaBox::save_field() in the PHP
 *                                           repo and resolveResourceDisplay() in resource-utils.js. Empty for
 *                                           legacy data saved before this existed.
 * @param {Function} [props.onSourceChange]  Called with the new sourceValue whenever value changes via the
 *                                           modal or Remove - kept in lockstep with onChange so the chip stays
 *                                           accurate instead of stale.
 * @param {string}   [props.editUrlValue]    The value's `{key}_edit_url` sibling meta - the wp-admin edit
 *                                           screen for the value's underlying post, when its source has one
 *                                           (e.g. Pro's linked-document source) - empty for a plain Media
 *                                           Library file or external link, in which case no "Edit" action is
 *                                           shown ("Replace" already covers changing a plain URL/file).
 * @param {Function} [props.onEditUrlChange] Called with the new editUrlValue whenever value changes via the
 *                                           modal or Remove - kept in lockstep with onChange the same way
 *                                           onSourceChange is.
 * @param {Object}   props.allValues         Every field's current value, keyed by field key - used to resolve a
 *                                           {date}-templated title (see field.titleTemplate) against the meeting date.
 * @param {Object}   [props.attachedField]   Present when field.attachedFieldKey points at an `attached`
 *                                           field (MetaBoxApp resolves the lookup) - `{ field, value, onChange }`
 *                                           for that attached field, rendered via AttachedRepeaterField inside
 *                                           this card's children slot - see attached-repeater-field.js (no
 *                                           current consumer as of PRO-1349, kept generic for a future one).
 * @return {JSX.Element} The field.
 */
export function ResourceField( { field, value, onChange, sourceValue, onSourceChange, editUrlValue, onEditUrlChange, allValues, attachedField } ) {
	const [ isModalOpen, setIsModalOpen ] = useState( false );
	const containerRef = useRef( null );
	const hasValue = !! value;
	const sources = resolveFieldSources( field, [ 'media_library', 'external_url' ] );

	const handleSave = ( nextValue, extra ) => {
		onChange( nextValue );
		if ( onSourceChange ) {
			onSourceChange( ( extra && extra.source ) || '' );
		}
		if ( onEditUrlChange ) {
			onEditUrlChange( ( extra && extra.editUrl ) || '' );
		}
		setIsModalOpen( false );
		if ( ! hasValue ) {
			// Add swaps the empty prompt's own "Add" button for the card's
			// action row - the button that was just clicked no longer
			// exists, so nothing carries focus forward on its own. Move it
			// to whatever's now first in the card once that re-render
			// lands. Replace doesn't need this: its own "Replace" button
			// stays mounted, so WordPress's Modal already restores focus
			// there when it unmounts - calling this unconditionally would
			// hijack that focus away to the card's first action (View)
			// instead. Same reasoning as resource-list-field.js's isAdding
			// guard around its own focusFirstActionable() call.
			focusFirstActionable( containerRef );
		}
		speak( hasValue
			? sprintf( /* translators: %s: field label, e.g. "Agenda". */ __( '%s replaced.', 'boardscribe' ), field.label )
			: sprintf( /* translators: %s: field label, e.g. "Agenda". */ __( '%s added.', 'boardscribe' ), field.label ),
		);
	};

	const handleRemove = () => {
		onChange( '' );
		if ( onSourceChange ) {
			onSourceChange( '' );
		}
		if ( onEditUrlChange ) {
			onEditUrlChange( '' );
		}
		// The attached field's own rows (e.g. Recording's caption tracks)
		// belong to *this* resource, not to whatever gets added next -
		// MetaBoxApp keeps its value around independently of this card's
		// own hasValue/hidden state, so without this, adding a new
		// resource before saving would silently resurrect and submit the
		// removed resource's old attached rows.
		if ( attachedField ) {
			attachedField.onChange( [] );
		}
		focusFirstActionable( containerRef );
		speak( sprintf( /* translators: %s: field label, e.g. "Agenda". */ __( '%s removed.', 'boardscribe' ), field.label ) );
	};

	const { chips, meta } = resolveResourceDisplay( value, sourceValue );
	const title = resolveResourceTitle( field, allValues && allValues.edbs_meeting_date );

	const actions = [
		{ label: __( 'View', 'boardscribe' ), ariaLabel: sprintf( /* translators: %s: field label, e.g. "Agenda". */ __( 'View %s', 'boardscribe' ), field.label ), href: value },
	];
	if ( editUrlValue ) {
		actions.push( {
			label: __( 'Edit', 'boardscribe' ),
			ariaLabel: sprintf( /* translators: %s: field label, e.g. "Agenda". */ __( 'Edit %s', 'boardscribe' ), field.label ),
			href: editUrlValue,
		} );
	}
	actions.push(
		{ label: __( 'Replace', 'boardscribe' ), ariaLabel: sprintf( /* translators: %s: field label, e.g. "Agenda". */ __( 'Replace %s', 'boardscribe' ), field.label ), onClick: () => setIsModalOpen( true ) },
		{ label: __( 'Remove', 'boardscribe' ), ariaLabel: sprintf( /* translators: %s: field label, e.g. "Agenda". */ __( 'Remove %s', 'boardscribe' ), field.label ), danger: true, onClick: handleRemove },
	);

	return (
		<BaseControl id={ field.key } label={ field.label } help={ field.description || undefined } __nextHasNoMarginBottom>
			<div ref={ containerRef }>
				{ hasValue ? (
					<ResourceCard
						title={ title }
						chips={ chips }
						meta={ meta }
						actions={ actions }
					>
						{ attachedField && (
							<AttachedRepeaterField
								field={ attachedField.field }
								value={ attachedField.value }
								onChange={ attachedField.onChange }
							/>
						) }
					</ResourceCard>
				) : (
					<ResourceCardEmpty
						message={ sprintf( /* translators: %s: field label, e.g. "minutes". */ __( 'No %s attached.', 'boardscribe' ), field.label.toLowerCase() ) }
						actionLabel={ sprintf( /* translators: %s: field label, e.g. "Minutes". */ __( 'Add %s', 'boardscribe' ), field.label ) }
						onAdd={ () => setIsModalOpen( true ) }
					/>
				) }
			</div>

			<HiddenFields fields={ [
				{ name: field.key, value },
				{ name: `${ field.key }_source`, value: sourceValue },
				{ name: `${ field.key }_edit_url`, value: editUrlValue },
			] } />

			{ isModalOpen && (
				<ResourceModal
					title={ resourceModalTitle( field.label, hasValue ) }
					sources={ sources }
					mediaTitle={ field.mediaTitle }
					fieldLabel={ field.label }
					currentValue={ value }
					fieldKey={ field.key }
					onSave={ handleSave }
					onClose={ () => setIsModalOpen( false ) }
				/>
			) }
		</BaseControl>
	);
}
