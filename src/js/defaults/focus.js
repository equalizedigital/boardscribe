/**
 * Moves focus into the rendered list after a pagination click.
 * Used when a template doesn't override focus.
 *
 * The list container itself (not any element a template renders inside
 * it - a table, or several tables for e.g. Pro's year-timeline) is made
 * a temporary, programmatic-only focus target: tabindex="-1" rather than
 * "0" keeps it out of the normal Tab order (this isn't meant to become a
 * permanent stop, just a landing point right after the async re-render),
 * and the attribute is removed again immediately after - an element
 * doesn't lose focus just because the attribute that made it focusable
 * is later removed, so focus stays right where it landed.
 *
 * @param {HTMLElement} container - The list container element.
 */
export function defaultFocus( container ) {
	container.setAttribute( 'tabindex', '-1' );
	container.focus();
	container.removeAttribute( 'tabindex' );
}
