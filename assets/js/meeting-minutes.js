( function () {
	'use strict';

	const cfg  = window.edmmConfig || {};
	const i18n = cfg.i18n || {};
	const apiBaseUrl = cfg.apiUrl || '';

	/**
	 * Initialises a single meeting minutes table instance.
	 *
	 * @param {HTMLElement} container - The .edmm-meeting-minutes-wrap element.
	 */
	function initInstance( container ) {
		const instanceCfg = JSON.parse( container.dataset.config || '{}' );
		const id          = instanceCfg.instanceId;

		const tableEl      = document.getElementById( 'edmm-table-' + id );
		const paginationEl = document.getElementById( 'edmm-pagination-' + id );
		const infoEl       = document.getElementById( 'edmm-info-' + id );

		if ( ! tableEl || ! paginationEl ) {
			return;
		}

		// Query param name for this instance, e.g. "edmm_page_1".
		const pageParam = 'edmm_page_' + id.replace( 'edmm_', '' );

		// Read the initial page from the URL so shared/bookmarked links work.
		const initParams = new URLSearchParams( window.location.search );
		let currentPage  = parseInt( initParams.get( pageParam ), 10 ) || 1;

		const maxSlots   = 7;
		const postsPerPage = instanceCfg.postsPerPage || 20;

		// Build the base API URL for this instance.
		const apiUrl = apiBaseUrl
			+ '?included_years=' + encodeURIComponent( instanceCfg.includedYears || '' )
			+ '&held_date_format=' + encodeURIComponent( instanceCfg.heldDateFormat || 'Y/m/d' )
			+ '&not_held_date_format=' + encodeURIComponent( instanceCfg.notHeldDateFormat || 'Y/m' )
			+ '&posts_per_page=' + encodeURIComponent( postsPerPage )
			+ '&agenda_link_label=' + encodeURIComponent( instanceCfg.agendaLinkLabel || '' )
			+ '&minutes_link_label=' + encodeURIComponent( instanceCfg.minutesLinkLabel || '' )
			+ '&category=' + encodeURIComponent( instanceCfg.category || '' );

		// ----------------------------------------------------------------
		// Rendering
		// ----------------------------------------------------------------

		// Resolve column labels: instance override → i18n global → hard-coded fallback.
		const labelTitle  = instanceCfg.titleLabel  || i18n.colTitle  || 'Title';
		const labelDate   = instanceCfg.dateLabel   || i18n.colDate   || 'Date';
		const labelAgenda = instanceCfg.agendaLabel || i18n.colAgenda || 'Agenda';
		const labelMinutes = instanceCfg.minutesLabel || i18n.colMinutes || 'Minutes';

		function renderTable( data, refocus ) {
			refocus = refocus || false;

			const tableClass = instanceCfg.tableClass || '';
			let table = '<table tabindex="0" class="edmm-meeting-minutes-table ' + tableClass + '">'
				+ '<thead class="desktop"><tr>';

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

			table += '</tr></thead><tbody>';

			data.meetings.forEach( function ( meeting ) {
				table += '<tr>';
				if ( ! instanceCfg.hideTitle ) {
					table += '<td data-label="' + labelTitle + '" scope="row">' + meeting.title + '</td>';
				}
				if ( ! instanceCfg.hideDate ) {
					table += '<td data-label="' + labelDate + '">' + meeting.date + '</td>';
				}
				if ( ! instanceCfg.hideAgenda ) {
					table += '<td data-label="' + labelAgenda + '">' + meeting.agenda + '</td>';
				}
				if ( ! instanceCfg.hideMinutes ) {
					table += '<td data-label="' + labelMinutes + '">' + meeting.minutes + '</td>';
				}
				table += '</tr>';
			} );

			table += '</tbody></table>';
			tableEl.innerHTML = table;

			// Update the off-screen aria-live region with pagination info.
			const totalEntries = data.total_entries;
			const startEntry   = ( currentPage - 1 ) * postsPerPage + 1;
			const endEntry     = Math.min( currentPage * postsPerPage, totalEntries );

			if ( infoEl ) {
				const template = i18n.showingEntries || 'Showing %1$s to %2$s of %3$s entries';
				infoEl.textContent = template
					.replace( '%1$s', startEntry )
					.replace( '%2$s', endEntry )
					.replace( '%3$s', totalEntries );
			}

			// Build pagination controls.
			let pagination = '';
			if ( data.max_num_pages > 1 ) {
				pagination += '<nav aria-label="' + ( i18n.pagination || 'Pagination' ) + '">'
					+ '<div class="edmm-pagination-buttons">';

				if ( data.current_page > 1 ) {
					pagination += '<button type="button" class="edmm-pagination-button prev"'
						+ ' aria-label="' + ( i18n.previousPage || 'Previous Page' ) + '"'
						+ ' data-page="' + ( data.current_page - 1 ) + '">'
						+ ( i18n.previous || 'Previous' )
						+ '</button>';
				}

				const slots = calculatePaginationSlots( data.current_page, data.max_num_pages );

				slots.forEach( function ( slot ) {
					if ( slot === '...' ) {
						pagination += '<span class="pagination-ellipsis">...</span>';
					} else {
						const isCurrent = slot === data.current_page;
						const label     = ( i18n.pageNum || 'Page %s' ).replace( '%s', slot );
						pagination += '<button type="button"'
							+ ' class="edmm-pagination-button' + ( isCurrent ? ' current' : '' ) + '"'
							+ ' data-page="' + slot + '"'
							+ ' aria-label="' + label + '"'
							+ ( isCurrent ? ' aria-current="true"' : '' )
							+ '>' + slot + '</button>';
					}
				} );

				if ( data.current_page < data.max_num_pages ) {
					pagination += '<button type="button" class="edmm-pagination-button next"'
						+ ' aria-label="' + ( i18n.nextPage || 'Next Page' ) + '"'
						+ ' data-page="' + ( data.current_page + 1 ) + '">'
						+ ( i18n.next || 'Next' )
						+ '</button>';
				}

				pagination += '</div></nav>';
			}

			paginationEl.innerHTML = pagination;

			// Attach click handlers to all pagination buttons in this instance.
			paginationEl.querySelectorAll( 'button' ).forEach( function ( button ) {
				button.addEventListener( 'click', function ( e ) {
					e.preventDefault();
					const targetPage = parseInt( this.getAttribute( 'data-page' ), 10 );
					if ( ! isNaN( targetPage ) && targetPage >= 1 && targetPage <= data.max_num_pages ) {
						currentPage = targetPage;
						updateUrl( targetPage );
						fetchMeetings( true );
					}
				} );
			} );

			// Shift focus to the table when pagination is clicked.
			if ( refocus ) {
				setTimeout( function () {
					const tbl = tableEl.querySelector( '.edmm-meeting-minutes-table' );
					if ( tbl ) {
						tbl.focus();
					}
				}, 100 );
			}
		}

		// ----------------------------------------------------------------
		// Pagination slot calculation
		// ----------------------------------------------------------------

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
			const end   = Math.min( current + 1, total - 1 );

			for ( let i = start; i <= end; i++ ) {
				slots.push( i );
			}

			if ( current < total - 2 ) {
				slots.push( '...' );
			}

			slots.push( total );

			return slots;
		}

		// ----------------------------------------------------------------
		// URL state
		// ----------------------------------------------------------------

		function updateUrl( page ) {
			const params = new URLSearchParams( window.location.search );
			if ( page <= 1 ) {
				params.delete( pageParam );
			} else {
				params.set( pageParam, page );
			}
			const qs = params.toString();
			history.pushState( { [pageParam]: page }, '', qs ? '?' + qs : window.location.pathname );
		}

		// Sync this instance when the user navigates back/forward.
		window.addEventListener( 'popstate', function () {
			const params = new URLSearchParams( window.location.search );
			const popped = parseInt( params.get( pageParam ), 10 ) || 1;
			if ( popped !== currentPage ) {
				currentPage = popped;
				fetchMeetings( false );
			}
		} );

		// ----------------------------------------------------------------
		// Data fetching
		// ----------------------------------------------------------------

		function fetchMeetings( refocus ) {
			refocus = refocus || false;

			const url = apiUrl + '&page=' + encodeURIComponent( currentPage );

			fetch( url )
				.then( function ( response ) {
					if ( ! response.ok ) {
						throw new Error( 'Network response was not ok: ' + response.statusText );
					}
					return response.json();
				} )
				.then( function ( data ) {
					renderTable( data, refocus );
				} )
				.catch( function ( error ) {
					console.error( 'EDMM: fetch error:', error );
				} );
		}

		// Initial load.
		fetchMeetings();
	}

	// ----------------------------------------------------------------
	// Bootstrap — initialise all instances when the DOM is ready.
	// ----------------------------------------------------------------

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.edmm-meeting-minutes-wrap' ).forEach( initInstance );
	} );

} )();
