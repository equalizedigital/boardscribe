/**
 * PRO-1359 / PRO-1409 coverage for EditableTitle, the inline title editor
 * behind Supporting Documents (and caption-track) labels: Escape and a visible
 * Cancel discard the draft, Enter and Save keep it, focus returns to the
 * trigger, results are announced, and Supporting Documents explain that the
 * label doesn't rename the document itself.
 */
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { Simulate } from 'react-dom/test-utils';
import { useState } from '@wordpress/element';
import { speak } from '@wordpress/a11y';
import { EditableTitle, ResourceListField } from '../../../src/js/metabox/resource-list-field';

jest.mock( '@wordpress/a11y', () => ( { speak: jest.fn() } ) );

window.IS_REACT_ACT_ENVIRONMENT = true;

let container;
let root;

beforeEach( () => {
	jest.useFakeTimers();
	speak.mockClear();
	container = document.createElement( 'div' );
	document.body.appendChild( container );
	root = createRoot( container );
} );

afterEach( () => {
	act( () => root.unmount() );
	container.remove();
	jest.useRealTimers();
} );

function Standalone( { onChange, help } ) {
	const [ label, setLabel ] = useState( 'Original title' );
	return (
		<EditableTitle
			label={ label }
			help={ help }
			onChange={ ( next ) => {
				setLabel( next );
				onChange( next );
			} }
		/>
	);
}

const button = ( text ) => Array.from( container.querySelectorAll( 'button' ) ).find( ( b ) => text === b.textContent );
const input = () => container.querySelector( '.edbs-resource-card__title-edit input' );
const flush = () => {
	act( () => {
		jest.runAllTimers();
	} );
};

function openAndType( value ) {
	act( () => Simulate.click( button( 'Edit title' ) ) );
	act( () => Simulate.change( input(), { target: { value } } ) );
}

describe( 'EditableTitle', () => {
	it( 'Escape discards the draft, keeps the stored title, and returns focus to Edit title', () => {
		const onChange = jest.fn();
		act( () => root.render( <Standalone onChange={ onChange } /> ) );

		openAndType( 'Changed' );
		act( () => Simulate.keyDown( input(), { key: 'Escape' } ) );
		flush();

		expect( onChange ).not.toHaveBeenCalled();
		expect( input() ).toBeNull();
		expect( container.textContent ).toContain( 'Original title' );
		expect( document.activeElement ).toBe( button( 'Edit title' ) );
		expect( speak ).toHaveBeenCalledWith( expect.stringMatching( /cancel/i ) );

		// The discarded draft doesn't come back the next time it opens.
		act( () => Simulate.click( button( 'Edit title' ) ) );
		expect( input().value ).toBe( 'Original title' );
	} );

	it( 'Escape during IME composition does not discard the draft', () => {
		const onChange = jest.fn();
		act( () => root.render( <Standalone onChange={ onChange } /> ) );

		openAndType( 'Changed' );
		act( () => Simulate.keyDown( input(), { key: 'Escape', isComposing: true } ) );
		flush();

		expect( onChange ).not.toHaveBeenCalled();
		// Still open, with the in-progress draft intact - Escape was
		// consumed by the IME, not this field.
		expect( input() ).not.toBeNull();
		expect( input().value ).toBe( 'Changed' );
	} );

	it( 'has a visible Cancel button that behaves the same as Escape', () => {
		const onChange = jest.fn();
		act( () => root.render( <Standalone onChange={ onChange } /> ) );

		openAndType( 'Changed' );
		expect( button( 'Save' ) ).toBeDefined();
		act( () => Simulate.click( button( 'Cancel' ) ) );
		flush();

		expect( onChange ).not.toHaveBeenCalled();
		expect( container.textContent ).toContain( 'Original title' );
		expect( document.activeElement ).toBe( button( 'Edit title' ) );
	} );

	it( 'Enter saves the draft, announces it, and returns focus to Edit title', () => {
		const onChange = jest.fn();
		act( () => root.render( <Standalone onChange={ onChange } /> ) );

		openAndType( 'Saved title' );
		act( () => Simulate.keyDown( input(), { key: 'Enter' } ) );
		flush();

		expect( onChange ).toHaveBeenCalledWith( 'Saved title' );
		expect( container.textContent ).toContain( 'Saved title' );
		expect( document.activeElement ).toBe( button( 'Edit title' ) );
		expect( speak ).toHaveBeenCalledWith( expect.stringMatching( /saved|updated/i ) );
	} );

	it( 'moves focus into the field when editing starts', () => {
		act( () => root.render( <Standalone onChange={ () => {} } /> ) );

		act( () => Simulate.click( button( 'Edit title' ) ) );

		expect( document.activeElement ).toBe( input() );
	} );

	it( 'passes the help text to the input control when one is given, and none by default', () => {
		act( () => root.render( <Standalone onChange={ () => {} } help="Sets how this document appears here." /> ) );
		act( () => Simulate.click( button( 'Edit title' ) ) );

		expect( container.querySelector( '.edbs-resource-card__title-edit label' ).getAttribute( 'data-help' ) ).toBe( 'Sets how this document appears here.' );

		act( () => root.unmount() );
		root = createRoot( container );
		act( () => root.render( <Standalone onChange={ () => {} } /> ) );
		act( () => Simulate.click( button( 'Edit title' ) ) );

		expect( container.querySelector( '.edbs-resource-card__title-edit label' ).getAttribute( 'data-help' ) ).toBe( '' );
	} );
} );

describe( 'ResourceListField (Supporting Documents) title editor', () => {
	const FIELD = { key: 'edbs_supporting_documents', label: 'Supporting Documents', itemNoun: 'Document' };

	function Harness() {
		const [ items, setItems ] = useState( [ { label: 'Budget', url: 'https://example.com/budget.pdf', source: 'external_url' } ] );
		return <ResourceListField field={ FIELD } value={ items } onChange={ setItems } />;
	}

	const editButton = () => container.querySelector( '.edbs-resource-card__actions button[id$="-edit-title"]' );

	it( 'explains that the title does not rename the document, Escape cancels, and focus returns to the row\'s Edit title', () => {
		act( () => root.render( <Harness /> ) );

		act( () => Simulate.click( editButton() ) );
		expect( container.querySelector( '.edbs-resource-card__title-edit label' ).getAttribute( 'data-help' ) ).toMatch( /does not rename the document/i );

		act( () => Simulate.change( input(), { target: { value: 'Changed' } } ) );
		act( () => Simulate.keyDown( input(), { key: 'Escape' } ) );
		flush();

		expect( input() ).toBeNull();
		expect( container.textContent ).toContain( 'Budget' );
		expect( container.textContent ).not.toContain( 'Changed' );
		expect( document.activeElement ).toBe( editButton() );
	} );

	it( 'Cancel and Save both close the editor and return focus to the row\'s Edit title', () => {
		act( () => root.render( <Harness /> ) );

		act( () => Simulate.click( editButton() ) );
		act( () => Simulate.click( button( 'Cancel' ) ) );
		flush();
		expect( document.activeElement ).toBe( editButton() );

		act( () => Simulate.click( editButton() ) );
		act( () => Simulate.change( input(), { target: { value: 'Budget 2027' } } ) );
		act( () => Simulate.click( button( 'Save' ) ) );
		flush();

		expect( container.textContent ).toContain( 'Budget 2027' );
		expect( document.activeElement ).toBe( editButton() );
	} );
} );
