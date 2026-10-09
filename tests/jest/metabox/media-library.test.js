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
} );
