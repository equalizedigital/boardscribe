import { Button, Modal, TextControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { openMediaLibrary } from './media-library';

/**
 * Opens the wp.media() library modal, calling onSave(url, meta) on
 * selection or onCancel() if the frame is closed without picking
 * anything.
 *
 * @param {Object}   props              Component props.
 * @param {string}   [props.mediaTitle] wp.media() modal title.
 * @param {Function} props.onSave       Called with the selected attachment's URL, and a
 *                                      second `{ title }` argument (the attachment's own
 *                                      title, falling back to its filename) - callers that
 *                                      don't need it (every field except Supporting
 *                                      Documents, which uses it to suggest a new row's
 *                                      title) can simply ignore the extra argument.
 * @param {Function} props.onCancel     Called if the frame closes with no selection.
 * @return {null} Renders nothing - it's a side-effecting launcher, not UI.
 */
function MediaLibrarySource( { mediaTitle, onSave, onCancel } ) {
	useState( () => {
		openMediaLibrary( { title: mediaTitle, onSelect: onSave, onCancel } );
		return null;
	} );

	return null;
}

/**
 * A plain URL text field + confirm button - the "Enter an external URL"
 * source.
 *
 * Wrapped in a real <form> rather than a plain onClick handler so the
 * "required" + type="url" attributes below get real browser constraint
 * validation before onSave ever runs - the disabled prop alone only
 * blocks a literally-empty value, so an unedited "https://" default (or
 * any other non-empty string that isn't actually a valid absolute URL)
 * would otherwise sail through and get persisted as-is.
 *
 * @param {Object}   props              Component props.
 * @param {string}   props.label        Field label, e.g. "Livestream URL".
 * @param {string}   props.initialValue The field's current value, if any.
 * @param {Function} props.onSave       Called with the entered URL.
 * @return {JSX.Element} The form.
 */
function ExternalUrlSource( { label, initialValue, onSave } ) {
	const [ url, setUrl ] = useState( initialValue || 'https://' );

	const handleSubmit = ( event ) => {
		event.preventDefault();
		onSave( url );
	};

	return (
		<form className="edbs-resource-modal__url-source" onSubmit={ handleSubmit }>
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ label }
				type="url"
				required
				value={ url }
				onChange={ setUrl }
				help={ __( 'The current resource stays unchanged until you confirm.', 'boardscribe' ) }
			/>
			<Button type="submit" variant="primary" disabled={ '' === url.trim() }>
				{ __( 'Save URL', 'boardscribe' ) }
			</Button>
		</form>
	);
}

/**
 * The Add/Replace modal for a resource field. With exactly one source it
 * skips straight to that source's step; with more than one it shows a
 * chooser first. Built-in sources are "media_library" and "external_url";
 * a field can list additional source ids a plugin has registered on
 * window.edbsResourceSources (keyed by id, each `{ label, description,
 * render( { onSave, onCancel, currentValue, fieldKey } ) }`) - e.g. Pro's
 * document picker, shared by Agenda and Minutes and told apart via fieldKey.
 *
 * @param {Object}        props                Component props.
 * @param {string}        props.title          Modal title, e.g. "Replace Agenda".
 * @param {Array<string>} props.sources        Source ids this field accepts, in display order.
 * @param {string}        [props.mediaTitle]   wp.media() modal title for the media_library source.
 * @param {string}        [props.fieldLabel]   Field label passed to the external_url source's text field.
 * @param {string}        [props.currentValue] The field's current value, for the external_url source's starting text.
 * @param {string}        [props.fieldKey]     The field's meta key (e.g. "edbs_agenda_url") - passed through to a
 *                                             custom source's render() as `fieldKey` so it can tell which field
 *                                             it's serving when the same source id is shared across fields
 *                                             (e.g. Pro's "document" source, offered on both Agenda and Minutes).
 * @param {Function}      props.onSave         Called with the new value, and a second `{ ...extra, source }`
 *                                             argument, once a source completes - `source` is the completed
 *                                             source's own id (stamped on by this component, see
 *                                             handleSourceSave below), always present regardless of whether
 *                                             the source itself passed its own `extra` (e.g. Media Library's
 *                                             `{ title }`). Callers that care persist it alongside the value
 *                                             (see resource-utils.js's resolveResourceDisplay()); callers
 *                                             that don't can ignore the second argument entirely.
 * @param {Function}      props.onClose        Called to dismiss the modal without saving.
 * @return {JSX.Element} The modal.
 */
export function ResourceModal( { title, sources, mediaTitle, fieldLabel, currentValue, fieldKey, onSave, onClose } ) {
	const [ activeSource, setActiveSource ] = useState( 1 === sources.length ? sources[ 0 ] : null );

	// Every source component below just calls onSave(url) or onSave(url,
	// extra) without knowing (or needing to know) which source it is - the
	// modal is the one place that already knows, so it stamps activeSource
	// onto the extra object here rather than every source component doing
	// it itself. Callers that care (ResourceField, ResourceListField,
	// AttachedRepeaterField) read extra.source to persist which source
	// produced the value - see resource-utils.js's resolveResourceDisplay().
	const handleSourceSave = ( url, extra ) => onSave( url, { ...( extra || {} ), source: activeSource } );

	const registry = {
		media_library: {
			label: __( 'Select from Media Library', 'boardscribe' ),
			description: __( "Open WordPress's native file picker to choose or upload a file.", 'boardscribe' ),
		},
		external_url: {
			label: __( 'Enter an external URL', 'boardscribe' ),
			description: __( 'Link to a file or webpage hosted elsewhere.', 'boardscribe' ),
		},
		...( window.edbsResourceSources || {} ),
	};

	const renderSourceStep = () => {
		if ( 'media_library' === activeSource ) {
			return (
				<MediaLibrarySource
					mediaTitle={ mediaTitle }
					onSave={ handleSourceSave }
					onCancel={ 1 === sources.length ? onClose : () => setActiveSource( null ) }
				/>
			);
		}

		if ( 'external_url' === activeSource ) {
			return <ExternalUrlSource label={ fieldLabel } initialValue={ currentValue } onSave={ handleSourceSave } />;
		}

		const CustomSource = window.edbsResourceSources && window.edbsResourceSources[ activeSource ] && window.edbsResourceSources[ activeSource ].render;
		if ( CustomSource ) {
			return (
				<CustomSource
					onSave={ handleSourceSave }
					onCancel={ () => setActiveSource( null ) }
					currentValue={ currentValue }
					fieldKey={ fieldKey }
				/>
			);
		}

		return null;
	};

	return (
		<Modal title={ title } onRequestClose={ onClose } className="edbs-resource-modal">
			{ null === activeSource ? (
				<div className="edbs-resource-modal__chooser">
					{ sources.map( ( sourceId ) => {
						const source = registry[ sourceId ];
						if ( ! source ) {
							return null;
						}
						return (
							<Button
								key={ sourceId }
								className="edbs-resource-modal__source-row"
								onClick={ () => setActiveSource( sourceId ) }
							>
								<span className="edbs-resource-modal__source-label">{ source.label }</span>
								<span className="edbs-resource-modal__source-description">{ source.description }</span>
							</Button>
						);
					} ) }
				</div>
			) : (
				<>
					{ sources.length > 1 && (
						<Button
							className="edbs-resource-modal__back"
							variant="tertiary"
							onClick={ () => setActiveSource( null ) }
						>
							{ __( '← Back', 'boardscribe' ) }
						</Button>
					) }
					{ renderSourceStep() }
				</>
			) }
		</Modal>
	);
}

/**
 * Builds the modal title for adding/replacing a field's value.
 *
 * @param {string}  label    Field label, e.g. "Agenda".
 * @param {boolean} hasValue Whether the field currently has a value (Replace vs Add).
 * @return {string} The title.
 */
export function resourceModalTitle( label, hasValue ) {
	return hasValue
		? sprintf( /* translators: %s: field label, e.g. "Agenda". */ __( 'Replace %s', 'boardscribe' ), label )
		: sprintf( /* translators: %s: field label, e.g. "Agenda". */ __( 'Add %s', 'boardscribe' ), label );
}
