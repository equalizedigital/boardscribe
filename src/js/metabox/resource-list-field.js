import { BaseControl, Button, TextControl } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { ResourceCard, ResourceCardEmpty } from './resource-card';
import { ResourceModal, resourceModalTitle } from './resource-modal';
import { HiddenFields, resolveFieldSources, resolveResourceDisplay } from './resource-utils';

/**
 * One row's reorder controls: a drag handle for mouse/touch users (plain
 * HTML5 drag-and-drop, no added dependency - `onDragStart`/`onDrop` live
 * on the row itself, see ResourceListField) plus Move up/down buttons
 * for keyboard users, since native HTML5 drag-and-drop has no keyboard
 * equivalent of its own. Deliberately plain glyphs (▲/▼/⠿), not the
 * WordPress-UI icon set, matching resource-card.js's existing drag
 * handle - that package isn't one of the packages WordPress core ships
 * as a window.wp.icons global (only bundled inside window.wp.components
 * itself), so this free plugin's webpack build has nothing to
 * externalize that import to.
 *
 * @param {Object}   props            Component props.
 * @param {string}   props.itemLabel  The row's own title, or the field's item noun if
 *                                    untitled - used in the buttons' accessible names so
 *                                    "Move up"/"Move down" identify which row they act on.
 * @param {boolean}  props.isFirst    Disables Move up.
 * @param {boolean}  props.isLast     Disables Move down.
 * @param {Function} props.onMoveUp   Called when Move up is activated.
 * @param {Function} props.onMoveDown Called when Move down is activated.
 * @return {JSX.Element} The controls.
 */
function ReorderControls( { itemLabel, isFirst, isLast, onMoveUp, onMoveDown } ) {
	return (
		<div className="edbs-resource-card__reorder">
			<span className="edbs-resource-card__drag-handle" aria-hidden="true">
				⠿
			</span>
			<Button
				className="edbs-resource-card__reorder-button"
				aria-label={ sprintf( /* translators: %s: the row's title, e.g. "Board packet". */ __( 'Move %s up', 'boardscribe' ), itemLabel ) }
				disabled={ isFirst }
				onClick={ onMoveUp }
			>
				<span aria-hidden="true">▲</span>
			</Button>
			<Button
				className="edbs-resource-card__reorder-button"
				aria-label={ sprintf( /* translators: %s: the row's title, e.g. "Board packet". */ __( 'Move %s down', 'boardscribe' ), itemLabel ) }
				disabled={ isLast }
				onClick={ onMoveDown }
			>
				<span aria-hidden="true">▼</span>
			</Button>
		</div>
	);
}

/**
 * One row's title, shown as plain text or, while editing, a text field
 * with Save/Cancel - the repeater's own stored label (unlike a single
 * resource field's computed title, this one really is stored per-item
 * data, see field.itemNoun and ProMetaFields::render_documents_html()'s
 * pre-registry equivalent).
 *
 * @param {Object}   props              Component props.
 * @param {string}   props.label        Current label.
 * @param {Function} props.onChange     Called with the new label on save.
 * @param {string}   [props.emptyLabel] Shown in place of an empty label - defaults to "Untitled
 *                                      document"; attached-repeater-field.js passes its own
 *                                      per-field default instead (see that file's docblock).
 * @param {string}   [props.fieldLabel] The hidden accessible label for the edit-mode text field
 *                                      (visually hidden either way) - defaults to "Document name".
 * @return {JSX.Element} The title cell.
 */
export function EditableTitle( { label, onChange, emptyLabel, fieldLabel } ) {
	const [ isEditing, setIsEditing ] = useState( false );
	const [ draft, setDraft ] = useState( label );

	// Rows are keyed by array index (see ResourceListField/AttachedRepeaterField),
	// so a reorder or removal can hand this exact component instance a
	// different row's `label` without unmounting it. Resyncing here keeps
	// `draft` from going stale and, if a save happens while still
	// (now different-row) "editing", overwriting the new row's label with
	// leftover text from the row that used to be at this index.
	useEffect( () => {
		setDraft( label );
	}, [ label ] );

	if ( ! isEditing ) {
		return (
			<>
				{ label || emptyLabel || __( 'Untitled document', 'boardscribe' ) }
				<Button variant="link" className="edbs-resource-card__edit-title" onClick={ () => setIsEditing( true ) }>
					{ __( 'Edit title', 'boardscribe' ) }
				</Button>
			</>
		);
	}

	const save = () => {
		onChange( draft );
		setIsEditing( false );
	};

	return (
		<div className="edbs-resource-card__title-edit">
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ fieldLabel || __( 'Document name', 'boardscribe' ) }
				hideLabelFromVision
				value={ draft }
				onChange={ setDraft }
				onKeyDown={ ( event ) => {
					if ( 'Enter' === event.key ) {
						event.preventDefault();
						save();
					}
				} }
			/>
			<Button variant="secondary" onClick={ save }>
				{ __( 'Save', 'boardscribe' ) }
			</Button>
		</div>
	);
}

/**
 * A repeater of resource cards (label + URL pairs) - Supporting
 * Documents' field type. Reorderable two ways: a drag handle (mouse/touch,
 * plain HTML5 drag-and-drop) and Move up/down buttons (keyboard, and a
 * visible affordance for anyone who wouldn't discover drag-and-drop) -
 * see ReorderControls. "+ Add {noun}" opens the same Add/Replace modal
 * every other resource-shaped field uses rather than appending a blank
 * row directly; picking a Media Library file suggests the new row's
 * title from the attachment's own title (still freely editable after,
 * via each row's "Edit title") - see the modal's onSave below and
 * MediaLibrarySource's docblock in resource-modal.js. Stores its array of
 * `{ label, url, source }` items as hidden `name="{key}[i][label]"`/
 * `"[url]"`/`"[source]"` inputs per row - `source` is which Add/Replace-modal
 * source produced that row's url (see resource-modal.js's activeSource
 * wrapper and resource-utils.js's resolveResourceDisplay()) - the same
 * shape MetaBox::save_meta() has always skipped (via `saved_externally`)
 * for this field, so the plugin's own save handler is unaffected by how
 * the rows are rendered; it just needs to persist the extra property too
 * (see ProMetaFields::save_label_url_pairs_field() in the Pro repo).
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Field descriptor from MetaBoxFieldRegistry::js_schema().
 * @param {Array}    props.value    Current items, `[{ label, url, source }]`.
 * @param {Function} props.onChange Called with the new items array.
 * @return {JSX.Element} The field.
 */
export function ResourceListField( { field, value, onChange } ) {
	const items = Array.isArray( value ) ? value : [];
	const [ modalIndex, setModalIndex ] = useState( null );
	const dragIndex = useRef( null );

	const updateItem = ( index, patch ) => {
		onChange( items.map( ( item, i ) => ( i === index ? { ...item, ...patch } : item ) ) );
	};

	const removeItem = ( index ) => {
		onChange( items.filter( ( _, i ) => i !== index ) );
	};

	// 'new' opens the modal for a brand-new row (appended on save, with its
	// title suggested from the picked source - see the modal's onSave
	// below); a number opens it to replace that row's existing URL only,
	// leaving its title untouched. null means closed.
	const addItem = () => setModalIndex( 'new' );

	const reorder = ( fromIndex, toIndex ) => {
		if ( fromIndex === toIndex || fromIndex < 0 || toIndex < 0 || toIndex >= items.length ) {
			return;
		}
		const next = items.slice();
		const [ moved ] = next.splice( fromIndex, 1 );
		next.splice( toIndex, 0, moved );
		onChange( next );
	};

	const itemNoun = field.itemNoun || __( 'Document', 'boardscribe' );
	const isAdding = 'new' === modalIndex;

	return (
		<BaseControl id={ field.key } label={ field.label } help={ field.description || undefined } __nextHasNoMarginBottom>
			{ 0 === items.length && (
				<ResourceCardEmpty
					message={ sprintf( /* translators: %s: item noun, e.g. "documents". */ __( 'No %s added.', 'boardscribe' ), itemNoun.toLowerCase() + 's' ) }
					actionLabel={ sprintf( /* translators: %s: item noun, e.g. "Document". */ __( 'Add %s', 'boardscribe' ), itemNoun ) }
					onAdd={ addItem }
				/>
			) }

			{ items.map( ( item, index ) => {
				const { chips, meta } = resolveResourceDisplay( item.url, item.source );
				return (
					<div
						className="edbs-resource-list__row"
						key={ index }
						draggable
						onDragStart={ () => {
							dragIndex.current = index;
						} }
						onDragOver={ ( event ) => event.preventDefault() }
						onDrop={ ( event ) => {
							event.preventDefault();
							if ( null !== dragIndex.current ) {
								reorder( dragIndex.current, index );
								dragIndex.current = null;
							}
						} }
					>
						<ResourceCard
							dragHandle={
								<ReorderControls
									itemLabel={ item.label || itemNoun }
									isFirst={ 0 === index }
									isLast={ index === items.length - 1 }
									onMoveUp={ () => reorder( index, index - 1 ) }
									onMoveDown={ () => reorder( index, index + 1 ) }
								/>
							}
							title={ <EditableTitle label={ item.label } onChange={ ( label ) => updateItem( index, { label } ) } /> }
							chips={ item.url ? chips : [] }
							meta={ item.url ? meta : '' }
							actions={ [
								{ label: __( 'View', 'boardscribe' ), ariaLabel: sprintf( /* translators: %s: the row's title, e.g. "Board packet". */ __( 'View %s', 'boardscribe' ), item.label || itemNoun ), href: item.url || undefined },
								{ label: __( 'Replace', 'boardscribe' ), ariaLabel: sprintf( /* translators: %s: the row's title, e.g. "Board packet". */ __( 'Replace %s', 'boardscribe' ), item.label || itemNoun ), onClick: () => setModalIndex( index ) },
								{ label: __( 'Remove', 'boardscribe' ), ariaLabel: sprintf( /* translators: %s: the row's title, e.g. "Board packet". */ __( 'Remove %s', 'boardscribe' ), item.label || itemNoun ), danger: true, onClick: () => removeItem( index ) },
							] }
						>
							<HiddenFields fields={ [
								{ name: `${ field.key }[${ index }][label]`, value: item.label },
								{ name: `${ field.key }[${ index }][url]`, value: item.url },
								{ name: `${ field.key }[${ index }][source]`, value: item.source },
							] } />
						</ResourceCard>
					</div>
				);
			} ) }

			{ items.length > 0 && (
				<Button variant="secondary" onClick={ addItem }>
					{ sprintf( /* translators: %s: item noun, e.g. "Document". */ __( '+ Add %s', 'boardscribe' ), itemNoun ) }
				</Button>
			) }

			{ null !== modalIndex && (
				<ResourceModal
					title={ resourceModalTitle( itemNoun, ! isAdding ) }
					sources={ resolveFieldSources( field, [ 'media_library', 'external_url' ] ) }
					mediaTitle={ field.mediaTitle }
					fieldLabel={ itemNoun }
					currentValue={ isAdding ? '' : items[ modalIndex ].url }
					onSave={ ( url, extra ) => {
						const source = ( extra && extra.source ) || '';
						if ( isAdding ) {
							onChange( [ ...items, { label: ( extra && extra.title ) || '', url, source } ] );
						} else {
							updateItem( modalIndex, { url, source } );
						}
						setModalIndex( null );
					} }
					onClose={ () => setModalIndex( null ) }
				/>
			) }
		</BaseControl>
	);
}
