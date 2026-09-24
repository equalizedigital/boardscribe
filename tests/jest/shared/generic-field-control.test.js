/**
 * PRO-1416: GenericFieldControl (the shortcode builder's control for every
 * registry field) shows a select's per-choice help for the selected option,
 * and falls back to the field-level description.
 */
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { Simulate } from 'react-dom/test-utils';
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

/**
 * PRO-1418: the multiselect control (Pro's Category/Type filters) maps
 * FormTokenField's plain-string tokens back to term slugs.
 */
describe( 'GenericFieldControl multiselect', () => {
	const tokenField = () => container.querySelector( '[data-control="form-token-field"] input' );

	it( 'shows the selected slugs as their term labels, and saves back to slugs on change', () => {
		const field = { type: 'multiselect', label: 'Meeting Groups', choices: { regular: 'Regular', special: 'Special' } };
		const onChange = jest.fn();
		act( () => root.render( <GenericFieldControl field={ field } value="regular" onChange={ onChange } /> ) );

		expect( tokenField().value ).toBe( 'Regular' );

		act( () => Simulate.change( tokenField(), { target: { value: 'Regular,Special' } } ) );

		expect( onChange ).toHaveBeenCalledWith( 'regular,special' );
	} );

	it( 'disambiguates two terms sharing the same display name, so picking one never saves the other', () => {
		// Two "Finance" terms with different slugs - WordPress allows
		// duplicate term names, only slugs must be unique.
		const field = { type: 'multiselect', label: 'Meeting Groups', choices: { finance: 'Finance', 'finance-2': 'Finance' } };
		const onChange = jest.fn();
		act( () => root.render( <GenericFieldControl field={ field } value="finance-2" onChange={ onChange } /> ) );

		// The selected term's disambiguated token names its own slug, not
		// the bare (ambiguous) label.
		expect( tokenField().value ).toBe( 'Finance (finance-2)' );

		act( () => Simulate.change( tokenField(), { target: { value: 'Finance (finance-2),Finance (finance)' } } ) );

		expect( onChange ).toHaveBeenCalledWith( 'finance-2,finance' );
	} );

	it( 'shows plain, undecorated labels when no two choices share a name', () => {
		const field = { type: 'multiselect', label: 'Meeting Groups', choices: { regular: 'Regular', special: 'Special' } };
		act( () => root.render( <GenericFieldControl field={ field } value="" onChange={ () => {} } /> ) );

		expect( tokenField().closest( 'label' ).getAttribute( 'data-suggestions' ) ).toBe( 'Regular|Special' );
	} );
} );
