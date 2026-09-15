import { BaseControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { AttachedRepeaterField } from './attached-repeater-field';
import { ResourceCard, ResourceCardEmpty } from './resource-card';
import { ResourceModal, resourceModalTitle } from './resource-modal';
import { classifyResourceUrl, resolveResourceTitle } from './resource-utils';

/**
 * A "resource" field: a card (or, empty, a dashed add-prompt) fed by a
 * single URL-shaped value, with an Add/Replace modal offering one or more
 * sources (Media Library, an external URL, or a plugin-registered source -
 * see field.sources and window.edbsResourceSources in resource-modal.js).
 * The underlying value stays a plain URL string - the same shape the
 * pre-card 'url' type field stored - so no meta migration is needed; the
 * card's chip/meta line are derived from the URL itself at render time
 * (see resource-utils.js).
 *
 * @param {Object}   props                 Component props.
 * @param {Object}   props.field           Field descriptor from MetaBoxFieldRegistry::js_schema().
 * @param {string}   props.value           Current value (a URL, or '').
 * @param {Function} props.onChange        Called with the new value.
 * @param {Object}   props.allValues       Every field's current value, keyed by field key - used to resolve a
 *                                         {date}-templated title (see field.titleTemplate) against the meeting date.
 * @param {Object}   [props.attachedField] Present when field.attachedFieldKey points at an `attached`
 *                                         field (MetaBoxApp resolves the lookup) - `{ field, value, onChange }`
 *                                         for that attached field, rendered via AttachedRepeaterField inside
 *                                         this card's children slot. Only Recording's caption tracks use this
 *                                         today, but nothing here is caption-specific - see
 *                                         attached-repeater-field.js.
 * @return {JSX.Element} The field.
 */
export function ResourceField( { field, value, onChange, allValues, attachedField } ) {
	const [ isModalOpen, setIsModalOpen ] = useState( false );
	const hasValue = !! value;
	const sources = field.sources && field.sources.length ? field.sources : [ 'media_library', 'external_url' ];

	const handleSave = ( nextValue ) => {
		onChange( nextValue );
		setIsModalOpen( false );
	};

	const { chips, meta } = classifyResourceUrl( value );
	const title = resolveResourceTitle( field, allValues && allValues.edbs_meeting_date );

	return (
		<BaseControl id={ field.key } label={ field.label } help={ field.description || undefined } __nextHasNoMarginBottom>
			{ hasValue ? (
				<ResourceCard
					title={ title }
					chips={ chips }
					meta={ meta }
					actions={ [
						{ label: __( 'View', 'boardscribe' ), href: value },
						{ label: __( 'Replace', 'boardscribe' ), onClick: () => setIsModalOpen( true ) },
						{ label: __( 'Remove', 'boardscribe' ), danger: true, onClick: () => onChange( '' ) },
					] }
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

			<input type="hidden" name={ field.key } value={ value || '' } readOnly />

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
