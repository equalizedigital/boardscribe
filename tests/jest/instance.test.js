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
	 * initInstance() attaches a window-level 'popstate' listener with no
	 * matching removal, so a listener from an earlier test in this file
	 * stays live for the rest of the run. A default, shared instanceId
	 * ("edbs_1") would make that leftover listener react to a later test's
	 * own pageParam ("edbs_page_1") too - each test that dispatches
	 * popstate uses its own unique id instead so a stale listener's
	 * unrelated pageParam never matches.
	 *
	 * @param {Document} doc        The document to create elements with.
	 * @param {string}   instanceId Defaults to "edbs_1".
	 * @return {HTMLElement} The wrap element (not yet attached anywhere).
	 */
	function buildWrap( doc, instanceId = 'edbs_1' ) {
		const wrap = doc.createElement( 'div' );
		wrap.className = 'edbs-boardscribe-wrap';
		wrap.dataset.config = JSON.stringify( { instanceId } );

		const table = doc.createElement( 'div' );
		table.id = 'edbs-table-' + instanceId;
		const pagination = doc.createElement( 'div' );
		pagination.id = 'edbs-pagination-' + instanceId;

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

	/**
	 * PRO-1395 #5: a bookmarked/shared "?edbs_page_N" URL, or a page that
	 * no longer exists once the server-side max_num_pages shrinks, must
	 * self-correct instead of rendering an empty table with no way back -
	 * out-of-range clamps to the real last page, replaces the stale URL
	 * (no new history entry), and refetches once.
	 */
	it( 'clamps an out-of-range page to the last valid page and refetches (PRO-1395)', async () => {
		// A dedicated instanceId, distinct from other tests in this file -
		// see buildWrap()'s own docblock for why (its popstate listener is
		// never removed, and a later test dispatches a real popstate event).
		window.history.replaceState( null, '', '?edbs_page_range=5' );
		window.fetch = jest.fn().mockResolvedValue( {
			ok: true,
			json: () => Promise.resolve( { meetings: [], max_num_pages: 1, current_page: 5 } ),
		} );
		const replaceStateSpy = jest.spyOn( window.history, 'replaceState' );

		const { initInstance } = require( '../../src/js/instance' );
		const wrap = buildWrap( document, 'edbs_range' );
		document.body.appendChild( wrap );

		initInstance( wrap );
		await flushPromises();
		await flushPromises();

		// Initial fetch for page 5, then the recovery refetch for page 1.
		expect( window.fetch ).toHaveBeenCalledTimes( 2 );
		expect( replaceStateSpy ).toHaveBeenCalledWith( expect.anything(), '', expect.not.stringContaining( 'edbs_page_range=5' ) );
		expect( window.edbsTemplates.table.render ).toHaveBeenCalledTimes( 1 );

		wrap.remove();
		window.history.replaceState( null, '', window.location.pathname );
		replaceStateSpy.mockRestore();
	} );

	/**
	 * PRO-1395 #4: two requests in flight at once (here, the initial load
	 * still pending when a popstate fires a second fetch) must not let the
	 * older, slower one overwrite what the newer one already rendered -
	 * only the response matching the most recently sent request may render.
	 */
	it( 'ignores a stale response that resolves after a newer request was already sent (PRO-1395)', async () => {
		window.history.replaceState( null, '', window.location.pathname );

		let resolveFirst;
		const firstResponse = new Promise( ( resolve ) => {
			resolveFirst = resolve;
		} );
		window.fetch = jest.fn()
			.mockImplementationOnce( () => firstResponse )
			.mockResolvedValueOnce( {
				ok: true,
				json: () => Promise.resolve( { meetings: [ { title: 'second' } ], max_num_pages: 2, current_page: 2 } ),
			} );

		const { initInstance } = require( '../../src/js/instance' );
		const wrap = buildWrap( document, 'edbs_stale' );
		document.body.appendChild( wrap );

		initInstance( wrap ); // Sends the first (page 1) request; still pending.

		// Simulates the user navigating (back/forward) to page 2 before the
		// first request has resolved - initInstance's own popstate listener
		// sends a second request.
		window.history.replaceState( null, '', '?edbs_page_stale=2' );
		window.dispatchEvent( new PopStateEvent( 'popstate' ) );
		await flushPromises();

		// Only now does the first, older request resolve - after the newer
		// one has already rendered.
		resolveFirst( {
			ok: true,
			json: () => Promise.resolve( { meetings: [ { title: 'first-stale' } ], max_num_pages: 2, current_page: 1 } ),
		} );
		await flushPromises();
		await flushPromises();

		expect( window.edbsTemplates.table.render ).toHaveBeenCalledTimes( 1 );
		expect( window.edbsTemplates.table.render ).toHaveBeenCalledWith(
			expect.objectContaining( { meetings: [ { title: 'second' } ] } ),
			expect.anything(),
			expect.anything(),
		);

		wrap.remove();
		window.history.replaceState( null, '', window.location.pathname );
	} );
} );
