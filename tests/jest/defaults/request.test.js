/**
 * config.js reads window.edbsConfig once at module-load time, so each
 * test needs a fresh module registry after setting window.edbsConfig -
 * requiring inside each test (after jest.resetModules()) rather than a
 * single top-level import.
 */
describe( 'defaultBuildRequestUrl', () => {
	beforeEach( () => {
		jest.resetModules();
	} );

	it( 'forwards every field in edbsConfig.restArgMap using its instance-config value', () => {
		window.edbsConfig = {
			apiUrl: 'https://example.com/wp-json/edbs/v1/boardscribe/',
			restArgMap: [
				{ key: 'included_years', configKey: 'includedYears' },
				{ key: 'order', configKey: 'order' },
			],
		};
		const { defaultBuildRequestUrl } = require( '../../../src/js/defaults/request' );

		const url = new URL( defaultBuildRequestUrl( { includedYears: '2024,2025', order: 'asc' }, 1 ) );

		expect( url.searchParams.get( 'included_years' ) ).toBe( '2024,2025' );
		expect( url.searchParams.get( 'order' ) ).toBe( 'asc' );
		expect( url.searchParams.get( 'page' ) ).toBe( '1' );

		delete window.edbsConfig;
	} );

	it( 'forwards a Pro-registered field (e.g. category) with no changes to this file', () => {
		window.edbsConfig = {
			apiUrl: 'https://example.com/wp-json/edbs/v1/boardscribe/',
			restArgMap: [
				{ key: 'category', configKey: 'category' },
			],
		};
		const { defaultBuildRequestUrl } = require( '../../../src/js/defaults/request' );

		const url = new URL( defaultBuildRequestUrl( { category: 'finance' }, 1 ) );

		expect( url.searchParams.get( 'category' ) ).toBe( 'finance' );

		delete window.edbsConfig;
	} );

	it( 'falls back to the free plugin\'s own core rest_arg fields when restArgMap was not localized', () => {
		window.edbsConfig = { apiUrl: 'https://example.com/wp-json/edbs/v1/boardscribe/' };
		const { defaultBuildRequestUrl } = require( '../../../src/js/defaults/request' );

		const url = new URL( defaultBuildRequestUrl( { includedYears: '2024' }, 1 ) );

		expect( url.searchParams.get( 'included_years' ) ).toBe( '2024' );
		expect( url.searchParams.get( 'posts_per_page' ) ).toBe( '' );

		delete window.edbsConfig;
	} );

	it( 'defaults an unset field to an empty string rather than the literal "undefined"', () => {
		window.edbsConfig = {
			apiUrl: 'https://example.com/wp-json/edbs/v1/boardscribe/',
			restArgMap: [ { key: 'start_date', configKey: 'startDate' } ],
		};
		const { defaultBuildRequestUrl } = require( '../../../src/js/defaults/request' );

		const url = new URL( defaultBuildRequestUrl( {}, 1 ) );

		expect( url.searchParams.get( 'start_date' ) ).toBe( '' );

		delete window.edbsConfig;
	} );
} );
