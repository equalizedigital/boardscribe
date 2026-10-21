/**
 * PRO-1337 regression coverage: Replace must never touch a Supporting
 * Documents row's own display title, regardless of whether the source it
 * was replaced with hands back a title of its own.
 *
 * Before this fix, ResourceListField's Replace branch overwrote a row's
 * label whenever the new source supplied an `extra.title` (Media
 * Library's attachment title, or Pro's "document" source's post title),
 * but left the label alone for a source with no title (External URL).
 * That made Replace's behavior depend on which source it was replaced
 * *with* - a title-bearing source silently clobbered a custom title an
 * editor had set by hand, while a titleless source left a stale title
 * behind when replacing a title-bearing row. A source's title should
 * only ever seed a *new* row (see the Add-path tests below).
 */
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { Simulate } from 'react-dom/test-utils';
import { useEffect, useState } from '@wordpress/element';
import { ResourceListField } from '../../../src/js/metabox/resource-list-field';

window.IS_REACT_ACT_ENVIRONMENT = true;

/**
 * A minimal custom Add/Replace source that immediately "picks" a fixed
 * url/title pair on render, standing in for Pro's document source or
 * Media Library - both hand back an `extra.title`.
 *
 * @param {Object}   props        Component props.
 * @param {Function} props.onSave Called with the fixed url/title.
 * @return {null} Renders nothing.
 */
function TitledSource( { onSave } ) {
	useEffect( () => {
		onSave( 'https://example.com/new-document', { title: 'New Document Title' } );
		// Intentionally fires once on mount, not on every onSave identity change.
	}, [] );
	return null;
}

beforeAll( () => {
	window.edbsResourceSources = {
		titled_source: {
			label: 'Titled source',
			description: 'A source that hands back a title.',
			render: TitledSource,
		},
	};
} );

afterAll( () => {
	delete window.edbsResourceSources;
} );

const FIELD_WITH_TITLED_SOURCE = {
	key: 'edbs_supporting_documents',
	label: 'Supporting Documents',
	itemNoun: 'Document',
	sources: [ 'titled_source' ],
};

const FIELD_WITH_EXTERNAL_URL = {
	key: 'edbs_supporting_documents',
	label: 'Supporting Documents',
	itemNoun: 'Document',
	sources: [ 'external_url' ],
};

/**
 * Mirrors how MetaBoxApp actually uses ResourceListField - a controlled
 * `value`/`onChange` pair.
 *
 * @param {Object}   props               Component props.
 * @param {Object}   props.field         Field descriptor.
 * @param {Array}    props.initialItems  The repeater's starting items.
 * @param {Function} props.onItemsChange Called with the latest items array on every change.
 * @return {JSX.Element} The harness.
 */
function Harness( { field, initialItems, onItemsChange } ) {
	const [ items, setItems ] = useState( initialItems );
	return (
		<ResourceListField
			field={ field }
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

function clickAction( label ) {
	const button = Array.from( container.querySelectorAll( 'button' ) ).find( ( candidate ) => label === candidate.textContent );
	if ( ! button ) {
		throw new Error( `No button found with text "${ label }"` );
	}
	act( () => {
		Simulate.click( button );
	} );
}

describe( 'ResourceListField Replace preserves the existing title', () => {
	it( 'does not overwrite a custom title when replacing via a source that supplies its own title', () => {
		let latestItems;
		act( () => {
			root.render(
				<Harness
					field={ FIELD_WITH_TITLED_SOURCE }
					initialItems={ [ { label: 'My Custom Title', url: 'https://example.com/old-document', source: 'external_url' } ] }
					onItemsChange={ ( next ) => {
						latestItems = next;
					} }
				/>,
			);
		} );

		clickAction( 'Replace' );

		expect( latestItems ).toHaveLength( 1 );
		expect( latestItems[ 0 ].label ).toBe( 'My Custom Title' );
		expect( latestItems[ 0 ].url ).toBe( 'https://example.com/new-document' );
		expect( latestItems[ 0 ].source ).toBe( 'titled_source' );
	} );

	it( 'does not introduce a title when replacing a titled row via a source with no title of its own', () => {
		let latestItems;
		act( () => {
			root.render(
				<Harness
					field={ FIELD_WITH_EXTERNAL_URL }
					initialItems={ [ { label: 'Old Document Title', url: 'https://example.com/old-document', source: 'titled_source' } ] }
					onItemsChange={ ( next ) => {
						latestItems = next;
					} }
				/>,
			);
		} );

		clickAction( 'Replace' );
		act( () => {
			Simulate.change( container.querySelector( 'input[type="url"]' ), { target: { value: 'https://example.com/replaced' } } );
		} );
		clickAction( 'Save URL' );

		expect( latestItems[ 0 ].label ).toBe( 'Old Document Title' );
		expect( latestItems[ 0 ].url ).toBe( 'https://example.com/replaced' );
	} );

	it( 'still seeds a new row\'s title from a source that supplies one (Add is unaffected)', () => {
		let latestItems;
		act( () => {
			root.render(
				<Harness field={ FIELD_WITH_TITLED_SOURCE } initialItems={ [] } onItemsChange={ ( next ) => ( latestItems = next ) } />,
			);
		} );

		clickAction( 'Add Document' ); // ResourceCardEmpty's actionLabel, "Add {itemNoun}".

		expect( latestItems ).toHaveLength( 1 );
		expect( latestItems[ 0 ].label ).toBe( 'New Document Title' );
	} );
} );
