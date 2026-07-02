import { escapeAttribute } from '@wordpress/escape-html';
import './registries';
import { tableTemplate } from './templates/table';
import { initInstance } from './instance';

// Exposed so add-on templates/columns can reuse the same vetted escaping
// instead of re-implementing it (and risking divergence from fixes here).
// Wraps @wordpress/escape-html's escapeAttribute, which throws on
// null/undefined — callers rely on getting '' back for those.
window.edmmEscapeAttr = function( value ) {
	if ( value === null || value === undefined ) {
		return '';
	}
	return escapeAttribute( String( value ) );
};

window.edmmTemplates.table = window.edmmTemplates.table || tableTemplate;

// Initialise all instances when the DOM is ready.
document.addEventListener( 'DOMContentLoaded', function() {
	document.querySelectorAll( '.edmm-meeting-minutes-wrap' ).forEach( initInstance );
} );
