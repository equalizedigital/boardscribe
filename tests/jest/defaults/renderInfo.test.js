import { defaultRenderInfo } from '../../../src/js/defaults/renderInfo';

/**
 * PRO-1395 #3: the old implementation derived both the range and the
 * per-page divisor from instanceCfg.postsPerPage, which is the raw
 * request-side config value - it can be -1 ("show all") or exceed the
 * server's own cap, either of which produced a nonsensical range (e.g.
 * "Showing 1 to -1 of 42 entries"). defaultRenderInfo() now reads the
 * endpoint's own effective data.per_page and derives the end of the range
 * from how many rows actually came back, not postsPerPage again.
 */
describe( 'defaultRenderInfo', () => {
	let element;

	beforeEach( () => {
		element = document.createElement( 'div' );
	} );

	it( 'does nothing when no element is given', () => {
		expect( () => defaultRenderInfo( {}, {}, null ) ).not.toThrow();
	} );

	it( 'computes a normal middle-page range from data.per_page', () => {
		defaultRenderInfo(
			{ meetings: new Array( 20 ), total_entries: 42, current_page: 2, per_page: 20 },
			{ postsPerPage: 20 },
			element,
		);

		expect( element.textContent ).toBe( 'Showing 21 to 40 of 42 entries' );
	} );

	it( 'shows the correct range for a short final page', () => {
		defaultRenderInfo(
			{ meetings: new Array( 2 ), total_entries: 42, current_page: 3, per_page: 20 },
			{ postsPerPage: 20 },
			element,
		);

		expect( element.textContent ).toBe( 'Showing 41 to 42 of 42 entries' );
	} );

	it( 'never shows a negative end for "show all" (postsPerPage -1), using data.per_page instead', () => {
		defaultRenderInfo(
			{ meetings: new Array( 42 ), total_entries: 42, current_page: 1, per_page: 500 },
			{ postsPerPage: -1 },
			element,
		);

		expect( element.textContent ).toBe( 'Showing 1 to 42 of 42 entries' );
	} );

	it( 'is correct when postsPerPage exceeds the server cap reflected in data.per_page', () => {
		defaultRenderInfo(
			{ meetings: new Array( 100 ), total_entries: 250, current_page: 1, per_page: 100 },
			{ postsPerPage: 999 },
			element,
		);

		expect( element.textContent ).toBe( 'Showing 1 to 100 of 250 entries' );
	} );

	it( 'shows all zeros when there are no entries', () => {
		defaultRenderInfo(
			{ meetings: [], total_entries: 0, current_page: 1, per_page: 20 },
			{ postsPerPage: 20 },
			element,
		);

		expect( element.textContent ).toBe( 'Showing 0 to 0 of 0 entries' );
	} );

	it( 'falls back to instanceCfg.postsPerPage when data.per_page is missing (a template-defined response shape)', () => {
		defaultRenderInfo(
			{ meetings: new Array( 20 ), total_entries: 42, current_page: 2 },
			{ postsPerPage: 20 },
			element,
		);

		expect( element.textContent ).toBe( 'Showing 21 to 40 of 42 entries' );
	} );
} );
