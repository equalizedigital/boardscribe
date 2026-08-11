import { escapeAttribute, escapeHTML } from '@wordpress/escape-html';
import { __ } from '@wordpress/i18n';
import { i18n } from '../config';

/**
 * Builds a <table> element's HTML for a list of meetings, honoring
 * instanceCfg's hide-column, label, and tableClass settings and any
 * Pro-registered window.edbsExtraColumns - the same column set,
 * label-resolution, and escaping rules the built-in "table" template
 * uses. Exposed globally as
 * window.edbsBuildTable (see index.js) so Pro's own templates (e.g. one
 * that renders a separate table per year) can reuse this instead of
 * re-implementing the same column-building logic, which would otherwise
 * need to be kept in sync by hand across two codebases.
 *
 * As a public API any external caller can invoke directly (not just this
 * bundle's own render(), which already normalizes its inputs), this
 * defensively normalizes meetings/instanceCfg/window.edbsExtraColumns and
 * falls back to '' for missing meeting fields, rather than throwing or
 * rendering literal "undefined" text.
 *
 * @param {Array}  meetings    - Meeting row objects from the REST response.
 * @param {Object} instanceCfg - The per-instance configuration.
 * @return {string} The rendered <table> HTML.
 */
export function buildTableHtml( meetings, instanceCfg ) {
	const safeMeetings = Array.isArray( meetings ) ? meetings : [];
	const cfg = instanceCfg || {};
	const extraColumns = Array.isArray( window.edbsExtraColumns ) ? window.edbsExtraColumns : [];

	// Resolve column labels: instance override → i18n global → hard-coded fallback.
	const labelTitle = cfg.titleLabel || i18n.colTitle || __( 'Title', 'boardscribe' );
	const labelDate = cfg.dateLabel || i18n.colDate || __( 'Date', 'boardscribe' );
	const labelAgenda = cfg.agendaLabel || i18n.colAgenda || __( 'Agenda', 'boardscribe' );
	const labelMinutes = cfg.minutesLabel || i18n.colMinutes || __( 'Minutes', 'boardscribe' );

	// The class list and core labels are already sanitized server-side
	// (sanitize_class_list()/sanitize_text_field() in the shortcode),
	// but escape at the insertion point too so values injected via the
	// edbs_shortcode_instance_config filter get the same treatment.
	const tableClass = escapeAttribute( cfg.tableClass || '' );
	const templateClass = escapeAttribute( 'edbs-template-' + ( cfg.resolvedTemplate || 'table' ) );
	const equalColumnsClass = cfg.equalColumns ? 'edbs-equal-columns' : '';
	// Explicit table/row/cell roles below are belt-and-braces: the
	// responsive stacking layout (see the max-width:768px block in
	// boardscribe.css) overrides `display` on every one of these
	// elements, which strips their *implicit* table semantics in some
	// browsers (most notably Safari/VoiceOver) - explicit roles keep the
	// table announced as a table, with header/cell relationships intact,
	// regardless of that CSS.
	let table = '<table tabindex="0" role="table" class="edbs-boardscribe-table ' + templateClass + ' ' + equalColumnsClass + ' ' + tableClass + '">' +
		'<thead class="desktop" role="rowgroup"><tr role="row">';

	if ( ! cfg.hideTitle ) {
		table += '<th scope="col" role="columnheader">' + escapeHTML( labelTitle ) + '</th>';
	}
	if ( ! cfg.hideDate ) {
		table += '<th scope="col" role="columnheader">' + escapeHTML( labelDate ) + '</th>';
	}
	if ( ! cfg.hideAgenda ) {
		table += '<th scope="col" role="columnheader">' + escapeHTML( labelAgenda ) + '</th>';
	}
	if ( ! cfg.hideMinutes ) {
		table += '<th scope="col" role="columnheader">' + escapeHTML( labelMinutes ) + '</th>';
	}

	// hidden()/getLabel() only depend on cfg, not on any one meeting, so
	// resolve the visible extra columns once here rather than recomputing
	// them for every row below - this is now the shared helper Pro
	// templates may call once per section, each with its own row set.
	const visibleExtraColumns = extraColumns
		.filter( function( col ) {
			return ! ( typeof col.hidden === 'function' ? col.hidden( cfg ) : false );
		} )
		.map( function( col ) {
			return {
				col,
				// Deliberately NOT escaped: label/getLabel() are raw header
				// HTML by documented contract - the registrant escapes.
				label: typeof col.getLabel === 'function' ? col.getLabel( cfg ) : col.label,
			};
		} );

	visibleExtraColumns.forEach( function( entry ) {
		table += '<th scope="col" role="columnheader">' + ( entry.label || '' ) + '</th>';
	} );

	table += '</tr></thead><tbody role="rowgroup">';

	// Every row needs exactly one cell marked as its row header so screen
	// readers can announce which row a cell belongs to. The title is the
	// natural label, but it's an optional column - when it's hidden the
	// date takes over, as it's the only other core column whose value
	// identifies the row (agenda/minutes cells are identical link text on
	// every row, so promoting one of those would announce nothing useful).
	let rowHeaderColumn = '';
	if ( ! cfg.hideTitle ) {
		rowHeaderColumn = 'title';
	} else if ( ! cfg.hideDate ) {
		rowHeaderColumn = 'date';
	}

	// Cell content below (meeting.title, meeting.date, meeting.agenda,
	// meeting.minutes, and any Pro-registered renderCell() output) is
	// inserted as trusted, pre-escaped HTML by contract - the REST
	// endpoint escapes title/date server-side, and agenda/minutes are
	// pre-built escaped <a> markup. Any new field or extra-column
	// renderCell() must escape its own output before returning it here.
	safeMeetings.forEach( function( meeting ) {
		if ( ! meeting ) {
			return;
		}

		table += '<tr role="row">';
		if ( ! cfg.hideTitle ) {
			// role="rowheader" is the ARIA (not `scope`) way to mark a <td>
			// as a row header - `scope` is only valid on <th> and browsers
			// silently ignore it here, leaving the row with no accessible
			// header at all.
			table += '<td data-label="' + escapeAttribute( labelTitle ) + '" role="' + ( 'title' === rowHeaderColumn ? 'rowheader' : 'cell' ) + '">' + ( meeting.title || '' ) + '</td>';
		}
		if ( ! cfg.hideDate ) {
			table += '<td data-label="' + escapeAttribute( labelDate ) + '" role="' + ( 'date' === rowHeaderColumn ? 'rowheader' : 'cell' ) + '">' + ( meeting.date || '' ) + '</td>';
		}
		if ( ! cfg.hideAgenda ) {
			table += '<td data-label="' + escapeAttribute( labelAgenda ) + '" role="cell">' + ( meeting.agenda || '' ) + '</td>';
		}
		if ( ! cfg.hideMinutes ) {
			table += '<td data-label="' + escapeAttribute( labelMinutes ) + '" role="cell">' + ( meeting.minutes || '' ) + '</td>';
		}

		visibleExtraColumns.forEach( function( entry ) {
			const cell = entry.col.renderCell ? entry.col.renderCell( meeting, cfg ) : ( meeting[ entry.col.key ] || '' );
			table += '<td data-label="' + escapeAttribute( entry.label || '' ) + '" role="cell">' + cell + '</td>';
		} );

		table += '</tr>';
	} );

	table += '</tbody></table>';
	return table;
}

// The built-in "table" display template, registered as
// window.edbsTemplates.table by the entry point.
export const tableTemplate = {
	render( data, instanceCfg, container ) {
		// Tolerate malformed responses (e.g. an edbs_rest_response filter
		// emptying the payload) rather than throwing mid-render.
		const meetings = ( data && Array.isArray( data.meetings ) ) ? data.meetings : [];
		container.innerHTML = buildTableHtml( meetings, instanceCfg );
	},
};
