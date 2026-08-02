/**
 * Test-only stand-in for @wordpress/components (see blocks.js mock for
 * why this is mocked at all). Each control renders as a plain DOM node
 * carrying data-control/data-label attributes so tests can find "the
 * control for field X" without depending on the real component library's
 * internal markup - only the props this codebase actually reads
 * (label/value/checked/onChange/options/min) are wired up.
 */
import { createElement } from '@wordpress/element';

export function PanelBody( { title, initialOpen, children } ) {
	return createElement(
		'section',
		{ 'data-panel': title, 'data-initial-open': String( !! initialOpen ) },
		children
	);
}

export function ToggleControl( { label, checked, onChange, help } ) {
	return createElement(
		'label',
		{ 'data-control': 'toggle', 'data-label': label, 'data-help': help || '' },
		createElement( 'input', {
			type: 'checkbox',
			checked: !! checked,
			onChange: ( event ) => onChange( event.target.checked ),
		} )
	);
}

export function SelectControl( { label, value, options, onChange, help } ) {
	return createElement(
		'label',
		{ 'data-control': 'select', 'data-label': label, 'data-help': help || '' },
		createElement(
			'select',
			{ value, onChange: ( event ) => onChange( event.target.value ) },
			( options || [] ).map( ( option ) =>
				createElement( 'option', { key: option.value, value: option.value }, option.label )
			)
		)
	);
}

export function TextControl( { label, value, onChange, placeholder, help } ) {
	return createElement(
		'label',
		{ 'data-control': 'text', 'data-label': label, 'data-help': help || '' },
		createElement( 'input', {
			type: 'text',
			value: value || '',
			placeholder: placeholder || '',
			onChange: ( event ) => onChange( event.target.value ),
		} )
	);
}

export function TextareaControl( { label, value, onChange, help } ) {
	return createElement(
		'label',
		{ 'data-control': 'textarea', 'data-label': label, 'data-help': help || '' },
		createElement( 'textarea', {
			value: value || '',
			onChange: ( event ) => onChange( event.target.value ),
		} )
	);
}

export function __experimentalNumberControl( { label, value, onChange, min, help } ) {
	return createElement(
		'label',
		{ 'data-control': 'number', 'data-label': label, 'data-help': help || '' },
		createElement( 'input', {
			type: 'number',
			value: value ?? '',
			min,
			onChange: ( event ) => onChange( event.target.value ),
		} )
	);
}
