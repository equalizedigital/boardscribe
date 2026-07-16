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
use EqualizeDigital\BoardScribe\PostType\MeetingMinutes as MeetingMinutesCPT;
use EqualizeDigital\BoardScribe\REST\MeetingMinutesEndpoint;
use EqualizeDigital\BoardScribe\Shortcode\FieldRegistry;
use EqualizeDigital\BoardScribe\Shortcode\MeetingMinutesShortcode;

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
	 * @since x.x.x
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
	 * @since x.x.x
	 */
	private function __construct() {}

	/**
	 * Boots the plugin. Hooked to plugins_loaded.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public function boot(): void {
		load_plugin_textdomain( 'boardscribe', false, dirname( plugin_basename( EDBS_FILE ) ) . '/languages' );

		( new FieldRegistry() )->register();
		( new MeetingMinutesCPT() )->register();
		( new MetaBox() )->register();
		( new SettingsPage() )->register();
		( new MeetingMinutesEndpoint() )->register();
		( new MeetingMinutesShortcode() )->register();
		( new BoardScribeBlock() )->register();

		add_filter(
			'edac_fix_file_size_and_type_additional_filters',
			[ $this, 'register_edacp_filters' ]
		);

		/**
		 * Fires after all free plugin components are registered. Pro plugin's sole entry point.
		 *
		 * @since x.x.x
		 *
		 * @param Plugin $plugin The plugin instance.
		 */
		do_action( 'edbs_loaded', $this );
	}

	/**
	 * Adds meeting minutes link filters for Accessibility Checker Pro.
	 *
	 * @since x.x.x
	 *
	 * @param array $additional_filters Existing filter names.
	 * @return array
	 */
	public function register_edacp_filters( array $additional_filters ): array {
		$additional_filters[] = 'edbs_meeting_minutes_link';
		$additional_filters[] = 'edbs_meeting_agenda_link';
		return $additional_filters;
	}

	/**
	 * Gets a single plugin setting with fallback to defaults.
	 *
	 * @since x.x.x
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
