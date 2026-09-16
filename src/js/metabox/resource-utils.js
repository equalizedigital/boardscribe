import { __ } from '@wordpress/i18n';

const VIDEO_EXTENSIONS = [ 'mp4', 'mov', 'webm', 'm4v', 'avi' ];

/**
 * A field's Add/Replace-modal source list: its own `field.sources` when
 * set, else a caller-supplied default. Each of the three current callers
 * (resource-field.js, resource-list-field.js, attached-repeater-field.js)
 * hand-wrote this same `field.sources && field.sources.length ? ... : ...`
 * check independently - only the *shape* of the check is shared, not the
 * default itself: a top-level resource field defaults to both built-ins
 * (`['media_library', 'external_url']`), while an attached field defaults
 * to Media Library only (an attached row is virtually always an upload,
 * not an external link) - see attached-repeater-field.js's own docblock.
 * Not to be confused with resource-modal.js's own `registry` object,
 * which maps a source id to its `{ label, description }` metadata rather
 * than deciding which ids a field allows - a different concern, kept
 * separate.
 *
 * @param {Object}        field          Field descriptor.
 * @param {Array<string>} defaultSources The source ids to use when `field.sources` is unset/empty.
 * @return {Array<string>} The resolved source ids.
 */
export function resolveFieldSources( field, defaultSources ) {
	return field.sources && field.sources.length ? field.sources : defaultSources;
}

/**
 * A set of hidden `<input>`s writing a resource value's fields to the
 * post form - the pattern resource-field.js's single-value `{key}`/
 * `{key}_source`/`{key}_edit_url` triplet and attached-repeater-field.js/
 * resource-list-field.js's per-row `{key}[i][label]`/`[url]`/`[source]`
 * triplet both hand-wrote independently. Deliberately name/value-list
 * shaped rather than a fixed `{ label, url, source }` signature, since
 * the two call shapes above don't actually share field names or a
 * fields count - only the "one hidden input per field, empty string for
 * an unset value" boilerplate.
 *
 * @param {Object}                               props        Component props.
 * @param {Array<{name: string, value: string}>} props.fields Each hidden input's `name`/`value` pair.
 * @return {JSX.Element} The hidden inputs.
 */
export function HiddenFields( { fields } ) {
	return (
		<>
			{ fields.map( ( { name, value } ) => (
				<input key={ name } type="hidden" name={ name } value={ value || '' } readOnly />
			) ) }
		</>
	);
}

/**
 * Extracts a resource URL's filename for display - the last path segment,
 * decoded, or the raw url when it isn't a well-formed absolute URL (a
 * relative path, or malformed data). Shared by the Media Library display
 * path in both resolveResourceDisplay() and the legacy same-origin guess
 * below.
 *
 * @param {string} url The field's raw value.
 * @return {string} The filename.
 */
function filenameOf( url ) {
	try {
		const parsed = new URL( url );
		return decodeURIComponent( parsed.pathname.split( '/' ).pop() || url );
	} catch ( e ) {
		return url;
	}
}

/**
 * The Media Library chip + meta line for a URL known (via its `source`,
 * see resolveResourceDisplay()) to be a Media Library attachment -
 * unconditional on origin, since an offloaded/CDN'd attachment (S3, Bunny,
 * Cloudflare R2, etc.) is a real Media Library file despite not sharing
 * the site's own origin.
 *
 * @param {string} url The field's raw value.
 * @return {{chips: Array<string>, meta: string}} Display chips and the meta line.
 */
function mediaLibraryDisplay( url ) {
	const filename = filenameOf( url );
	const extension = filename.includes( '.' ) ? filename.split( '.' ).pop().toLowerCase() : '';
	const chip = VIDEO_EXTENSIONS.includes( extension )
		? __( 'Media Library video', 'boardscribe' )
		: __( 'Media Library', 'boardscribe' );
	return { chips: [ chip ], meta: filename };
}

/**
 * The meta line (host + path, or the raw value when it isn't a
 * well-formed absolute URL) for anything that isn't a Media Library file -
 * an external link, or a plugin-registered custom source (e.g. Pro's
 * linked-document source).
 *
 * @param {string} url The field's raw value.
 * @return {string} The meta line.
 */
function hostPathMeta( url ) {
	if ( ! /^https?:\/\//i.test( url ) ) {
		return url;
	}
	try {
		const parsed = new URL( url );
		return parsed.host + parsed.pathname + parsed.search;
	} catch ( e ) {
		return url;
	}
}

/**
 * Classifies a resource field's raw URL value into a display chip + meta
 * line purely by guessing from the URL's shape (same-origin -> treated as
 * a Media Library file, video extensions getting their own chip;
 * everything else -> an external link). No server round-trip, but also no
 * real knowledge of where the value actually came from - a same-origin
 * link a user typed by hand reads as "Media Library", and a Media Library
 * file offloaded to a CDN/S3 (a different origin) reads as "External URL".
 * Used only as resolveResourceDisplay()'s fallback for a value saved
 * before source-tracking existed (no sibling `{key}_source` meta), or set
 * by something that bypassed the meta box UI (e.g. the CSV importer).
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

	if ( parsed.origin === window.location.origin ) {
		return mediaLibraryDisplay( url );
	}

	return { chips: [ __( 'External URL', 'boardscribe' ) ], meta: hostPathMeta( url ) };
}

/**
 * Resolves a resource value's display chip + meta line. Prefers the
 * `source` id the Add/Replace modal recorded when the value was set (see
 * resource-modal.js's activeSource wrapper and MetaBox::save_field()'s
 * `{key}_source` sibling meta) over guessing from the URL's shape -
 * `source` is authoritative when present, since it's what the user
 * actually picked, not an inference. Only falls back to the same-origin
 * guess (classifyResourceUrl()) when no source is known: legacy data
 * saved before this existed, or set by something that bypassed the meta
 * box UI entirely (e.g. the CSV importer).
 *
 * `source` is one of the built-in 'media_library'/'external_url', or a
 * plugin-registered id on window.edbsResourceSources (e.g. Pro's
 * 'document') - the chip then uses that source's own registered `label`,
 * so a custom source is labeled correctly with zero knowledge of what it
 * actually is (see resource-modal.js's registry).
 *
 * @param {string} url    The field's raw value.
 * @param {string} source The value's `{key}_source` (or one item's own
 *                        `source` property, for a resource_list row) -
 *                        empty/unset falls back to classifyResourceUrl().
 * @return {{chips: Array<string>, meta: string}} Display chips and the meta line.
 */
export function resolveResourceDisplay( url, source ) {
	if ( ! url ) {
		return { chips: [], meta: '' };
	}

	if ( ! source ) {
		return classifyResourceUrl( url );
	}

	if ( 'media_library' === source ) {
		return mediaLibraryDisplay( url );
	}

	if ( 'external_url' === source ) {
		return { chips: [ __( 'External URL', 'boardscribe' ) ], meta: hostPathMeta( url ) };
	}

	const registered = window.edbsResourceSources && window.edbsResourceSources[ source ];
	const chip = registered ? registered.label : source;
	return { chips: [ chip ], meta: hostPathMeta( url ) };
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
