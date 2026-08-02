/**
 * Test-only stand-in for @wordpress/server-side-render (see blocks.js
 * mock for why this is mocked at all) - the real component performs a
 * REST request the test environment can't serve, so this just renders a
 * placeholder marking where it would appear.
 */
import { createElement } from '@wordpress/element';

export default function ServerSideRender() {
	return createElement( 'div', { 'data-testid': 'server-side-render' } );
}
