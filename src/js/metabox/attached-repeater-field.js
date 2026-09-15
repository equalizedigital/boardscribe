import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { EditableTitle } from './resource-list-field';
import { ResourceModal } from './resource-modal';
import { resolveResourceDisplay } from './resource-utils';

/**
 * An `attached` field's inline repeater - a 'resource' field's own card
 * surfaces this via its `attachedFieldKey` (see resource-field.js and
 * MetaBoxFieldRegistry's `$attached`/`$attached_field_key` docblock for how
 * a field opts into the relationship). Pro's Recording field is the first
 * (and, so far, only) consumer, for its VTT/SRT caption tracks - but
 * nothing here is caption-specific: every string a plugin might want to
 * change (this section's heading, the item noun, a new row's default
 * label, the empty-label fallback, the inline edit field's hidden
 * accessible label, the Add modal's title/sources) comes from the
 * attached field's own descriptor, with generic fallbacks below. A future
 * attached list for something else entirely (exhibits, related links,
 * whatever) configures its own field descriptor and gets its own wording
 * without touching this file - see each prop's doc line for which
 * registry property drives it.
 *
 * Deliberately lighter-weight than ResourceListField (Supporting
 * Documents' full repeater, the free plugin's own top-level `resource_list`
 * type): one line per row ("{label} · {filename}" + Remove), no
 * drag-to-reorder or View/Replace actions - order doesn't matter for an
 * attached list the way it does for Supporting Documents, and "viewing" a
 * caption file (or whatever a future consumer attaches) isn't generally as
 * meaningful an action as it is for a document.
 *
 * Stores the same `{ label, url, source }` shape ResourceListField uses, as
 * hidden `name="{key}[i][label]"`/`"[url]"`/`"[source]"` inputs per row -
 * whichever plugin owns the attached field's `saved_externally` save
 * handling (e.g. Pro's ProMetaFields::save_label_url_pairs_field()) reads
 * and saves it the same way.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    The attached field's own descriptor:
 *                                  - `label` is this section's heading (e.g. "Caption tracks").
 *                                  - `description` is optional help text below the Add button.
 *                                  - `itemNoun` feeds the "+ Add {noun}" button and Add modal
 *                                  title, default "Item".
 *                                  - `defaultItemLabel` seeds a newly added row's label (e.g.
 *                                  "English" for captions) - defaults to '' (shows as
 *                                  `emptyItemLabel` until edited) when unset.
 *                                  - `emptyItemLabel` is shown in place of an unlabeled row's
 *                                  label - defaults to "Untitled {noun}".
 *                                  - `itemFieldLabel` is the inline edit field's own hidden
 *                                  accessible label - defaults to `itemNoun`.
 *                                  - `sources` defaults to `['media_library']` only, unlike a
 *                                  top-level resource field's default of both built-ins - an
 *                                  attached row is virtually always an upload, not an
 *                                  external link.
 *                                  - `mediaTitle` is the Media Library frame's title - defaults
 *                                  to "Select a {noun} File".
 * @param {Array}    props.value    Current rows, `[{ label, url }]`.
 * @param {Function} props.onChange Called with the new rows array.
 * @return {JSX.Element} The field.
 */
export function AttachedRepeaterField( { field, value, onChange } ) {
	const rows = Array.isArray( value ) ? value : [];
	const [ isModalOpen, setIsModalOpen ] = useState( false );
	const itemNoun = field.itemNoun || __( 'Item', 'boardscribe' );
	const emptyItemLabel = field.emptyItemLabel || sprintf( /* translators: %s: item noun, e.g. "Caption Track". */ __( 'Untitled %s', 'boardscribe' ), itemNoun.toLowerCase() );
	const sources = field.sources && field.sources.length ? field.sources : [ 'media_library' ];

	const updateRow = ( index, patch ) => {
		onChange( rows.map( ( row, i ) => ( i === index ? { ...row, ...patch } : row ) ) );
	};

	const removeRow = ( index ) => {
		onChange( rows.filter( ( _, i ) => i !== index ) );
	};

	return (
		<div className="edbs-attached-repeater">
			<div className="edbs-attached-repeater__heading">{ field.label }</div>

			{ rows.map( ( row, index ) => {
				const { meta } = resolveResourceDisplay( row.url, row.source );
				return (
					<div className="edbs-attached-repeater__row" key={ index }>
						<span className="edbs-attached-repeater__label">
							<EditableTitle
								label={ row.label }
								emptyLabel={ emptyItemLabel }
								fieldLabel={ field.itemFieldLabel || itemNoun }
								onChange={ ( label ) => updateRow( index, { label } ) }
							/>
						</span>
						{ meta && (
							<>
								<span className="edbs-attached-repeater__separator" aria-hidden="true">·</span>
								<span className="edbs-attached-repeater__filename">{ meta }</span>
							</>
						) }
						<Button
							variant="link"
							className="edbs-resource-card__action edbs-resource-card__action--danger edbs-attached-repeater__remove"
							onClick={ () => removeRow( index ) }
						>
							{ __( 'Remove', 'boardscribe' ) }
						</Button>
						<input type="hidden" name={ `${ field.key }[${ index }][label]` } value={ row.label || '' } readOnly />
						<input type="hidden" name={ `${ field.key }[${ index }][url]` } value={ row.url || '' } readOnly />
						<input type="hidden" name={ `${ field.key }[${ index }][source]` } value={ row.source || '' } readOnly />
					</div>
				);
			} ) }

			<Button variant="secondary" onClick={ () => setIsModalOpen( true ) }>
				{ sprintf( /* translators: %s: item noun, e.g. "Caption Track". */ __( '+ Add %s', 'boardscribe' ), itemNoun ) }
			</Button>

			{ field.description && <p className="edbs-attached-repeater__help">{ field.description }</p> }

			{ isModalOpen && (
				<ResourceModal
					title={ sprintf( /* translators: %s: item noun, e.g. "Caption Track". */ __( 'Add %s', 'boardscribe' ), itemNoun ) }
					sources={ sources }
					mediaTitle={ field.mediaTitle || sprintf( /* translators: %s: item noun, e.g. "Caption Track". */ __( 'Select a %s File', 'boardscribe' ), itemNoun ) }
					fieldLabel={ itemNoun }
					currentValue=""
					onSave={ ( url, extra ) => {
						onChange( [ ...rows, { label: field.defaultItemLabel || '', url, source: ( extra && extra.source ) || '' } ] );
						setIsModalOpen( false );
					} }
					onClose={ () => setIsModalOpen( false ) }
				/>
			) }
		</div>
	);
}
