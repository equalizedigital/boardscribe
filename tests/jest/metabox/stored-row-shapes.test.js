/**
 * A resource_list field's rows come from post meta, and that meta can hold a
 * shape this app never wrote: an older version of the plugin, a CSV import,
 * a filter, or a database write that skipped the sanitizer. The app trusted
 * every parsed row to be an object, but a repeater row is put in a WeakMap to
 * get its React key (a primitive throws "Invalid value used as weak map key")
 * and its label is rendered as a child (an object throws "Objects are not
 * valid as a React child"). Either way React unmounts the whole Meeting
 * Details box, taking every other field on the screen with it until the
 * editor is reloaded - found while testing the reported title-editor crash
 * (PRO-1424).
 *
 * These tests render the real app against each stored shape and assert that
 * the box survives, that unusable rows are dropped rather than dropped on,
 * and that what the form would go on to save holds only real rows.
 */
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { MetaBoxApp } from '../../../src/js/metabox/app';

window.IS_REACT_ACT_ENVIRONMENT = true;

const FIELD = {
	key: 'edbs_supporting_documents',
	type: 'resource_list',
	label: 'Supporting Documents',
	itemNoun: 'Document',
	group: null,
	description: null,
	required: false,
};

const GOOD_ROW = {
	label: 'Board packet',
	url: 'https://example.org/board-packet.pdf',
	source: 'external_url',
	document_id: 12,
	edit_url: '',
};

const SECOND_ROW = {
	label: 'Minutes',
	url: 'https://example.org/minutes.pdf',
	source: 'external_url',
	document_id: 12,
	edit_url: '',
};

/**
 * Renders the app with one stored value and collects anything React threw.
 *
 * The throw is caught (rather than left to fail the test on its own) so the
 * DOM assertions below can report what the screen actually ended up as -
 * the failure this guards against is a silent unmount, not a crash page.
 *
 * @param {string} raw The stored meta value, as `data-values` would deliver it.
 * @return {{container: HTMLElement, root: Object, errors: Array}} Mounted app.
 */
function renderApp( raw ) {
	const container = document.createElement( 'div' );
	document.body.appendChild( container );
	const root = createRoot( container );
	const errors = [];

	try {
		act( () => {
			root.render( <MetaBoxApp fields={ [ FIELD ] } initialValues={ { [ FIELD.key ]: raw } } /> );
		} );
	} catch ( e ) {
		errors.push( `${ e.name }: ${ e.message }` );
	}

	return { container, root, errors };
}

/**
 * The hidden inputs this field would submit, in DOM order.
 *
 * @param {HTMLElement} container Mounted app container.
 * @return {Array<Array<string>>} `[name, value]` pairs.
 */
function submittedRows( container ) {
	return [ ...container.querySelectorAll( 'input[type="hidden"]' ) ]
		.filter( ( input ) => input.name.startsWith( FIELD.key ) )
		.map( ( input ) => [ input.name, input.value ] );
}

/**
 * Unmounts a mounted app, so a failing assertion can't leak a root into the
 * next test.
 *
 * @param {{container: HTMLElement, root: Object}} mounted Result of renderApp().
 */
function cleanup( { container, root } ) {
	act( () => root.unmount() );
	container.remove();
}

describe( 'a stored resource_list value the app did not write', () => {
	const unusable = {
		'a bare string row': JSON.stringify( [ 'Board packet' ] ),
		'a null row': JSON.stringify( [ null ] ),
		'a number row': JSON.stringify( [ 233 ] ),
		'an array row': JSON.stringify( [ [ 'label', 'url' ] ] ),
		'a boolean row': JSON.stringify( [ true ] ),
	};

	for ( const [ description, raw ] of Object.entries( unusable ) ) {
		it( `renders the empty state for ${ description } instead of taking the box down`, () => {
			const mounted = renderApp( raw );

			expect( mounted.errors ).toEqual( [] );
			expect( mounted.container.querySelector( '.edbs-metabox-app' ) ).not.toBe( null );
			expect( mounted.container.textContent ).toContain( 'No documents added.' );
			expect( mounted.container.querySelectorAll( '.edbs-resource-card:not(.edbs-resource-card--empty)' ) ).toHaveLength( 0 );

			cleanup( mounted );
		} );
	}

	it( 'keeps the rows it can render and drops the ones it cannot', () => {
		const raw = JSON.stringify( [ GOOD_ROW, 'junk', null, SECOND_ROW ] );
		const mounted = renderApp( raw );

		expect( mounted.errors ).toEqual( [] );

		const titles = [ ...mounted.container.querySelectorAll( '.edbs-resource-card__title' ) ].map( ( el ) => el.textContent );
		expect( titles ).toEqual( [ 'Board packet', 'Minutes' ] );

		// What the form would submit is only real rows, so one bad stored row
		// heals itself the next time the meeting is saved.
		expect( submittedRows( mounted.container ) ).toEqual( [
			[ `${ FIELD.key }[0][label]`, 'Board packet' ],
			[ `${ FIELD.key }[0][url]`, 'https://example.org/board-packet.pdf' ],
			[ `${ FIELD.key }[0][source]`, 'external_url' ],
			[ `${ FIELD.key }[0][document_id]`, '12' ],
			[ `${ FIELD.key }[1][label]`, 'Minutes' ],
			[ `${ FIELD.key }[1][url]`, 'https://example.org/minutes.pdf' ],
			[ `${ FIELD.key }[1][source]`, 'external_url' ],
			[ `${ FIELD.key }[1][document_id]`, '12' ],
		] );

		cleanup( mounted );
	} );

	it( 'renders a row whose label is not a string as untitled rather than throwing', () => {
		const raw = JSON.stringify( [ { ...GOOD_ROW, label: { raw: 'Board packet' } } ] );
		const mounted = renderApp( raw );

		expect( mounted.errors ).toEqual( [] );
		expect( mounted.container.querySelector( '.edbs-resource-card__title' ).textContent ).toBe( 'Untitled document' );
		expect( submittedRows( mounted.container ) ).toContainEqual( [ `${ FIELD.key }[0][label]`, '' ] );

		cleanup( mounted );
	} );

	it( 'renders a row whose url is not a string as blank rather than throwing', () => {
		const raw = JSON.stringify( [ { label: 'Board packet', url: { raw: 'https://example.org/board-packet.pdf' }, source: 'external_url' } ] );
		const mounted = renderApp( raw );

		expect( mounted.errors ).toEqual( [] );
		expect( mounted.container.querySelector( '.edbs-resource-card__title' ).textContent ).toBe( 'Board packet' );
		expect( submittedRows( mounted.container ) ).toContainEqual( [ `${ FIELD.key }[0][url]`, '' ] );

		cleanup( mounted );
	} );

	it( 'keeps an object row with nothing usable in it rather than dropping stored data', () => {
		const mounted = renderApp( JSON.stringify( [ {} ] ) );

		expect( mounted.errors ).toEqual( [] );
		expect( mounted.container.querySelectorAll( '.edbs-resource-card:not(.edbs-resource-card--empty)' ) ).toHaveLength( 1 );
		expect( mounted.container.querySelector( '.edbs-resource-card__title' ).textContent ).toBe( 'Untitled document' );

		cleanup( mounted );
	} );

	it( 'still renders a normal stored value', () => {
		const mounted = renderApp( JSON.stringify( [ GOOD_ROW ] ) );

		expect( mounted.errors ).toEqual( [] );
		expect( mounted.container.querySelector( '.edbs-resource-card__title' ).textContent ).toBe( 'Board packet' );
		expect( submittedRows( mounted.container ) ).toContainEqual( [ `${ FIELD.key }[0][label]`, 'Board packet' ] );

		cleanup( mounted );
	} );

	it( 'renders the empty state for a stored value that is not an array at all', () => {
		for ( const raw of [ JSON.stringify( { 0: GOOD_ROW } ), '', 'not json' ] ) {
			const mounted = renderApp( raw );

			expect( mounted.errors ).toEqual( [] );
			expect( mounted.container.textContent ).toContain( 'No documents added.' );

			cleanup( mounted );
		}
	} );
} );
