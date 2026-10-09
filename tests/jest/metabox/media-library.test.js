jest.mock( '@wordpress/a11y', () => ( { speak: jest.fn() } ) );

import { speak } from '@wordpress/a11y';
import { openMediaLibrary } from '../../../src/js/metabox/media-library';

describe( 'openMediaLibrary', () => {
	let media;

	beforeEach( () => {
		media = jest.fn( () => ( { on: jest.fn(), open: jest.fn(), close: jest.fn() } ) );
		window.wp = { media };
	} );

	afterEach( () => {
		delete window.wp;
	} );

	it( 'limits the library to the given MIME types', () => {
		openMediaLibrary( { title: 'Pick', types: [ 'application/pdf' ], onSelect: () => {} } );

		expect( media.mock.calls[ 0 ][ 0 ].library ).toEqual( { type: [ 'application/pdf' ] } );
	} );

	it( 'leaves the library unrestricted without types', () => {
		openMediaLibrary( { onSelect: () => {} } );
		openMediaLibrary( { types: [], onSelect: () => {} } );

		expect( media.mock.calls[ 0 ][ 0 ] ).not.toHaveProperty( 'library' );
		expect( media.mock.calls[ 1 ][ 0 ] ).not.toHaveProperty( 'library' );
	} );

	describe( 'selection check', () => {
		let handlers;
		let selection;
		let frame;
		let content;

		beforeEach( () => {
			handlers = {};
			selection = { first: jest.fn(), reset: jest.fn() };
			content = document.createElement( 'div' );
			frame = {
				on: ( event, fn ) => {
					handlers[ event ] = fn;
				},
				open: jest.fn(),
				close: jest.fn(),
				state: () => ( { get: () => selection } ),
				$el: { find: () => ( { get: () => content } ) },
			};
			media.mockReturnValue( frame );
		} );

		const pick = ( attachment ) => {
			selection.first.mockReturnValue( { toJSON: () => attachment } );
			handlers.select();
		};

		it( 'accepts a file of an allowed type', () => {
			const onSelect = jest.fn();
			openMediaLibrary( { types: [ 'application/pdf' ], onSelect } );

			pick( { url: 'u', title: 't', mime: 'application/pdf', type: 'application' } );

			expect( onSelect ).toHaveBeenCalledWith( 'u', { title: 't' } );
			expect( frame.close ).toHaveBeenCalled();
		} );

		it( 'rejects an uploaded file of another type by reopening the frame with a message', () => {
			jest.useFakeTimers();
			const onSelect = jest.fn();
			const onCancel = jest.fn();
			openMediaLibrary( { types: [ 'application/pdf' ], onSelect, onCancel } );

			// wp.media closes the frame, then fires select.
			handlers.close();
			pick( { url: 'u', title: 't', mime: 'image/png', type: 'image' } );
			jest.runAllTimers();

			expect( onSelect ).not.toHaveBeenCalled();
			expect( onCancel ).not.toHaveBeenCalled();
			expect( frame.open ).toHaveBeenCalledTimes( 2 );
			expect( selection.reset ).toHaveBeenCalled();
			expect( content.querySelector( '.edbs-media-type-error' ).textContent ).toContain( "can't be used" );
			expect( speak ).toHaveBeenCalledWith( expect.any( String ), 'assertive' );

			// A later, real close is still a cancel.
			handlers.close();
			jest.runAllTimers();
			expect( onCancel ).toHaveBeenCalledTimes( 1 );
			jest.useRealTimers();
		} );

		it( 'does not check anything without types', () => {
			const onSelect = jest.fn();
			openMediaLibrary( { onSelect } );

			pick( { url: 'u', title: 't', mime: 'image/png', type: 'image' } );

			expect( onSelect ).toHaveBeenCalled();
		} );
	} );
} );
