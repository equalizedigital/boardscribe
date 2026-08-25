<?php
/**
 * Main plugin class.
 *
 * @package EqualizeDigital\BoardScribe
 */

namespace EqualizeDigital\BoardScribe;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use EqualizeDigital\BoardScribe\Admin\MetaBox;
use EqualizeDigital\BoardScribe\Admin\SettingsPage;
use EqualizeDigital\BoardScribe\Block\BoardScribeBlock;
use EqualizeDigital\BoardScribe\PostType\BoardScribeCPT;
use EqualizeDigital\BoardScribe\REST\BoardScribeEndpoint;
use EqualizeDigital\BoardScribe\Shortcode\FieldRegistry;
use EqualizeDigital\BoardScribe\Shortcode\BoardScribeShortcode;

/**
 * Singleton plugin bootstrap. Wires all components together.
 */
class Plugin {

	/**
	 * Default values for plugin settings.
	 * Only persistent settings are stored — display defaults live in the shortcode builder.
	 */
	const DEFAULTS = [
		'delete_on_uninstall' => 0,
	];

	/**
	 * Single instance of this class.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Returns the single instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Plugin
	 */
	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor — use get_instance().
	 *
	 * @since 1.0.0
	 */
	private function __construct() {}

	/**
	 * Boots the plugin. Hooked to plugins_loaded.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function boot(): void {
		// No manual load_plugin_textdomain() call - discouraged since
		// WordPress 4.6. Core's just-in-time textdomain loading
		// (_load_textdomain_just_in_time()) already finds this plugin's
		// translations automatically because its Text Domain header
		// ("boardscribe") matches its slug/directory name; that lookup
		// isn't gated on being wp.org-hosted.
		( new FieldRegistry() )->register();
		( new BoardScribeCPT() )->register();
		( new MetaBox() )->register();
		( new SettingsPage() )->register();
		( new BoardScribeEndpoint() )->register();
		( new BoardScribeShortcode() )->register();
		( new BoardScribeBlock() )->register();

		add_filter(
			'edac_fix_file_size_and_type_additional_filters',
			[ $this, 'register_edacp_filters' ]
		);

		/**
		 * Fires after all free plugin components are registered. Pro plugin's sole entry point.
		 *
		 * @since 1.0.0
		 *
		 * @param Plugin $plugin The plugin instance.
		 */
		do_action( 'edbs_loaded', $this );
	}

	/**
	 * Adds agenda/minutes link filters for Accessibility Checker Pro.
	 *
	 * @since 1.0.0
	 *
	 * @param array $additional_filters Existing filter names.
	 * @return array
	 */
	public function register_edacp_filters( array $additional_filters ): array {
		$additional_filters[] = 'edbs_minutes_link';
		$additional_filters[] = 'edbs_agenda_link';
		return $additional_filters;
	}

	/**
	 * Gets a single plugin setting with fallback to defaults.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key           The setting key.
	 * @param mixed  $default_value Optional override default (falls back to DEFAULTS constant).
	 * @return mixed
	 */
	public static function get_setting( string $key, $default_value = null ) {
		$settings = get_option( 'edbs_settings', [] );
		if ( isset( $settings[ $key ] ) ) {
			return $settings[ $key ];
		}
		if ( null !== $default_value ) {
			return $default_value;
		}
		return self::DEFAULTS[ $key ] ?? null;
	}
}
