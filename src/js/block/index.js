import { registerBlockType } from '@wordpress/blocks';
import {
	InspectorControls,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	TextareaControl,
	ToggleControl,
	SelectControl,
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { useRef } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import metadata from '../../../block.json';

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
	{ key: 'link_labels', title: __( 'Link Labels', 'boardscribe' ), initialOpen: false },
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
 * Renders the control matching a field's type - the generic half of the
 * InspectorControls, covering every field except the special-cased ones
 * above. One renderer per FieldRegistry type (see FieldRegistry.php).
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Field descriptor from FIELD_REGISTRY.
 * @param {*}        props.value    Current attribute value.
 * @param {Function} props.onChange Called with the new value.
 * @return {JSX.Element} The control.
 */
function GenericFieldControl( { field, value = '', onChange } ) {
	const help = field.description || undefined;

	switch ( field.type ) {
		case 'checkbox':
			return (
				<ToggleControl
					label={ field.label }
					help={ help }
					checked={ !! value }
					onChange={ onChange }
				/>
			);

		case 'select': {
			const choices = field.choices || {};
			const options = Object.keys( choices ).map( ( choiceValue ) => ( {
				value: choiceValue,
				label: choices[ choiceValue ],
			} ) );
			return (
				<SelectControl
					label={ field.label }
					help={ help }
					value={ value }
					options={ options }
					onChange={ onChange }
				/>
			);
		}

		case 'number':
		case 'number_with_all':
			return (
				<NumberControl
					label={ field.label }
					help={ help }
					value={ value }
					onChange={ ( val ) => onChange( parseInt( val, 10 ) || 0 ) }
					min={ 'number_with_all' === field.type ? -1 : 1 }
				/>
			);

		case 'textarea':
			return (
				<TextareaControl
					label={ field.label }
					help={ help }
					value={ value }
					onChange={ onChange }
				/>
			);

		case 'text':
		default:
			return (
				<TextControl
					label={ field.label }
					help={ help }
					placeholder={ field.placeholder || '' }
					value={ value }
					onChange={ onChange }
				/>
			);
	}
}

registerBlockType( metadata.name, {
	edit( { attributes, setAttributes } ) {
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
		// change here. With only the built-in table registered there is
		// nothing to pick, so the control is omitted entirely.
		const templateField = FIELD_REGISTRY.find( ( field ) => 'template' === field.attributeKey );
		const templateChoices = ( templateField && templateField.choices ) || {};
		const showTemplatePicker = Object.keys( templateChoices ).length > 1;

		const fieldsByGroup = {};
		FIELD_REGISTRY.forEach( ( field ) => {
			if ( SPECIAL_CASED_KEYS.includes( field.attributeKey ) ) {
				return;
			}
			const group = field.group || 'general';
			( fieldsByGroup[ group ] = fieldsByGroup[ group ] || [] ).push( field );
		} );

		const renderGenericFields = ( group ) =>
			( fieldsByGroup[ group ] || [] ).map( ( field ) => (
				<GenericFieldControl
					key={ field.attributeKey }
					field={ field }
					value={ attributes[ field.attributeKey ] }
					onChange={ ( val ) => setAttributes( { [ field.attributeKey ]: val } ) }
				/>
			) );

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
					<ServerSideRender
						block={ metadata.name }
						attributes={ attributes }
					/>
				</div>
			</>
		);
	},

	// Dynamic block — PHP handles all rendering, no static save needed.
	save: () => null,
} );
