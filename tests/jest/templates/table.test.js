import { buildTableHtml, tableTemplate } from '../../../src/js/templates/table';

const MEETING = {
	title: 'January Board Meeting',
	date: 'January 15, 2026',
	agenda: '<a href="https://example.com/agenda.pdf">Agenda</a>',
	minutes: '<a href="https://example.com/minutes.pdf">Minutes</a>',
};

describe( 'buildTableHtml', () => {
	it( 'never renders a tabindex on the table, with or without rows', () => {
		expect( buildTableHtml( [], {} ) ).not.toMatch( /tabindex/ );
		expect( buildTableHtml( [ MEETING ], {} ) ).not.toMatch( /tabindex/ );
	} );

	it( 'still renders explicit table/row/cell roles for accessible semantics', () => {
		const html = buildTableHtml( [ MEETING ], {} );
		expect( html ).toMatch( /<table role="table"/ );
		expect( html ).toMatch( /role="columnheader"/ );
		expect( html ).toMatch( /role="rowheader"/ );
	} );
} );

describe( 'tableTemplate.render', () => {
	it( 'renders into the container without adding a tabindex, on the initial render', () => {
		const container = document.createElement( 'div' );

		tableTemplate.render( { meetings: [ MEETING ] }, {}, container );

		expect( container.querySelector( 'table' ) ).not.toBeNull();
		expect( container.querySelector( '[tabindex]' ) ).toBeNull();
	} );
} );
