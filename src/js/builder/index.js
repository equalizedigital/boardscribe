import { createRoot } from '@wordpress/element';
import { BuilderApp } from './app';

// Localized by SettingsPage::enqueue_builder_script() from the shared
// field registry (FieldRegistry::js_schema()) - the same source the
// shortcode defaults, instance config, REST args, and the block's
// InspectorControls all derive from, so a field any plugin registers
// via edbs_shortcode_field_registry appears here automatically.
const FIELD_REGISTRY = window.edbsBuilderFieldRegistry || [];

document.addEventListener( 'DOMContentLoaded', () => {
	const rootEl = document.getElementById( 'edbs-shortcode-builder-root' );
	if ( ! rootEl ) {
		return;
	}
	createRoot( rootEl ).render( <BuilderApp fields={ FIELD_REGISTRY } /> );
} );
