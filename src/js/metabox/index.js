import { createRoot } from '@wordpress/element';
import { MetaBoxApp } from './app';
import { ResourceCard, ResourceCardEmpty } from './resource-card';
import { focusFirstActionable } from './resource-utils';

// Exposed so a plugin's own custom-type control (window.edbsMetaBoxControls,
// below) can render the exact same card system every built-in resource-
// shaped field uses (Agenda, Minutes, Livestream, Recording, Transcript,
// Supporting Documents) instead of hand-copying this markup/CardAction -
// Pro's Location field control is the first consumer (a custom type, since
// it needs its own async-fetch/loading-state behavior a plain 'resource'
// field descriptor can't express). See ResourceCard's own docblock for the
// props shape, especially `actions[].ariaLabel` - a field with only one
// card on screen still needs distinguishing accessible names so a screen
// reader's controls list doesn't read four bare "Edit"/"Replace"/"Remove"
// entries with nothing tying them to that field.
window.edbsResourceCard = { ResourceCard, ResourceCardEmpty, focusFirstActionable };

// Localized by MetaBox::enqueue_scripts() from MetaBoxFieldRegistry::js_schema()
// - the merged core + edbs_meeting_meta_fields field list, already ordered
// (insert_after resolved server-side).
const FIELD_REGISTRY = window.edbsMetaBoxFieldRegistry || [];

/**
 * Renders a control for a field type this bundle doesn't know about
 * natively (text/url/date/checkbox). A plugin (Pro) registering a field
 * with a custom `type` (e.g. "document_picker") pushes a render function
 * here, keyed by that type, before DOMContentLoaded:
 *
 *     window.edbsMetaBoxControls = window.edbsMetaBoxControls || {};
 *     window.edbsMetaBoxControls.document_picker = ( { field, value, onChange, id } ) => (
 *         <MyDocumentPicker id={ id } field={ field } value={ value } onChange={ onChange } />
 *     );
 *
 * `onChange( nextValue )` updates the app's state for that field; the
 * control itself is responsible for its own hidden `<input name={ field.key }>`
 * (or equivalent) so the value is submitted with the native post form,
 * same contract as every built-in field type.
 */
window.edbsMetaBoxControls = window.edbsMetaBoxControls || {};

document.addEventListener( 'DOMContentLoaded', () => {
	const rootEl = document.getElementById( 'edbs-meeting-meta-box-root' );
	if ( ! rootEl ) {
		return;
	}

	let initialValues = {};
	try {
		initialValues = JSON.parse( rootEl.dataset.values || '{}' );
	} catch ( e ) {
		initialValues = {};
	}

	createRoot( rootEl ).render(
		<MetaBoxApp fields={ FIELD_REGISTRY } initialValues={ initialValues } />,
	);
} );
