<?php
/**
 * Plugin settings page with shortcode builder.
 *
 * @package EqualizeDigital\BoardScribe
 */

namespace EqualizeDigital\BoardScribe\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use EqualizeDigital\BoardScribe\Plugin;
use EqualizeDigital\BoardScribe\Shortcode\FieldRegistry;

/**
 * Registers and renders the BoardScribe settings page.
 *
 * The page contains:
 * - A shortcode builder that generates a ready-to-copy shortcode
 *   based on the user's selections (no values are stored).
 * - An Advanced section for persistent settings (e.g. delete on uninstall).
 */
class SettingsPage {

	/**
	 * Hooks settings registration and admin menu into WordPress.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_submenu_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_builder_script' ] );
	}

	/**
	 * Adds the Settings submenu under the BoardScribe CPT menu.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public function add_submenu_page(): void {
		add_submenu_page(
			'edit.php?post_type=edbs_boardscribe',
			__( 'BoardScribe Settings', 'boardscribe' ),
			__( 'Settings', 'boardscribe' ),
			'manage_options',
			'edbs-settings',
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Registers only the persistent plugin settings (not shortcode builder values).
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'edbs_settings_group',
			'edbs_settings',
			[
				'type'              => 'array',
				'default'           => Plugin::DEFAULTS,
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
			]
		);

		add_settings_section(
			'edbs_advanced_section',
			__( 'Advanced', 'boardscribe' ),
			'__return_empty_string',
			'edbs-settings'
		);

		add_settings_field(
			'delete_on_uninstall',
			__( 'Delete Data on Uninstall', 'boardscribe' ),
			[ $this, 'render_delete_on_uninstall' ],
			'edbs-settings',
			'edbs_advanced_section'
		);
	}

	/**
	 * Enqueues the inline shortcode builder script on the settings page.
	 *
	 * @since x.x.x
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_builder_script( string $hook ): void {
		if ( 'edbs_boardscribe_page_edbs-settings' !== $hook ) {
			return;
		}

		// Inline script — no separate file needed for a small builder.
		wp_register_script( 'edbs-shortcode-builder', false, [], EDBS_VERSION, true );
		wp_enqueue_script( 'edbs-shortcode-builder' );
		wp_add_inline_script( 'edbs-shortcode-builder', $this->get_builder_script() );
	}

	/**
	 * Returns the inline JavaScript for the shortcode builder.
	 *
	 * Walks every named form field generically (rather than a hardcoded
	 * field list) so fields any plugin adds via the
	 * edbs_shortcode_field_registry filter (see FieldRegistry) are
	 * automatically included in the generated shortcode - no matching JS
	 * needed for a new field, as long as it follows this file's
	 * data-default/checkbox-value conventions.
	 *
	 * @since x.x.x
	 *
	 * @return string
	 */
	private function get_builder_script(): string {
		return <<<'JS'
document.addEventListener( 'DOMContentLoaded', function () {
	const form    = document.getElementById( 'edbs-shortcode-builder' );
	const output  = document.getElementById( 'edbs-shortcode-output' );
	const copyBtn = document.getElementById( 'edbs-copy-shortcode' );

	if ( ! form || ! output ) return;

	function buildShortcode() {
		let shortcode = '[edbs_boardscribe';
		const seen = new Set();

		// WordPress shortcode parsing never decodes HTML entities in
		// attribute values, so entity-escaping quotes alone doesn't stop a
		// literal "]" in a free-text field from prematurely closing the
		// generated shortcode - it has to be stripped instead.
		function sanitizeValue( value ) {
			return value.replace( /[[\]]/g, '' ).replace( /"/g, '&quot;' );
		}

		Array.from( form.elements ).forEach( function ( el ) {
			if ( ! el.name || el.disabled ) {
				return;
			}

			// Checkboxes/radios: only the checked option should count, and
			// for a radio group that means skipping unchecked options
			// without marking the group "seen" - otherwise whichever same-
			// named option happens to come first in the DOM (checked or
			// not) would win instead of the actually-checked one.
			if ( 'checkbox' === el.type || 'radio' === el.type ) {
				if ( seen.has( el.name ) ) {
					return;
				}
				if ( el.checked ) {
					seen.add( el.name );
					// A checkbox with no value="" attribute defaults to "on"
					// in the DOM, not "" - hasAttribute() is what actually
					// distinguishes "no value given" from "value is on".
					const boolValue = el.hasAttribute( 'value' ) ? el.value : 'true';
					shortcode += ' ' + el.name + '="' + sanitizeValue( boolValue ) + '"';
				}
				return;
			}

			if ( seen.has( el.name ) ) {
				return;
			}
			seen.add( el.name );

			const val = ( el.value || '' ).trim();
			const defaultVal = el.hasAttribute( 'data-default' ) ? el.getAttribute( 'data-default' ) : '';
			if ( val && val !== defaultVal ) {
				shortcode += ' ' + el.name + '="' + sanitizeValue( val ) + '"';
			}
		} );

		shortcode += ']';
		output.value = shortcode;
	}

	form.addEventListener( 'input', buildShortcode );
	form.addEventListener( 'change', buildShortcode );

	// Wires up every "number_with_all" field (see FieldRegistry) generically:
	// checking the toggle disables the number input and enables the paired
	// hidden field (name="{key}" value="all"), so the generic walk above
	// picks up the "all" value instead - no per-field JS needed.
	document.querySelectorAll( '.edbs-builder-show-all-toggle' ).forEach( function ( toggle ) {
		const numberInput = document.getElementById( toggle.dataset.numberInput );
		const allHidden   = document.getElementById( toggle.dataset.allHidden );
		if ( ! numberInput || ! allHidden ) return;

		toggle.addEventListener( 'change', function () {
			numberInput.disabled = toggle.checked;
			allHidden.disabled   = ! toggle.checked;
			buildShortcode();
		} );
	} );

	if ( copyBtn ) {
		copyBtn.addEventListener( 'click', function () {
			const markCopied = function () {
				copyBtn.textContent = copyBtn.dataset.copied;
				setTimeout( function () {
					copyBtn.textContent = copyBtn.dataset.copy;
				}, 2000 );
			};

			const legacyCopy = function () {
				output.select();
				if ( document.execCommand( 'copy' ) ) {
					markCopied();
				}
			};

			if ( navigator.clipboard && window.isSecureContext ) {
				navigator.clipboard.writeText( output.value ).then( markCopied ).catch( legacyCopy );
				return;
			}

			// Fallback for non-HTTPS admin screens.
			legacyCopy();
		} );
	}

	buildShortcode();
} );
JS;
	}

	/**
	 * Renders the standalone form control for a "general" group field —
	 * these each get their own settings-table row in the partial.
	 *
	 * @since x.x.x
	 *
	 * @param array $field The field descriptor, see FieldRegistry::add_core_fields().
	 * @return void
	 */
	public function render_field_control( array $field ): void {
		$key     = $field['key'];
		$id      = 'edbs_builder_' . $key;
		$default = $field['default'] ?? '';

		switch ( $field['type'] ) {
			case FieldRegistry::TYPE_CHECKBOX:
				?>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="true" <?php checked( (bool) $default ); ?> />
					<?php echo esc_html( $field['label'] ?? '' ); ?>
				</label>
				<?php
				break;

			case FieldRegistry::TYPE_SELECT:
				?>
				<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $key ); ?>" data-default="<?php echo esc_attr( $default ); ?>">
					<?php foreach ( ( $field['choices'] ?? [] ) as $value => $choice_label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $default, $value ); ?>><?php echo esc_html( $choice_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php
				break;

			case FieldRegistry::TYPE_NUMBER_WITH_ALL:
				$is_all = ( 'all' === $default || -1 === (int) $default );
				?>
				<input
					type="number"
					id="<?php echo esc_attr( $id ); ?>"
					name="<?php echo esc_attr( $key ); ?>"
					value="<?php echo esc_attr( $is_all ? '' : $default ); ?>"
					data-default="<?php echo esc_attr( $default ); ?>"
					min="1"
					class="small-text"
					<?php disabled( $is_all ); ?>
				/>
				<label style="margin-left:8px;">
					<input
						type="checkbox"
						class="edbs-builder-show-all-toggle"
						data-number-input="<?php echo esc_attr( $id ); ?>"
						data-all-hidden="<?php echo esc_attr( $id ); ?>_all"
						<?php checked( $is_all ); ?>
					/>
					<?php esc_html_e( 'Show all', 'boardscribe' ); ?>
				</label>
				<input
					type="hidden"
					id="<?php echo esc_attr( $id ); ?>_all"
					name="<?php echo esc_attr( $key ); ?>"
					value="all"
					<?php disabled( ! $is_all ); ?>
				/>
				<?php
				break;

			case FieldRegistry::TYPE_TEXTAREA:
				?>
				<textarea
					id="<?php echo esc_attr( $id ); ?>"
					name="<?php echo esc_attr( $key ); ?>"
					data-default="<?php echo esc_attr( $default ); ?>"
					class="regular-text"
				><?php echo esc_textarea( is_string( $default ) ? $default : '' ); ?></textarea>
				<?php
				break;

			case FieldRegistry::TYPE_NUMBER:
				?>
				<input
					type="number"
					id="<?php echo esc_attr( $id ); ?>"
					name="<?php echo esc_attr( $key ); ?>"
					value="<?php echo esc_attr( $default ); ?>"
					data-default="<?php echo esc_attr( $default ); ?>"
					min="1"
					class="small-text"
				/>
				<?php
				break;

			case FieldRegistry::TYPE_TEXT:
			default:
				?>
				<input
					type="text"
					id="<?php echo esc_attr( $id ); ?>"
					name="<?php echo esc_attr( $key ); ?>"
					value="<?php echo esc_attr( is_string( $default ) ? $default : '' ); ?>"
					data-default="<?php echo esc_attr( is_string( $default ) ? $default : '' ); ?>"
					placeholder="<?php echo esc_attr( $field['placeholder'] ?? '' ); ?>"
					class="regular-text"
				/>
				<?php
				break;
		}

		if ( ! empty( $field['description'] ) ) {
			echo '<p class="description">' . esc_html( $field['description'] ) . '</p>';
		}
	}

	/**
	 * Renders one mini text control for the "column_labels"/"link_labels"
	 * groups, which bundle several fields into a single settings-table row.
	 *
	 * @since x.x.x
	 *
	 * @param array $field The field descriptor.
	 * @return void
	 */
	public function render_bundled_text_field( array $field ): void {
		$label   = $field['label'] ?? '';
		$default = $field['default'] ?? '';
		$default = is_string( $default ) ? $default : '';
		?>
		<label style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
			<span style="width:60px;"><?php echo esc_html( $label ); ?></span>
			<input
				type="text"
				name="<?php echo esc_attr( $field['key'] ); ?>"
				value="<?php echo esc_attr( $default ); ?>"
				data-default="<?php echo esc_attr( $default ); ?>"
				class="regular-text"
				placeholder="<?php echo esc_attr( $field['placeholder'] ?? $label ); ?>"
			/>
		</label>
		<?php
	}

	/**
	 * Renders one mini checkbox control for the "hide_columns" group,
	 * which bundles several fields into a single settings-table row.
	 *
	 * @since x.x.x
	 *
	 * @param array $field The field descriptor.
	 * @return void
	 */
	public function render_bundled_checkbox_field( array $field ): void {
		$default = $field['default'] ?? false;
		?>
		<label style="display:block; margin-bottom:4px;">
			<input type="checkbox" name="<?php echo esc_attr( $field['key'] ); ?>" value="true" <?php checked( (bool) $default ); ?> />
			<?php echo esc_html( $field['label'] ?? '' ); ?>
		</label>
		<?php
	}

	/**
	 * Renders the delete on uninstall settings field.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public function render_delete_on_uninstall(): void {
		$value = Plugin::get_setting( 'delete_on_uninstall' );
		?>
		<label for="edbs_delete_on_uninstall">
			<input
				type="checkbox"
				name="edbs_settings[delete_on_uninstall]"
				id="edbs_delete_on_uninstall"
				value="1"
				<?php checked( 1, (int) $value ); ?>
			/>
			<?php esc_html_e( 'Remove all BoardScribe meeting posts and plugin settings when this plugin is deleted.', 'boardscribe' ); ?>
		</label>
		<p class="description" style="color:#d63638;"><?php esc_html_e( 'Warning: this cannot be undone.', 'boardscribe' ); ?></p>
		<?php
	}

	/**
	 * Sanitizes settings before saving.
	 *
	 * @since x.x.x
	 *
	 * @param array $input Raw input from the settings form.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( array $input ): array {
		return [
			'delete_on_uninstall' => isset( $input['delete_on_uninstall'] ) ? 1 : 0,
		];
	}

	/**
	 * Renders the settings page HTML.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		require EDBS_DIR . 'partials/settings-page.php';
	}
}
