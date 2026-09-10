import { resolveColumns } from '../../../src/js/templates/table';

const MEETING = {
	title: 'January Board Meeting',
	date: 'January 15, 2026',
	agenda: '<a href="https://example.com/agenda.pdf">Agenda</a>',
	minutes: '<a href="https://example.com/minutes.pdf">Minutes</a>',
	location: 'City Hall, Room 2',
};

afterEach( () => {
	delete window.edbsExtraColumns;
} );

/**
 * Convenience: the resolved column keys, in render order.
 *
 * @param {Object} cfg - The per-instance configuration.
 * @return {string[]} The column keys.
 */
function keys( cfg ) {
	return resolveColumns( cfg ).map( ( column ) => column.key );
}

describe( 'resolveColumns', () => {
	it( 'returns the four core columns in title/date/agenda/minutes order', () => {
		expect( keys( {} ) ).toEqual( [ 'title', 'date', 'agenda', 'minutes' ] );
	} );

	it( 'omits every column its hide toggle switches off', () => {
		expect( keys( { hideDate: true, hideMinutes: true } ) ).toEqual( [ 'title', 'agenda' ] );
		expect( keys( { hideTitle: true, hideDate: true, hideAgenda: true, hideMinutes: true } ) ).toEqual( [] );
	} );

	it( 'prefers the instance label override over the hard-coded fallback', () => {
		const [ title ] = resolveColumns( { titleLabel: 'Meeting' } );

		expect( title.label ).toBe( 'Meeting' );
		expect( title.labelHtml ).toBe( 'Meeting' );
	} );

	it( 'escapes a core column label for its header HTML but leaves the plain-text label alone', () => {
		const [ title ] = resolveColumns( { titleLabel: 'Board & Council' } );

		expect( title.label ).toBe( 'Board & Council' );
		expect( title.labelHtml ).toBe( 'Board &amp; Council' );
	} );

	it( 'marks the title as the row header, falling back to the date and then to nothing', () => {
		const rowHeader = ( cfg ) => ( resolveColumns( cfg ).find( ( column ) => column.isRowHeader ) || {} ).key;

		expect( rowHeader( {} ) ).toBe( 'title' );
		expect( rowHeader( { hideTitle: true } ) ).toBe( 'date' );
		expect( rowHeader( { hideTitle: true, hideDate: true } ) ).toBeUndefined();
	} );

	it( 'renders a core column from the row field of the same name, falling back to an empty string', () => {
		const [ title ] = resolveColumns( {} );

		expect( title.render( MEETING ) ).toBe( 'January Board Meeting' );
		expect( title.render( {} ) ).toBe( '' );
	} );

	it( 'appends registered extra columns after the core ones', () => {
		window.edbsExtraColumns = [ { key: 'location', label: 'Location' } ];

		expect( keys( {} ) ).toEqual( [ 'title', 'date', 'agenda', 'minutes', 'location' ] );
	} );

	it( 'skips an extra column whose hidden() returns true for this instance', () => {
		window.edbsExtraColumns = [
			{ key: 'location', label: 'Location', hidden: ( cfg ) => ! cfg.showLocation },
		];

		expect( keys( {} ) ).not.toContain( 'location' );
		expect( keys( { showLocation: true } ) ).toContain( 'location' );
	} );

	it( 'resolves an extra column label via getLabel() and keeps it as raw header HTML', () => {
		window.edbsExtraColumns = [
			{ key: 'location', label: 'Location', getLabel: ( cfg ) => cfg.locationLabel || '<em>Where</em>' },
		];

		const [ location ] = resolveColumns( {} ).slice( -1 );

		expect( location.labelHtml ).toBe( '<em>Where</em>' );
		expect( location.label ).toBe( '<em>Where</em>' );
	} );

	it( 'renders an extra column through renderCell(), passing the instance config', () => {
		const renderCell = jest.fn( () => '<span>cell</span>' );
		window.edbsExtraColumns = [ { key: 'location', label: 'Location', renderCell } ];
		const cfg = { showLocation: true };

		const [ location ] = resolveColumns( cfg ).slice( -1 );

		expect( location.render( MEETING ) ).toBe( '<span>cell</span>' );
		expect( renderCell ).toHaveBeenCalledWith( MEETING, cfg );
	} );

	it( 'falls back to the row field of the same key when an extra column has no renderCell()', () => {
		window.edbsExtraColumns = [ { key: 'location', label: 'Location' } ];

		const [ location ] = resolveColumns( {} ).slice( -1 );

		expect( location.render( MEETING ) ).toBe( 'City Hall, Room 2' );
		expect( location.render( {} ) ).toBe( '' );
	} );

	it( 'never marks an extra column as the row header', () => {
		window.edbsExtraColumns = [ { key: 'location', label: 'Location' } ];

		const [ location ] = resolveColumns( { hideTitle: true, hideDate: true } ).slice( -1 );

		expect( location.isRowHeader ).toBe( false );
	} );

	it( 'tolerates a missing config and a malformed extra-column registry', () => {
		window.edbsExtraColumns = 'not an array';
		expect( keys( undefined ) ).toEqual( [ 'title', 'date', 'agenda', 'minutes' ] );

		window.edbsExtraColumns = [ null, { key: 'location', label: 'Location' } ];
		expect( keys( {} ) ).toEqual( [ 'title', 'date', 'agenda', 'minutes', 'location' ] );
	} );
} );
