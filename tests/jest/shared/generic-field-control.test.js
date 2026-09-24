/**
 * PRO-1416: GenericFieldControl (the shortcode builder's control for every
 * registry field) shows a select's per-choice help for the selected option,
 * and falls back to the field-level description.
 */
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { GenericFieldControl } from '../../../src/js/shared/generic-field-control';

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

const TEMPLATE_FIELD = {
	type: 'select',
	label: 'Display Template',
	choices: { '': 'Table (default)', list: 'List (stacked)' },
	choiceDescriptions: { '': 'Table help.', list: 'List help.' },
	description: 'Field-level help.',
};

const help = () => container.querySelector( '[data-control="select"]' ).getAttribute( 'data-help' );

describe( 'GenericFieldControl select help', () => {
	it( 'shows the help for the selected choice, and changes with the selection', () => {
		act( () => root.render( <GenericFieldControl field={ TEMPLATE_FIELD } value="" onChange={ () => {} } /> ) );
		expect( help() ).toBe( 'Table help.' );

		act( () => root.render( <GenericFieldControl field={ TEMPLATE_FIELD } value="list" onChange={ () => {} } /> ) );
		expect( help() ).toBe( 'List help.' );
	} );

	it( 'falls back to the field-level description when the selected choice has none', () => {
		const field = { ...TEMPLATE_FIELD, choiceDescriptions: { list: 'List help.' } };
		act( () => root.render( <GenericFieldControl field={ field } value="" onChange={ () => {} } /> ) );

		expect( help() ).toBe( 'Field-level help.' );
	} );

	it( 'keeps using the description for a select with no per-choice help', () => {
		const field = { type: 'select', label: 'Order', choices: { asc: 'Ascending' }, description: 'Sort direction.' };
		act( () => root.render( <GenericFieldControl field={ field } value="asc" onChange={ () => {} } /> ) );

		expect( help() ).toBe( 'Sort direction.' );
	} );
} );
