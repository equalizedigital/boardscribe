/**
 * PRO-1403: an href action opens a new tab, so it needs an icon and
 * "(opens in a new tab)" in its accessible name - View and Edit both
 * render through the same CardAction, so both are covered here.
 */
import { createRoot } from 'react-dom/client';
import { act } from 'react';
import { ResourceCard } from '../../../src/js/metabox/resource-card';

window.IS_REACT_ACT_ENVIRONMENT = true;

let container;
let root;

function render( actions ) {
	container = document.createElement( 'div' );
	document.body.appendChild( container );
	root = createRoot( container );
	act( () => {
		root.render( <ResourceCard title="Doc" chips={ [] } actions={ actions } /> );
	} );
	return { container };
}

afterEach( () => {
	act( () => root.unmount() );
	container.remove();
} );

describe( 'ResourceCard href actions', () => {
	it( 'appends the new-tab notice to a caller-supplied aria-label and shows an icon', () => {
		const { container: rendered } = render( [ { label: 'View', ariaLabel: 'View Agenda', href: 'https://example.com/a' } ] );
		const link = rendered.querySelector( 'a' );

		expect( link.getAttribute( 'aria-label' ) ).toBe( 'View Agenda (opens in a new tab)' );
		expect( link.querySelector( 'svg[aria-hidden="true"]' ) ).not.toBeNull();
		expect( link.getAttribute( 'target' ) ).toBe( '_blank' );
	} );

	it( 'adds visually-hidden new-tab text when there is no aria-label', () => {
		const { container: rendered } = render( [ { label: 'View', href: 'https://example.com/a' } ] );
		const link = rendered.querySelector( 'a' );

		expect( link.hasAttribute( 'aria-label' ) ).toBe( false );
		expect( link.querySelector( '.screen-reader-text' ).textContent ).toContain( '(opens in a new tab)' );
	} );

	it( 'leaves onClick actions untouched', () => {
		const { container: rendered } = render( [ { label: 'Remove', ariaLabel: 'Remove Agenda', onClick: () => {} } ] );
		const button = rendered.querySelector( 'button' );

		expect( button.getAttribute( 'aria-label' ) ).toBe( 'Remove Agenda' );
		expect( button.querySelector( 'svg' ) ).toBeNull();
	} );
} );
