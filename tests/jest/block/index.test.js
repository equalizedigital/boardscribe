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
 * The block-editor `@wordpress` packages (blocks, block-editor, components)
 * are mocked (see jest.config.js) - they're build-time externals (wp.*
 * globals) in the real bundle, not installed packages Jest can resolve.
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
	// PRO-1397: simulates a Pro field marked hidden_from_ui once its
	// license lapses (e.g. the category filter) - still present in the
	// localized array (FieldRegistry::js_schema( true )) so the live
	// preview can map its saved value, but flagged so no picker renders.
	{
		key: 'category',
		attributeKey: 'category',
		configKey: 'category',
		type: 'text',
		group: 'general',
		label: 'Category',
		default: '',
		choices: null,
		placeholder: null,
		description: null,
		hiddenFromUi: true,
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
		expect( controlFor( container, 'text', 'Title' ) ).not.toBeNull();
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

	/**
	 * PRO-1416: the template picker's help follows the selected choice
	 * (field.choiceDescriptions), falling back to the field-level description.
	 * Mutates the shared fixture in place for the test, like the hiddenFromUi
	 * test below, since the module keeps a reference to that array.
	 */
	it( 'shows the selected template choice\'s own help text (PRO-1416)', () => {
		const templateField = FIELD_FIXTURE.find( ( field ) => 'template' === field.attributeKey );
		const original = { choices: templateField.choices, choiceDescriptions: templateField.choiceDescriptions };
		templateField.choices = { '': 'Table (default)', list: 'List (stacked)' };
		templateField.choiceDescriptions = { '': 'Table help.', list: 'List help.' };

		try {
			container = renderEdit( { ...defaultAttributes(), template: '' } );
			expect( controlFor( container, 'select', 'Display Template' ).getAttribute( 'data-help' ) ).toBe( 'Table help.' );
			act( () => roots.get( container ).unmount() );
			container.remove();

			container = renderEdit( { ...defaultAttributes(), template: 'list' } );
			expect( controlFor( container, 'select', 'Display Template' ).getAttribute( 'data-help' ) ).toBe( 'List help.' );
		} finally {
			templateField.choices = original.choices;
			if ( undefined === original.choiceDescriptions ) {
				delete templateField.choiceDescriptions;
			} else {
				templateField.choiceDescriptions = original.choiceDescriptions;
			}
		}
	} );

	/**
	 * PRO-1397 follow-up (CodeRabbit finding on PR #141): the template
	 * picker is a special case rendered outside the generic fieldsByGroup
	 * loop, so its own hiddenFromUi check needed adding separately - the
	 * generic loop's skip doesn't cover it. Mutates the shared fixture's
	 * `template` entry in place for the duration of this one test (rather
	 * than reloading the module with a different registry, which would
	 * pull in a second React copy and break hooks) since FIELD_REGISTRY is
	 * the exact same array object window.edbsBlockFieldRegistry pointed to
	 * when the module was first imported in beforeAll().
	 */
	it( 'renders no Display Template control when the template field itself is hiddenFromUi', () => {
		const templateField = FIELD_FIXTURE.find( ( field ) => 'template' === field.attributeKey );
		templateField.hiddenFromUi = true;

		try {
			container = renderEdit( { ...defaultAttributes(), template: 'year_timeline' } );
			expect( controlFor( container, 'select', 'Display Template' ) ).toBeNull();
		} finally {
			delete templateField.hiddenFromUi;
		}
	} );

	it( 'renders no control at all for className - it uses the block\'s native Advanced panel field instead', () => {
		container = renderEdit( defaultAttributes() );

		expect( container.querySelector( '[data-label="Custom CSS Class"]' ) ).toBeNull();
	} );

	it( 'renders no control for a hiddenFromUi field (PRO-1397)', () => {
		container = renderEdit( defaultAttributes() );

		expect( controlFor( container, 'text', 'Category' ) ).toBeNull();
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

/**
 * PRO-1331 regression coverage: the block's live preview renders the same
 * wrapper markup the shortcode emits and drives it through the real
 * frontend pipeline (window.edbsInitInstance), rather than a server-side
 * lookalike - see buildInstanceConfig()'s own docblock in block/index.js.
 */
describe( 'BoardScribe block edit() live preview', () => {
	let container;

	beforeEach( () => {
		jest.useFakeTimers();
		window.edbsInitInstance = jest.fn();
	} );

	afterEach( () => {
		if ( container ) {
			act( () => {
				roots.get( container )?.unmount();
			} );
			container.remove();
			container = null;
		}
		delete window.edbsInitInstance;
		jest.useRealTimers();
	} );

	function rerender( setAttributes, attributes ) {
		act( () => {
			roots.get( container ).render(
				createElement( registerBlockType.mock.settings.edit, { attributes, setAttributes } ),
			);
		} );
	}

	it( 'renders the shortcode\'s wrapper markup and hands it to window.edbsInitInstance', () => {
		container = renderEdit( defaultAttributes() );
		act( () => {
			jest.advanceTimersByTime( 300 );
		} );

		const wrap = container.querySelector( '.edbs-boardscribe-wrap' );
		expect( wrap ).not.toBeNull();
		expect( wrap.querySelector( '.edbs-table-container' ) ).not.toBeNull();
		expect( wrap.querySelector( '.edbs-pagination-container' ) ).not.toBeNull();
		expect( window.edbsInitInstance ).toHaveBeenCalledWith( wrap );
	} );

	it( 'encodes the block\'s attributes into data-config keyed by each field\'s configKey', () => {
		container = renderEdit( { ...defaultAttributes(), includedYears: '2024,2025' } );
		act( () => {
			jest.advanceTimersByTime( 300 );
		} );

		const config = JSON.parse( container.querySelector( '.edbs-boardscribe-wrap' ).dataset.config );
		expect( config.includedYears ).toBe( '2024,2025' );
		// See instance.js - keeps preview pagination out of the editor's own URL.
		expect( config.urlState ).toBe( false );
		expect( config.instanceId ).toEqual( expect.stringMatching( /^edbs_block_preview_/ ) );
	} );

	it( 'still maps a hiddenFromUi field\'s saved value into data-config (PRO-1397)', () => {
		container = renderEdit( { ...defaultAttributes(), category: 'finance' } );
		act( () => {
			jest.advanceTimersByTime( 300 );
		} );

		const config = JSON.parse( container.querySelector( '.edbs-boardscribe-wrap' ).dataset.config );
		expect( config.category ).toBe( 'finance' );
	} );

	it( 'debounces rapid attribute changes into a single re-init', () => {
		const setAttributes = jest.fn();
		container = renderEdit( defaultAttributes(), setAttributes );
		act( () => {
			jest.advanceTimersByTime( 300 );
		} );
		window.edbsInitInstance.mockClear();

		rerender( setAttributes, { ...defaultAttributes(), includedYears: '2024' } );
		act( () => {
			jest.advanceTimersByTime( 100 );
		} );
		rerender( setAttributes, { ...defaultAttributes(), includedYears: '2024,2025' } );
		act( () => {
			jest.advanceTimersByTime( 300 );
		} );

		expect( window.edbsInitInstance ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'shows an error notice on edbs:fetch-error, and clears it on the next re-init', () => {
		container = renderEdit( defaultAttributes() );
		act( () => {
			jest.advanceTimersByTime( 300 );
		} );

		act( () => {
			container.querySelector( '.edbs-boardscribe-wrap' ).dispatchEvent(
				new CustomEvent( 'edbs:fetch-error', { bubbles: true, detail: {} } ),
			);
		} );
		expect( container.querySelector( '[data-control="notice"][data-status="error"]' ) ).not.toBeNull();

		rerender( jest.fn(), { ...defaultAttributes(), includedYears: '2024' } );
		act( () => {
			jest.advanceTimersByTime( 300 );
		} );
		expect( container.querySelector( '[data-control="notice"][data-status="error"]' ) ).toBeNull();
	} );
} );

