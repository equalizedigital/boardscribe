import { classifyResourceUrl, isValidExternalUrl, resolveResourceDisplay, resolveResourceTitle } from '../../../src/js/metabox/resource-utils';

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

describe( 'resolveResourceDisplay', () => {
	afterEach( () => {
		delete window.edbsResourceSources;
	} );

	it( 'uses a plugin-registered source\'s chipLabel for the chip, not its modal-chooser label', () => {
		window.edbsResourceSources = {
			document: {
				label: 'Choose a BoardScribe document',
				chipLabel: 'BoardScribe document',
				description: '',
				render: () => null,
			},
		};

		const result = resolveResourceDisplay( 'https://example.com/agenda/2025-05-13/', 'document' );

		expect( result.chips ).toEqual( [ 'BoardScribe document' ] );
	} );

	it( "falls back to a plugin-registered source's label when it registers no chipLabel", () => {
		window.edbsResourceSources = {
			document: {
				label: 'Choose a BoardScribe document',
				description: '',
				render: () => null,
			},
		};

		const result = resolveResourceDisplay( 'https://example.com/agenda/2025-05-13/', 'document' );

		expect( result.chips ).toEqual( [ 'Choose a BoardScribe document' ] );
	} );

	it( 'falls back to the raw source id when nothing is registered for it', () => {
		const result = resolveResourceDisplay( 'https://example.com/agenda.pdf', 'unregistered_source' );

		expect( result.chips ).toEqual( [ 'unregistered_source' ] );
	} );
} );

describe( 'isValidExternalUrl', () => {
	it( 'rejects plain non-URL text', () => {
		expect( isValidExternalUrl( 'not a url' ) ).toBe( false );
	} );

	it( 'rejects an incomplete "https://" with no host - the unedited default value', () => {
		expect( isValidExternalUrl( 'https://' ) ).toBe( false );
	} );

	it( 'rejects an incomplete "http://" with no host', () => {
		expect( isValidExternalUrl( 'http://' ) ).toBe( false );
	} );

	it( 'rejects an empty value', () => {
		expect( isValidExternalUrl( '' ) ).toBe( false );
	} );

	it( 'rejects a non-http(s) scheme', () => {
		expect( isValidExternalUrl( 'javascript:alert(1)' ) ).toBe( false );
		expect( isValidExternalUrl( 'ftp://example.com/file.pdf' ) ).toBe( false );
	} );

	it( 'rejects a host containing spaces (PRO-1410) - new URL() percent-encodes them instead of throwing', () => {
		expect( isValidExternalUrl( 'https://not a valid url' ) ).toBe( false );
		expect( isValidExternalUrl( 'https://exa mple.com/agenda.pdf' ) ).toBe( false );
		expect( isValidExternalUrl( 'https://not%20a%20valid%20url' ) ).toBe( false );
	} );

	it( 'rejects hosts with characters that cannot appear in a hostname', () => {
		expect( isValidExternalUrl( 'https://exa<mple.com' ) ).toBe( false );
		expect( isValidExternalUrl( 'https://-example.com' ) ).toBe( false );
		expect( isValidExternalUrl( 'https://example..com' ) ).toBe( false );
	} );

	it( 'accepts single-label, IPv4, IPv6 and punycoded hosts, with port, userinfo, path and query', () => {
		expect( isValidExternalUrl( 'http://localhost:8080/agenda' ) ).toBe( true );
		expect( isValidExternalUrl( 'http://192.168.1.10/agenda.pdf' ) ).toBe( true );
		expect( isValidExternalUrl( 'http://[::1]:3000/agenda' ) ).toBe( true );
		expect( isValidExternalUrl( 'https://b\u00fccher.de/agenda' ) ).toBe( true );
		expect( isValidExternalUrl( 'https://user:pw@sub-domain.example.com/a b?x=1#y' ) ).toBe( true );
		expect( isValidExternalUrl( 'https://my_bucket.example.com/agenda.pdf' ) ).toBe( true );
	} );

	it( 'does not throw on a non-string value', () => {
		expect( isValidExternalUrl( undefined ) ).toBe( false );
		expect( isValidExternalUrl( null ) ).toBe( false );
	} );

	it( 'accepts a complete https URL', () => {
		expect( isValidExternalUrl( 'https://example.com/agenda.pdf' ) ).toBe( true );
	} );

	it( 'accepts a complete http URL', () => {
		expect( isValidExternalUrl( 'http://example.com/agenda.pdf' ) ).toBe( true );
	} );

	it( 'trims surrounding whitespace before validating', () => {
		expect( isValidExternalUrl( '  https://example.com  ' ) ).toBe( true );
	} );
} );
