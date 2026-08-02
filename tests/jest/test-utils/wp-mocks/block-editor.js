/**
 * Test-only stand-in for @wordpress/block-editor (see blocks.js mock for
 * why this is mocked at all). Just enough to let the block's edit()
 * render: InspectorControls passes its children through untouched (the
 * real one portals them into the editor sidebar, which doesn't exist in
 * a jsdom test), and useBlockProps returns an empty props object.
 */
import { Fragment, createElement } from '@wordpress/element';

export function useBlockProps() {
	return {};
}

export function InspectorControls( { children } ) {
	return createElement( Fragment, null, children );
}
