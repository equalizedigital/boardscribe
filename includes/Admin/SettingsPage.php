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
		const data   = new FormData( form );
		let shortcode = '[edmm_meeting_minutes';

		const years = data.get( 'included_years' ) || '';
		if ( years.trim() ) {
			shortcode += ' included_years="' + years.trim() + '"';
		}

		const postsPerPage = parseInt( data.get( 'posts_per_page' ), 10 );
		if ( postsPerPage && postsPerPage !== 20 ) {
			shortcode += ' posts_per_page="' + postsPerPage + '"';
		}

		const heldFormat = data.get( 'held_date_format' ) || '';
		if ( heldFormat && heldFormat !== 'Y/m/d' ) {
			shortcode += ' held_date_format="' + heldFormat + '"';
		}

		const notHeldFormat = data.get( 'not_held_date_format' ) || '';
		if ( notHeldFormat && notHeldFormat !== 'Y/m' ) {
			shortcode += ' not_held_date_format="' + notHeldFormat + '"';
		}

		[ 'title_label', 'date_label', 'agenda_label', 'minutes_label', 'agenda_link_label', 'minutes_link_label' ].forEach( function ( key ) {
			const val = ( data.get( key ) || '' ).trim();
			if ( val ) {
				shortcode += ' ' + key + '="' + val + '"';
			}
		} );

		[ 'hide_title', 'hide_date', 'hide_agenda', 'hide_minutes' ].forEach( function ( key ) {
			if ( data.get( key ) === 'true' ) {
				shortcode += ' ' + key + '="true"';
			}
		} );

		const cssClass = data.get( 'class' ) || '';
		if ( cssClass.trim() ) {
			shortcode += ' class="' + cssClass.trim() + '"';
		}

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

		include EDMM_DIR . 'partials/settings-page.php';
	}
}
