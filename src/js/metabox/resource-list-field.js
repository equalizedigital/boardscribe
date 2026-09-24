import { BaseControl, Button, TextControl } from '@wordpress/components';
import { speak } from '@wordpress/a11y';
import { Fragment, useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { ResourceCard, ResourceCardEmpty } from './resource-card';
import { ResourceModal, resourceModalTitle } from './resource-modal';
import { createRowKeyer, focusFirstActionable, HiddenFields, resolveFieldSources, resolveResourceDisplay } from './resource-utils';

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
 * @param {Object}   props              Component props.
 * @param {string}   props.itemLabel    The row's own title, or the field's item noun if
 *                                      untitled - used in the buttons' accessible names so
 *                                      "Move up"/"Move down" identify which row they act on.
 * @param {string}   [props.moveUpId]   DOM id for the Move up button - lets a caller
 *                                      re-find and refocus this exact row's button after
 *                                      a move re-renders the list (see reorder()'s own
 *                                      focus-restore effect in ResourceListField).
 * @param {string}   [props.moveDownId] Same, for Move down.
 * @param {boolean}  props.isFirst      Move up is a no-op (first row already).
 * @param {boolean}  props.isLast       Move down is a no-op (last row already).
 * @param {Function} props.onMoveUp     Called when Move up is activated.
 * @param {Function} props.onMoveDown   Called when Move down is activated.
 * @return {JSX.Element} The controls.
 */
function ReorderControls( { itemLabel, moveUpId, moveDownId, isFirst, isLast, onMoveUp, onMoveDown } ) {
	// aria-disabled, not the `disabled` prop: a real `disabled` attribute
	// drops the button from the tab order entirely, so the button a
	// keyboard user just activated (to reach either end of the list) can
	// itself vanish from under their focus. Keeping it focusable (but a
	// no-op) means focus lands somewhere predictable either way - see
	// PRO-1362.
	return (
		<div className="edbs-resource-card__reorder">
			<span className="edbs-resource-card__drag-handle" aria-hidden="true">
				⠿
			</span>
			<Button
				id={ moveUpId }
				className="edbs-resource-card__reorder-button"
				aria-label={ sprintf( /* translators: %s: the row's title, e.g. "Board packet". */ __( 'Move %s up', 'boardscribe' ), itemLabel ) }
				aria-disabled={ isFirst }
				onClick={ () => {
					if ( ! isFirst ) {
						onMoveUp();
					}
				} }
			>
				<span aria-hidden="true">▲</span>
			</Button>
			<Button
				id={ moveDownId }
				className="edbs-resource-card__reorder-button"
				aria-label={ sprintf( /* translators: %s: the row's title, e.g. "Board packet". */ __( 'Move %s down', 'boardscribe' ), itemLabel ) }
				aria-disabled={ isLast }
				onClick={ () => {
					if ( ! isLast ) {
						onMoveDown();
					}
				} }
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
 * @param {Object}   props                   Component props.
 * @param {string}   props.label             Current label.
 * @param {Function} props.onChange          Called with the new label on save.
 * @param {string}   [props.emptyLabel]      Shown in place of an empty label - defaults to "Untitled
 *                                           document"; attached-repeater-field.js passes its own
 *                                           per-field default instead (see that file's docblock).
 * @param {string}   [props.fieldLabel]      The hidden accessible label for the edit-mode text
 *                                           field (visually hidden either way) - defaults to
 *                                           "Document name".
 * @param {boolean}  [props.hideEditButton]  Suppresses the own built-in trigger button - for a
 *                                           caller (ResourceListField) that puts its own trigger
 *                                           in the card's action row instead and drives `isEditing`.
 * @param {boolean}  [props.isEditing]       Controls editing state externally when provided,
 *                                           instead of the internal default.
 * @param {Function} [props.onEditingChange] Called with the next isEditing value (true from the
 *                                           own trigger button, false on save) when controlled.
 * @param {string}   [props.ariaLabel]       Accessible name for the built-in trigger button,
 *                                           overriding its visible "Edit title"/"Add title" text -
 *                                           a repeater with several rows needs this to tell a
 *                                           screen reader which row's title each button edits.
 * @return {JSX.Element} The title cell.
 */
export function EditableTitle( { label, onChange, emptyLabel, fieldLabel, hideEditButton, isEditing: isEditingProp, onEditingChange, ariaLabel } ) {
	const [ internalIsEditing, setInternalIsEditing ] = useState( false );
	const isControlled = undefined !== isEditingProp;
	const isEditing = isControlled ? isEditingProp : internalIsEditing;
	const setIsEditing = isControlled ? onEditingChange : setInternalIsEditing;
	const [ draft, setDraft ] = useState( label );
	const inputRef = useRef( null );

	// Belt-and-suspenders: ResourceListField/AttachedRepeaterField key rows
	// by stable identity (not array index - see createRowKeyer()) and
	// retarget their own editingIndex-style tracking through a
	// move/removal, so this instance should already stay matched to the
	// right row. Resyncing `draft` from `label` here anyway means a bug in
	// either of those (or a future caller that doesn't do the same
	// bookkeeping) fails safe - stale `draft` text, not a save that
	// silently overwrites a different row's label.
	useEffect( () => {
		setDraft( label );
	}, [ label ] );

	useEffect( () => {
		if ( isEditing && inputRef.current ) {
			inputRef.current.focus();
		}
	}, [ isEditing ] );

	if ( ! isEditing ) {
		return (
			<>
				{ label || emptyLabel || __( 'Untitled document', 'boardscribe' ) }
				{ ! hideEditButton && (
					<Button variant="link" className="edbs-resource-card__edit-title" aria-label={ ariaLabel || undefined } onClick={ () => setIsEditing( true ) }>
						{ label ? __( 'Edit title', 'boardscribe' ) : __( 'Add title', 'boardscribe' ) }
					</Button>
				) }
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
				ref={ inputRef }
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
 * @param {Array}    props.value    Current items, `[{ label, url, source, document_id, edit_url }]` -
 *                                  snake_case to match the raw JSON a plugin's own save handler
 *                                  persists (see e.g. Pro's ProMetaFields::save_label_url_pairs_field()) -
 *                                  this component never transforms the shape it's handed, only the
 *                                  camelCase `extra.documentId`/`extra.editUrl` a source hands back
 *                                  via the modal (see handleSourceSave below) gets mapped onto these
 *                                  two keys when building/patching an item. Only ever present for a
 *                                  row a plugin-registered source stamped (e.g. Pro's 'document'
 *                                  source); edit_url is display-only (not submitted, see the
 *                                  HiddenFields below) and document_id is submitted so a source can
 *                                  persist the linked post's ID alongside the URL.
 * @param {Function} props.onChange Called with the new items array.
 * @return {JSX.Element} The field.
 */
export function ResourceListField( { field, value, onChange } ) {
	const items = Array.isArray( value ) ? value : [];
	const [ modalIndex, setModalIndex ] = useState( null );
	const [ editingIndex, setEditingIndex ] = useState( null );
	const [ draggingIndex, setDraggingIndex ] = useState( null );
	// `{ index, position }` for the row currently under the dragged item -
	// `position` ('before'/'after') is which side of that row the drop
	// indicator (and the eventual insertion point) sits on, based on
	// which half of the row the pointer is over.
	const [ dropTarget, setDropTarget ] = useState( null );
	const containerRef = useRef( null );

	// A stable React `key` per row, independent of its position in `items` -
	// see createRowKeyer()'s own docblock for why. Kept in a ref so the
	// same WeakMap (and its generated keys) survives across re-renders.
	const getRowKey = useRef( createRowKeyer() ).current;

	// Set just before a reorder() triggered by the Move up/down buttons
	// (not drag-and-drop, which doesn't need keyboard focus restored);
	// consumed by the effect below once the move's re-render has
	// committed. Identifies the row by its stable getRowKey() id, not
	// index, since the row's position is exactly what just changed.
	const focusAfterMoveRef = useRef( null );
	const moveButtonId = ( rowKey, direction ) => `${ field.key }-${ rowKey }-move-${ direction }`;

	// reorder() itself can't move focus - it runs synchronously inside the
	// click handler, before the DOM has re-rendered at the row's new
	// position, so the button to focus doesn't exist yet under its new id.
	// This runs after that commit instead. Always the same direction the
	// user just activated, even when the move lands the row at that end of
	// the list (making that same button now a no-op) - ReorderControls
	// keeps a no-op button focusable (aria-disabled, not the `disabled`
	// attribute) precisely so this stays a valid, predictable target
	// instead of needing a fallback.
	useEffect( () => {
		if ( ! focusAfterMoveRef.current ) {
			return;
		}
		const { rowKey, direction } = focusAfterMoveRef.current;
		focusAfterMoveRef.current = null;
		const button = document.getElementById( moveButtonId( rowKey, direction ) );
		if ( button ) {
			button.focus();
		}
	}, [ items ] );

	const updateItem = ( index, patch ) => {
		onChange( items.map( ( item, i ) => ( i === index ? { ...item, ...patch } : item ) ) );
	};

	// Re-targets editingIndex through a removal, same reasoning as
	// reindexAfterMove() below but for a row disappearing rather than
	// moving: a later row shifts down by one, an earlier row is
	// unaffected, and the removed row's own editor (if open) has nothing
	// left to point at. Without this, removing row 1 while row 3's editor
	// is open left editingIndex === 2 pointing at whatever row ended up
	// there instead - closing row 3's real editor and spuriously opening
	// the wrong one (see PRO-1364).
	const reindexAfterRemove = ( index, removedIndex ) => {
		if ( index === removedIndex ) {
			return null;
		}
		return index > removedIndex ? index - 1 : index;
	};

	const removeItem = ( index ) => {
		onChange( items.filter( ( _, i ) => i !== index ) );
		setEditingIndex( ( current ) => ( null === current ? current : reindexAfterRemove( current, index ) ) );
		// Removing the last row swaps it (plus the "+ Add" button) for the
		// empty state's own "Add" button - focus it, same reasoning as
		// resource-field.js's focusFirstActionable() calls.
		focusFirstActionable( containerRef );
		speak( sprintf( /* translators: %s: item noun, e.g. "Document". */ __( '%s removed.', 'boardscribe' ), itemNoun ) );
	};

	// 'new' opens the modal for a brand-new row (appended on save, with its
	// title suggested from the picked source - see the modal's onSave
	// below); a number opens it to replace that row's existing URL only,
	// leaving its title untouched. null means closed.
	const addItem = () => setModalIndex( 'new' );

	/**
	 * Re-targets a tracked row index (e.g. editingIndex) through a reorder()
	 * move, so it keeps pointing at the same row instead of whatever row
	 * ends up at its old numeric position - see reorder()'s own docblock
	 * for why this matters.
	 *
	 * @param {number} index     The tracked index.
	 * @param {number} fromIndex The moved row's original index.
	 * @param {number} toIndex   The moved row's new index.
	 * @return {number} The tracked index's new position.
	 */
	const reindexAfterMove = ( index, fromIndex, toIndex ) => {
		if ( index === fromIndex ) {
			return toIndex;
		}
		if ( fromIndex < toIndex && index > fromIndex && index <= toIndex ) {
			return index - 1;
		}
		if ( fromIndex > toIndex && index >= toIndex && index < fromIndex ) {
			return index + 1;
		}
		return index;
	};

	// Shared by the Move up/down buttons and a completed drag-and-drop -
	// speak() here covers both, since a keyboard user reordering via the
	// buttons otherwise gets no confirmation the move actually happened.
	// Rows are keyed by array index (see the map() below), so a move has to
	// retarget any index-based state pointing at a row - otherwise an open
	// title editor (editingIndex) would silently follow the old position to
	// whichever row lands there instead of staying with the row it was
	// actually editing, discarding the draft and, if saved, applying it to
	// the wrong document (see EditableTitle's own docblock).
	const reorder = ( fromIndex, toIndex ) => {
		if ( fromIndex === toIndex || fromIndex < 0 || toIndex < 0 || toIndex >= items.length ) {
			return;
		}
		const next = items.slice();
		const [ moved ] = next.splice( fromIndex, 1 );
		next.splice( toIndex, 0, moved );
		onChange( next );
		setEditingIndex( ( current ) => ( null === current ? current : reindexAfterMove( current, fromIndex, toIndex ) ) );
		speak( sprintf( /* translators: 1: row title or item noun, 2: new 1-based position, 3: total row count. */ __( '%1$s moved to position %2$d of %3$d.', 'boardscribe' ), moved.label || itemNoun, toIndex + 1, next.length ) );
	};

	/**
	 * Resolves a `{ index, position }` drop target (see dropTarget's own
	 * comment) to the plain toIndex reorder() expects, accounting for the
	 * dragged row's own removal shifting every later index down by one.
	 *
	 * @param {number} fromIndex The dragged row's original index.
	 * @param {Object} target    `{ index, position }`.
	 * @return {number} The index to pass to reorder().
	 */
	const resolveDropIndex = ( fromIndex, target ) => {
		const rawTarget = 'after' === target.position ? target.index + 1 : target.index;
		return fromIndex < rawTarget ? rawTarget - 1 : rawTarget;
	};

	const itemNoun = field.itemNoun || __( 'Document', 'boardscribe' );
	const isAdding = 'new' === modalIndex;
	const editTitleButtonId = ( index ) => `${ field.key }-${ index }-edit-title`;

	// EditableTitle only closes editing via Save (there's no Cancel), so
	// this always means "just saved" - move focus to the action row's own
	// "Edit title" button, since it's now the only visible trigger.
	const finishEditingTitle = ( index ) => {
		setEditingIndex( null );
		window.setTimeout( () => {
			const button = document.getElementById( editTitleButtonId( index ) );
			if ( button ) {
				button.focus();
			}
		} );
	};

	return (
		<BaseControl id={ field.key } label={ field.label } help={ field.description || undefined } __nextHasNoMarginBottom>
			<div ref={ containerRef }>
				{ 0 === items.length && (
					<ResourceCardEmpty
						message={ sprintf( /* translators: %s: item noun, e.g. "documents". */ __( 'No %s added.', 'boardscribe' ), itemNoun.toLowerCase() + 's' ) }
						actionLabel={ sprintf( /* translators: %s: item noun, e.g. "Document". */ __( 'Add %s', 'boardscribe' ), itemNoun ) }
						onAdd={ addItem }
					/>
				) }

				{ items.map( ( item, index ) => {
					const { chips, meta } = resolveResourceDisplay( item.url, item.source, item.document_id );
					const isDragging = draggingIndex === index;
					const showIndicator = ( position ) =>
						null !== draggingIndex && dropTarget && dropTarget.index === index && dropTarget.position === position;

					// item.label alone isn't reliably unique across rows (two
					// documents can share a title, or both be untitled) - fold
					// in the filename too, same reasoning as
					// attached-repeater-field.js's rowDescription.
					const itemNounOrLabel = item.label || itemNoun;
					const itemDescription = meta ? sprintf( /* translators: 1: row title or item noun, 2: filename. */ __( '%1$s, %2$s', 'boardscribe' ), itemNounOrLabel, meta ) : itemNounOrLabel;
					const rowKey = getRowKey( item );

					return (
						<Fragment key={ rowKey }>
							{ showIndicator( 'before' ) && <div className="edbs-resource-list__drop-indicator" /> }
							<div
								className={ `edbs-resource-list__row${ isDragging ? ' edbs-resource-list__row--dragging' : '' }` }
								draggable
								onDragStart={ ( event ) => {
									// Firefox refuses to start a drag at all unless
									// dataTransfer.setData() is called during
									// dragstart - the value itself is never read
									// (onDrop drives the actual reorder off
									// draggingIndex/dropTarget state, not this
									// payload), so it's just the row's index as a
									// harmless, browser-required placeholder.
									event.dataTransfer.setData( 'text/plain', String( index ) );
									setDraggingIndex( index );
								} }
								onDragEnd={ () => {
									setDraggingIndex( null );
									setDropTarget( null );
								} }
								onDragOver={ ( event ) => {
									event.preventDefault();
									if ( null === draggingIndex || draggingIndex === index ) {
										return;
									}
									const rect = event.currentTarget.getBoundingClientRect();
									const position = event.clientY < rect.top + ( rect.height / 2 ) ? 'before' : 'after';
									if ( ! dropTarget || dropTarget.index !== index || dropTarget.position !== position ) {
										setDropTarget( { index, position } );
									}
								} }
								onDrop={ ( event ) => {
									event.preventDefault();
									if ( null !== draggingIndex && dropTarget ) {
										reorder( draggingIndex, resolveDropIndex( draggingIndex, dropTarget ) );
									}
									setDraggingIndex( null );
									setDropTarget( null );
								} }
							>
								<ResourceCard
									dragHandle={
										<ReorderControls
											itemLabel={ itemDescription }
											moveUpId={ moveButtonId( rowKey, 'up' ) }
											moveDownId={ moveButtonId( rowKey, 'down' ) }
											isFirst={ 0 === index }
											isLast={ index === items.length - 1 }
											onMoveUp={ () => {
												focusAfterMoveRef.current = { rowKey, direction: 'up' };
												reorder( index, index - 1 );
											} }
											onMoveDown={ () => {
												focusAfterMoveRef.current = { rowKey, direction: 'down' };
												reorder( index, index + 1 );
											} }
										/>
									}
									title={
										<EditableTitle
											label={ item.label }
											onChange={ ( label ) => updateItem( index, { label } ) }
											hideEditButton
											isEditing={ editingIndex === index }
											onEditingChange={ ( next ) => ( next ? setEditingIndex( index ) : finishEditingTitle( index ) ) }
										/>
									}
									chips={ item.url ? chips : [] }
									meta={ item.url ? meta : '' }
									actions={ [
										{ label: __( 'View', 'boardscribe' ), ariaLabel: sprintf( /* translators: %s: the row's title + filename, e.g. "Board packet, packet.pdf". */ __( 'View %s', 'boardscribe' ), itemDescription ), href: item.url || undefined },
										...( item.edit_url ? [ { label: __( 'Edit', 'boardscribe' ), ariaLabel: sprintf( /* translators: %s: the row's title + filename, e.g. "Board packet, packet.pdf". */ __( 'Edit %s', 'boardscribe' ), itemDescription ), href: item.edit_url } ] : [] ),
										{ id: editTitleButtonId( index ), label: item.label ? __( 'Edit title', 'boardscribe' ) : __( 'Add title', 'boardscribe' ), ariaLabel: sprintf( /* translators: %s: the row's title + filename, e.g. "Board packet, packet.pdf". */ item.label ? __( 'Edit title of %s', 'boardscribe' ) : __( 'Add title of %s', 'boardscribe' ), itemDescription ), disabled: editingIndex === index, onClick: () => setEditingIndex( index ) },
										{ label: __( 'Replace', 'boardscribe' ), ariaLabel: sprintf( /* translators: %s: the row's title + filename, e.g. "Board packet, packet.pdf". */ __( 'Replace %s', 'boardscribe' ), itemDescription ), onClick: () => setModalIndex( index ) },
										{ label: __( 'Remove', 'boardscribe' ), ariaLabel: sprintf( /* translators: %s: the row's title + filename, e.g. "Board packet, packet.pdf". */ __( 'Remove %s', 'boardscribe' ), itemDescription ), danger: true, onClick: () => removeItem( index ) },
									] }
								>
									<HiddenFields fields={ [
										{ name: `${ field.key }[${ index }][label]`, value: item.label },
										{ name: `${ field.key }[${ index }][url]`, value: item.url },
										{ name: `${ field.key }[${ index }][source]`, value: item.source },
										{ name: `${ field.key }[${ index }][document_id]`, value: item.document_id },
									] } />
								</ResourceCard>
							</div>
							{ showIndicator( 'after' ) && <div className="edbs-resource-list__drop-indicator" /> }
						</Fragment>
					);
				} ) }

				{ items.length > 0 && (
					<Button variant="secondary" onClick={ addItem }>
						{ sprintf( /* translators: %s: item noun, e.g. "Document". */ __( '+ Add %s', 'boardscribe' ), itemNoun ) }
					</Button>
				) }
			</div>

			{ null !== modalIndex && (
				<ResourceModal
					title={ resourceModalTitle( itemNoun, ! isAdding ) }
					sources={ resolveFieldSources( field, [ 'media_library', 'external_url' ] ) }
					mediaTitle={ field.mediaTitle }
					fieldLabel={ itemNoun }
					currentValue={ isAdding ? '' : items[ modalIndex ].url }
					currentSource={ isAdding ? '' : items[ modalIndex ].source }
					onSave={ ( url, extra ) => {
						const source = ( extra && extra.source ) || '';
						// A source hands these back as camelCase (the same
						// contract resource-field.js's editUrlValue uses) -
						// mapped onto the item's own snake_case document_id/
						// edit_url here, the one place that shape is decided
						// (see this component's own docblock).
						const documentId = ( extra && extra.documentId ) || '';
						const editUrl = ( extra && extra.editUrl ) || '';
						if ( isAdding ) {
							// A source's title (Media Library's attachment title,
							// Pro's document source's post title) only ever seeds a
							// *new* row's initial label - there's no existing display
							// title yet to preserve or clobber.
							onChange( [ ...items, { label: ( extra && extra.title ) || '', url, source, document_id: documentId, edit_url: editUrl } ] );
						} else {
							// Replace never touches the row's own label, regardless of
							// whether the new source hands back a title. This is a
							// meeting-specific display title (see PRO-1344), independent
							// of whatever the underlying resource happens to be titled -
							// letting a source's title clobber it here made Replace's
							// behavior depend on which source you replaced *with*
							// (Media Library/document sources overwrote a custom title,
							// External URL silently kept it, and swapping a linked
							// document out for a plain URL left its now-stale title
							// behind either way). document_id/edit_url are still always
							// overwritten (not merged) so replacing a linked document
							// with a plain URL/file doesn't leave a stale link behind -
							// only the label is exempt.
							updateItem( modalIndex, { url, source, document_id: documentId, edit_url: editUrl } );
						}
						setModalIndex( null );
						if ( isAdding ) {
							// A first row (0 -> 1) swaps the empty state's
							// own "Add" button for this row's card - same
							// reasoning as resource-field.js's
							// focusFirstActionable() calls. Replacing an
							// existing row doesn't unmount anything, so the
							// Modal's own focus-return to that row's
							// "Replace" button already works correctly -
							// calling this here too would override it and
							// jump focus to the list's first item instead.
							focusFirstActionable( containerRef );
							speak( sprintf( /* translators: %s: item noun, e.g. "Document". */ __( '%s added.', 'boardscribe' ), itemNoun ) );
						}
					} }
					onClose={ () => setModalIndex( null ) }
				/>
			) }
		</BaseControl>
	);
}
