import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

// Fixed instance id for the single preview instance - never collides
// with front-end ids (those are edbs_1, edbs_2, ... per render).
const PREVIEW_ID = 'edbs_builder_preview';

/**
 * Maps the builder's current field values into the same instance-config
 * shape BoardScribeShortcode::render() emits in data-config, so the
 * real frontend pipeline (window.edbsInitInstance) can render the
 * preview. Only the type coercions the JS side depends on are applied
 * (checkbox -> boolean, "all" -> -1); full sanitization stays PHP-side
 * for real renders - this config never leaves the admin's own browser.
 *
 * @param {Array}  fields Field descriptors from the localized registry schema.
 * @param {Object} values Current values keyed by field.key.
 * @return {Object} The instance config.
 */
function buildInstanceConfig( fields, values ) {
	const config = {
		instanceId: PREVIEW_ID,
		// See instance.js - keeps preview pagination out of the admin URL.
		urlState: false,
	};

	fields.forEach( ( field ) => {
		const value = values[ field.key ];
		if ( 'checkbox' === field.type ) {
			config[ field.configKey ] = !! value;
		} else if ( 'number_with_all' === field.type ) {
			config[ field.configKey ] = 'all' === value ? -1 : parseInt( value, 10 ) || -1;
		} else if ( 'number' === field.type ) {
			config[ field.configKey ] = parseInt( value, 10 ) || 0;
		} else {
			config[ field.configKey ] = String( value === undefined || value === null ? '' : value );
		}
	} );

	return config;
}

/**
 * Live preview of the shortcode output: renders the same wrapper markup
 * the shortcode produces and hands it to the real frontend pipeline,
 * re-initialising (debounced) whenever the builder values change - so
 * whatever a template/extra column would do on the front end, including
 * Pro's, is exactly what the preview shows.
 *
 * @param {Object} props        Component props.
 * @param {Array}  props.fields Field descriptors.
 * @param {Object} props.values Current values keyed by field.key.
 * @return {JSX.Element|null} The preview, or null when the frontend bundle is absent.
 */
export function Preview( { fields, values } ) {
	const config = useMemo( () => buildInstanceConfig( fields, values ), [ fields, values ] );
	const configJson = JSON.stringify( config );

	// Debounce so a keystroke burst in a text field causes one refetch,
	// not one per character.
	const [ debouncedJson, setDebouncedJson ] = useState( configJson );
	useEffect( () => {
		const timer = setTimeout( () => setDebouncedJson( configJson ), 300 );
		return () => clearTimeout( timer );
	}, [ configJson ] );

	const wrapRef = useRef( null );
	useEffect( () => {
		if ( wrapRef.current && window.edbsInitInstance ) {
			window.edbsInitInstance( wrapRef.current );
		}
	}, [ debouncedJson ] );

	if ( ! window.edbsInitInstance ) {
		return null;
	}

	return (
		<div className="edbs-builder-preview">
			<h2>{ __( 'Preview', 'boardscribe' ) }</h2>
			{ /* Keyed on the config so each change remounts a clean copy of
			     the exact wrapper markup BoardScribeShortcode::render()
			     emits - the frontend pipeline owns everything inside it,
			     which React must never reconcile over. */ }
			<div
				key={ debouncedJson }
				ref={ wrapRef }
				className="edbs-boardscribe-wrap"
				data-config={ debouncedJson }
			>
				<div id={ `edbs-table-${ PREVIEW_ID }` } className="edbs-table-container"></div>
				<div id={ `edbs-pagination-${ PREVIEW_ID }` } className="edbs-pagination-container"></div>
				<div
					id={ `edbs-info-${ PREVIEW_ID }` }
					className="edbs-pagination-info"
					aria-live="polite"
					aria-atomic="true"
					style={ { position: 'absolute', left: '-9999px' } }
				></div>
			</div>
		</div>
	);
}
