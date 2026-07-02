// Registry for extra columns. Add-ons (e.g. Pro) push column definitions here
// before DOMContentLoaded so they are included in the initial render.
//
// Each entry: { key: string, label: string, renderCell: function(meeting) → string }
// renderCell()'s return value, and label/getLabel()'s return value (used as
// raw column header HTML), are inserted directly as HTML - each must escape
// any untrusted data itself before returning.
window.edmmExtraColumns = window.edmmExtraColumns || [];

// Registry for display templates. Add-ons (e.g. Pro) assign template objects
// here, keyed by template name, before DOMContentLoaded. An instance selects
// its template via the shortcode's template="" attribute; unknown or missing
// names fall back to the built-in "table" template.
//
// Template contract:
//   render( data, instanceCfg, container )                      - required.
//       Render the meetings list into container (data is the REST response).
//   renderPagination( data, instanceCfg, container, goToPage )  - optional.
//       Replace the default pagination controls. Call goToPage( n ) to
//       navigate; core handles URL state and refetching.
//   renderInfo( data, instanceCfg, element )                    - optional.
//       Replace the default aria-live "Showing X to Y of Z" text.
//   focus( container, instanceCfg )                             - optional.
//       Move focus after a pagination-triggered re-render. The default
//       focuses the first [tabindex="0"] element in the container.
//   buildRequestUrl( instanceCfg, page )                        - optional.
//       Return the URL to fetch instead of the default query string -
//       core still performs the fetch, error handling, and JSON parsing.
//   request( instanceCfg, page )                                - optional.
//       Fully replace the request: return a Promise resolving to the data
//       passed to the renderers (different route, POST, multiple requests).
//       Takes precedence over buildRequestUrl. If the resolved data doesn't
//       keep the core shape, override the renderers that consume it; keep
//       numeric max_num_pages/current_page if relying on core pagination
//       or goToPage() navigation.
//
// All rendered output is inserted as raw HTML - templates must escape any
// untrusted data themselves (window.edmmEscapeAttr is available for this).
window.edmmTemplates = window.edmmTemplates || {};

( function() {
	'use strict';

	const cfg = window.edmmConfig || {};
	const i18n = cfg.i18n || {};
	const apiBaseUrl = cfg.apiUrl || '';
	const maxSlots = 7;

	/**
	 * Escapes a string for safe use inside an HTML attribute value.
	 *
	 * @param {string} value - The raw value to escape.
	 * @return {string} The escaped value.
	 */
	function escapeAttr( value ) {
		return String( value )
			.replace( /&/g, '&amp;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' );
	}

	// Exposed so add-on templates/columns can reuse the same vetted escaping
	// instead of re-implementing it (and risking divergence from fixes here).
	window.edmmEscapeAttr = escapeAttr;

	// The built-in "table" template.
	window.edmmTemplates.table = window.edmmTemplates.table || {
		render( data, instanceCfg, container ) {
			// Resolve column labels: instance override → i18n global → hard-coded fallback.
			const labelTitle = instanceCfg.titleLabel || i18n.colTitle || 'Title';
			const labelDate = instanceCfg.dateLabel || i18n.colDate || 'Date';
			const labelAgenda = instanceCfg.agendaLabel || i18n.colAgenda || 'Agenda';
			const labelMinutes = instanceCfg.minutesLabel || i18n.colMinutes || 'Minutes';

			const tableClass = instanceCfg.tableClass || '';
			let table = '<table tabindex="0" class="edmm-meeting-minutes-table ' + tableClass + '">' +
				'<thead class="desktop"><tr>';

			if ( ! instanceCfg.hideTitle ) {
				table += '<th scope="col">' + labelTitle + '</th>';
			}
			if ( ! instanceCfg.hideDate ) {
				table += '<th scope="col">' + labelDate + '</th>';
			}
			if ( ! instanceCfg.hideAgenda ) {
				table += '<th scope="col">' + labelAgenda + '</th>';
			}
			if ( ! instanceCfg.hideMinutes ) {
				table += '<th scope="col">' + labelMinutes + '</th>';
			}

			window.edmmExtraColumns.forEach( function( col ) {
				const hidden = typeof col.hidden === 'function' ? col.hidden( instanceCfg ) : false;
				if ( ! hidden ) {
					const label = typeof col.getLabel === 'function' ? col.getLabel( instanceCfg ) : col.label;
					table += '<th scope="col">' + label + '</th>';
				}
			} );

			table += '</tr></thead><tbody>';

			// Cell content below (meeting.title, meeting.date, meeting.agenda,
			// meeting.minutes, and any Pro-registered renderCell() output) is
			// inserted as trusted, pre-escaped HTML by contract - the REST
			// endpoint escapes title/date server-side, and agenda/minutes are
			// pre-built escaped <a> markup. Any new field or extra-column
			// renderCell() must escape its own output before returning it here.
			//
			// Tolerate malformed responses (e.g. an edmm_rest_response filter
			// emptying the payload) rather than throwing mid-render.
			const meetings = ( data && Array.isArray( data.meetings ) ) ? data.meetings : [];
			meetings.forEach( function( meeting ) {
				table += '<tr>';
				if ( ! instanceCfg.hideTitle ) {
					table += '<td data-label="' + escapeAttr( labelTitle ) + '" scope="row">' + meeting.title + '</td>';
				}
				if ( ! instanceCfg.hideDate ) {
					table += '<td data-label="' + escapeAttr( labelDate ) + '">' + meeting.date + '</td>';
				}
				if ( ! instanceCfg.hideAgenda ) {
					table += '<td data-label="' + escapeAttr( labelAgenda ) + '">' + meeting.agenda + '</td>';
				}
				if ( ! instanceCfg.hideMinutes ) {
					table += '<td data-label="' + escapeAttr( labelMinutes ) + '">' + meeting.minutes + '</td>';
				}

				window.edmmExtraColumns.forEach( function( col ) {
					const hidden = typeof col.hidden === 'function' ? col.hidden( instanceCfg ) : false;
					if ( ! hidden ) {
						const label = typeof col.getLabel === 'function' ? col.getLabel( instanceCfg ) : col.label;
						const cell = col.renderCell ? col.renderCell( meeting, instanceCfg ) : ( meeting[ col.key ] || '' );
						table += '<td data-label="' + escapeAttr( label ) + '">' + cell + '</td>';
					}
				} );

				table += '</tr>';
			} );

			table += '</tbody></table>';
			container.innerHTML = table;
		},
	};

	/**
	 * Updates the off-screen aria-live region with pagination info.
	 * Default implementation, used when a template doesn't override renderInfo.
	 *
	 * @param {Object}      data        - The REST response data.
	 * @param {Object}      instanceCfg - The per-instance configuration.
	 * @param {HTMLElement} element     - The aria-live info element.
	 */
	function defaultRenderInfo( data, instanceCfg, element ) {
		if ( ! element ) {
			return;
		}

		const postsPerPage = instanceCfg.postsPerPage || 20;
		const totalEntries = data.total_entries || 0;
		const startEntry = totalEntries === 0 ? 0 : ( ( data.current_page - 1 ) * postsPerPage ) + 1;
		const endEntry = Math.min( data.current_page * postsPerPage, totalEntries );

		const template = i18n.showingEntries || 'Showing %1$s to %2$s of %3$s entries';
		element.textContent = template
			.replace( '%1$s', startEntry )
			.replace( '%2$s', endEntry )
			.replace( '%3$s', totalEntries );
	}

	/**
	 * Builds the default pagination controls and wires them to goToPage().
	 * Used when a template doesn't override renderPagination.
	 *
	 * @param {Object}      data        - The REST response data.
	 * @param {Object}      instanceCfg - The per-instance configuration.
	 * @param {HTMLElement} element     - The pagination container element.
	 * @param {Function}    goToPage    - Navigates to the given page number.
	 */
	function defaultRenderPagination( data, instanceCfg, element, goToPage ) {
		let pagination = '';
		if ( data.max_num_pages > 1 ) {
			pagination += '<nav aria-label="' + ( i18n.pagination || 'Pagination' ) + '">' +
				'<div class="edmm-pagination-buttons">';

			if ( data.current_page > 1 ) {
				pagination += '<button type="button" class="edmm-pagination-button prev"' +
					' aria-label="' + ( i18n.previousPage || 'Previous Page' ) + '"' +
					' data-page="' + ( data.current_page - 1 ) + '">' +
					( i18n.previous || 'Previous' ) +
					'</button>';
			}

			const slots = calculatePaginationSlots( data.current_page, data.max_num_pages );

			slots.forEach( function( slot ) {
				if ( slot === '...' ) {
					pagination += '<span class="pagination-ellipsis">...</span>';
				} else {
					const isCurrent = slot === data.current_page;
					const label = ( i18n.pageNum || 'Page %s' ).replace( '%s', slot );
					pagination += '<button type="button"' +
						' class="edmm-pagination-button' + ( isCurrent ? ' current' : '' ) + '"' +
						' data-page="' + slot + '"' +
						' aria-label="' + label + '"' +
						( isCurrent ? ' aria-current="true"' : '' ) +
						'>' + slot + '</button>';
				}
			} );

			if ( data.current_page < data.max_num_pages ) {
				pagination += '<button type="button" class="edmm-pagination-button next"' +
					' aria-label="' + ( i18n.nextPage || 'Next Page' ) + '"' +
					' data-page="' + ( data.current_page + 1 ) + '">' +
					( i18n.next || 'Next' ) +
					'</button>';
			}

			pagination += '</div></nav>';
		}

		element.innerHTML = pagination;

		// Attach click handlers to all pagination buttons in this instance.
		element.querySelectorAll( 'button' ).forEach( function( button ) {
			button.addEventListener( 'click', function( e ) {
				e.preventDefault();
				goToPage( parseInt( this.getAttribute( 'data-page' ), 10 ) );
			} );
		} );
	}

	/**
	 * Builds the default REST request URL for a page of meetings.
	 * Used when a template doesn't override buildRequestUrl or request.
	 *
	 * @param {Object} instanceCfg - The per-instance configuration.
	 * @param {number} page        - The 1-based page number to request.
	 * @return {string} The URL to fetch.
	 */
	function defaultBuildRequestUrl( instanceCfg, page ) {
		return apiBaseUrl +
			'?included_years=' + encodeURIComponent( instanceCfg.includedYears || '' ) +
			'&held_date_format=' + encodeURIComponent( instanceCfg.heldDateFormat || 'Y/m/d' ) +
			'&not_held_date_format=' + encodeURIComponent( instanceCfg.notHeldDateFormat || 'Y/m' ) +
			'&posts_per_page=' + encodeURIComponent( instanceCfg.postsPerPage || 20 ) +
			'&agenda_link_label=' + encodeURIComponent( instanceCfg.agendaLinkLabel || '' ) +
			'&minutes_link_label=' + encodeURIComponent( instanceCfg.minutesLinkLabel || '' ) +
			'&category=' + encodeURIComponent( instanceCfg.category || '' ) +
			'&page=' + encodeURIComponent( page );
	}

	/**
	 * Moves focus into the rendered list after a pagination click.
	 *
	 * @param {HTMLElement} container - The list container element.
	 */
	function defaultFocus( container ) {
		const target = container.querySelector( '[tabindex="0"]' );
		if ( target ) {
			target.focus();
		}
	}

	function calculatePaginationSlots( current, total ) {
		const slots = [ 1 ];

		if ( total <= maxSlots ) {
			for ( let i = 2; i <= total; i++ ) {
				slots.push( i );
			}
			return slots;
		}

		if ( current > 3 ) {
			slots.push( '...' );
		}

		const start = Math.max( 2, current - 1 );
		const end = Math.min( current + 1, total - 1 );

		for ( let i = start; i <= end; i++ ) {
			slots.push( i );
		}

		if ( current < total - 2 ) {
			slots.push( '...' );
		}

		slots.push( total );

		return slots;
	}

	/**
	 * Initialises a single meeting minutes instance.
	 *
	 * @param {HTMLElement} container - The .edmm-meeting-minutes-wrap element.
	 */
	function initInstance( container ) {
		const instanceCfg = JSON.parse( container.dataset.config || '{}' );
		const id = instanceCfg.instanceId;

		const tableEl = document.getElementById( 'edmm-table-' + id );
		const paginationEl = document.getElementById( 'edmm-pagination-' + id );
		const infoEl = document.getElementById( 'edmm-info-' + id );

		if ( ! tableEl || ! paginationEl ) {
			return;
		}

		// Resolve this instance's display template; unknown or missing
		// names fall back to the built-in table.
		const requested = instanceCfg.template;
		const registered = requested && window.edmmTemplates[ requested ];
		const template = ( registered && typeof registered.render === 'function' )
			? registered
			: window.edmmTemplates.table;

		// Query param name for this instance, e.g. "edmm_page_1".
		const pageParam = 'edmm_page_' + id.replace( 'edmm_', '' );

		// Read the initial page from the URL so shared/bookmarked links work.
		const initParams = new URLSearchParams( window.location.search );
		let currentPage = parseInt( initParams.get( pageParam ), 10 ) || 1;
		let maxNumPages = 1;

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
			updateUrl( targetPage );
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

			template.render( data, instanceCfg, tableEl );
			( template.renderInfo || defaultRenderInfo )( data, instanceCfg, infoEl );
			( template.renderPagination || defaultRenderPagination )( data, instanceCfg, paginationEl, goToPage );

			// Shift focus into the list when pagination is clicked.
			if ( refocus ) {
				setTimeout( function() {
					( template.focus || defaultFocus )( tableEl, instanceCfg );
				}, 100 );
			}
		}

		function updateUrl( page ) {
			const params = new URLSearchParams( window.location.search );
			if ( page <= 1 ) {
				params.delete( pageParam );
			} else {
				params.set( pageParam, page );
			}
			const qs = params.toString();
			history.pushState( { [ pageParam ]: page }, '', qs ? '?' + qs : window.location.pathname );
		}

		// Sync this instance when the user navigates back/forward.
		window.addEventListener( 'popstate', function() {
			const params = new URLSearchParams( window.location.search );
			const popped = parseInt( params.get( pageParam ), 10 ) || 1;
			if ( popped !== currentPage ) {
				currentPage = popped;
				fetchMeetings( false );
			}
		} );

		function fetchMeetings( refocus ) {
			refocus = refocus || false;

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
					renderInstance( data, refocus );
				} )
				.catch( function( error ) {
					// eslint-disable-next-line no-console -- Surface fetch failures for debugging; there is no other error-reporting mechanism here.
					console.error( 'EDMM: fetch error:', error );
				} );
		}

		// Initial load.
		fetchMeetings();
	}

	// Initialise all instances when the DOM is ready.
	document.addEventListener( 'DOMContentLoaded', function() {
		document.querySelectorAll( '.edmm-meeting-minutes-wrap' ).forEach( initInstance );
	} );
}() );
