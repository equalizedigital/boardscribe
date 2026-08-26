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

use EqualizeDigital\BoardScribe\Helpers\Helpers;
use EqualizeDigital\BoardScribe\Plugin;
use EqualizeDigital\BoardScribe\Shortcode\BoardScribeShortcode;
use EqualizeDigital\BoardScribe\Shortcode\FieldRegistry;

/**
 * Registers and renders the BoardScribe settings page.
 *
 * The page uses a sidebar + tabbed-panel layout. Tabs:
 * - General:          persistent plugin settings (e.g. delete on uninstall).
 * - Shortcode Builder: the React builder app that generates a ready-to-copy
 *                       shortcode (no values are stored).
 * - Support:          documentation and contact links.
 *
 * Fields still use the Settings API (register_setting/add_settings_section/
 * add_settings_field). Accessibility comes from the field render callbacks
 * emitting semantic markup (fieldset/legend/label + aria-describedby) inside
 * core's role="presentation" form-table, rather than from abandoning the API.
 */
class SettingsPage {

	/**
	 * Parent menu slug the settings page lives under.
	 */
	private const PARENT_SLUG = 'edit.php?post_type=edbs_meeting';

	/**
	 * Settings page menu slug.
	 */
	private const PAGE_SLUG = 'edbs-settings';

	/**
	 * The admin_enqueue_scripts hook suffix for the settings page.
	 */
	private const PAGE_HOOK = 'edbs_meeting_page_edbs-settings';

	/**
	 * Hooks settings registration and admin menu into WordPress.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_submenu_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Returns the settings page tab definitions, keyed by tab slug.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array{icon:string,label:string}>
	 */
	private function get_tabs(): array {
		$tabs = [
			'general' => [
				'icon'  => 'dashicons-admin-generic',
				'label' => __( 'General', 'boardscribe' ),
			],
			'builder' => [
				'icon'  => 'dashicons-shortcode',
				'label' => __( 'Shortcode Builder', 'boardscribe' ),
			],
			'support' => [
				'icon'  => 'dashicons-sos',
				'label' => __( 'Support', 'boardscribe' ),
			],
		];

		/**
		 * Filters the settings page tabs, keyed by tab slug. Pro plugin adds
		 * its own tabs here (e.g. an Import tab) and renders their content on
		 * the matching `edbs_settings_tab_content_{$tab}` action. Array order
		 * controls display order, so a callback can splice a tab in before
		 * "support" rather than only appending.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, array{icon:string,label:string}> $tabs Tab definitions.
		 */
		return apply_filters( 'edbs_settings_tabs', $tabs );
	}

	/**
	 * Returns the current tab slug, defaulting to "general" when absent or
	 * unrecognized.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private function get_current_tab(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab switching is a read-only navigation action.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';

		return array_key_exists( $tab, $this->get_tabs() ) ? $tab : 'general';
	}

	/**
	 * Builds the admin URL for a settings tab.
	 *
	 * @since 1.0.0
	 *
	 * @param string $tab The tab slug.
	 * @return string
	 */
	private function get_tab_url( string $tab ): string {
		return add_query_arg(
			[
				'post_type' => 'edbs_meeting',
				'page'      => self::PAGE_SLUG,
				'tab'       => $tab,
			],
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Adds the Settings submenu under the BoardScribe CPT menu.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function add_submenu_page(): void {
		add_submenu_page(
			self::PARENT_SLUG,
			__( 'BoardScribe Settings', 'boardscribe' ),
			__( 'Settings', 'boardscribe' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Registers the persistent plugin settings.
	 *
	 * @since 1.0.0
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
			'edbs_general_section',
			__( 'General Settings', 'boardscribe' ),
			[ $this, 'render_general_section' ],
			self::PAGE_SLUG
		);

		add_settings_field(
			'delete_on_uninstall',
			__( 'Delete Data on Uninstall', 'boardscribe' ),
			[ $this, 'render_delete_on_uninstall' ],
			self::PAGE_SLUG,
			'edbs_general_section'
		);
	}

	/**
	 * Enqueues settings-page assets, plus the shortcode builder app on the
	 * builder tab (its live preview runs the real frontend pipeline).
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( self::PAGE_HOOK !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'edbs-settings',
			EDBS_URL . 'assets/css/settings.css',
			[],
			EDBS_VERSION
		);

		if ( 'builder' !== $this->get_current_tab() ) {
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
		wp_enqueue_style(
			'edbs-shortcode-builder',
			EDBS_URL . 'assets/css/builder.css',
			[ 'wp-components' ],
			EDBS_VERSION
		);
		wp_set_script_translations( 'edbs-shortcode-builder', 'boardscribe' );
		wp_localize_script( 'edbs-shortcode-builder', 'edbsBuilderFieldRegistry', FieldRegistry::js_schema() );

		// The live preview runs the real frontend pipeline.
		( new BoardScribeShortcode() )->enqueue_assets();
	}

	/**
	 * Renders the General section intro copy.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_general_section(): void {
		echo '<p>' . esc_html__( 'Configure general settings for the BoardScribe plugin.', 'boardscribe' ) . '</p>';
	}

	/**
	 * Renders the delete on uninstall settings field.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_delete_on_uninstall(): void {
		$value          = Plugin::get_setting( 'delete_on_uninstall' );
		$description_id = 'edbs_delete_on_uninstall-description';
		?>
		<fieldset>
			<legend class="screen-reader-text">
				<?php esc_html_e( 'Delete Data on Uninstall', 'boardscribe' ); ?>
			</legend>
			<label for="edbs_delete_on_uninstall">
				<input
					type="checkbox"
					name="edbs_settings[delete_on_uninstall]"
					id="edbs_delete_on_uninstall"
					value="1"
					aria-describedby="<?php echo esc_attr( $description_id ); ?>"
					<?php checked( 1, (int) $value ); ?>
				/>
				<?php esc_html_e( 'Remove all BoardScribe meeting posts and plugin settings when this plugin is deleted.', 'boardscribe' ); ?>
			</label>
		</fieldset>
		<p id="<?php echo esc_attr( $description_id ); ?>" class="description" style="color:#d63638;">
			<?php esc_html_e( 'Warning: this cannot be undone.', 'boardscribe' ); ?>
		</p>
		<?php
	}

	/**
	 * Sanitizes settings before saving.
	 *
	 * @since 1.0.0
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
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$current_tab = $this->get_current_tab();

		require EDBS_DIR . 'partials/settings-page.php';
	}

	/**
	 * Renders the sidebar tab navigation.
	 *
	 * @since 1.0.0
	 *
	 * @param string $current_tab The active tab slug.
	 * @return void
	 */
	public function render_tabs( string $current_tab ): void {
		?>
		<nav class="edbs-settings-tabs" aria-label="<?php esc_attr_e( 'BoardScribe settings', 'boardscribe' ); ?>">
			<?php foreach ( $this->get_tabs() as $tab_key => $tab ) : ?>
				<a
					href="<?php echo esc_url( $this->get_tab_url( $tab_key ) ); ?>"
					class="edbs-settings-tabs-tab<?php echo $current_tab === $tab_key ? ' edbs-settings-tabs-tab-active' : ''; ?>"
					<?php echo $current_tab === $tab_key ? 'aria-current="page"' : ''; ?>
				>
					<span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>" aria-hidden="true"></span>
					<?php echo esc_html( $tab['label'] ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
	 * Renders the panel header (icon + label) for the current tab.
	 *
	 * @since 1.0.0
	 *
	 * @param string $current_tab The active tab slug.
	 * @return void
	 */
	public function render_panel_header( string $current_tab ): void {
		$tabs = $this->get_tabs();

		if ( ! isset( $tabs[ $current_tab ] ) ) {
			return;
		}
		?>
		<h1 class="edbs-settings-panel-header">
			<span class="dashicons <?php echo esc_attr( $tabs[ $current_tab ]['icon'] ); ?>" aria-hidden="true"></span>
			<?php echo esc_html( $tabs[ $current_tab ]['label'] ); ?>
		</h1>
		<?php
	}

	/**
	 * Renders the active tab's panel content.
	 *
	 * @since 1.0.0
	 *
	 * @param string $current_tab The active tab slug.
	 * @return void
	 */
	public function render_tab_content( string $current_tab ): void {
		switch ( $current_tab ) {
			case 'builder':
				$this->render_builder_tab();
				break;
			case 'support':
				$this->render_support_tab();
				break;
			case 'general':
				$this->render_general_tab();
				break;
			default:
				/**
				 * Fires to render the content of a settings tab that isn't one
				 * of the free plugin's core tabs. The dynamic portion is the tab
				 * slug (e.g. `edbs_settings_tab_content_import`). A plugin that
				 * registered a tab via `edbs_settings_tabs` renders its UI here.
				 *
				 * Only fires for tabs present in the (filtered) tab list, since
				 * get_current_tab() falls back to "general" for unknown slugs.
				 *
				 * @since 1.0.0
				 */
				do_action( "edbs_settings_tab_content_{$current_tab}" );
				break;
		}
	}

	/**
	 * Renders the General tab (persistent settings form).
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function render_general_tab(): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
			<?php
			settings_fields( 'edbs_settings_group' );
			do_settings_sections( self::PAGE_SLUG );
			submit_button();
			?>
		</form>
		<?php
		/**
		 * Fires inside the settings page after the main form. Pro plugin uses this
		 * for sections with their own form (e.g. license management) that don't use
		 * the Settings API.
		 *
		 * @since 1.0.0
		 */
		do_action( 'edbs_settings_fields' );
	}

	/**
	 * Renders the Shortcode Builder tab (React app mount point).
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function render_builder_tab(): void {
		?>
		<p><?php esc_html_e( 'Configure your shortcode options below, then copy the generated shortcode and paste it into any page or post.', 'boardscribe' ); ?></p>
		<div id="edbs-shortcode-builder-root">
			<noscript><?php esc_html_e( 'The shortcode builder requires JavaScript.', 'boardscribe' ); ?></noscript>
		</div>
		<?php
	}

	/**
	 * Builds a UTM-tagged link for the Support tab.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url         Absolute URL, or a path relative to the BoardScribe site section.
	 * @param string $utm_content Identifies which link on the tab was clicked.
	 *
	 * @return string
	 */
	private function support_url( string $url, string $utm_content ): string {
		return Helpers::utm_link_builder(
			$url,
			[
				'utm_campaign' => 'settings-support',
				'utm_content'  => $utm_content,
			]
		);
	}

	/**
	 * Renders the Support tab (documentation and contact links).
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function render_support_tab(): void {
		$quick_links = [
			[
				'url'         => '/documentation/downloading-and-installing-boardscribe/',
				'label'       => __( 'Downloading and Installing BoardScribe', 'boardscribe' ),
				'utm_content' => 'downloading-and-installing-boardscribe',
			],
			[
				'url'         => '/documentation/boardscribe-settings/',
				'label'       => __( 'BoardScribe Settings', 'boardscribe' ),
				'utm_content' => 'boardscribe-settings',
			],
			[
				'url'         => '/documentation/board-meetings-post-type/',
				'label'       => __( 'Board Meetings Post Type', 'boardscribe' ),
				'utm_content' => 'board-meetings-post-type',
			],
			[
				'url'         => '/documentation/shortcode-builder/',
				'label'       => __( 'Shortcode Builder', 'boardscribe' ),
				'utm_content' => 'shortcode-builder',
			],
			[
				'url'         => '/documentation/is-boardscribe-accessible/',
				'label'       => __( 'Is BoardScribe accessible?', 'boardscribe' ),
				'utm_content' => 'is-boardscribe-accessible',
			],
			[
				'url'         => '/documentation/what-languages-is-boardscribe-available-in/',
				'label'       => __( 'What languages is BoardScribe translated into?', 'boardscribe' ),
				'utm_content' => 'what-languages-is-boardscribe-available-in',
			],
			[
				'url'         => '/documentation/how-do-i-add-file-type-and-size-information-to-links/',
				'label'       => __( 'How do I add file size and type information to links?', 'boardscribe' ),
				'utm_content' => 'how-do-i-add-file-type-and-size-information-to-links',
			],
		];
		?>
		<div class="edbs-settings-panel-sub">
			<h2><?php esc_html_e( 'Documentation', 'boardscribe' ); ?></h2>
			<p class="edbs-settings-support-description">
				<?php esc_html_e( 'Need help? Check out our documentation for step-by-step guides and feature walkthroughs.', 'boardscribe' ); ?>
			</p>
			<a class="button button-secondary" href="<?php echo esc_url( $this->support_url( '/documentation/', 'button-documentation' ) ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'View Documentation', 'boardscribe' ); ?>
				<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'boardscribe' ); ?></span>
			</a>
		</div>

		<div class="edbs-settings-panel-sub">
			<h2><?php esc_html_e( 'Contact Us', 'boardscribe' ); ?></h2>
			<p class="edbs-settings-support-description">
				<?php esc_html_e( 'Have questions or need help? Reach out to our support team.', 'boardscribe' ); ?>
			</p>
			<a class="button button-secondary" href="<?php echo esc_url( $this->support_url( 'https://my.equalizedigital.com/support/', 'button-support' ) ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Contact Support', 'boardscribe' ); ?>
				<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'boardscribe' ); ?></span>
			</a>
		</div>

		<div class="edbs-settings-panel-sub">
			<h2><?php esc_html_e( 'Quick Links', 'boardscribe' ); ?></h2>
			<ul class="edbs-settings-links-list">
				<?php foreach ( $quick_links as $link ) : ?>
					<li>
						<a href="<?php echo esc_url( $this->support_url( $link['url'], $link['utm_content'] ) ); ?>" target="_blank" rel="noopener noreferrer">
							<?php echo esc_html( $link['label'] ); ?>
							<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'boardscribe' ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}
}
