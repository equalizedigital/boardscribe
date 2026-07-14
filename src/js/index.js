import { escapeAttribute } from '@wordpress/escape-html';
import './registries';
import { tableTemplate, buildTableHtml } from './templates/table';
import { initInstance } from './instance';

// Exposed so add-on templates/columns can reuse the same vetted escaping
// instead of re-implementing it (and risking divergence from fixes here).
// Wraps @wordpress/escape-html's escapeAttribute, which throws on
// null/undefined — callers rely on getting '' back for those.
window.edbsEscapeAttr = function( value ) {
	if ( value === null || value === undefined ) {
		return '';
	}
	return escapeAttribute( String( value ) );
};

// Exposed so a Pro display template can build a standard meetings <table>
// (same columns/labels/window.edbsExtraColumns handling as the built-in
// "table" template) for however it lays out multiple tables/sections,
// instead of re-implementing that column-building logic itself.
window.edbsBuildTable = buildTableHtml;

window.edbsTemplates.table = window.edbsTemplates.table || tableTemplate;

// Initialise all instances when the DOM is ready.
document.addEventListener( 'DOMContentLoaded', function() {
	document.querySelectorAll( '.edbs-meeting-minutes-wrap' ).forEach( initInstance );
} );
