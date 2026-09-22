import { __ } from '@wordpress/i18n';

/**
 * Opens the wp.media() library modal and reports the selected attachment
 * back to the caller. Shared by field-control.js's url-field button and
 * resource-modal.js's media_library source.
 *
 * @param {Object}   config            Config.
 * @param {string}   [config.title]    wp.media() modal title.
 * @param {Function} config.onSelect   Called with the selected attachment's URL and a
 *                                     `{ title }` argument (title falls back to filename).
 * @param {Function} [config.onCancel] Called if the frame closes with no selection, or
 *                                     wp.media isn't available. Optional.
 * @return {Object|undefined} The wp.media frame, so a caller (e.g.
 *                             resource-modal.js's MediaLibrarySource) can close it itself
 *                             if it unmounts while the frame is still open - undefined
 *                             when wp.media isn't available and nothing was opened.
 */
export function openMediaLibrary( { title, onSelect, onCancel } ) {
	if ( ! window.wp || ! window.wp.media ) {
		if ( onCancel ) {
			onCancel();
		}
		return undefined;
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
		// wp.media doesn't auto-close after a plain select() - close it
		// ourselves so the frame doesn't linger open behind the field's
		// own updated card.
		frame.close();
	} );

	frame.on( 'close', () => {
		if ( ! picked && onCancel ) {
			onCancel();
		}
	} );

	frame.open();

	return frame;
}
