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

	it( 'does not make anything inside the container permanently focusable', () => {
		const container = document.createElement( 'div' );
		container.innerHTML = '<table><tbody><tr><td>Row</td></tr></tbody></table>';
		document.body.appendChild( container );

		defaultFocus( container );

		const table = container.querySelector( 'table' );
		expect( table.hasAttribute( 'tabindex' ) ).toBe( false );
		expect( document.activeElement ).toBe( container );

		container.remove();
	} );
} );
