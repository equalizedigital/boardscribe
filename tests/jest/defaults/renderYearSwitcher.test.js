import { defaultRenderYearSwitcher } from '../../../src/js/defaults/renderYearSwitcher';

describe( 'defaultRenderYearSwitcher', () => {
	it( 'renders nothing when there are no available years', () => {
		const element = document.createElement( 'div' );

		defaultRenderYearSwitcher( {}, {}, element, jest.fn() );

		expect( element.innerHTML ).toBe( '' );
	} );

	it( 'renders a select populated with every available year, newest first', () => {
		const element = document.createElement( 'div' );
		element.id = 'edbs-year-switcher-edbs_1';

		defaultRenderYearSwitcher(
			{ available_years: [ 2026, 2025, 2023 ] },
			{ currentYear: 2025 },
			element,
			jest.fn()
		);

		const options = Array.from( element.querySelectorAll( 'option' ) ).map( ( option ) => option.value );
		expect( options ).toEqual( [ '2026', '2025', '2023' ] );

		const select = element.querySelector( 'select' );
		expect( select.value ).toBe( '2025' );
	} );

	it( 'selecting a year in the dropdown calls goToYear with that year', () => {
		const element = document.createElement( 'div' );
		element.id = 'edbs-year-switcher-edbs_1';
		const goToYear = jest.fn();

		defaultRenderYearSwitcher(
			{ available_years: [ 2026, 2025, 2023 ] },
			{ currentYear: 2025 },
			element,
			goToYear
		);

		const select = element.querySelector( 'select' );
		select.value = '2023';
		select.dispatchEvent( new Event( 'change' ) );

		expect( goToYear ).toHaveBeenCalledWith( 2023 );
	} );

	it( 'clicking prev/next steps to the adjacent year and disables at the ends', () => {
		const element = document.createElement( 'div' );
		element.id = 'edbs-year-switcher-edbs_1';
		const goToYear = jest.fn();

		defaultRenderYearSwitcher(
			{ available_years: [ 2026, 2025, 2023 ] },
			{ currentYear: 2025 },
			element,
			goToYear
		);

		const prevButton = element.querySelector( '.edbs-year-switcher-button.prev' );
		const nextButton = element.querySelector( '.edbs-year-switcher-button.next' );
		expect( prevButton.hasAttribute( 'disabled' ) ).toBe( false );
		expect( nextButton.hasAttribute( 'disabled' ) ).toBe( false );

		prevButton.click();
		expect( goToYear ).toHaveBeenCalledWith( 2023 );

		nextButton.click();
		expect( goToYear ).toHaveBeenCalledWith( 2026 );
	} );

	it( 'disables prev at the oldest year and next at the newest year', () => {
		const newestElement = document.createElement( 'div' );
		newestElement.id = 'edbs-year-switcher-edbs_1';
		defaultRenderYearSwitcher(
			{ available_years: [ 2026, 2025 ] },
			{ currentYear: 2026 },
			newestElement,
			jest.fn()
		);
		expect( newestElement.querySelector( '.next' ).hasAttribute( 'disabled' ) ).toBe( true );

		const oldestElement = document.createElement( 'div' );
		oldestElement.id = 'edbs-year-switcher-edbs_1';
		defaultRenderYearSwitcher(
			{ available_years: [ 2026, 2025 ] },
			{ currentYear: 2025 },
			oldestElement,
			jest.fn()
		);
		expect( oldestElement.querySelector( '.prev' ).hasAttribute( 'disabled' ) ).toBe( true );
	} );
} );
