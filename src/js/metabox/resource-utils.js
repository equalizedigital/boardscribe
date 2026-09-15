import { __ } from '@wordpress/i18n';

const VIDEO_EXTENSIONS = [ 'mp4', 'mov', 'webm', 'm4v', 'avi' ];

/**
 * Classifies a resource field's raw URL value into a display chip + meta
 * line, without any server round-trip - same-origin URLs are treated as
 * Media Library files (video extensions get their own chip), everything
 * else as an external link.
 *
 * @param {string} url The field's raw value.
 * @return {{chips: Array<string>, meta: string}} Display chips and the meta line.
 */
export function classifyResourceUrl( url ) {
	if ( ! url ) {
		return { chips: [], meta: '' };
	}

	// Only attempt to classify well-formed absolute URLs - new URL() happily
	// "resolves" arbitrary text against a base (e.g. "not a url" becomes
	// same-origin "/not%20a%20url"), which would misclassify malformed data
	// as a same-origin Media Library file instead of falling back safely.
	if ( ! /^https?:\/\//i.test( url ) ) {
		return { chips: [ __( 'External URL', 'boardscribe' ) ], meta: url };
	}

	let parsed;
	try {
		parsed = new URL( url );
	} catch ( e ) {
		return { chips: [ __( 'External URL', 'boardscribe' ) ], meta: url };
	}

	const isSameOrigin = parsed.origin === window.location.origin;

	if ( isSameOrigin ) {
		const filename = decodeURIComponent( parsed.pathname.split( '/' ).pop() || url );
		const extension = filename.includes( '.' ) ? filename.split( '.' ).pop().toLowerCase() : '';
		const chip = VIDEO_EXTENSIONS.includes( extension )
			? __( 'Media Library video', 'boardscribe' )
			: __( 'Media Library', 'boardscribe' );
		return { chips: [ chip ], meta: filename };
	}

	const meta = parsed.host + parsed.pathname + parsed.search;
	return { chips: [ __( 'External URL', 'boardscribe' ) ], meta };
}

/**
 * Resolves a field's card title. `field.titleTemplate` is either a plain
 * static string (e.g. "Live meeting stream") or one containing "{date}",
 * resolved against the meeting's own edbs_meeting_date value (falls back
 * to the field's label when no date is set yet). Mirrors the free-text
 * convention the meta box's example content uses ("May 13, 2025 Board
 * Meeting Agenda") rather than being a separately stored/edited value -
 * unlike Supporting Documents rows, which do store their own title.
 *
 * @param {Object} field          Field descriptor.
 * @param {string} meetingDateIso The current edbs_meeting_date value (Y-m-d), if any.
 * @return {string} The resolved title.
 */
export function resolveResourceTitle( field, meetingDateIso ) {
	const template = field.titleTemplate || field.label;

	if ( ! template.includes( '{date}' ) ) {
		return template;
	}

	if ( ! meetingDateIso ) {
		return field.label;
	}

	const date = new Date( meetingDateIso + 'T00:00:00' );
	if ( isNaN( date.getTime() ) ) {
		return field.label;
	}

	// A fixed locale (rather than the viewer's browser locale) so every
	// admin sees the same wording - this doesn't read WordPress's own
	// site date_format option, unlike the PHP-rendered dates elsewhere in
	// the plugin; a future pass could localize it via window.edbsConfig.
	const formatted = new Intl.DateTimeFormat( 'en-US', { year: 'numeric', month: 'long', day: 'numeric' } ).format( date );
	return template.replace( '{date}', formatted );
}
