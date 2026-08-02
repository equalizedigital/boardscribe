import { defaultFocus } from '../../../src/js/defaults/focus';

describe( 'defaultFocus', () => {
	it( 'moves focus onto the container itself', () => {
		const container = document.createElement( 'div' );
		document.body.appendChild( container );

		defaultFocus( container );

		expect( document.activeElement ).toBe( container );

		container.remove();
	} );

	it( 'removes the tabindex attribute after focusing, without losing focus', () => {
		const container = document.createElement( 'div' );
		document.body.appendChild( container );

		defaultFocus( container );

		expect( container.hasAttribute( 'tabindex' ) ).toBe( false );
		expect( document.activeElement ).toBe( container );

		container.remove();
	} );

	it( 'leaves an existing tabindex on a child element untouched', () => {
		const container = document.createElement( 'div' );
		// A pre-existing tabindex="0" here (e.g. a template that still sets
		// one on its own markup) proves defaultFocus() only ever touches
		// the container it's given, not anything rendered inside it.
		container.innerHTML = '<table tabindex="0"><tbody><tr><td>Row</td></tr></tbody></table>';
		document.body.appendChild( container );

		defaultFocus( container );

		const table = container.querySelector( 'table' );
		expect( table.getAttribute( 'tabindex' ) ).toBe( '0' );
		expect( document.activeElement ).toBe( container );

		container.remove();
	} );
} );
