/**
 * Test-only stand-in for @wordpress/components (see blocks.js mock for
 * why this is mocked at all). Each control renders as a plain DOM node
 * carrying data-control/data-label attributes so tests can find "the
 * control for field X" without depending on the real component library's
 * internal markup - only the props this codebase actually reads
 * (label/value/checked/onChange/onKeyDown/options/min) are wired up.
 */
import { createElement, forwardRef } from '@wordpress/element';

export function BaseControl( { id, label, help, children } ) {
	return createElement(
		'div',
		{ 'data-control': 'base', id, 'data-label': label, 'data-help': help || '' },
		children,
	);
}

export const Button = forwardRef( function Button( { id, className, variant, onClick, disabled, children, 'aria-label': ariaLabel, 'aria-disabled': ariaDisabled }, ref ) {
	return createElement(
		'button',
		{ ref, id, className, type: 'button', 'data-variant': variant, disabled: !! disabled, onClick, 'aria-label': ariaLabel, 'aria-disabled': ariaDisabled },
		children,
	);
} );

export function PanelBody( { title, initialOpen, children } ) {
	return createElement(
		'section',
		{ 'data-panel': title, 'data-initial-open': String( !! initialOpen ) },
		children,
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
		} ),
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
				createElement( 'option', { key: option.value, value: option.value }, option.label ),
			),
		),
	);
}

export const TextControl = forwardRef( function TextControl( { label, value, onChange, onKeyDown, placeholder, help, type, 'aria-invalid': ariaInvalid, 'aria-describedby': ariaDescribedBy }, ref ) {
	return createElement(
		'label',
		{ 'data-control': 'text', 'data-label': label, 'data-help': help || '' },
		createElement( 'input', {
			ref,
			type: type || 'text',
			value: value || '',
			placeholder: placeholder || '',
			onChange: ( event ) => onChange( event.target.value ),
			onKeyDown,
			'aria-invalid': ariaInvalid,
			'aria-describedby': ariaDescribedBy,
		} ),
	);
} );

export function Modal( { title, className, children } ) {
	return createElement(
		'div',
		{ 'data-control': 'modal', className, role: 'dialog', 'aria-label': title },
		children,
	);
}

export function Notice( { status, children } ) {
	return createElement(
		'div',
		{ 'data-control': 'notice', 'data-status': status, role: 'alert' },
		children,
	);
}

export function TextareaControl( { label, value, onChange, help } ) {
	return createElement(
		'label',
		{ 'data-control': 'textarea', 'data-label': label, 'data-help': help || '' },
		createElement( 'textarea', {
			value: value || '',
			onChange: ( event ) => onChange( event.target.value ),
		} ),
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
		} ),
	);
}
