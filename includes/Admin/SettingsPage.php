<?php
/**
 * Plugin settings page with shortcode builder.
 *
 * @package EqualizeDigital\MeetingMinutes
 */

namespace EqualizeDigital\MeetingMinutes\Admin;

use EqualizeDigital\MeetingMinutes\Plugin;

/**
 * Registers and renders the Meeting Minutes settings page.
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
	 * Adds the Settings submenu under the Meeting Minutes CPT menu.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public function add_submenu_page(): void {
		add_submenu_page(
			'edit.php?post_type=edmm_meeting_minutes',
			__( 'Meeting Minutes Settings', 'edmm' ),
			__( 'Settings', 'edmm' ),
			'manage_options',
			'edmm-settings',
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
			'edmm_settings_group',
			'edmm_settings',
			[ 'sanitize_callback' => [ $this, 'sanitize_settings' ] ]
		);

		add_settings_section(
			'edmm_advanced_section',
			__( 'Advanced', 'edmm' ),
			'__return_empty_string',
			'edmm-settings'
		);

		add_settings_field(
			'delete_on_uninstall',
			__( 'Delete Data on Uninstall', 'edmm' ),
			[ $this, 'render_delete_on_uninstall' ],
			'edmm-settings',
			'edmm_advanced_section'
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
		if ( 'edmm_meeting_minutes_page_edmm-settings' !== $hook ) {
			return;
		}

		// Inline script — no separate file needed for a small builder.
		wp_add_inline_script( 'jquery', $this->get_builder_script() );
	}

	/**
	 * Returns the inline JavaScript for the shortcode builder.
	 *
	 * Walks every named form field generically (rather than a hardcoded
	 * field list) so fields the Pro plugin adds via the
	 * edmm_shortcode_builder_fields action are automatically included in
	 * the generated shortcode - see the doc comment on that action in
	 * partials/settings-page.php for the naming convention Pro fields need
	 * to follow.
	 *
	 * @since x.x.x
	 *
	 * @return string
	 */
	private function get_builder_script(): string {
		return <<<'JS'
document.addEventListener( 'DOMContentLoaded', function () {
	const form    = document.getElementById( 'edmm-shortcode-builder' );
	const output  = document.getElementById( 'edmm-shortcode-output' );
	const copyBtn = document.getElementById( 'edmm-copy-shortcode' );

	if ( ! form || ! output ) return;

	function buildShortcode() {
		let shortcode = '[edmm_meeting_minutes';
		const seen = new Set();

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
					shortcode += ' ' + el.name + '="' + boolValue.replace( /"/g, '&quot;' ) + '"';
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
				shortcode += ' ' + el.name + '="' + val.replace( /"/g, '&quot;' ) + '"';
			}
		} );

		shortcode += ']';
		output.value = shortcode;
	}

	form.addEventListener( 'input', buildShortcode );
	form.addEventListener( 'change', buildShortcode );

	if ( copyBtn ) {
		copyBtn.addEventListener( 'click', function () {
			output.select();
			document.execCommand( 'copy' );
			copyBtn.textContent = copyBtn.dataset.copied;
			setTimeout( function () {
				copyBtn.textContent = copyBtn.dataset.copy;
			}, 2000 );
		} );
	}

	buildShortcode();
} );
JS;
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
		<label for="edmm_delete_on_uninstall">
			<input
				type="checkbox"
				name="edmm_settings[delete_on_uninstall]"
				id="edmm_delete_on_uninstall"
				value="1"
				<?php checked( 1, (int) $value ); ?>
			/>
			<?php esc_html_e( 'Remove all meeting minutes posts and plugin settings when this plugin is deleted.', 'edmm' ); ?>
		</label>
		<p class="description" style="color:#d63638;"><?php esc_html_e( 'Warning: this cannot be undone.', 'edmm' ); ?></p>
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

		require EDMM_DIR . 'partials/settings-page.php';
	}
}
