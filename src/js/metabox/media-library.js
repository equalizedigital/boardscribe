import { speak } from '@wordpress/a11y';
import { __ } from '@wordpress/i18n';

/**
 * Shows an inline error at the top of a wp.media frame's content area,
 * replacing any earlier one.
 *
 * @param {Object} frame   The wp.media frame.
 * @param {string} message The error text.
 */
function showFrameError( frame, message ) {
	const content = frame.$el && frame.$el.find( '.media-frame-content' ).get( 0 );
	if ( ! content ) {
		return;
	}

	const existing = content.querySelector( '.edbs-media-type-error' );
	if ( existing ) {
		existing.remove();
	}

	const notice = content.ownerDocument.createElement( 'div' );
	notice.className = 'notice notice-error inline edbs-media-type-error';
	notice.setAttribute( 'role', 'alert' );
	notice.textContent = message;
	content.insertBefore( notice, content.firstChild );
}

/**
 * Opens the wp.media() library modal and reports the selected attachment
 * back to the caller. Shared by field-control.js's url-field button and
 * resource-modal.js's media_library source.
 *
 * @param {Object}   config            Config.
 * @param {string}   [config.title]    wp.media() modal title.
 * @param {string[]} [config.types]    MIME types the library is limited to. Optional.
 * @param {Function} config.onSelect   Called with the selected attachment's URL and a
 *                                     `{ title }` argument (title falls back to filename).
 * @param {Function} [config.onCancel] Called if the frame closes with no selection, or
 *                                     wp.media isn't available. Optional.
 * @return {Object|undefined} The wp.media frame, so a caller (e.g.
 *                             resource-modal.js's MediaLibrarySource) can close it itself
 *                             if it unmounts while the frame is still open - undefined
 *                             when wp.media isn't available and nothing was opened.
 */
export function openMediaLibrary( { title, types, onSelect, onCancel } ) {
	if ( ! window.wp || ! window.wp.media ) {
		if ( onCancel ) {
			onCancel();
		}
		return undefined;
	}

	let picked = false;
	let rejected = false;

	const frame = window.wp.media( {
		title: title || __( 'Select a File', 'boardscribe' ),
		button: { text: __( 'Use this file', 'boardscribe' ) },
		multiple: false,
		...( Array.isArray( types ) && types.length ? { library: { type: types } } : {} ),
	} );

	// wp.media closes the frame before it fires 'select', so a rejected
	// file reopens it (below) rather than being reported as a cancel.
	frame.on( 'select', () => {
		const selection = frame.state().get( 'selection' );
		const attachment = selection.first().toJSON();

		// library.type only filters what the Library tab lists; a file
		// uploaded in the Upload tab is selected whatever its type, so it's
		// checked here too.
		if ( Array.isArray( types ) && types.length && ! types.includes( attachment.mime ) && ! types.includes( attachment.type ) ) {
			const message = __( "That file type can't be used for this field. Choose a different file.", 'boardscribe' );
			rejected = true;
			selection.reset();
			frame.open();
			showFrameError( frame, message );
			speak( message, 'assertive' );
			return;
		}

		picked = true;
		onSelect( attachment.url, { title: attachment.title || attachment.filename || '' } );
		frame.close();
	} );

	frame.on( 'close', () => {
		// 'select' follows 'close' synchronously, so decide on the next tick.
		setTimeout( () => {
			if ( ! picked && ! rejected && onCancel ) {
				onCancel();
			}
			rejected = false;
		}, 0 );
	} );

	frame.open();

	return frame;
}
