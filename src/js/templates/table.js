import { escapeAttribute, escapeHTML } from '@wordpress/escape-html';
import { __ } from '@wordpress/i18n';
import { i18n } from '../config';

/**
 * Resolves the column set for an instance: which columns are visible, in
 * what order, what each one is labelled, which one identifies the row, and
 * how to render a given meeting's value for it.
 *
 * This is the single implementation of that logic. buildTableHtml() below
 * consumes it, and it is exposed as window.edbsResolveColumns (see index.js)
 * so an add-on template rendering something other than a table (e.g. Pro's
 * stacked "list" template) can lay the same columns out its own way instead
 * of re-implementing the label fallbacks, hide toggles and
 * window.edbsExtraColumns handling - which would otherwise have to be kept
 * in sync by hand across two codebases.
 *
 * Each returned entry:
 *   key         - 'title' | 'date' | 'agenda' | 'minutes', or the extra
 *                 column's own key.
 *   label       - The label as plain text, for attribute and text contexts
 *                 (the table's data-label, a list's <dt>). Escape at the
 *                 insertion point.
 *   labelHtml   - The label as header HTML, ready to insert unescaped. For
 *                 core columns that is escapeHTML( label ); for extra
 *                 columns it is the registrant's label/getLabel() return
 *                 value verbatim, which is raw header HTML by documented
 *                 contract (see registries.js) - the registrant escapes.
 *   isRowHeader - True for the one column that identifies the row. The title
 *                 is the natural label, but it's an optional column - when
 *                 it's hidden the date takes over, as it's the only other
 *                 core column whose value identifies the row (agenda/minutes
 *                 are identical link text on every row, so promoting one of
 *                 those would announce nothing useful). No entry carries it
 *                 when both are hidden.
 *   render      - function( meeting ) → cell HTML. The return value is
 *                 trusted, pre-escaped HTML by contract: the REST endpoint
 *                 escapes title/date server-side, agenda/minutes are
 *                 pre-built escaped <a> markup, and an extra column's
 *                 renderCell() escapes its own output.
 *
 * As a public API any external caller can invoke directly, this defensively
 * normalizes instanceCfg and window.edbsExtraColumns, and its render()
 * callbacks fall back to '' for missing meeting fields, rather than throwing
 * or rendering literal "undefined" text.
 *
 * @param {Object} instanceCfg - The per-instance configuration.
 * @return {Array<Object>} The visible columns, in render order.
 */
export function resolveColumns( instanceCfg ) {
	const cfg = instanceCfg || {};
	const extraColumns = Array.isArray( window.edbsExtraColumns ) ? window.edbsExtraColumns : [];

	// Resolve column labels: instance override → i18n global → hard-coded fallback.
	const labelTitle = cfg.titleLabel || i18n.colTitle || __( 'Title', 'boardscribe' );
	const labelDate = cfg.dateLabel || i18n.colDate || __( 'Date', 'boardscribe' );
	const labelAgenda = cfg.agendaLabel || i18n.colAgenda || __( 'Agenda', 'boardscribe' );
	const labelMinutes = cfg.minutesLabel || i18n.colMinutes || __( 'Minutes', 'boardscribe' );

	// See isRowHeader in the docblock above for why the title, then the
	// date, and nothing else can carry it.
	let rowHeaderColumn = '';
	if ( ! cfg.hideTitle ) {
		rowHeaderColumn = 'title';
	} else if ( ! cfg.hideDate ) {
		rowHeaderColumn = 'date';
	}

	/**
	 * Builds a core column entry, whose label is plain text and whose value
	 * is the row field of the same name.
	 *
	 * @param {string} key   - The row field name, also the column key.
	 * @param {string} label - The resolved plain-text label.
	 * @return {Object} The column entry.
	 */
	function coreColumn( key, label ) {
		return {
			key,
			label,
			labelHtml: escapeHTML( label ),
			isRowHeader: key === rowHeaderColumn,
			render( meeting ) {
				return ( meeting && meeting[ key ] ) || '';
			},
		};
	}

	const columns = [];

	if ( ! cfg.hideTitle ) {
		columns.push( coreColumn( 'title', labelTitle ) );
	}
	if ( ! cfg.hideDate ) {
		columns.push( coreColumn( 'date', labelDate ) );
	}
	if ( ! cfg.hideAgenda ) {
		columns.push( coreColumn( 'agenda', labelAgenda ) );
	}
	if ( ! cfg.hideMinutes ) {
		columns.push( coreColumn( 'minutes', labelMinutes ) );
	}

	// hidden()/getLabel() only depend on cfg, not on any one meeting, so
	// they are resolved once here rather than per row.
	extraColumns.forEach( function( col ) {
		if ( ! col ) {
			return;
		}
		if ( typeof col.hidden === 'function' ? col.hidden( cfg ) : false ) {
			return;
		}

		// Deliberately NOT escaped: label/getLabel() are raw header HTML by
		// documented contract - the registrant escapes.
		const label = ( typeof col.getLabel === 'function' ? col.getLabel( cfg ) : col.label ) || '';

		columns.push( {
			key: col.key,
			label,
			labelHtml: label,
			isRowHeader: false,
			render( meeting ) {
				if ( col.renderCell ) {
					return col.renderCell( meeting, cfg );
				}
				return ( meeting && meeting[ col.key ] ) || '';
			},
		} );
	} );

	return columns;
}

/**
 * Builds a <table> element's HTML for a list of meetings, honoring
 * instanceCfg's hide-column, label, and tableClass settings and any
 * Pro-registered window.edbsExtraColumns - the column set, label
 * resolution, and escaping rules all come from resolveColumns() above.
 * Exposed globally as window.edbsBuildTable (see index.js) so Pro's own
 * templates (e.g. one that renders a separate table per year) can reuse
 * this instead of re-implementing the same table markup, which would
 * otherwise need to be kept in sync by hand across two codebases.
 *
 * As a public API any external caller can invoke directly (not just this
 * bundle's own render(), which already normalizes its inputs), this
 * defensively normalizes meetings/instanceCfg and falls back to '' for
 * missing meeting fields, rather than throwing or rendering literal
 * "undefined" text.
 *
 * @param {Array}  meetings    - Meeting row objects from the REST response.
 * @param {Object} instanceCfg - The per-instance configuration.
 * @return {string} The rendered <table> HTML.
 */
export function buildTableHtml( meetings, instanceCfg ) {
	const safeMeetings = Array.isArray( meetings ) ? meetings : [];
	const cfg = instanceCfg || {};
	const columns = resolveColumns( cfg );

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
	let table = '<table role="table" class="edbs-boardscribe-table ' + templateClass + ' ' + equalColumnsClass + ' ' + tableClass + '">' +
		'<thead class="desktop" role="rowgroup"><tr role="row">';

	columns.forEach( function( column ) {
		table += '<th scope="col" role="columnheader">' + column.labelHtml + '</th>';
	} );

	table += '</tr></thead><tbody role="rowgroup">';

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

		columns.forEach( function( column ) {
			// role="rowheader" is the ARIA (not `scope`) way to mark a <td>
			// as a row header - `scope` is only valid on <th> and browsers
			// silently ignore it here, leaving the row with no accessible
			// header at all.
			const role = column.isRowHeader ? 'rowheader' : 'cell';
			table += '<td data-label="' + escapeAttribute( column.label ) + '" role="' + role + '">' + column.render( meeting ) + '</td>';
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
