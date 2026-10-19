/**
 * The Meeting Time row notes when the end time is earlier than the start
 * time, but treats it as an overnight meeting rather than an error.
 */
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { MetaField } from '../../../src/js/metabox/field-control';

window.IS_REACT_ACT_ENVIRONMENT = true;

let container;
let root;

beforeEach( () => {
	container = document.createElement( 'div' );
	document.body.appendChild( container );
	root = createRoot( container );
} );

afterEach( () => {
	act( () => root.unmount() );
	container.remove();
} );

const FIELD = { key: 'edbs_meeting_start_time', type: 'time_range', label: 'Meeting Time' };

const render = ( value ) => act( () => root.render( <MetaField field={ FIELD } value={ value } onChange={ () => {} } /> ) );
const hint = () => container.querySelector( '.edbs-metabox-app__time-hint' );

describe( 'TimeRangeField order hint', () => {
	it( 'says an end before the start ends the next day, without calling it an error', () => {
		render( { start: '22:00', end: '01:00' } );

		expect( hint().textContent ).toBe( 'The end time is earlier than the start time, so this meeting ends the next day.' );
		expect( container.querySelector( 'input[name="edbs_meeting_end_time"]' ).getAttribute( 'aria-describedby' ) ).toBe( hint().id );
	} );

	it( 'shows no hint for an ordinary range or a single time', () => {
		render( { start: '09:00', end: '10:00' } );
		expect( hint() ).toBeNull();

		render( { start: '09:00', end: '' } );
		expect( hint() ).toBeNull();
	} );
} );
