/**
 * PRO-1365 regression coverage: AttachedRepeaterField used to key each row
 * by its position in the array (`key={ index }`), so removing an earlier
 * row while a later row's inline title editor was open let React reuse
 * that editor's own component instance (and its open/focused isEditing
 * state) for whatever row ended up at the same index afterward - the
 * editor stayed visually open, but silently attached to different data.
 * Keying by the same stable per-row identity ResourceListField already
 * uses (getRowKey(), see resource-utils.js's createRowKeyer()) fixes it:
 * React moves the same component instance (and its state) along with the
 * row it belongs to, instead of reusing it by position.
 */
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { Simulate } from 'react-dom/test-utils';
import { useState } from '@wordpress/element';
import { AttachedRepeaterField } from '../../../src/js/metabox/attached-repeater-field';

window.IS_REACT_ACT_ENVIRONMENT = true;

const FIELD = { key: 'edbs_caption_tracks', label: 'Caption tracks', itemNoun: 'Track' };

/**
 * Mirrors how a real caller wires this field - a controlled `value`/
 * `onChange` pair.
 *
 * @param {Object} props             Component props.
 * @param {Array}  props.initialRows The repeater's starting rows.
 * @return {JSX.Element} The harness.
 */
function Harness( { initialRows } ) {
	const [ fieldRows, setFieldRows ] = useState( initialRows );
	return <AttachedRepeaterField field={ FIELD } value={ fieldRows } onChange={ setFieldRows } />;
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
	return Array.from( container.querySelectorAll( '.edbs-attached-repeater__row' ) );
}

function editTitleButtonFor( row ) {
	return Array.from( row.querySelectorAll( 'button' ) ).find( ( button ) => /title/i.test( button.textContent ) );
}

function removeButtonFor( row ) {
	return Array.from( row.querySelectorAll( 'button' ) ).find( ( button ) => 'Remove' === button.textContent );
}

describe( 'AttachedRepeaterField title editor across a removal (PRO-1365)', () => {
	const threeRows = () => [
		{ label: 'Row A', url: 'https://example.com/a.vtt', source: 'media_library' },
		{ label: 'Row B', url: 'https://example.com/b.vtt', source: 'media_library' },
		{ label: 'Row C', url: 'https://example.com/c.vtt', source: 'media_library' },
	];

	it( 'keeps an open editor (and its unsaved draft) on the row being edited after an earlier row is removed', () => {
		act( () => {
			root.render( <Harness initialRows={ threeRows() } /> );
		} );

		// Open row B's (index 1) title editor.
		act( () => {
			Simulate.click( editTitleButtonFor( rows()[ 1 ] ) );
		} );
		const rowBInput = rows()[ 1 ].querySelector( '.edbs-resource-card__title-edit input' );
		expect( rowBInput ).not.toBeNull();
		act( () => {
			Simulate.change( rowBInput, { target: { value: 'Draft for row B' } } );
		} );

		// Remove row A (index 0) while row B's editor is still open.
		act( () => {
			Simulate.click( removeButtonFor( rows()[ 0 ] ) );
		} );

		const [ firstRow, secondRow ] = rows();
		expect( firstRow.textContent ).toContain( 'b.vtt' );
		expect( secondRow.textContent ).toContain( 'c.vtt' );

		// The editor - with its draft intact - followed row B to its new
		// (now first) position, not left open on whichever row landed at
		// the old index-1 slot.
		const firstRowInput = firstRow.querySelector( '.edbs-resource-card__title-edit input' );
		expect( firstRowInput ).not.toBeNull();
		expect( firstRowInput.value ).toBe( 'Draft for row B' );
		expect( secondRow.querySelector( '.edbs-resource-card__title-edit input' ) ).toBeNull();
	} );
} );
