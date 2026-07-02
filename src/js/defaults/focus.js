/**
 * Moves focus into the rendered list after a pagination click.
 * Used when a template doesn't override focus.
 *
 * @param {HTMLElement} container - The list container element.
 */
export function defaultFocus( container ) {
	const target = container.querySelector( '[tabindex="0"]' );
	if ( target ) {
		target.focus();
	}
}
