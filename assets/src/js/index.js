import './registries';
import { escapeAttr } from './escape';
import { tableTemplate } from './templates/table';
import { initInstance } from './instance';

// Exposed so add-on templates/columns can reuse the same vetted escaping
// instead of re-implementing it (and risking divergence from fixes here).
window.edmmEscapeAttr = escapeAttr;

window.edmmTemplates.table = window.edmmTemplates.table || tableTemplate;

// Initialise all instances when the DOM is ready.
document.addEventListener( 'DOMContentLoaded', function() {
	document.querySelectorAll( '.edmm-meeting-minutes-wrap' ).forEach( initInstance );
} );
