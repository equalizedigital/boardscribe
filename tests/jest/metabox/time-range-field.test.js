/**
 * The Meeting Time row notes when the end time is earlier than the start
 * time, but treats it as an overnight meeting rather than an error.
 */
import { act } from 'react';
import { Simulate } from 'react-dom/test-utils';
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

const FIELD = { key: 'edbs_meeting_start_time', endKey: 'edbs_meeting_end_time', type: 'time_range', label: 'Meeting Time' };

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

describe( 'TimeRangeField inputs', () => {
	const input = ( name ) => container.querySelector( `input[name="${ name }"]` );
	const change = ( element, value ) => act( () => Simulate.change( element, { target: { value } } ) );

	it( 'names the end input from the field descriptor, not a literal', () => {
		act( () => root.render( <MetaField field={ { ...FIELD, endKey: 'custom_end_key' } } value={ { start: '', end: '' } } onChange={ () => {} } /> ) );

		expect( container.querySelector( 'input[name="custom_end_key"]' ) ).not.toBeNull();
		expect( container.querySelector( 'input[name="edbs_meeting_end_time"]' ) ).toBeNull();
	} );

	it( 'posts the start under the field key and the end under its own meta key', () => {
		render( { start: '09:00', end: '10:00' } );

		expect( input( 'edbs_meeting_start_time' ).type ).toBe( 'time' );
		expect( input( 'edbs_meeting_start_time' ).value ).toBe( '09:00' );
		expect( input( 'edbs_meeting_end_time' ).type ).toBe( 'time' );
		expect( input( 'edbs_meeting_end_time' ).value ).toBe( '10:00' );
	} );

	it( 'calls onChange with both halves, keeping the one that did not change', () => {
		const onChange = jest.fn();
		act( () => root.render( <MetaField field={ FIELD } value={ { start: '09:00', end: '10:00' } } onChange={ onChange } /> ) );

		change( input( 'edbs_meeting_start_time' ), '08:30' );
		change( input( 'edbs_meeting_end_time' ), '' );

		expect( onChange ).toHaveBeenNthCalledWith( 1, { start: '08:30', end: '10:00' } );
		expect( onChange ).toHaveBeenNthCalledWith( 2, { start: '09:00', end: '' } );
	} );

	it( 'labels both inputs and groups them under the field label', () => {
		render( { start: '', end: '' } );

		expect( container.querySelector( 'label[for="edbs_meeting_start_time"]' ).textContent ).toBe( 'Start' );
		expect( container.querySelector( 'label[for="edbs_meeting_start_time-end"]' ).textContent ).toBe( 'End' );

		const group = container.querySelector( '[role="group"]' );
		expect( document.getElementById( group.getAttribute( 'aria-labelledby' ) ).textContent ).toBe( 'Meeting Time' );
	} );

	it( 'marks the order hint as a status message', () => {
		render( { start: '22:00', end: '01:00' } );

		expect( hint().getAttribute( 'role' ) ).toBe( 'status' );
	} );
} );
