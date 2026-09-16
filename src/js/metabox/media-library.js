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
