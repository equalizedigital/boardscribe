import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Panel,
	PanelBody,
	Snackbar,
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

// Opt every builder control into the current component sizing/margins
// (the app owns its spacing via builder.css). The block's sidebar
// controls deliberately don't get these - they follow the editor.
const CONTROL_PROPS = {
	__next40pxDefaultSize: true,
	__nextHasNoMarginBottom: true,
};

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
			{ ! showingAll && (
				<NumberControl
					label={ field.label }
					help={ field.description || undefined }
					value={ value }
					onChange={ ( val ) => onChange( parseInt( val, 10 ) || field.default ) }
					min={ 1 }
					{ ...CONTROL_PROPS }
				/>
			) }
			<ToggleControl
				label={ __( 'Show all', 'boardscribe' ) }
				checked={ showingAll }
				onChange={ ( checked ) => onChange( checked ? 'all' : lastCustom.current ) }
				__nextHasNoMarginBottom
			/>
		</>
	);
}

/**
 * The shortcode builder app: every registry field (free's own plus
 * anything Pro/third parties added via edbs_shortcode_field_registry)
 * rendered in its group, with the generated shortcode and live preview
 * kept in sync. Layout (two columns, spacing) lives in builder.css.
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
	const [ notice, setNotice ] = useState( null );

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
			: <GenericFieldControl { ...props } controlProps={ CONTROL_PROPS } />;
	};

	const copyShortcode = () => {
		// Screen readers hear the result either way, via the Snackbar's own
		// announcement (see its politeness prop below): success politely (it
		// doesn't need to interrupt), failure assertively (the user's next
		// action - pasting - is about to silently do the wrong thing). No
		// manual speak() here: the Snackbar announces its content itself on
		// mount, and a second announcement would clear/overwrite the first.
		const markCopied = () => {
			setCopyLabel( __( 'Copied!', 'boardscribe' ) );
			clearTimeout( copyResetTimer.current );
			copyResetTimer.current = setTimeout( () => {
				setCopyLabel( __( 'Copy', 'boardscribe' ) );
			}, 2000 );
			setNotice( { content: __( 'Shortcode copied to clipboard.', 'boardscribe' ), isError: false } );
		};

		const markFailed = () => {
			setNotice( {
				content: __( 'Copying failed. Select the shortcode text and copy it manually.', 'boardscribe' ),
				isError: true,
			} );
			// Do the selecting for them - one keystroke left to copy.
			if ( outputRef.current ) {
				outputRef.current.select();
			}
		};

		// Fallback for non-HTTPS admin screens, where the async
		// clipboard API is unavailable.
		const legacyCopy = () => {
			if ( ! outputRef.current ) {
				markFailed();
				return;
			}
			// select() moves focus into the input; on success put it back
			// on the Copy button so the click doesn't silently relocate
			// keyboard focus. On failure the selection (and its focus) is
			// exactly what markFailed wants left in place.
			const previouslyFocused = document.activeElement;
			outputRef.current.select();
			if ( document.execCommand( 'copy' ) ) {
				if ( previouslyFocused && previouslyFocused.focus ) {
					previouslyFocused.focus();
				}
				markCopied();
			} else {
				markFailed();
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
			<div className="edbs-builder-app__settings">
				<Panel>
					{ GROUPS.map( ( group ) => {
						const groupFields = fieldsByGroup[ group.key ] || [];
						if ( ! groupFields.length ) {
							return null;
						}
						return (
							<PanelBody key={ group.key } title={ group.title } initialOpen={ group.initialOpen }>
								<div className="edbs-builder-app__fields">
									{ groupFields.map( renderField ) }
								</div>
							</PanelBody>
						);
					} ) }
				</Panel>
			</div>

			<div className="edbs-builder-app__result">
				<Card>
					<CardHeader>{ __( 'Your Shortcode', 'boardscribe' ) }</CardHeader>
					<CardBody>
						<div className="edbs-builder-app__output-row">
							<input
								type="text"
								ref={ outputRef }
								readOnly
								value={ shortcode }
								className="edbs-builder-app__output"
								aria-label={ __( 'Your Shortcode', 'boardscribe' ) }
							/>
							<Button variant="primary" onClick={ copyShortcode } __next40pxDefaultSize>
								{ copyLabel }
							</Button>
						</div>
					</CardBody>
				</Card>

				<Preview fields={ fields } values={ values } />
			</div>

			{ notice && (
				<div className="edbs-builder-app__snackbar">
					{ /* Snackbar announces its own content on mount (that's
					     why copyShortcode doesn't also call speak() - the
					     later of two announcements clears the earlier one)
					     and auto-dismisses via its built-in timeout. */ }
					<Snackbar
						className={ notice.isError ? 'edbs-builder-app__snackbar-error' : undefined }
						politeness={ notice.isError ? 'assertive' : 'polite' }
						onRemove={ () => setNotice( null ) }
					>
						{ notice.content }
					</Snackbar>
				</div>
			) }
		</div>
	);
}
