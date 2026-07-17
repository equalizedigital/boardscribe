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
use EqualizeDigital\BoardScribe\Shortcode\BoardScribeShortcode;
use EqualizeDigital\BoardScribe\Shortcode\FieldRegistry;

/**
 * Registers and renders the BoardScribe settings page and the
 * shortcode builder page.
 *
 * - The Shortcode Builder page generates a ready-to-copy shortcode based
 *   on the user's selections (no values are stored).
 * - The Settings page holds an Advanced section for persistent settings
 *   (e.g. delete on uninstall).
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
		add_action( 'admin_menu', [ $this, 'add_builder_submenu_page' ] );
		add_action( 'admin_menu', [ $this, 'add_submenu_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_builder_script' ] );
	}

	/**
	 * Adds the Shortcode Builder submenu under the BoardScribe CPT menu.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public function add_builder_submenu_page(): void {
		add_submenu_page(
			'edit.php?post_type=edbs_boardscribe',
			__( 'Shortcode Builder', 'boardscribe' ),
			__( 'Shortcode Builder', 'boardscribe' ),
			'manage_options',
			'edbs-shortcode-builder',
			[ $this, 'render_builder_page' ]
		);
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
	 * Enqueues the shortcode builder React app on the builder page.
	 *
	 * The app renders every field from the shared registry (see
	 * FieldRegistry::js_schema()), so fields any plugin adds via the
	 * edbs_shortcode_field_registry filter appear automatically. The
	 * frontend bundle is enqueued alongside it to power the live
	 * preview — including the edbs_enqueue_assets action, so Pro's
	 * columns/templates render there too.
	 *
	 * @since x.x.x
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_builder_script( string $hook ): void {
		if ( 'edbs_boardscribe_page_edbs-shortcode-builder' !== $hook ) {
			return;
		}

		// Built by `npm run build` (assets/build/ is gitignored); the
		// generated *.asset.php carries the bundle's wp-* dependencies
		// and a content-hash version.
		$asset_file = EDBS_DIR . 'assets/build/builder/index.asset.php';
		$asset      = file_exists( $asset_file )
			? require $asset_file
			: [
				'dependencies' => [],
				'version'      => EDBS_VERSION,
			];

		wp_enqueue_script(
			'edbs-shortcode-builder',
			EDBS_URL . 'assets/build/builder/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_enqueue_style( 'wp-components' );
		wp_set_script_translations( 'edbs-shortcode-builder', 'boardscribe' );
		wp_localize_script( 'edbs-shortcode-builder', 'edbsBuilderFieldRegistry', FieldRegistry::js_schema() );

		// The live preview runs the real frontend pipeline.
		( new BoardScribeShortcode() )->enqueue_assets();
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

	/**
	 * Renders the shortcode builder page HTML.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public function render_builder_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		require EDBS_DIR . 'partials/shortcode-builder-page.php';
	}
}
