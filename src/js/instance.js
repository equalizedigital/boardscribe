import { defaultRenderInfo } from './defaults/renderInfo';
import { defaultRenderPagination } from './defaults/renderPagination';
import { defaultBuildRequestUrl } from './defaults/request';
import { defaultFocus } from './defaults/focus';

/**
 * Initialises a single BoardScribe instance.
 *
 * @param {HTMLElement} container - The .edbs-boardscribe-wrap element.
 */
export function initInstance( container ) {
	// A malformed data-config must only break its own instance - an
	// uncaught throw here would abort the forEach in the bootstrap and
	// leave every later instance on the page uninitialised.
	let instanceCfg;
	try {
		instanceCfg = JSON.parse( container.dataset.config || '{}' );
	} catch ( error ) {
		// eslint-disable-next-line no-console -- Surface config failures for debugging; there is no other error-reporting mechanism here.
		console.error( 'EDBS: invalid instance config:', error );
		return;
	}
	const id = instanceCfg.instanceId;

	// Cross-initialization generation guard, stored on the container itself
	// rather than a local closure variable: requestSequence below only
	// orders requests *within* one initInstance() call, so it can't stop an
	// older call's in-flight fetch from rendering after a newer call has
	// already replaced it on the same container - a caller that re-inits an
	// already-initialized container in place (rather than tearing it down
	// first) needs exactly that, e.g. the Shortcode Builder's live preview,
	// which re-runs this on every debounced config change without remounting
	// the wrapper (see builder/preview.js).
	container.__edbsGeneration = ( container.__edbsGeneration || 0 ) + 1;
	const generation = container.__edbsGeneration;

	// Scoped off container's own document, not the bare global - container
	// can live inside a different document than this script's own realm
	// (the block editor canvas is iframed by default since WP ~6.3, so
	// window.edbsInitInstance's caller and the elements it needs to find
	// are on opposite sides of that boundary there). Every other consumer
	// (front end, Shortcode Builder) happens to run same-document today,
	// so this is a no-op change for them.
	const ownerDocument = container.ownerDocument || document;
	const tableEl = ownerDocument.getElementById( 'edbs-table-' + id );
	const paginationEl = ownerDocument.getElementById( 'edbs-pagination-' + id );
	const infoEl = ownerDocument.getElementById( 'edbs-info-' + id );

	if ( ! tableEl || ! paginationEl ) {
		return;
	}

	/**
	 * Dispatches a namespaced, bubbling CustomEvent on this instance's
	 * container so add-ons can bind without any build-time coupling to
	 * this bundle (document.addEventListener/container.addEventListener
	 * work with no imports) - the same no-dependency principle as the
	 * window.edbsTemplates/edbsExtraColumns registries. See the event
	 * contract docs in registries.js.
	 *
	 * @param {string} name   - Event name, e.g. "edbs:table-rendered".
	 * @param {Object} detail - Data passed as event.detail.
	 */
	function emit( name, detail ) {
		container.dispatchEvent( new CustomEvent( name, { bubbles: true, detail } ) );
	}

	// Resolve this instance's display template; unknown or missing
	// names fall back to the built-in table.
	const requested = instanceCfg.template;
	const registered = requested && window.edbsTemplates[ requested ];
	const isValidTemplate = !! ( registered && typeof registered.render === 'function' );
	const template = isValidTemplate ? registered : window.edbsTemplates.table;

	// The template name actually rendering, after the fallback above -
	// distinct from instanceCfg.template, which may be blank/unrecognized.
	// Templates use this to add a dedicated "edbs-template-<name>" class to
	// their own root element(s), so a site can target one template's output
	// in CSS without needing the class="" shortcode attribute.
	instanceCfg.resolvedTemplate = isValidTemplate ? requested : 'table';

	// An instance can opt out of URL state entirely (initial page read,
	// pushState on pagination, popstate sync) with urlState: false - the
	// admin builder's live preview uses this so paginating the preview
	// never rewrites the admin URL, and so repeated re-inits attach no
	// window-level listeners. Front-end instances keep the default.
	const urlState = false !== instanceCfg.urlState;

	// Query param name for this instance, e.g. "edbs_page_1".
	const pageParam = 'edbs_page_' + id.replace( 'edbs_', '' );

	// Read the initial page from the URL so shared/bookmarked links work.
	const initParams = new URLSearchParams( window.location.search );
	let currentPage = urlState ? Math.max( 1, parseInt( initParams.get( pageParam ), 10 ) || 1 ) : 1;
	let maxNumPages = 1;

	// Incremented on every fetchMeetings() call and compared against the
	// value captured when each request started - two requests in flight at
	// once (a quick double pagination click, or a popstate firing while a
	// click's request is still pending) can resolve in either order, and
	// without this an older, slower response can render after a newer one,
	// leaving the table showing a different page than the URL/pagination
	// controls say it's on.
	let requestSequence = 0;

	/**
	 * Navigates this instance to the given page: updates the URL and
	 * refetches. Passed to the template's pagination renderer.
	 *
	 * @param {number} targetPage - The 1-based page number to show.
	 */
	function goToPage( targetPage ) {
		targetPage = parseInt( targetPage, 10 );
		if ( isNaN( targetPage ) || targetPage < 1 || targetPage > maxNumPages ) {
			return;
		}
		currentPage = targetPage;
		emit( 'edbs:page-changed', { page: targetPage, instanceCfg } );
		updateUrl( targetPage );
		fetchMeetings( true );
	}

	function renderInstance( data, refocus ) {
		if ( ! data ) {
			return;
		}
		refocus = refocus || false;
		// Tolerate template-defined response shapes that omit
		// max_num_pages - goToPage() (and the out-of-range recovery below)
		// keep the last known bound in that case. parseInt()'s NaN (not a
		// falsy-but-valid 0, which a genuinely empty result set reports)
		// is what actually means "missing" here - `|| maxNumPages` would
		// otherwise also discard a real 0 and keep whatever bound an
		// earlier, non-empty response left behind.
		const parsedMaxNumPages = parseInt( data.max_num_pages, 10 );
		const hasMaxNumPages = ! isNaN( parsedMaxNumPages );
		if ( hasMaxNumPages ) {
			maxNumPages = parsedMaxNumPages;
		}

		// Recovers from a page number that no longer exists - e.g. a
		// bookmarked/shared "?edbs_page_3" URL after enough meetings were
		// removed that the list now fits on one page (no pagination would
		// render at all, table just looks empty), or a stale page past a
		// smaller new max_num_pages (Previous/Next would silently no-op
		// forever). Clamps to the last real page, replaces the stale URL
		// (no new history entry - this isn't a user-initiated navigation),
		// and refetches once. Only fires when the clamp actually changes
		// the page, so it can't loop even when max_num_pages is 0 (no
		// results at all - lastValidPage floors to 1, and a currentPage of
		// 1 is never > 1). Gated on hasMaxNumPages: without a bound this
		// response actually supplied, there's nothing to validate
		// currentPage against yet.
		const lastValidPage = Math.max( 1, maxNumPages );
		if ( hasMaxNumPages && currentPage > lastValidPage ) {
			currentPage = lastValidPage;
			replaceUrl( currentPage );
			fetchMeetings( refocus );
			return;
		}

		template.render( data, instanceCfg, tableEl );
		emit( 'edbs:table-rendered', { data, instanceCfg } );

		// Template renderInfo overrides can't be assumed to null-check the
		// element the way the core default does.
		if ( infoEl ) {
			( template.renderInfo || defaultRenderInfo )( data, instanceCfg, infoEl );
			emit( 'edbs:info-rendered', { data, instanceCfg } );
		}
		( template.renderPagination || defaultRenderPagination )( data, instanceCfg, paginationEl, goToPage );
		emit( 'edbs:pagination-rendered', { data, instanceCfg } );

		// Shift focus into the list when pagination is clicked.
		if ( refocus ) {
			setTimeout( function() {
				( template.focus || defaultFocus )( tableEl, instanceCfg );
			}, 100 );
		}
	}

	function updateUrl( page ) {
		if ( ! urlState ) {
			return;
		}
		const params = new URLSearchParams( window.location.search );
		if ( page <= 1 ) {
			params.delete( pageParam );
		} else {
			params.set( pageParam, page );
		}
		const qs = params.toString();
		history.pushState( { [ pageParam ]: page }, '', qs ? '?' + qs : window.location.pathname );
	}

	// Same URL update as updateUrl(), but via replaceState - used only for
	// the out-of-range recovery in renderInstance(), which isn't a user
	// navigation action and shouldn't add a Back-button stop for a page
	// number that was never actually shown.
	function replaceUrl( page ) {
		if ( ! urlState ) {
			return;
		}
		const params = new URLSearchParams( window.location.search );
		if ( page <= 1 ) {
			params.delete( pageParam );
		} else {
			params.set( pageParam, page );
		}
		const qs = params.toString();
		history.replaceState( { [ pageParam ]: page }, '', qs ? '?' + qs : window.location.pathname );
	}

	// Sync this instance when the user navigates back/forward.
	if ( urlState ) {
		window.addEventListener( 'popstate', function() {
			const params = new URLSearchParams( window.location.search );
			const popped = Math.max( 1, parseInt( params.get( pageParam ), 10 ) || 1 );
			if ( popped !== currentPage ) {
				currentPage = popped;
				fetchMeetings( false );
			}
		} );
	}

	function fetchMeetings( refocus ) {
		refocus = refocus || false;

		// Captured now, compared once this specific request resolves - see
		// requestSequence's own declaration for why (two in-flight requests
		// can resolve out of order).
		const seq = ++requestSequence;

		// A template can take over the request entirely (request), or
		// just point the default fetch elsewhere (buildRequestUrl).
		const request = template.request
			? template.request( instanceCfg, currentPage )
			: fetch( ( template.buildRequestUrl || defaultBuildRequestUrl )( instanceCfg, currentPage ) )
				.then( function( response ) {
					if ( ! response.ok ) {
						throw new Error( 'Network response was not ok: ' + response.statusText );
					}
					return response.json();
				} );

		request
			.then( function( data ) {
				// A newer request has started since this one was sent - an
				// older, slower response must never overwrite what the
				// newer one already rendered (or is about to).
				if ( seq !== requestSequence ) {
					return;
				}
				// A newer initInstance() call on this same container has
				// superseded this whole closure - requestSequence above
				// can't catch this, it only orders requests started by
				// *this* closure's own fetchMeetings().
				if ( container.__edbsGeneration !== generation ) {
					return;
				}
				// Caught separately from the request promise below so a
				// throw from renderInstance() (e.g. a broken template or
				// edbs:table-rendered listener) isn't misreported to
				// consumers as an edbs:fetch-error - the request itself
				// succeeded.
				try {
					renderInstance( data, refocus );
				} catch ( error ) {
					// eslint-disable-next-line no-console -- Surface render failures for debugging; there is no other error-reporting mechanism here.
					console.error( 'EDBS: render error:', error );
				}
			} )
			.catch( function( error ) {
				if ( seq !== requestSequence || container.__edbsGeneration !== generation ) {
					return;
				}
				// eslint-disable-next-line no-console -- Surface fetch failures for debugging; there is no other error-reporting mechanism here.
				console.error( 'EDBS: fetch error:', error );
				emit( 'edbs:fetch-error', { error, instanceCfg } );
			} );
	}

	// Initial load.
	fetchMeetings();
}
