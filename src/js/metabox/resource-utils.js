import { __ } from '@wordpress/i18n';

const VIDEO_EXTENSIONS = [ 'mp4', 'mov', 'webm', 'm4v', 'avi' ];

/**
 * A field's Add/Replace-modal source list: `field.sources` when set, else
 * a caller-supplied default. Extracted from resource-field.js,
 * resource-list-field.js, and attached-repeater-field.js, which each
 * hand-wrote this same check.
 *
 * @param {Object}        field          Field descriptor.
 * @param {Array<string>} defaultSources The source ids to use when `field.sources` is unset/empty.
 * @return {Array<string>} The resolved source ids.
 */
export function resolveFieldSources( field, defaultSources ) {
	return field.sources && field.sources.length ? field.sources : defaultSources;
}

/**
 * Whether a string is a complete, absolute http(s) URL - "not a url" and
 * an unedited/incomplete "https://" (no host) both fail this, unlike a
 * bare non-empty check. Used by resource-modal.js's ExternalUrlSource to
 * validate before calling onSave, rather than relying only on the
 * browser's native type="url" constraint validation.
 *
 * @param {string} value The value to check.
 * @return {boolean} Whether it's a valid external URL.
 */
export function isValidExternalUrl( value ) {
	try {
		const parsed = new URL( value.trim() );
		return ( 'http:' === parsed.protocol || 'https:' === parsed.protocol ) && '' !== parsed.hostname;
	} catch ( error ) {
		return false;
	}
}

/**
 * Builds a `getRowKey(item)` function for a repeater's `key={}` prop - a
 * stable id per row, independent of its position in the array. Keying by
 * array index (as ResourceListField used to, and AttachedRepeaterField
 * still does - see PRO-1365) makes React reuse a row's child component
 * instances by *position* across a reorder/removal, so in-progress state
 * local to that child (e.g. EditableTitle's own draft) stays behind at the
 * old position instead of following the row it actually belongs to. A
 * WeakMap from item object identity to a generated key stays valid across
 * reorders and only regenerates for a row whose own data just changed -
 * updateItem/reorder/removeItem-style handlers never replace an
 * *untouched* item's object reference, only the one actually patched gets
 * a new one, and that row is already mid-save at that point anyway, so
 * losing its in-progress-draft identity there is harmless.
 *
 * @return {(item: Object) => string} getRowKey, bound to its own WeakMap.
 */
export function createRowKeyer() {
	const keys = new WeakMap();
	let next = 0;
	return ( item ) => {
		if ( ! keys.has( item ) ) {
			keys.set( item, `row-${ next++ }` );
		}
		return keys.get( item );
	};
}

/**
 * Restores keyboard focus into a card after an Add/Replace/Remove action
 * re-renders it (e.g. the empty state's "Add" button is swapped for the
 * card's own action row, or vice versa) - the element that was just
 * clicked no longer exists once that happens, so nothing carries focus
 * forward on its own and it silently falls through to the document body.
 * Deferred via setTimeout so it runs after the state update that
 * triggered the re-render has actually committed.
 *
 * @param {import('react').RefObject<HTMLElement>} containerRef Ref to the field's card container.
 */
export function focusFirstActionable( containerRef ) {
	window.setTimeout( () => {
		// :not([disabled]) matters here - a repeater row's own reorder
		// controls (ReorderControls) can render a disabled "Move up"/"Move
		// down" button first in DOM order (the only/first/last row), and
		// .focus() on a disabled element silently no-ops. :not([aria-disabled="true"])
		// covers the same buttons' no-op-but-still-focusable state (see
		// PRO-1362) - they're valid .focus() targets, just not useful ones
		// to land on right after an Add/Replace/Remove.
		const focusable = containerRef.current && containerRef.current.querySelector( 'button:not([disabled]):not([aria-disabled="true"]), a[href]' );
		if ( focusable ) {
			focusable.focus();
		}
	} );
}

/**
 * A set of hidden `<input>`s writing a resource value's fields to the post
 * form. Takes a name/value list rather than a fixed shape since callers
 * (resource-field.js's single triplet vs. the per-row triplets in
 * resource-list-field.js/attached-repeater-field.js) don't share field names.
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
 * decoded, or the raw url when it isn't a well-formed absolute URL.
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
 * The Media Library chip + meta line for a URL known (via its tracked
 * `source`) to be a Media Library attachment - unconditional on origin, so
 * an offloaded/CDN'd attachment still reads as Media Library.
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
 * The meta line (host + path, or the raw value if not a well-formed
 * absolute URL) for anything that isn't a Media Library file.
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
 * Classifies a resource field's raw URL by guessing from its shape
 * (same-origin -> Media Library, else -> external link). Used only as
 * resolveResourceDisplay()'s fallback for legacy values with no tracked
 * `source` (e.g. pre-source-tracking data, or CSV-imported values).
 *
 * @param {string} url The field's raw value.
 * @return {{chips: Array<string>, meta: string}} Display chips and the meta line.
 */
export function classifyResourceUrl( url ) {
	if ( ! url ) {
		return { chips: [], meta: '' };
	}

	// Only classify well-formed absolute URLs - new URL() would otherwise
	// "resolve" malformed text into a same-origin URL and misclassify it.
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
 * tracked `source` (authoritative - what the user actually picked) over
 * guessing from the URL's shape; falls back to classifyResourceUrl() only
 * when no source is known (legacy data, or CSV-imported values). `source`
 * is a built-in ('media_library'/'external_url') or a plugin-registered
 * id on window.edbsResourceSources, whose own `chipLabel` (falling back
 * to `label`) is used for the chip.
 *
 * @param {string} url    The field's raw value.
 * @param {string} source The value's tracked source - empty/unset falls
 *                        back to classifyResourceUrl().
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
	// chipLabel is optional - a source's `label` reads well as a modal
	// chooser row's own heading ("Choose a BoardScribe document") but is
	// often too verbose/imperative for a small persistent card chip once
	// something's actually been picked; chipLabel lets a source register
	// a shorter noun-phrase for that spot instead ("BoardScribe
	// document"). Falls back to `label` for a source that only registers
	// that one string.
	const chip = registered ? ( registered.chipLabel || registered.label ) : source;
	return { chips: [ chip ], meta: hostPathMeta( url ) };
}

/**
 * Resolves a field's card title. `field.titleTemplate` is either a plain
 * static string or one containing "{date}", resolved against the
 * meeting's edbs_meeting_date (falls back to the field's label when no
 * date is set).
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

	// Fixed locale so every admin sees the same wording (doesn't use WP's date_format option).
	const formatted = new Intl.DateTimeFormat( 'en-US', { year: 'numeric', month: 'long', day: 'numeric' } ).format( date );
	return template.replace( '{date}', formatted );
}
