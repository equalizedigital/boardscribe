import { classifyResourceUrl, resolveResourceTitle } from '../../../src/js/metabox/resource-utils';

describe( 'classifyResourceUrl', () => {
	it( 'returns no chips/meta for an empty value', () => {
		expect( classifyResourceUrl( '' ) ).toEqual( { chips: [], meta: '' } );
	} );

	it( 'classifies a same-origin URL as Media Library, with the filename as meta', () => {
		const url = `${ window.location.origin }/wp-content/uploads/2025/05/agenda.pdf`;
		const result = classifyResourceUrl( url );

		expect( result.chips ).toEqual( [ 'Media Library' ] );
		expect( result.meta ).toBe( 'agenda.pdf' );
	} );

	it( 'classifies a same-origin video file as Media Library video', () => {
		const url = `${ window.location.origin }/wp-content/uploads/2025/05/recording.mp4`;
		const result = classifyResourceUrl( url );

		expect( result.chips ).toEqual( [ 'Media Library video' ] );
	} );

	it( 'classifies a cross-origin URL as External URL, with host+path as meta', () => {
		const result = classifyResourceUrl( 'https://example.org/agendas/2025-05-13.pdf' );

		expect( result.chips ).toEqual( [ 'External URL' ] );
		expect( result.meta ).toBe( 'example.org/agendas/2025-05-13.pdf' );
	} );

	it( 'treats an unparseable value as an external URL rather than throwing', () => {
		const result = classifyResourceUrl( 'not a url' );

		expect( result.chips ).toEqual( [ 'External URL' ] );
		expect( result.meta ).toBe( 'not a url' );
	} );
} );

describe( 'resolveResourceTitle', () => {
	it( 'returns a static template as-is', () => {
		const field = { label: 'Livestream', titleTemplate: 'Live meeting stream' };

		expect( resolveResourceTitle( field, '2025-05-13' ) ).toBe( 'Live meeting stream' );
	} );

	it( 'resolves {date} against the meeting date', () => {
		const field = { label: 'Agenda', titleTemplate: '{date} Board Meeting Agenda' };

		expect( resolveResourceTitle( field, '2025-05-13' ) ).toBe( 'May 13, 2025 Board Meeting Agenda' );
	} );

	it( 'falls back to the field label when no meeting date is set yet', () => {
		const field = { label: 'Agenda', titleTemplate: '{date} Board Meeting Agenda' };

		expect( resolveResourceTitle( field, '' ) ).toBe( 'Agenda' );
	} );

	it( 'falls back to the field label for an unparseable date', () => {
		const field = { label: 'Agenda', titleTemplate: '{date} Board Meeting Agenda' };

		expect( resolveResourceTitle( field, 'not-a-date' ) ).toBe( 'Agenda' );
	} );

	it( 'falls back to the label when no titleTemplate is set', () => {
		const field = { label: 'Agenda' };

		expect( resolveResourceTitle( field, '2025-05-13' ) ).toBe( 'Agenda' );
	} );
} );
