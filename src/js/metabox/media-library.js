import { __ } from '@wordpress/i18n';

/**
 * Opens the wp.media() library modal and reports the selected attachment
 * back to the caller - the shared frame-open/select-handling logic
 * field-control.js's inline "Media Library" button (a plain url field) and
 * resource-modal.js's MediaLibrarySource (the 'resource' Add/Replace
 * modal's media_library source) each used to implement independently.
 *
 * @param {Object}   config            Config.
 * @param {string}   [config.title]    wp.media() modal title.
 * @param {Function} config.onSelect   Called with the selected attachment's URL, and a
 *                                     second `{ title }` argument (the attachment's own
 *                                     title, falling back to its filename) - a caller that
 *                                     doesn't need it can simply ignore the extra argument.
 * @param {Function} [config.onCancel] Called if the frame closes with no selection made,
 *                                     or if wp.media isn't available at all. Optional - a
 *                                     caller with nothing to do on cancel can omit it.
 */
export function openMediaLibrary( { title, onSelect, onCancel } ) {
	if ( ! window.wp || ! window.wp.media ) {
		if ( onCancel ) {
			onCancel();
		}
		return;
	}

	let picked = false;
	const frame = window.wp.media( {
		title: title || __( 'Select a File', 'boardscribe' ),
		button: { text: __( 'Use this file', 'boardscribe' ) },
		multiple: false,
	} );

	frame.on( 'select', () => {
		picked = true;
		const attachment = frame.state().get( 'selection' ).first().toJSON();
		onSelect( attachment.url, { title: attachment.title || attachment.filename || '' } );
	} );

	if ( onCancel ) {
		frame.on( 'close', () => {
			if ( ! picked ) {
				onCancel();
			}
		} );
	}

	frame.open();
}
