/**
 * PRO-1363 regression coverage: wp.media() opens its own frame appended to
 * document.body, entirely separate from ResourceModal's own <Modal>. Nesting
 * the media_library step inside <Modal> (which sets aria-modal="true" and
 * hides everything outside itself from assistive tech) made the just-opened
 * media frame unreachable to screen reader users in browse mode. The fix:
 * ResourceModal renders no <Modal> wrapper at all once the media_library
 * step is active - wp.media's own frame is the only dialog on screen.
 */
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { Simulate } from 'react-dom/test-utils';
import { ResourceModal } from '../../../src/js/metabox/resource-modal';

window.IS_REACT_ACT_ENVIRONMENT = true;

/**
 * A minimal stand-in for wp.media() - enough for openMediaLibrary() (see
 * media-library.js) to open/close it and report a selection back.
 *
 * @return {Object} The fake frame plus its registered event handlers, so a
 *                   test can trigger 'select'/'close' itself.
 */
function makeMediaFrame() {
	const handlers = {};
	const frame = {
		on: jest.fn( ( event, handler ) => {
			handlers[ event ] = handler;
		} ),
		open: jest.fn(),
		close: jest.fn(),
		state: jest.fn( () => ( {
			get: () => ( {
				first: () => ( {
					toJSON: () => ( { url: 'https://example.com/picked.pdf', title: 'Picked File' } ),
				} ),
			} ),
		} ) ),
	};
	return { frame, handlers };
}

let container;
let root;
let lastFrame;

beforeEach( () => {
	container = document.createElement( 'div' );
	document.body.appendChild( container );
	root = createRoot( container );
	window.wp = {
		media: jest.fn( () => {
			const { frame, handlers } = makeMediaFrame();
			lastFrame = { frame, handlers };
			return frame;
		} ),
	};
} );

afterEach( () => {
	act( () => root.unmount() );
	container.remove();
	delete window.wp;
	lastFrame = undefined;
} );

function modalNode() {
	return container.querySelector( '[data-control="modal"]' );
}

describe( 'ResourceModal and the wp.media step (PRO-1363)', () => {
	it( 'renders no <Modal> wrapper when media_library is the (sole) active source, and opens wp.media directly', () => {
		act( () => {
			root.render(
				<ResourceModal
					title="Add Document"
					sources={ [ 'media_library' ] }
					onSave={ () => {} }
					onClose={ () => {} }
				/>,
			);
		} );

		expect( modalNode() ).toBeNull();
		expect( window.wp.media ).toHaveBeenCalledTimes( 1 );
		expect( lastFrame.frame.open ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'renders <Modal> for the chooser step, then drops it once Media Library is picked', () => {
		act( () => {
			root.render(
				<ResourceModal
					title="Add Document"
					sources={ [ 'media_library', 'external_url' ] }
					onSave={ () => {} }
					onClose={ () => {} }
				/>,
			);
		} );

		expect( modalNode() ).not.toBeNull();
		expect( window.wp.media ).not.toHaveBeenCalled();

		const mediaLibraryRow = Array.from( container.querySelectorAll( '.edbs-resource-modal__source-row' ) )
			.find( ( button ) => /Media Library/.test( button.textContent ) );
		act( () => {
			Simulate.click( mediaLibraryRow );
		} );

		// The <Modal> chooser is gone - wp.media's own frame is now the
		// only dialog, not nested inside another aria-modal container.
		expect( modalNode() ).toBeNull();
		expect( window.wp.media ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'renders <Modal> for the external_url step (not a native dialog, safe to nest)', () => {
		act( () => {
			root.render(
				<ResourceModal
					title="Add Document"
					sources={ [ 'external_url' ] }
					fieldLabel="Document"
					onSave={ () => {} }
					onClose={ () => {} }
				/>,
			);
		} );

		expect( modalNode() ).not.toBeNull();
		expect( container.querySelector( '.edbs-resource-modal__url-source' ) ).not.toBeNull();
	} );

	it( 'closes the wp.media frame if the component unmounts while it is still open', () => {
		const scratchContainer = document.createElement( 'div' );
		document.body.appendChild( scratchContainer );
		const scratchRoot = createRoot( scratchContainer );

		act( () => {
			scratchRoot.render(
				<ResourceModal
					title="Add Document"
					sources={ [ 'media_library' ] }
					onSave={ () => {} }
					onClose={ () => {} }
				/>,
			);
		} );

		expect( lastFrame.frame.close ).not.toHaveBeenCalled();

		act( () => {
			scratchRoot.unmount();
		} );

		expect( lastFrame.frame.close ).toHaveBeenCalledTimes( 1 );
		scratchContainer.remove();
	} );

	it( 'reports the selected file back through onSave, stamped with source: "media_library"', () => {
		let saved;
		act( () => {
			root.render(
				<ResourceModal
					title="Add Document"
					sources={ [ 'media_library' ] }
					onSave={ ( url, extra ) => {
						saved = { url, extra };
					} }
					onClose={ () => {} }
				/>,
			);
		} );

		act( () => {
			lastFrame.handlers.select();
		} );

		expect( saved.url ).toBe( 'https://example.com/picked.pdf' );
		expect( saved.extra ).toEqual( { title: 'Picked File', source: 'media_library' } );
	} );
} );

/**
 * PRO-1368 regression coverage: ExternalUrlSource's invalid-URL error was
 * only announced once via role="alert" - a screen reader user who
 * navigated away from the field and back later had no indication anything
 * was still wrong. The input is now persistently linked to the error via
 * aria-invalid/aria-describedby, matched only while an error is showing.
 */
describe( 'ExternalUrlSource error association (PRO-1368)', () => {
	it( 'has no aria-invalid/aria-describedby before a save is attempted', () => {
		act( () => {
			root.render(
				<ResourceModal
					title="Add Document"
					sources={ [ 'external_url' ] }
					fieldLabel="Document"
					onSave={ () => {} }
					onClose={ () => {} }
				/>,
			);
		} );

		const input = container.querySelector( 'input[type="url"]' );
		expect( input.getAttribute( 'aria-invalid' ) ).toBe( 'false' );
		expect( input.hasAttribute( 'aria-describedby' ) ).toBe( false );
	} );

	it( 'links the input to the error message once Save is clicked with an invalid URL', () => {
		act( () => {
			root.render(
				<ResourceModal
					title="Add Document"
					sources={ [ 'external_url' ] }
					fieldLabel="Document"
					onSave={ () => {} }
					onClose={ () => {} }
				/>,
			);
		} );

		act( () => {
			Simulate.change( container.querySelector( 'input[type="url"]' ), { target: { value: 'not-a-url' } } );
		} );
		act( () => {
			Simulate.click( Array.from( container.querySelectorAll( 'button' ) ).find( ( button ) => 'Save URL' === button.textContent ) );
		} );

		const input = container.querySelector( 'input[type="url"]' );
		const errorNode = container.querySelector( '.edbs-resource-modal__url-source-error' );

		expect( errorNode ).not.toBeNull();
		expect( errorNode.id ).toBeTruthy();
		expect( input.getAttribute( 'aria-invalid' ) ).toBe( 'true' );
		expect( input.getAttribute( 'aria-describedby' ) ).toBe( errorNode.id );
	} );
} );
