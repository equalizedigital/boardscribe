/**
 * Regression test for the block's InspectorControls: every field the
 * shared FieldRegistry (PHP) hands the editor via window.edbsBlockFieldRegistry
 * must produce a visible control, whether it's a core field, a
 * Pro/third-party field (edbs_shortcode_field_registry), or one of the
 * hand-rendered special cases (template/postsPerPage/className) - this is
 * exactly the gap a previous manual test run reported (postsPerPage
 * missing from the block), which turned out not to reproduce on this
 * codebase; this test pins that down so a real future regression fails
 * loudly instead of needing another manual comparison.
 *
 * The block-editor `@wordpress` packages (blocks, block-editor, components,
 * server-side-render) are mocked (see jest.config.js) - they're build-time
 * externals (wp.* globals) in the real bundle, not installed packages
 * Jest can resolve.
 */
import { createRoot } from 'react-dom/client';
import { act } from 'react';
import { createElement } from '@wordpress/element';
import { registerBlockType } from '@wordpress/blocks';

// React 18's act() only suppresses its "not wrapped in act()" warnings
// when this flag is set - there's no jsdom auto-detection for it.
window.IS_REACT_ACT_ENVIRONMENT = true;

// Every rendered container's root, keyed by container, so afterEach can
// unmount via the same React 18 API each was created with.
const roots = new WeakMap();

const FIELD_FIXTURE = [
	{
		key: 'included_years',
		attributeKey: 'includedYears',
		configKey: 'includedYears',
		type: 'text',
		group: 'general',
		label: 'Included Years',
		default: '',
		choices: null,
		placeholder: '2023,2024',
		description: null,
	},
	{
		key: 'posts_per_page',
		attributeKey: 'postsPerPage',
		configKey: 'postsPerPage',
		type: 'number_with_all',
		group: 'general',
		label: 'Posts Per Page',
		default: 20,
		choices: null,
		placeholder: null,
		description: null,
	},
	{
		key: 'template',
		attributeKey: 'template',
		configKey: 'template',
		type: 'select',
		group: 'general',
		label: 'Display Template',
		default: '',
		choices: { '': 'Table (default)' },
		placeholder: null,
		description: null,
	},
	{
		key: 'equal_columns',
		attributeKey: 'equalColumns',
		configKey: 'equalColumns',
		type: 'checkbox',
		group: 'general',
		label: 'Force all columns to the same width',
		default: false,
		choices: null,
		placeholder: null,
		description: null,
	},
	{
		key: 'class',
		attributeKey: 'className',
		configKey: 'tableClass',
		type: 'text',
		group: 'general',
		label: 'Custom CSS Class',
		default: '',
		choices: null,
		placeholder: null,
		description: null,
	},
	{
		key: 'title_label',
		attributeKey: 'titleLabel',
		configKey: 'titleLabel',
		type: 'text',
		group: 'column_labels',
		label: 'Title',
		default: '',
		choices: null,
		placeholder: null,
		description: null,
	},
	{
		key: 'hide_title',
		attributeKey: 'hideTitle',
		configKey: 'hideTitle',
		type: 'checkbox',
		group: 'hide_columns',
		label: 'Title',
		default: false,
		choices: null,
		placeholder: null,
		description: null,
	},
	// Simulates a Pro/third-party field appended via the
	// edbs_shortcode_field_registry filter - proves it shows up with zero
	// block-specific code, per the shared-registry design.
	{
		key: 'location_label',
		attributeKey: 'locationLabel',
		configKey: 'locationLabel',
		type: 'text',
		group: 'column_labels',
		label: 'Location',
		default: '',
		choices: null,
		placeholder: null,
		description: null,
	},
];

function defaultAttributes() {
	const attributes = {};
	FIELD_FIXTURE.forEach( ( field ) => {
		attributes[ field.attributeKey ] = field.default;
	} );
	return attributes;
}

function renderEdit( attributes, setAttributes = jest.fn() ) {
	const container = document.createElement( 'div' );
	document.body.appendChild( container );

	const root = createRoot( container );
	roots.set( container, root );

	act( () => {
		root.render(
			// Rendered as a component (not called as a plain function) so
			// React's hook dispatcher is actually active when edit() runs
			// useRef/etc - calling it directly throws "Invalid hook call".
			createElement( registerBlockType.mock.settings.edit, { attributes, setAttributes } ),
		);
	} );

	return container;
}

function controlFor( container, controlType, label ) {
	return container.querySelector(
		`[data-control="${ controlType }"][data-label="${ label }"]`,
	);
}

describe( 'BoardScribe block edit()', () => {
	let container;

	beforeAll( () => {
		window.edbsBlockFieldRegistry = FIELD_FIXTURE;
		// The block registers itself as a side effect of being imported.
		require( '../../../src/js/block/index' );
	} );

	afterEach( () => {
		if ( container ) {
			act( () => {
				roots.get( container )?.unmount();
			} );
			container.remove();
			container = null;
		}
	} );

	it( 'registers the equalize-digital/boardscribe block', () => {
		expect( registerBlockType.mock.name ).toBe( 'equalize-digital/boardscribe' );
		expect( typeof registerBlockType.mock.settings.edit ).toBe( 'function' );
	} );

	it( 'renders a control for every non-special-cased registry field', () => {
		container = renderEdit( defaultAttributes() );

		// Generic "general" group fields.
		expect( controlFor( container, 'text', 'Included Years' ) ).not.toBeNull();
		expect( controlFor( container, 'toggle', 'Force all columns to the same width' ) ).not.toBeNull();

		// column_labels group, including a simulated Pro field - proves
		// third-party registry additions need no block-specific code.
		expect( controlFor( container, 'text', 'Location' ) ).not.toBeNull();

		// hide_columns group - "Title" here is a checkbox, distinct from
		// the "Title" text field in column_labels above.
		expect( controlFor( container, 'toggle', 'Title' ) ).not.toBeNull();
	} );

	it( 'renders the Posts Per Page control (the field previously reported missing)', () => {
		container = renderEdit( defaultAttributes() );

		expect( controlFor( container, 'toggle', 'Show all meetings' ) ).not.toBeNull();
		expect( controlFor( container, 'number', 'Records Per Page' ) ).not.toBeNull();
	} );

	it( 'hides the Records Per Page number field once "Show all meetings" is on', () => {
		container = renderEdit( { ...defaultAttributes(), postsPerPage: -1 } );

		expect( controlFor( container, 'toggle', 'Show all meetings' ) ).not.toBeNull();
		expect( controlFor( container, 'number', 'Records Per Page' ) ).toBeNull();
	} );

	it( 'renders the template picker from the registry field, not a hand-maintained list', () => {
		container = renderEdit( defaultAttributes() );

		expect( controlFor( container, 'select', 'Display Template' ) ).not.toBeNull();
	} );

	it( 'renders no control at all for className - it uses the block\'s native Advanced panel field instead', () => {
		container = renderEdit( defaultAttributes() );

		expect( container.querySelector( '[data-label="Custom CSS Class"]' ) ).toBeNull();
	} );

	it( 'groups fields into panels matching the shortcode builder\'s groups, skipping empty ones', () => {
		container = renderEdit( defaultAttributes() );

		const panelTitles = Array.from( container.querySelectorAll( '[data-panel]' ) ).map(
			( el ) => el.getAttribute( 'data-panel' ),
		);

		expect( panelTitles ).toEqual(
			expect.arrayContaining( [ 'Display Settings', 'Column Labels', 'Hide Columns' ] ),
		);
		// The fixture has no link_labels/show_columns fields - those panels
		// must not render at all (renderGenericFields() gates on group.length).
		expect( panelTitles ).not.toContain( 'Link Labels' );
		expect( panelTitles ).not.toContain( 'Show Columns' );
	} );
} );
