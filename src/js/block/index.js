import { registerBlockType } from '@wordpress/blocks';
import {
	InspectorControls,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	SelectControl,
	Notice,
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import { useInstanceId } from '@wordpress/compose';
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import metadata from '../../../block.json';
import { withDateFilterHelpText } from '../shared/date-filter-help-text';
import { GenericFieldControl } from '../shared/generic-field-control';

// Localized by BoardScribeBlock::register_block() from the shared
// field registry (see Shortcode/FieldRegistry.php) - the same source
// shortcode defaults, instance config, REST args, and the shortcode
// builder UI all derive from. A field (core or Pro) only needs to be
// declared once, PHP-side, to show up here - no matching JS control to
// hand-write. Falls back to an empty list if it isn't present (it's
// attached to this bundle's own handle, so that should never happen).
const FIELD_REGISTRY = window.edbsBlockFieldRegistry || [];

// Panel grouping/order - matches the shortcode builder's groups.
const GROUPS = [
	{ key: 'general', title: __( 'Display Settings', 'boardscribe' ), initialOpen: true },
	{ key: 'column_labels', title: __( 'Column Labels', 'boardscribe' ), initialOpen: false },
	{ key: 'link_labels', title: __( 'Links', 'boardscribe' ), initialOpen: false },
	{ key: 'hide_columns', title: __( 'Hide Columns', 'boardscribe' ), initialOpen: false },
	{ key: 'show_columns', title: __( 'Show Columns', 'boardscribe' ), initialOpen: false },
];

// Rendered by hand above the generic loop instead of generically:
// - template/postsPerPage's onChange behavior can be coupled to each
//   other (Pro's editor script uses the edbs.block.templateChangeAttributes
//   filter below to auto-set postsPerPage when its year-timeline template
//   is picked), not just to their own value - not expressible as a single
//   generic field.
// - className has no control here at all; it's driven by the block's
//   native "Additional CSS Class(es)" Advanced-panel field instead.
const SPECIAL_CASED_KEYS = [ 'template', 'postsPerPage', 'className' ];

/**
 * Maps the block's current attributes into the same instance-config shape
 * BoardScribeShortcode::render() emits in data-config, so the real
 * frontend pipeline (window.edbsInitInstance) can render the editor's own
 * live preview - the same mapping src/js/builder/preview.js's
 * buildInstanceConfig() does for the Shortcode Builder's live preview,
 * just reading from block attributes (already natively typed per
 * block.json's attribute schema) instead of raw form field values. See
 * PRO-1331 - this preview is the real output, not a lookalike.
 *
 * @param {Array}  fields     Field descriptors from the localized registry schema.
 * @param {Object} attributes Current block attributes.
 * @param {string} instanceId This block instance's unique preview instance id.
 * @return {Object} The instance config.
 */
function buildInstanceConfig( fields, attributes, instanceId ) {
	const config = {
		instanceId,
		// See instance.js - keeps preview pagination out of the editor's
		// own URL/history, same reasoning as the Shortcode Builder preview.
		urlState: false,
	};

	fields.forEach( ( field ) => {
		const value = attributes[ field.attributeKey ];
		if ( 'checkbox' === field.type ) {
			config[ field.configKey ] = !! value;
		} else if ( 'number_with_all' === field.type ) {
			const num = parseInt( value, 10 );
			config[ field.configKey ] = Number.isNaN( num ) ? -1 : num;
		} else if ( 'number' === field.type ) {
			config[ field.configKey ] = parseInt( value, 10 ) || 0;
		} else {
			config[ field.configKey ] = String( value === undefined || value === null ? '' : value );
		}
	} );

	return config;
}

/**
 * The block's editor UI: the InspectorControls sidebar plus a live
 * preview rendered through the real frontend pipeline (see
 * buildInstanceConfig()'s own docblock) - a named function expression so
 * useInstanceId() below has a stable per-component-type key to dedupe
 * instance ids against, the same way it's normally used for a
 * top-level component.
 *
 * @param {Object}   props               Component props.
 * @param {Object}   props.attributes    Current block attributes.
 * @param {Function} props.setAttributes Updates block attributes.
 * @return {JSX.Element} The block editor UI.
 */
function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps();

	// Normalized once up front: postsPerPage is declared as a number
	// attribute, but a strict-equality check against a value that
	// arrived as a string (e.g. "-1", from hand-edited block markup or
	// an external attribute editor) would silently fail, leaving the
	// show-all toggle below out of sync.
	const postsPerPage = Number( attributes.postsPerPage );

	// Remembers the last non-"all" postsPerPage value seen, so turning
	// "Show all meetings" off (or a template-change filter that
	// auto-enabled it, see below) can restore what the user had before
	// instead of resetting to a hard-coded 20.
	const lastCustomPostsPerPage = useRef( 20 );
	if ( -1 !== postsPerPage ) {
		lastCustomPostsPerPage.current = postsPerPage;
	}
	const showingAllMeetings = -1 === postsPerPage;

	// The template picker renders from the registry field's choices, so
	// a Pro/third-party template registered via the
	// edbs_shortcode_field_registry filter becomes selectable with no
	// change here. It renders as soon as the field is present in the
	// registry, even with only the built-in "Table (default)" choice -
	// matching how the shortcode builder page (which renders every
	// registry field generically) behaves. Pro contributes additional
	// choices onto this same field rather than making the control's
	// existence conditional on more than one choice existing.
	const templateField = FIELD_REGISTRY.find( ( field ) => 'template' === field.attributeKey );
	const templateChoices = ( templateField && templateField.choices ) || {};
	// hiddenFromUi (PRO-1397) applies to this special-cased picker too, same
	// as the generic fieldsByGroup loop below - a hidden template field
	// (e.g. Pro gating which templates a NEW instance can pick once
	// unlicensed) must still map into buildInstanceConfig() so an
	// already-saved choice keeps previewing correctly, but its own picker
	// must not render.
	const showTemplatePicker = Boolean( templateField ) && ! templateField.hiddenFromUi && Object.keys( templateChoices ).length > 0;

	const fieldsByGroup = {};
	FIELD_REGISTRY.forEach( ( field ) => {
		// hiddenFromUi fields (PRO-1397) still need to reach
		// buildInstanceConfig() below, via the same FIELD_REGISTRY array,
		// so a saved value keeps rendering in the live preview exactly as
		// it does on the front end - only their picker is skipped here.
		if ( SPECIAL_CASED_KEYS.includes( field.attributeKey ) || field.hiddenFromUi ) {
			return;
		}
		const group = field.group || 'general';
		( fieldsByGroup[ group ] = fieldsByGroup[ group ] || [] ).push( field );
	} );

	const renderGenericFields = ( group ) =>
		( fieldsByGroup[ group ] || [] ).map( ( field ) => (
			<GenericFieldControl
				key={ field.attributeKey }
				field={ withDateFilterHelpText(
					field,
					attributes.includedYears,
					attributes.startDate,
					attributes.endDate,
				) }
				value={ attributes[ field.attributeKey ] }
				onChange={ ( val ) => setAttributes( { [ field.attributeKey ]: val } ) }
			/>
		) );

	// A stable id per block instance (persists across re-renders,
	// unique even with several BoardScribe blocks on the same post) -
	// never collides with front-end instance ids (those are
	// edbs_1, edbs_2, ... per shortcode/block render() call on the
	// front end) or the Shortcode Builder's fixed preview id.
	const blockInstanceId = useInstanceId( Edit );
	const previewInstanceId = `edbs_block_preview_${ blockInstanceId }`;

	// FIELD_REGISTRY is a module-level constant and previewInstanceId is
	// stable for this component's lifetime - only attributes actually
	// varies, so it's the only real dependency.
	const config = useMemo(
		() => buildInstanceConfig( FIELD_REGISTRY, attributes, previewInstanceId ),
		[ attributes ],
	);
	const configJson = JSON.stringify( config );

	// Debounce so a keystroke burst in a text field (e.g. a column
	// label) causes one refetch, not one per character - same as the
	// Shortcode Builder's own live preview.
	const [ debouncedJson, setDebouncedJson ] = useState( configJson );
	useEffect( () => {
		const timer = setTimeout( () => setDebouncedJson( configJson ), 300 );
		return () => clearTimeout( timer );
	}, [ configJson ] );

	const wrapRef = useRef( null );
	const [ previewError, setPreviewError ] = useState( false );

	// One effect owns both concerns together (rather than two effects
	// each keyed on debouncedJson) so the fetch-error listener is
	// always bound to the exact wrap element window.edbsInitInstance
	// is about to (re)populate - the wrap below is keyed on
	// debouncedJson too, so this fires after React has mounted a
	// fresh copy of the wrapper markup, never a stale one left over
	// from a previous config.
	useEffect( () => {
		const wrap = wrapRef.current;
		if ( ! wrap ) {
			return undefined;
		}

		setPreviewError( false );

		const handleFetchError = () => setPreviewError( true );
		wrap.addEventListener( 'edbs:fetch-error', handleFetchError );

		if ( window.edbsInitInstance ) {
			window.edbsInitInstance( wrap );
		}

		return () => wrap.removeEventListener( 'edbs:fetch-error', handleFetchError );
	}, [ debouncedJson ] );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ GROUPS[ 0 ].title } initialOpen={ GROUPS[ 0 ].initialOpen }>
					{ showTemplatePicker && (
						<SelectControl
							label={ templateField.label }
							help={ templateField.description || undefined }
							value={ attributes.template }
							options={ Object.keys( templateChoices ).map( ( choiceValue ) => ( {
								value: choiceValue,
								label: templateChoices[ choiceValue ],
							} ) ) }
							onChange={ ( val ) => {
								/**
								 * Filters the attribute changes applied when the display
								 * template is switched, so the plugin that owns a template
								 * can couple other attributes to it (e.g. Pro's
								 * year-timeline auto-enables "Show all meetings").
								 *
								 * @param {Object} changes Attribute changes to apply; starts as { template }.
								 * @param {Object} context { attributes, postsPerPage, lastCustomPostsPerPage }.
								 */
								const changes = applyFilters(
									'edbs.block.templateChangeAttributes',
									{ template: val },
									{
										attributes,
										postsPerPage,
										lastCustomPostsPerPage: lastCustomPostsPerPage.current,
									},
								);
								setAttributes( changes );
							} }
						/>
					) }
					<ToggleControl
						label={ __( 'Show all meetings', 'boardscribe' ) }
						help={ __( 'Ignores the per-page limit below and fetches every meeting in one request.', 'boardscribe' ) }
						checked={ showingAllMeetings }
						onChange={ ( val ) => setAttributes( { postsPerPage: val ? -1 : lastCustomPostsPerPage.current } ) }
					/>
					{ ! showingAllMeetings && (
						<NumberControl
							label={ __( 'Records Per Page', 'boardscribe' ) }
							value={ postsPerPage }
							onChange={ ( val ) => setAttributes( { postsPerPage: parseInt( val, 10 ) || 20 } ) }
							min={ 1 }
						/>
					) }
					{ renderGenericFields( 'general' ) }
				</PanelBody>

				{ GROUPS.slice( 1 ).map( ( group ) => {
					const fields = fieldsByGroup[ group.key ] || [];
					if ( ! fields.length ) {
						return null;
					}
					return (
						<PanelBody
							key={ group.key }
							title={ group.title }
							initialOpen={ group.initialOpen }
						>
							{ renderGenericFields( group.key ) }
						</PanelBody>
					);
				} ) }
			</InspectorControls>

			<div { ...blockProps }>
				{ previewError && (
					<Notice status="error" isDismissible={ false }>
						{ __( 'The preview couldn’t load. Check that the site’s REST API is reachable, then try again.', 'boardscribe' ) }
					</Notice>
				) }
				{ /* Keyed on the config so each change remounts a clean copy of
					     the exact wrapper markup BoardScribeShortcode::render()
					     emits - the frontend pipeline owns everything inside it,
					     which React must never reconcile over (same reasoning as
					     the Shortcode Builder's own live preview). */ }
				<div
					key={ debouncedJson }
					ref={ wrapRef }
					className="edbs-boardscribe-wrap"
					data-config={ debouncedJson }
				>
					<div id={ `edbs-table-${ previewInstanceId }` } className="edbs-table-container"></div>
					<div id={ `edbs-pagination-${ previewInstanceId }` } className="edbs-pagination-container"></div>
					<div
						id={ `edbs-info-${ previewInstanceId }` }
						className="edbs-pagination-info"
						aria-live="polite"
						aria-atomic="true"
						style={ { position: 'absolute', left: '-9999px' } }
					></div>
				</div>
			</div>
		</>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,

	// Dynamic block — PHP handles all rendering, no static save needed.
	save: () => null,
} );
