<?php
/**
 * Main plugin class.
 *
 * @package EqualizeDigital\MeetingMinutes
 */

namespace EqualizeDigital\MeetingMinutes;

use EqualizeDigital\MeetingMinutes\Admin\MetaBox;
use EqualizeDigital\MeetingMinutes\Admin\SettingsPage;
use EqualizeDigital\MeetingMinutes\PostType\MeetingMinutes as MeetingMinutesCPT;
use EqualizeDigital\MeetingMinutes\REST\MeetingMinutesEndpoint;
use EqualizeDigital\MeetingMinutes\Shortcode\MeetingMinutesShortcode;

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
	 */
	private function __construct() {}

	/**
	 * Boots the plugin. Hooked to plugins_loaded.
	 *
	 * @return void
	 */
	public function boot(): void {
		load_plugin_textdomain( 'edmm', false, dirname( plugin_basename( EDMM_FILE ) ) . '/languages' );

		( new MeetingMinutesCPT() )->register();
		( new MetaBox() )->register();
		( new SettingsPage() )->register();
		( new MeetingMinutesEndpoint() )->register();
		( new MeetingMinutesShortcode() )->register();

		add_filter(
			'edac_fix_file_size_and_type_additional_filters',
			[ $this, 'register_edacp_filters' ]
		);

		/**
		 * Fires after all free plugin components are registered.
		 *
		 * The pro plugin hooks here as its sole entry point.
		 *
		 * @param Plugin $plugin The plugin instance.
		 */
		do_action( 'edmm_loaded', $this );
	}

	/**
	 * Adds meeting minutes link filters for Accessibility Checker Pro.
	 *
	 * @param array $additional_filters Existing filter names.
	 * @return array
	 */
	public function register_edacp_filters( array $additional_filters ): array {
		$additional_filters[] = 'edmm_meeting_minutes_link';
		$additional_filters[] = 'edmm_meeting_agenda_link';
		return $additional_filters;
	}

	/**
	 * Retrieves a single plugin setting with fallback to defaults.
	 *
	 * @param string $key     The setting key.
	 * @param mixed  $default Optional override default (falls back to DEFAULTS constant).
	 * @return mixed
	 */
	public static function get_setting( string $key, $default = null ) {
		$settings = get_option( 'edmm_settings', [] );
		if ( isset( $settings[ $key ] ) ) {
			return $settings[ $key ];
		}
		if ( null !== $default ) {
			return $default;
		}
		return self::DEFAULTS[ $key ] ?? null;
	}
}
