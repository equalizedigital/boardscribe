/**
 * The table HTML for meetings without a time is exactly what develop produced
 * before meeting times existed (checked against develop's table.js).
 */
import { buildTableHtml } from '../../../src/js/templates/table';

const MEETINGS = [
	{
		title: 'January Board Meeting',
		date: 'January 15, 2026',
		agenda: '<a href="https://example.com/a.pdf" aria-label="View Agenda for January 15, 2026">View Agenda</a>',
		minutes: '<span class="sr-text screen-reader-text">Minutes not available</span>',
	},
	{ title: 'Special Session', date: 'Tuesday, March 3, 2026', agenda: 'x', minutes: 'y' },
];

describe( 'buildTableHtml for untimed meetings', () => {
	it( 'renders the same markup as before meeting times existed', () => {
		expect( buildTableHtml( MEETINGS, {} ) ).toBe(
			'<table role="table" class="edbs-boardscribe-table edbs-template-table  "><thead class="desktop" role="rowgroup"><tr role="row"><th scope="col" role="columnheader">Title</th><th scope="col" role="columnheader">Date</th><th scope="col" role="columnheader">Agenda</th><th scope="col" role="columnheader">Minutes</th></tr></thead><tbody role="rowgroup"><tr role="row"><td data-label="Title" role="rowheader">January Board Meeting</td><td data-label="Date" role="cell">January 15, 2026</td><td data-label="Agenda" role="cell"><a href="https://example.com/a.pdf" aria-label="View Agenda for January 15, 2026">View Agenda</a></td><td data-label="Minutes" role="cell"><span class="sr-text screen-reader-text">Minutes not available</span></td></tr><tr role="row"><td data-label="Title" role="rowheader">Special Session</td><td data-label="Date" role="cell">Tuesday, March 3, 2026</td><td data-label="Agenda" role="cell">x</td><td data-label="Minutes" role="cell">y</td></tr></tbody></table>',
		);
	} );

	it( 'adds no time wrapper whether or not hideTime is set', () => {
		expect( buildTableHtml( MEETINGS, { hideTime: true } ) ).toBe( buildTableHtml( MEETINGS, {} ) );
		expect( buildTableHtml( MEETINGS, {} ) ).not.toContain( 'edbs-meeting-datetime' );
	} );
} );
