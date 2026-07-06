import { escapeAttribute, escapeHTML } from '@wordpress/escape-html';
import { __ } from '@wordpress/i18n';
import { i18n } from '../config';

// The built-in "table" display template, registered as
// window.edmmTemplates.table by the entry point.
export const tableTemplate = {
	render( data, instanceCfg, container ) {
		// Resolve column labels: instance override → i18n global → hard-coded fallback.
		const labelTitle = instanceCfg.titleLabel || i18n.colTitle || __( 'Title', 'edmm' );
		const labelDate = instanceCfg.dateLabel || i18n.colDate || __( 'Date', 'edmm' );
		const labelAgenda = instanceCfg.agendaLabel || i18n.colAgenda || __( 'Agenda', 'edmm' );
		const labelMinutes = instanceCfg.minutesLabel || i18n.colMinutes || __( 'Minutes', 'edmm' );

		// The class list and core labels are already sanitized server-side
		// (sanitize_class_list()/sanitize_text_field() in the shortcode),
		// but escape at the insertion point too so values injected via the
		// edmm_shortcode_instance_config filter get the same treatment.
		const tableClass = escapeAttribute( instanceCfg.tableClass || '' );
		const templateClass = escapeAttribute( 'edmm-template-' + ( instanceCfg.resolvedTemplate || 'table' ) );
		let table = '<table tabindex="0" class="edmm-meeting-minutes-table ' + templateClass + ' ' + tableClass + '">' +
			'<thead class="desktop"><tr>';

		if ( ! instanceCfg.hideTitle ) {
			table += '<th scope="col">' + escapeHTML( labelTitle ) + '</th>';
		}
		if ( ! instanceCfg.hideDate ) {
			table += '<th scope="col">' + escapeHTML( labelDate ) + '</th>';
		}
		if ( ! instanceCfg.hideAgenda ) {
			table += '<th scope="col">' + escapeHTML( labelAgenda ) + '</th>';
		}
		if ( ! instanceCfg.hideMinutes ) {
			table += '<th scope="col">' + escapeHTML( labelMinutes ) + '</th>';
		}

		window.edmmExtraColumns.forEach( function( col ) {
			const hidden = typeof col.hidden === 'function' ? col.hidden( instanceCfg ) : false;
			if ( ! hidden ) {
				// Deliberately NOT escaped: label/getLabel() are raw header
				// HTML by documented contract - the registrant escapes.
				const label = typeof col.getLabel === 'function' ? col.getLabel( instanceCfg ) : col.label;
				table += '<th scope="col">' + ( label || '' ) + '</th>';
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
				table += '<td data-label="' + escapeAttribute( labelTitle ) + '" scope="row">' + meeting.title + '</td>';
			}
			if ( ! instanceCfg.hideDate ) {
				table += '<td data-label="' + escapeAttribute( labelDate ) + '">' + meeting.date + '</td>';
			}
			if ( ! instanceCfg.hideAgenda ) {
				table += '<td data-label="' + escapeAttribute( labelAgenda ) + '">' + meeting.agenda + '</td>';
			}
			if ( ! instanceCfg.hideMinutes ) {
				table += '<td data-label="' + escapeAttribute( labelMinutes ) + '">' + meeting.minutes + '</td>';
			}

			window.edmmExtraColumns.forEach( function( col ) {
				const hidden = typeof col.hidden === 'function' ? col.hidden( instanceCfg ) : false;
				if ( ! hidden ) {
					const label = typeof col.getLabel === 'function' ? col.getLabel( instanceCfg ) : col.label;
					const cell = col.renderCell ? col.renderCell( meeting, instanceCfg ) : ( meeting[ col.key ] || '' );
					table += '<td data-label="' + escapeAttribute( label || '' ) + '">' + cell + '</td>';
				}
			} );

			table += '</tr>';
		} );

		table += '</tbody></table>';
		container.innerHTML = table;
	},
};
