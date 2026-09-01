import { defaultRenderInfo } from './defaults/renderInfo';
import { defaultRenderPagination } from './defaults/renderPagination';
import { defaultRenderYearSwitcher } from './defaults/renderYearSwitcher';
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

	const tableEl = document.getElementById( 'edbs-table-' + id );
	const paginationEl = document.getElementById( 'edbs-pagination-' + id );
	const infoEl = document.getElementById( 'edbs-info-' + id );
	const yearSwitcherEl = document.getElementById( 'edbs-year-switcher-' + id );

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

	// Query param names for this instance, e.g. "edbs_page_1"/"edbs_year_1".
	const pageParam = 'edbs_page_' + id.replace( 'edbs_', '' );
	const yearParam = 'edbs_year_' + id.replace( 'edbs_', '' );

	// Read the initial page/year from the URL so shared/bookmarked links work.
	const initParams = new URLSearchParams( window.location.search );
	let currentPage = urlState ? Math.max( 1, parseInt( initParams.get( pageParam ), 10 ) || 1 ) : 1;
	// Defaults to the newest available year once known - see renderInstance().
	let currentYear = urlState ? ( parseInt( initParams.get( yearParam ), 10 ) || null ) : null;
	let maxNumPages = 1;
	// Only the latest fetchMeetings() call's response is allowed to render.
	let latestRequestId = 0;

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
		updateUrl();
		fetchMeetings( true );
	}

	/**
	 * Navigates this instance to the given year: resets to page 1, updates
	 * the URL, and refetches. Passed to the template's year-switcher
	 * renderer. Only relevant when instanceCfg.yearView is true.
	 *
	 * @param {number} targetYear - The calendar year to show.
	 */
	function goToYear( targetYear ) {
		targetYear = parseInt( targetYear, 10 );
		if ( isNaN( targetYear ) ) {
			return;
		}
		currentYear = targetYear;
		instanceCfg.currentYear = currentYear;
		currentPage = 1;
		emit( 'edbs:year-changed', { year: targetYear, instanceCfg } );
		updateUrl();
		fetchMeetings( true );
	}

	function renderInstance( data, refocus ) {
		if ( ! data ) {
			return;
		}
		refocus = refocus || false;
		// Tolerate template-defined response shapes that omit
		// max_num_pages - goToPage() keeps its last known bound.
		maxNumPages = parseInt( data.max_num_pages, 10 ) || maxNumPages;

		if ( instanceCfg.yearView && Array.isArray( data.available_years ) && data.available_years.length ) {
			// No year selected yet, or a stale/deleted year from the URL -
			// fall back to the newest available year and refetch scoped to it.
			if ( ! currentYear || -1 === data.available_years.indexOf( currentYear ) ) {
				currentYear = data.available_years[ 0 ];
				instanceCfg.currentYear = currentYear;
				currentPage = 1;
				updateUrl();
				fetchMeetings( refocus );
				return;
			}
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

		if ( instanceCfg.yearView && yearSwitcherEl ) {
			( template.renderYearSwitcher || defaultRenderYearSwitcher )( data, instanceCfg, yearSwitcherEl, goToYear );
			emit( 'edbs:year-switcher-rendered', { data, instanceCfg } );
		}

		// Shift focus into the list when pagination is clicked.
		if ( refocus ) {
			setTimeout( function() {
				( template.focus || defaultFocus )( tableEl, instanceCfg );
			}, 100 );
		}
	}

	function updateUrl() {
		if ( ! urlState ) {
			return;
		}
		const params = new URLSearchParams( window.location.search );
		if ( currentPage <= 1 ) {
			params.delete( pageParam );
		} else {
			params.set( pageParam, currentPage );
		}
		if ( instanceCfg.yearView && currentYear ) {
			params.set( yearParam, currentYear );
		} else {
			params.delete( yearParam );
		}
		const qs = params.toString();
		history.pushState( { [ pageParam ]: currentPage, [ yearParam ]: currentYear }, '', qs ? '?' + qs : window.location.pathname );
	}

	// Sync this instance when the user navigates back/forward.
	if ( urlState ) {
		window.addEventListener( 'popstate', function() {
			const params = new URLSearchParams( window.location.search );
			const popped = Math.max( 1, parseInt( params.get( pageParam ), 10 ) || 1 );
			const poppedYear = parseInt( params.get( yearParam ), 10 ) || null;
			if ( popped !== currentPage || poppedYear !== currentYear ) {
				currentPage = popped;
				currentYear = poppedYear;
				instanceCfg.currentYear = currentYear;
				fetchMeetings( false );
			}
		} );
	}

	function fetchMeetings( refocus ) {
		refocus = refocus || false;
		instanceCfg.currentYear = currentYear;
		const requestId = ++latestRequestId;

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
				if ( requestId !== latestRequestId ) {
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
				if ( requestId !== latestRequestId ) {
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
