import { buildShortcode } from '../../../src/js/builder/build-shortcode';

describe( 'checked-by-default column visibility', () => {
	const fields = [ { key: 'hide_location', type: 'checkbox', default: true } ];

	it( 'omits a hidden column that matches its default', () => {
		expect( buildShortcode( fields, { hide_location: true } ) ).toBe( '[edbs_boardscribe]' );
	} );

	it( 'preserves explicit false when the column is shown', () => {
		expect( buildShortcode( fields, { hide_location: false } ) ).toBe( '[edbs_boardscribe hide_location="false"]' );
	} );
} );
