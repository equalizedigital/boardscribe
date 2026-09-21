/**
 * PRO-1352 regression coverage: reordering Supporting Documents while a
 * row's title editor is open must keep the editor (and its unsaved draft)
 * attached to the row being edited, not to whatever row ends up at the old
 * numeric position. See resource-list-field.js's reorder()/getRowKey()
 * docblocks for the two separate bugs this used to hit (editingIndex not
 * retargeted; rows keyed by array position) and why both had to be fixed
 * together.
 */
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { Simulate } from 'react-dom/test-utils';
import { useState } from '@wordpress/element';
import { ResourceListField } from '../../../src/js/metabox/resource-list-field';

// React 18's act() only suppresses its "not wrapped in act" warnings when
// this is set - @wordpress/jest-preset-default's jsdom environment doesn't
// set it for us the way @testing-library/react's setup normally would.
window.IS_REACT_ACT_ENVIRONMENT = true;

const FIELD = { key: 'edbs_supporting_documents', label: 'Supporting Documents', itemNoun: 'Document' };

/**
 * Mirrors how MetaBoxApp actually uses ResourceListField - a controlled
 * `value`/`onChange` pair - so a reorder's onChange -> setState -> re-render
 * round trip behaves the same as it does in the real meta box, not just
 * against a static value prop.
 *
 * @param {Object}   props               Component props.
 * @param {Array}    props.initialItems  The repeater's starting items.
 * @param {Function} props.onItemsChange Called with the latest items array on every change.
 * @return {JSX.Element} The harness.
 */
function Harness( { initialItems, onItemsChange } ) {
	const [ items, setItems ] = useState( initialItems );
	return (
		<ResourceListField
			field={ FIELD }
			value={ items }
			onChange={ ( next ) => {
				setItems( next );
				onItemsChange( next );
			} }
		/>
	);
}

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

function rows() {
	return Array.from( container.querySelectorAll( '.edbs-resource-list__row' ) );
}

function moveUpButtonFor( row ) {
	return row.querySelectorAll( '.edbs-resource-card__reorder-button' )[ 0 ];
}

describe( 'ResourceListField title editor across a reorder', () => {
	const twoRows = () => [
		{ label: '', url: 'https://example.com/doc-one.pdf', source: 'external_url' },
		{ label: '', url: 'https://example.com/doc-two.pdf', source: 'external_url' },
	];

	it( 'keeps an open title editor and its unsaved draft on the row being edited after Move up', () => {
		act( () => {
			root.render( <Harness initialItems={ twoRows() } onItemsChange={ () => {} } /> );
		} );

		// Open the second row's title editor (the "Add title" trigger - both
		// rows are untitled).
		const addTitleButtons = Array.from( container.querySelectorAll( '.edbs-resource-card__actions button' ) )
			.filter( ( button ) => 'Add title' === button.textContent );
		expect( addTitleButtons ).toHaveLength( 2 );
		act( () => {
			Simulate.click( addTitleButtons[ 1 ] );
		} );

		const secondRowInput = rows()[ 1 ].querySelector( '.edbs-resource-card__title-edit input' );
		expect( secondRowInput ).not.toBeNull();

		act( () => {
			Simulate.change( secondRowInput, { target: { value: 'Draft title for doc two' } } );
		} );

		// Move that same row (still index 1) up, before saving the draft.
		act( () => {
			Simulate.click( moveUpButtonFor( rows()[ 1 ] ) );
		} );

		const [ firstRow, secondRow ] = rows();

		// The moved document (doc-two) is now first...
		expect( firstRow.textContent ).toContain( 'doc-two.pdf' );
		expect( secondRow.textContent ).toContain( 'doc-one.pdf' );

		// ...and the title editor - with its draft intact, not reset to
		// empty - followed it there, rather than staying on the row that
		// now happens to sit at the old index-1 position (doc-one).
		const firstRowInput = firstRow.querySelector( '.edbs-resource-card__title-edit input' );
		expect( firstRowInput ).not.toBeNull();
		expect( firstRowInput.value ).toBe( 'Draft title for doc two' );
		expect( secondRow.querySelector( '.edbs-resource-card__title-edit input' ) ).toBeNull();
	} );

	it( 'applies a save after Move up to the originally-edited document, not the row left behind', () => {
		let latestItems = twoRows();
		act( () => {
			root.render(
				<Harness
					initialItems={ latestItems }
					onItemsChange={ ( next ) => {
						latestItems = next;
					} }
				/>,
			);
		} );

		const addTitleButtons = Array.from( container.querySelectorAll( '.edbs-resource-card__actions button' ) )
			.filter( ( button ) => 'Add title' === button.textContent );
		act( () => {
			Simulate.click( addTitleButtons[ 1 ] );
		} );
		act( () => {
			Simulate.change( rows()[ 1 ].querySelector( '.edbs-resource-card__title-edit input' ), {
				target: { value: 'Draft title for doc two' },
			} );
		} );
		act( () => {
			Simulate.click( moveUpButtonFor( rows()[ 1 ] ) );
		} );

		// Save via the row's own Save button, now at the top.
		const saveButton = Array.from( rows()[ 0 ].querySelectorAll( 'button' ) ).find( ( button ) => 'Save' === button.textContent );
		act( () => {
			Simulate.click( saveButton );
		} );

		expect( latestItems.find( ( item ) => 'https://example.com/doc-two.pdf' === item.url ).label ).toBe( 'Draft title for doc two' );
		expect( latestItems.find( ( item ) => 'https://example.com/doc-one.pdf' === item.url ).label ).toBe( '' );
	} );
} );
