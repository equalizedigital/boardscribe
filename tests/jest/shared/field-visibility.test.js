import { isFieldVisible } from '../../../src/js/shared/field-visibility';

describe( 'isFieldVisible', () => {
	const values = { template: 'list', date_scope: '' };
	const get = ( key ) => values[ key ];

	it( 'shows a field with no conditions', () => {
		expect( isFieldVisible( { visibleWhen: null }, get ) ).toBe( true );
		expect( isFieldVisible( {}, get ) ).toBe( true );
	} );

	it( 'shows only while the controlling field holds an allowed value', () => {
		expect( isFieldVisible( { visibleWhen: { template: [ 'list', 'table' ] } }, get ) ).toBe( true );
		expect( isFieldVisible( { visibleWhen: { template: [ 'year-timeline' ] } }, get ) ).toBe( false );
	} );

	it( 'requires every condition to hold', () => {
		expect( isFieldVisible( { visibleWhen: { template: [ 'list' ], date_scope: [ 'past' ] } }, get ) ).toBe( false );
	} );

	it( 'treats an unset or empty controlling value as the empty string', () => {
		expect( isFieldVisible( { visibleWhen: { template: [ '' ] } }, () => undefined ) ).toBe( true );
		expect( isFieldVisible( { visibleWhen: { date_scope: [ '' ] } }, get ) ).toBe( true );
	} );
} );
