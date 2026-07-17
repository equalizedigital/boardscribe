import {
	Button,
	Panel,
	PanelBody,
	ToggleControl,
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import { useMemo, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { GenericFieldControl } from '../shared/generic-field-control';
import { buildShortcode } from './build-shortcode';
import { Preview } from './preview';

// Panel grouping/order - same groups the block editor sidebar uses.
const GROUPS = [
	{ key: 'general', title: __( 'Display Settings', 'boardscribe' ), initialOpen: true },
	{ key: 'column_labels', title: __( 'Column Labels', 'boardscribe' ), initialOpen: false },
	{ key: 'link_labels', title: __( 'Link Labels', 'boardscribe' ), initialOpen: false },
	{ key: 'hide_columns', title: __( 'Hide Columns', 'boardscribe' ), initialOpen: false },
	{ key: 'show_columns', title: __( 'Show Columns', 'boardscribe' ), initialOpen: false },
];

/**
 * A number_with_all field renders as a number input paired with a
 * "Show all" toggle; toggled on, the field's shortcode value is "all".
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Field descriptor.
 * @param {*}        props.value    Current value (a number, or "all").
 * @param {Function} props.onChange Called with the new value.
 * @return {JSX.Element} The paired controls.
 */
function NumberWithAllControl( { field, value, onChange } ) {
	// Remembers the last concrete number so toggling "Show all" off
	// restores what the user had instead of resetting to the default.
	const lastCustom = useRef( 'all' === value ? field.default : value );
	if ( 'all' !== value ) {
		lastCustom.current = value;
	}
	const showingAll = 'all' === value;

	return (
		<>
			<ToggleControl
				label={ __( 'Show all', 'boardscribe' ) }
				checked={ showingAll }
				onChange={ ( checked ) => onChange( checked ? 'all' : lastCustom.current ) }
			/>
			{ ! showingAll && (
				<NumberControl
					label={ field.label }
					help={ field.description || undefined }
					value={ value }
					onChange={ ( val ) => onChange( parseInt( val, 10 ) || field.default ) }
					min={ 1 }
				/>
			) }
		</>
	);
}

/**
 * The shortcode builder app: every registry field (free's own plus
 * anything Pro/third parties added via edbs_shortcode_field_registry)
 * rendered in its group, with the generated shortcode kept in sync.
 *
 * @param {Object} props        Component props.
 * @param {Array}  props.fields Field descriptors from FieldRegistry::js_schema().
 * @return {JSX.Element} The app.
 */
export function BuilderApp( { fields } ) {
	const [ values, setValues ] = useState( () => {
		const initial = {};
		fields.forEach( ( field ) => {
			initial[ field.key ] = field.default;
		} );
		return initial;
	} );
	const [ copyLabel, setCopyLabel ] = useState( __( 'Copy', 'boardscribe' ) );

	const outputRef = useRef( null );
	const copyResetTimer = useRef( null );

	const shortcode = useMemo( () => buildShortcode( fields, values ), [ fields, values ] );

	const fieldsByGroup = useMemo( () => {
		const grouped = {};
		fields.forEach( ( field ) => {
			const group = GROUPS.some( ( { key } ) => key === field.group ) ? field.group : 'general';
			( grouped[ group ] = grouped[ group ] || [] ).push( field );
		} );
		return grouped;
	}, [ fields ] );

	const setValue = ( key, value ) => setValues( ( prev ) => ( { ...prev, [ key ]: value } ) );

	const renderField = ( field ) => {
		const props = {
			key: field.key,
			field,
			value: values[ field.key ],
			onChange: ( val ) => setValue( field.key, val ),
		};
		return 'number_with_all' === field.type
			? <NumberWithAllControl { ...props } />
			: <GenericFieldControl { ...props } />;
	};

	const copyShortcode = () => {
		const markCopied = () => {
			setCopyLabel( __( 'Copied!', 'boardscribe' ) );
			clearTimeout( copyResetTimer.current );
			copyResetTimer.current = setTimeout( () => {
				setCopyLabel( __( 'Copy', 'boardscribe' ) );
			}, 2000 );
		};

		// Fallback for non-HTTPS admin screens, where the async
		// clipboard API is unavailable.
		const legacyCopy = () => {
			if ( ! outputRef.current ) {
				return;
			}
			outputRef.current.select();
			if ( document.execCommand( 'copy' ) ) {
				markCopied();
			}
		};

		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( shortcode ).then( markCopied ).catch( legacyCopy );
			return;
		}

		legacyCopy();
	};

	return (
		<div className="edbs-builder-app">
			<Panel>
				{ GROUPS.map( ( group ) => {
					const groupFields = fieldsByGroup[ group.key ] || [];
					if ( ! groupFields.length ) {
						return null;
					}
					return (
						<PanelBody key={ group.key } title={ group.title } initialOpen={ group.initialOpen }>
							{ groupFields.map( renderField ) }
						</PanelBody>
					);
				} ) }
			</Panel>

			<h2>{ __( 'Your Shortcode', 'boardscribe' ) }</h2>
			<div style={ { display: 'flex', gap: '8px', alignItems: 'center', maxWidth: '600px' } }>
				<input
					type="text"
					ref={ outputRef }
					readOnly
					value={ shortcode }
					className="large-text"
					style={ { fontFamily: 'monospace' } }
					aria-label={ __( 'Your Shortcode', 'boardscribe' ) }
				/>
				<Button variant="secondary" onClick={ copyShortcode }>
					{ copyLabel }
				</Button>
			</div>

			<Preview fields={ fields } values={ values } />
		</div>
	);
}
