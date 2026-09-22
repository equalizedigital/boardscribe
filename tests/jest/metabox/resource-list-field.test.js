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

/**
 * PRO-1362 regression coverage: a keyboard reorder must land focus back on
 * the moved row's own Move up/down button in its new position, not
 * document.body - and a row's reorder buttons stay in the tab order (just
 * a no-op) at either end of the list instead of vanishing via a real
 * `disabled` attribute.
 */
describe( 'ResourceListField keyboard reorder focus (PRO-1362)', () => {
	const threeRows = () => [
		{ label: '', url: 'https://example.com/doc-one.pdf', source: 'external_url' },
		{ label: '', url: 'https://example.com/doc-two.pdf', source: 'external_url' },
		{ label: '', url: 'https://example.com/doc-three.pdf', source: 'external_url' },
	];

	it( 'moves focus to the moved row\'s own Move up button in its new position', () => {
		act( () => {
			root.render( <Harness initialItems={ threeRows() } onItemsChange={ () => {} } /> );
		} );

		// Move the middle row (doc-two) up - it should end up first.
		act( () => {
			Simulate.click( moveUpButtonFor( rows()[ 1 ] ) );
		} );

		const [ firstRow ] = rows();
		expect( firstRow.textContent ).toContain( 'doc-two.pdf' );
		expect( document.activeElement ).toBe( moveUpButtonFor( firstRow ) );
	} );

	it( 'moves focus to the moved row\'s own Move down button in its new position', () => {
		act( () => {
			root.render( <Harness initialItems={ threeRows() } onItemsChange={ () => {} } /> );
		} );

		function moveDownButtonFor( row ) {
			return row.querySelectorAll( '.edbs-resource-card__reorder-button' )[ 1 ];
		}

		// Move the first row (doc-one) down - it should end up second.
		act( () => {
			Simulate.click( moveDownButtonFor( rows()[ 0 ] ) );
		} );

		const secondRow = rows()[ 1 ];
		expect( secondRow.textContent ).toContain( 'doc-one.pdf' );
		expect( document.activeElement ).toBe( moveDownButtonFor( secondRow ) );
	} );

	it( 'keeps the first row\'s Move up button in the tab order (aria-disabled, not disabled) and a no-op', () => {
		act( () => {
			root.render( <Harness initialItems={ threeRows() } onItemsChange={ () => {} } /> );
		} );

		const firstMoveUp = moveUpButtonFor( rows()[ 0 ] );
		expect( firstMoveUp.disabled ).toBe( false );
		expect( firstMoveUp.getAttribute( 'aria-disabled' ) ).toBe( 'true' );

		act( () => {
			Simulate.click( firstMoveUp );
		} );

		// Still doc-one first - clicking the boundary button was a no-op.
		expect( rows()[ 0 ].textContent ).toContain( 'doc-one.pdf' );
	} );
} );

/**
 * PRO-1364 regression coverage: removing a row must retarget editingIndex
 * (by the same stable identity reorder() already retargets through a move),
 * not leave it pointing at whatever row ends up at its old numeric
 * position.
 */
describe( 'ResourceListField title editor across a removal (PRO-1364)', () => {
	const threeRows = () => [
		{ label: '', url: 'https://example.com/doc-one.pdf', source: 'external_url' },
		{ label: '', url: 'https://example.com/doc-two.pdf', source: 'external_url' },
		{ label: '', url: 'https://example.com/doc-three.pdf', source: 'external_url' },
	];

	function removeButtonFor( row ) {
		return Array.from( row.querySelectorAll( 'button' ) ).find( ( button ) => 'Remove' === button.textContent );
	}

	it( 'keeps an open title editor (and its draft) on the row being edited after an earlier row is removed', () => {
		act( () => {
			root.render( <Harness initialItems={ threeRows() } onItemsChange={ () => {} } /> );
		} );

		// Open the third row's (doc-three) title editor.
		const addTitleButtons = Array.from( container.querySelectorAll( '.edbs-resource-card__actions button' ) )
			.filter( ( button ) => 'Add title' === button.textContent );
		act( () => {
			Simulate.click( addTitleButtons[ 2 ] );
		} );
		act( () => {
			Simulate.change( rows()[ 2 ].querySelector( '.edbs-resource-card__title-edit input' ), {
				target: { value: 'Draft for doc three' },
			} );
		} );

		// Remove the first row (doc-one) while that editor is still open.
		act( () => {
			Simulate.click( removeButtonFor( rows()[ 0 ] ) );
		} );

		const [ firstRow, secondRow ] = rows();
		expect( firstRow.textContent ).toContain( 'doc-two.pdf' );
		expect( secondRow.textContent ).toContain( 'doc-three.pdf' );

		// The editor followed doc-three to its new (now second) position,
		// draft intact - doc-two's row (now first) has no open editor.
		expect( firstRow.querySelector( '.edbs-resource-card__title-edit input' ) ).toBeNull();
		const secondRowInput = secondRow.querySelector( '.edbs-resource-card__title-edit input' );
		expect( secondRowInput ).not.toBeNull();
		expect( secondRowInput.value ).toBe( 'Draft for doc three' );
	} );

	it( 'closes the editor when its own row is the one removed', () => {
		act( () => {
			root.render( <Harness initialItems={ threeRows() } onItemsChange={ () => {} } /> );
		} );

		const addTitleButtons = Array.from( container.querySelectorAll( '.edbs-resource-card__actions button' ) )
			.filter( ( button ) => 'Add title' === button.textContent );
		act( () => {
			Simulate.click( addTitleButtons[ 0 ] );
		} );
		expect( rows()[ 0 ].querySelector( '.edbs-resource-card__title-edit input' ) ).not.toBeNull();

		act( () => {
			Simulate.click( removeButtonFor( rows()[ 0 ] ) );
		} );

		expect( rows() ).toHaveLength( 2 );
		expect( container.querySelector( '.edbs-resource-card__title-edit input' ) ).toBeNull();
	} );
} );
