/**
 * PRO-1331 regression coverage: initInstance() must resolve its
 * table/pagination/info elements off container's own document, not the
 * bare global `document` - the block editor canvas is iframed by default
 * (WP ~6.3+), so a container mounted there lives in a different document
 * than the top-level script realm running this bundle. Before this fix,
 * `document.getElementById(...)` would find nothing for such a container,
 * and initInstance() would silently no-op (its own early-return guard, no
 * error) - the block's "live" preview would just be a permanently empty
 * box. Both cases (same document, and a genuinely different one, standing
 * in for the iframe boundary) are covered so a regression in either
 * direction fails loudly.
 */
describe( 'initInstance', () => {
	beforeEach( () => {
		jest.resetModules();
		window.edbsTemplates = {
			table: {
				render: jest.fn(),
			},
		};
		window.fetch = jest.fn().mockResolvedValue( {
			ok: true,
			json: () => Promise.resolve( { meetings: [], max_num_pages: 1 } ),
		} );
	} );

	afterEach( () => {
		delete window.edbsTemplates;
		delete window.fetch;
	} );

	/**
	 * Flushes pending microtasks (the fetch().then(json).then(render)
	 * chain is several promise hops deep) via a macrotask boundary - more
	 * reliable than a fixed number of bare `await Promise.resolve()` hops.
	 *
	 * @return {Promise<void>}
	 */
	function flushPromises() {
		return new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
	}

	/**
	 * Builds the minimal .edbs-boardscribe-wrap markup initInstance()
	 * expects, created via the given document so it can be mounted either
	 * in the real global document or a detached stand-in for an iframed one.
	 *
	 * @param {Document} doc The document to create elements with.
	 * @return {HTMLElement} The wrap element (not yet attached anywhere).
	 */
	function buildWrap( doc ) {
		const wrap = doc.createElement( 'div' );
		wrap.className = 'edbs-boardscribe-wrap';
		wrap.dataset.config = JSON.stringify( { instanceId: 'edbs_1' } );

		const table = doc.createElement( 'div' );
		table.id = 'edbs-table-edbs_1';
		const pagination = doc.createElement( 'div' );
		pagination.id = 'edbs-pagination-edbs_1';

		wrap.appendChild( table );
		wrap.appendChild( pagination );
		return wrap;
	}

	it( 'finds its elements and renders when the container lives in the global document (the front end, the Shortcode Builder)', async () => {
		const { initInstance } = require( '../../src/js/instance' );
		const wrap = buildWrap( document );
		document.body.appendChild( wrap );

		initInstance( wrap );
		await flushPromises();

		expect( window.edbsTemplates.table.render ).toHaveBeenCalledTimes( 1 );

		wrap.remove();
	} );

	it( 'finds its elements and renders when the container lives in a different document (standing in for the iframed block editor canvas)', async () => {
		const { initInstance } = require( '../../src/js/instance' );
		const otherDocument = document.implementation.createHTMLDocument( 'iframe-canvas' );
		const wrap = buildWrap( otherDocument );
		otherDocument.body.appendChild( wrap );

		initInstance( wrap );
		await flushPromises();

		expect( window.edbsTemplates.table.render ).toHaveBeenCalledTimes( 1 );
	} );
} );
